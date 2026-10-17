<?php

namespace App\Support;

use App\Enums\ResultSheetStatus;
use App\Enums\ReviewVerdict;
use App\Models\Election;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use App\Models\State;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How many polling units have result sheets, and how far voters back them, for the public dashboard.
 *
 * A polling unit is disputed when any of its sheets is (ResultSheetStatus::Contested), and confirmed
 * when exactly one sheet reached the threshold and none is disputed: the same rules as the per-sheet
 * status, worked out in SQL so the dashboard never loads every sheet.
 *
 * @phpstan-type UnitRow array{id: int, name: string, code: string, lga: string, state: string, sheets: int, status: string, status_label: string}
 */
final readonly class ResultSheetCoverage
{
    /**
     * The result sheets page lists this many units at a time; disputed ones come first, so they never fall off.
     */
    public const int UNIT_LIMIT = 50;

    private const string DISPUTED = '(unit_stats.disputed = 1 or unit_stats.reached >= 2)';

    private const string CONFIRMED = '(unit_stats.disputed = 0 and unit_stats.reached = 1)';

    public function __construct(private Election $election, private ?State $state = null) {}

    /**
     * @return array{polling_units: int, corroborated: int, contested: int}
     */
    public function summary(): array
    {
        /** @var object{polling_units: int|string|null, corroborated: int|string|null, contested: int|string|null} $row */
        $row = $this->units()
            ->selectRaw('count(*) as polling_units, sum(case when '.self::CONFIRMED.' then 1 else 0 end) as corroborated, sum(case when '.self::DISPUTED.' then 1 else 0 end) as contested')
            ->first();

        return [
            'polling_units' => (int) $row->polling_units,
            'corroborated' => (int) $row->corroborated,
            'contested' => (int) $row->contested,
        ];
    }

    /**
     * @return list<UnitRow>
     */
    public function pollingUnits(): array
    {
        $statusCase = 'case when '.self::DISPUTED." then 'contested' when ".self::CONFIRMED." then 'corroborated' else 'unconfirmed' end";

        return $this->units()
            ->join('states', 'states.id', '=', 'lgas.state_id')
            ->selectRaw("polling_units.id, polling_units.name, polling_units.code, lgas.name as lga, states.name as state, unit_stats.sheets, {$statusCase} as status")
            ->orderByRaw('case when '.self::DISPUTED.' then 0 when '.self::CONFIRMED.' then 1 else 2 end')
            ->orderBy('lgas.name')
            ->orderBy('polling_units.name')
            ->limit(self::UNIT_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
                'lga' => (string) $row->lga,
                'state' => (string) $row->state,
                'sheets' => (int) $row->sheets,
                'status' => (string) $row->status,
                'status_label' => match ($row->status) {
                    'contested' => ResultSheetStatus::Contested->label(),
                    'corroborated' => ResultSheetStatus::Corroborated->label(),
                    default => 'Not yet confirmed by several voters',
                },
            ])
            ->pipe(fn (Collection $units): array => array_values($units->all()));
    }

    /**
     * IDs of the polling units whose sheets voters confirmed, by the same rules as the dashboard.
     */
    public function confirmedPollingUnits(): Builder
    {
        return $this->units()->whereRaw(self::CONFIRMED)->select('unit_stats.polling_unit_id');
    }

    /**
     * One row per polling unit with a sheet: how many sheets, how many reached the threshold, and whether any is disputed.
     */
    private function units(): Builder
    {
        $sheetStats = DB::table((new ResultSheet)->getTable().' as sheets')
            ->leftJoin((new ResultSheetReview)->getTable().' as reviews', 'reviews.result_sheet_id', '=', 'sheets.id')
            ->where('sheets.election_id', $this->election->id)
            ->groupBy('sheets.id', 'sheets.polling_unit_id')
            ->selectRaw(
                'sheets.polling_unit_id, sum(case when reviews.verdict = ? then 1 else 0 end) as confirmations, sum(case when reviews.verdict = ? then 1 else 0 end) as disputes',
                [ReviewVerdict::Confirm->value, ReviewVerdict::Dispute->value],
            );

        $unitStats = DB::query()
            ->fromSub($sheetStats, 'sheet_stats')
            ->groupBy('sheet_stats.polling_unit_id')
            ->selectRaw(
                'sheet_stats.polling_unit_id, count(*) as sheets, sum(case when confirmations >= ? then 1 else 0 end) as reached, max(case when disputes > 0 and disputes >= confirmations then 1 else 0 end) as disputed',
                [ResultSheetStatus::CORROBORATION_THRESHOLD],
            );

        return DB::query()
            ->fromSub($unitStats, 'unit_stats')
            ->join('polling_units', 'polling_units.id', '=', 'unit_stats.polling_unit_id')
            ->join('wards', 'wards.id', '=', 'polling_units.ward_id')
            ->join('lgas', 'lgas.id', '=', 'wards.lga_id')
            ->when($this->state, fn (Builder $query) => $query->where('lgas.state_id', $this->state?->id));
    }
}
