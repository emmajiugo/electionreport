<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\State;
use App\Support\ElectionResults;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultsController extends Controller
{
    public function index(Request $request): Response
    {
        $election = Election::current() ?? Election::query()->latest('held_on')->first();
        $state = $request->integer('state') ? State::query()->find($request->integer('state')) : null;

        return Inertia::render('Results/Index', [
            'election' => $election?->only('id', 'name'),
            'selectedState' => $state?->only('id', 'code', 'name'),
            'results' => $election ? (new ElectionResults($election, $state))->toArray() : null,
        ]);
    }
}
