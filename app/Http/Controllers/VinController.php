<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreVinRequest;
use App\Models\Election;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VinController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Report/Vin', [
            'election' => Election::current()?->only('id', 'name'),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    public function store(StoreVinRequest $request): RedirectResponse
    {
        $vin = $request->vin();

        // A voter whose election held may come back to add the result sheet. Two voters can share a
        // partial VIN, so only a single match is taken as theirs; the sheet page lets them back out.
        $returningReportIds = $request->election()?->reports()
            ->where('vin_hash', $vin->hash())
            ->where('election_held', true)
            ->limit(2)
            ->pluck('id');

        $request->session()->put(EnsureVinEntered::SESSION_KEY, [
            'hash' => $vin->hash(),
            'masked' => $vin->masked(),
            'report_id' => $returningReportIds?->count() === 1 ? $returningReportIds->first() : null,
        ]);

        if ($returningReportIds?->count() === 1) {
            return to_route('result-sheets.create');
        }

        return to_route('reports.create');
    }
}
