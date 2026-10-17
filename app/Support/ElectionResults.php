<?php

namespace App\Support;

use App\Enums\AppMode;
use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Models\Election;
use App\Models\Lga;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates voter reports for the public results dashboard, nationally or for one state.
 *
 * @phpstan-type AreaRow array{id: int, code: string, name: string, reports: int, held: int, not_held: int, voted: int, not_voted: int, leading_party: string|null}
 */
final readonly class ElectionResults
{
    /**
     * Results are cached briefly because the dashboard is public and polled while reports flood in.
     */
    public const int CACHE_SECONDS = 60;

    public function __construct(private Election $election, private ?State $state = null) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $key = 'results:'.AppMode::current()->value.":{$this->election->id}:".($this->state->id ?? 'national');

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => [
            'summary' => $this->summary(),
            'parties' => $this->partyVotes(),
            'notHeldReasons' => $this->reasonCounts('not_held_reason', ElectionNotHeldReason::cases()),
            'notVotedReasons' => $this->reasonCounts('not_voted_reason', NotVotedReason::cases()),
            'states' => $this->areaRows(State::query()->orderBy('name')->get(), 'lgas.state_id', nationally: true),
            'lgas' => $this->state
                ? $this->areaRows($this->state->lgas()->orderBy('name')->get(), 'lgas.id')
                : [],
            'sheets' => $this->resultSheets(),
            'standings' => (new ReportCorroboration($this->election, $this->state))->standings(),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Counts only; the polling units themselves are listed on their own page so the dashboard stays light.
     *
     * @return array{polling_units: int, corroborated: int, contested: int}
     */
    private function resultSheets(): array
    {
        return (new ResultSheetCoverage($this->election, $this->state))->summary();
    }

    /**
     * @return Builder<Report>
     */
    private function reports(bool $nationally = false): Builder
    {
        // Aliased so the raw SQL below reads "reports." whichever table the current mode uses.
        return Report::query()
            ->from((new Report)->getTable().' as reports')
            ->where('reports.election_id', $this->election->id)
            ->join('polling_units', 'polling_units.id', '=', 'reports.polling_unit_id')
            ->join('wards', 'wards.id', '=', 'polling_units.ward_id')
            ->join('lgas', 'lgas.id', '=', 'wards.lga_id')
            ->when(! $nationally && $this->state, fn (Builder $query) => $query->where('lgas.state_id', $this->state?->id));
    }

    /**
     * Portable across SQLite, MySQL and Postgres, whose boolean columns differ.
     */
    private const string TALLIES = 'count(*) as reports,
        sum(case when reports.election_held then 1 else 0 end) as held,
        sum(case when reports.election_held and reports.voted then 1 else 0 end) as voted';

    /**
     * @return array{reports: int, held: int, not_held: int, voted: int, not_voted: int, polling_units_reporting: int, polling_units: int}
     */
    private function summary(): array
    {
        /** @var object{reports: int|string|null, held: int|string|null, voted: int|string|null, polling_units_reporting: int|string|null} $row */
        $row = $this->reports()
            ->selectRaw(self::TALLIES.', count(distinct reports.polling_unit_id) as polling_units_reporting')
            ->toBase()
            ->first();

        $pollingUnits = PollingUnit::query()
            ->when($this->state, fn (Builder $query) => $query
                ->join('wards', 'wards.id', '=', 'polling_units.ward_id')
                ->join('lgas', 'lgas.id', '=', 'wards.lga_id')
                ->where('lgas.state_id', $this->state?->id))
            ->count();

        return [
            ...$this->tallies($row),
            'polling_units_reporting' => (int) $row->polling_units_reporting,
            'polling_units' => $pollingUnits,
        ];
    }

    /**
     * @return list<array{id: int, acronym: string, name: string, logo_url: string|null, votes: int}>
     */
    private function partyVotes(): array
    {
        /** @var Collection<int, int|string> $votes */
        $votes = $this->reports()
            ->whereNotNull('reports.party_id')
            ->groupBy('reports.party_id')
            ->selectRaw('reports.party_id, count(*) as votes')
            ->toBase()
            ->pluck('votes', 'party_id');

        return $this->election->parties()->get()
            ->map(fn (Party $party): array => [
                'id' => $party->id,
                'acronym' => $party->acronym,
                'name' => $party->name,
                'logo_url' => $party->logo_url,
                'votes' => (int) ($votes[$party->id] ?? 0),
            ])
            ->sortBy([['votes', 'desc'], ['acronym', 'asc']])
            ->pipe(fn (Collection $parties): array => array_values($parties->all()));
    }

    /**
     * @param  'not_held_reason'|'not_voted_reason'  $column
     * @param  list<ElectionNotHeldReason|NotVotedReason>  $cases
     * @return list<array{value: string, label: string, count: int}>
     */
    private function reasonCounts(string $column, array $cases): array
    {
        /** @var Collection<string, int|string> $counts */
        $counts = $this->reports()
            ->whereNotNull("reports.{$column}")
            ->groupBy("reports.{$column}")
            ->selectRaw("reports.{$column} as reason, count(*) as total")
            ->toBase()
            ->pluck('total', 'reason');

        return collect($cases)
            ->map(fn (ElectionNotHeldReason|NotVotedReason $reason): array => [
                'value' => $reason->value,
                'label' => $reason->label(),
                'count' => (int) ($counts[$reason->value] ?? 0),
            ])
            ->sortByDesc('count')
            ->pipe(fn (Collection $reasons): array => array_values($reasons->all()));
    }

    /**
     * @param  Collection<int, State>|Collection<int, Lga>  $areas
     * @param  'lgas.state_id'|'lgas.id'  $groupColumn
     * @return list<AreaRow>
     */
    private function areaRows(Collection $areas, string $groupColumn, bool $nationally = false): array
    {
        $tallies = $this->reports($nationally)
            ->groupBy($groupColumn)
            ->selectRaw("{$groupColumn} as area_id, ".self::TALLIES)
            ->toBase()
            ->get()
            ->keyBy('area_id');

        $leaders = $this->leadingParties($groupColumn, $nationally);

        return $areas->map(function (Model $area) use ($tallies, $leaders): array {
            /** @var State|Lga $area */
            return [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                ...$this->tallies($tallies->get($area->id)),
                'leading_party' => $leaders[$area->id] ?? null,
            ];
        })->pipe(fn (Collection $rows): array => array_values($rows->all()));
    }

    /**
     * @param  'lgas.state_id'|'lgas.id'  $groupColumn
     * @return array<int, string>
     */
    private function leadingParties(string $groupColumn, bool $nationally): array
    {
        return $this->reports($nationally)
            ->join('parties', 'parties.id', '=', 'reports.party_id')
            ->groupBy($groupColumn, 'parties.acronym')
            ->selectRaw("{$groupColumn} as area_id, parties.acronym, count(*) as votes")
            ->toBase()
            ->get()
            ->groupBy('area_id')
            ->map(fn (Collection $rows): string => $rows->sortBy([['votes', 'desc'], ['acronym', 'asc']])->first()->acronym)
            ->all();
    }

    /**
     * @return array{reports: int, held: int, not_held: int, voted: int, not_voted: int}
     */
    private function tallies(?object $row): array
    {
        $reports = (int) ($row->reports ?? 0);
        $held = (int) ($row->held ?? 0);
        $voted = (int) ($row->voted ?? 0);

        return [
            'reports' => $reports,
            'held' => $held,
            'not_held' => $reports - $held,
            'voted' => $voted,
            'not_voted' => $held - $voted,
        ];
    }
}
