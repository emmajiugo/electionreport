<?php

namespace App\Support;

use App\Models\Election;
use App\Models\Report;
use App\Models\State;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Group standings for gamified reporting: which states, LGAs and wards have the most polling units
 * whose reports other voters back up.
 *
 * A polling unit counts once AGREEING_REPORTS of its reports agree on whether the election held, or
 * once voters confirmed its result sheet. Areas are ranked by polling units, not reports, so flooding
 * one unit with fake VINs moves its area by one at most. How anyone voted never plays a part.
 *
 * @phpstan-type StandingRow array{id: int, name: string, lga?: string, polling_units: int, rank: int}
 */
final readonly class ReportCorroboration
{
    /**
     * A report plus three others that agree with it.
     */
    public const int AGREEING_REPORTS = 4;

    /**
     * Standings are a top list, not a full table, to keep the dashboard payload small.
     */
    public const int LIMIT = 10;

    public function __construct(private Election $election, private ?State $state = null) {}

    /**
     * @return array{states: list<StandingRow>, lgas: list<StandingRow>, wards: list<StandingRow>}
     */
    public function standings(): array
    {
        return [
            'states' => $this->rank('states', nationally: true),
            'lgas' => $this->state ? $this->rank('lgas') : [],
            'wards' => $this->state ? $this->rank('wards') : [],
        ];
    }

    /**
     * @param  'states'|'lgas'|'wards'  $area
     * @return list<StandingRow>
     */
    private function rank(string $area, bool $nationally = false): array
    {
        $rows = DB::query()
            ->fromSub($this->corroboratedPollingUnits(), 'corroborated')
            ->join('polling_units', 'polling_units.id', '=', 'corroborated.polling_unit_id')
            ->join('wards', 'wards.id', '=', 'polling_units.ward_id')
            ->join('lgas', 'lgas.id', '=', 'wards.lga_id')
            ->join('states', 'states.id', '=', 'lgas.state_id')
            ->when(! $nationally && $this->state, fn (Builder $query) => $query->where('lgas.state_id', $this->state?->id))
            ->groupBy("{$area}.id", "{$area}.name")
            ->selectRaw("{$area}.id, {$area}.name, count(*) as polling_units")
            // Ward names repeat across LGAs ("Ward 1"), so a ward carries its LGA's name.
            ->when($area === 'wards', fn (Builder $query) => $query->groupBy('lgas.name')->addSelect('lgas.name as lga'))
            ->orderByDesc('polling_units')
            ->orderBy("{$area}.name")
            ->limit(self::LIMIT)
            ->get();

        $standings = [];
        $rank = 0;
        $previous = null;

        // Areas with the same count share a rank, so a tie never reads as one beating the other.
        foreach ($rows as $position => $row) {
            if ($row->polling_units !== $previous) {
                $rank = $position + 1;
                $previous = $row->polling_units;
            }

            $standings[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                ...isset($row->lga) ? ['lga' => (string) $row->lga] : [],
                'polling_units' => (int) $row->polling_units,
                'rank' => $rank,
            ];
        }

        return $standings;
    }

    /**
     * One row per corroborated polling unit; the union drops units that qualify both ways.
     */
    private function corroboratedPollingUnits(): Builder
    {
        $byAgreement = DB::table((new Report)->getTable().' as reports')
            ->where('reports.election_id', $this->election->id)
            ->groupBy('reports.polling_unit_id')
            ->havingRaw(
                'sum(case when reports.election_held then 1 else 0 end) >= ? or sum(case when reports.election_held then 0 else 1 end) >= ?',
                [self::AGREEING_REPORTS, self::AGREEING_REPORTS],
            )
            ->select('reports.polling_unit_id');

        return $byAgreement->union((new ResultSheetCoverage($this->election))->confirmedPollingUnits());
    }
}
