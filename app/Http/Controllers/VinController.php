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

        $request->session()->put(EnsureVinEntered::SESSION_KEY, [
            'hash' => $vin->hash(),
            'masked' => $vin->masked(),
        ]);

        return to_route('reports.create');
    }
}
