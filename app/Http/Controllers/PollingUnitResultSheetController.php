<?php

namespace App\Http\Controllers;

use App\Enums\AppMode;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\State;
use App\Support\ElectionResults;
use App\Support\PollingUnitSheets;
use App\Support\ResultSheetCoverage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PollingUnitResultSheetController extends Controller
{
    /**
     * Public and read-only: anyone can see a polling unit's sheets, how voters reviewed them, and any flags.
     */
    public function show(PollingUnit $pollingUnit): Response
    {
        $election = Election::current() ?? Election::query()->latest('held_on')->first();

        // Cached like the dashboard, because a shared link to a disputed sheet can draw a crowd.
        $sheets = $election === null ? [] : Cache::remember(
            'result-sheets:'.AppMode::current()->value.":{$election->id}:{$pollingUnit->id}",
            ElectionResults::CACHE_SECONDS,
            fn (): array => PollingUnitSheets::for($election, $pollingUnit->id),
        );

        return Inertia::render('ResultSheet/Show', [
            'election' => $election?->only('id', 'name'),
            'pollingUnit' => PollingUnitSheets::pollingUnit($pollingUnit),
            'sheets' => $sheets,
            'figureFields' => PollingUnitSheets::figureFields(),
            'parties' => $election?->parties()->orderBy('acronym')->get(['parties.id', 'acronym', 'name']) ?? [],
        ]);
    }

    /**
     * Public and read-only: the polling units with result sheets, nationally or in one state, disputed first.
     */
    public function index(Request $request): Response
    {
        $election = Election::current() ?? Election::query()->latest('held_on')->first();
        $state = $request->integer('state') ? State::query()->find($request->integer('state')) : null;

        $sheets = $election === null ? null : Cache::remember(
            'result-sheet-units:'.AppMode::current()->value.":{$election->id}:".($state->id ?? 'national'),
            ElectionResults::CACHE_SECONDS,
            function () use ($election, $state): array {
                $coverage = new ResultSheetCoverage($election, $state);

                return [...$coverage->summary(), 'units' => $coverage->pollingUnits()];
            },
        );

        return Inertia::render('ResultSheet/Index', [
            'election' => $election?->only('id', 'name'),
            'selectedState' => $state?->only('id', 'name'),
            'states' => State::query()->orderBy('name')->get(['id', 'name']),
            'sheets' => $sheets,
        ]);
    }
}
