<?php

namespace App\Http\Controllers;

use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreReportRequest;
use App\Models\Election;
use App\Models\Party;
use App\Models\State;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function create(Request $request): Response
    {
        $election = Election::current();

        return Inertia::render('Report/Create', [
            'election' => $election?->only('id', 'name'),
            'maskedVin' => $request->session()->get(EnsureVinEntered::SESSION_KEY.'.masked'),
            'parties' => $election?->parties()->orderBy('acronym')->get()
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'acronym' => $party->acronym,
                    'name' => $party->name,
                    'logo_url' => $party->logo_url,
                ]) ?? [],
            'states' => State::query()->orderBy('name')->get(['id', 'name']),
            'notHeldReasons' => ElectionNotHeldReason::options(),
            'notVotedReasons' => NotVotedReason::options(),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        /** @var Election $election */
        $election = $request->election();

        try {
            $election->reports()->create([
                'polling_unit_id' => $request->integer('polling_unit_id'),
                'vin_hash' => $request->vinHash(),
                'vin_masked' => $request->maskedVin(),
                'election_held' => $request->boolean('election_held'),
                'not_held_reason' => $request->input('not_held_reason'),
                'voted' => $request->boolean('election_held') ? $request->boolean('voted') : null,
                'not_voted_reason' => $request->input('not_voted_reason'),
                'party_id' => $request->input('party_id'),
                'reason_details' => $request->input('reason_details'),
                'ip_hash' => VoterIdentificationNumber::hmac((string) $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 512) ?: null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two submissions for the same VIN raced past the validator's duplicate check.
            throw ValidationException::withMessages(['vin' => Election::duplicateVinMessage()]);
        }

        $request->session()->forget(EnsureVinEntered::SESSION_KEY);
        $request->session()->flash('report_submitted', true);

        return to_route('reports.submitted');
    }

    public function submitted(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->get('report_submitted')) {
            return to_route('home');
        }

        return Inertia::render('Report/Submitted');
    }
}
