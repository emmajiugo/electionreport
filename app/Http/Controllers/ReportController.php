<?php

namespace App\Http\Controllers;

use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreReportRequest;
use App\Models\Election;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\ResultSheet;
use App\Models\State;
use App\Support\ResultSheetImage;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        return $this->reportPage($request, 'Report/Create');
    }

    /**
     * Story mode: the same questions as the form, told as a day at the polling unit.
     */
    public function play(Request $request): Response|RedirectResponse
    {
        return $this->reportPage($request, 'Play/Show');
    }

    private function reportPage(Request $request, string $component): Response|RedirectResponse
    {
        $election = Election::current();

        if ($election?->hasReportFrom($request->session()->get(EnsureVinEntered::SESSION_KEY.'.hash'))) {
            return to_route('result-sheets.create');
        }

        return Inertia::render($component, [
            'election' => $election?->only('id', 'name'),
            'maskedVin' => $request->session()->get(EnsureVinEntered::SESSION_KEY.'.masked'),
            'parties' => $election?->parties()->orderBy('acronym')->get()
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'acronym' => $party->acronym,
                    'name' => $party->name,
                    'logo_url' => $party->logo_url,
                    'candidate' => $party->pivot->candidate,
                    'running_mate' => $party->pivot->running_mate,
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
        $ipHash = VoterIdentificationNumber::hmac((string) $request->ip());
        $image = $request->hasFile('result_sheet') ? ResultSheetImage::fromUpload($request->file('result_sheet')) : null;
        $path = $image?->store($election->id);

        try {
            DB::transaction(function () use ($request, $election, $ipHash, $image, $path): void {
                $report = $election->reports()->create([
                    'polling_unit_id' => $request->integer('polling_unit_id'),
                    'vin_hash' => $request->vinHash(),
                    'vin_masked' => $request->maskedVin(),
                    'election_held' => $request->boolean('election_held'),
                    'not_held_reason' => $request->input('not_held_reason'),
                    'voted' => $request->boolean('election_held') ? $request->boolean('voted') : null,
                    'not_voted_reason' => $request->input('not_voted_reason'),
                    'party_id' => $request->input('party_id'),
                    'reason_details' => $request->input('reason_details'),
                    'ip_hash' => $ipHash,
                    'user_agent' => substr((string) $request->userAgent(), 0, 512) ?: null,
                ]);

                if ($image !== null && $path !== null) {
                    $report->resultSheet()->create([
                        'election_id' => $election->id,
                        'polling_unit_id' => $report->polling_unit_id,
                        'path' => $path,
                        'image_hash' => $image->hash,
                        'ip_hash' => $ipHash,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            if ($path !== null) {
                Storage::disk(ResultSheet::disk())->delete($path);
            }

            // Two submissions for the same VIN raced past the validator's duplicate check.
            throw ValidationException::withMessages(['vin' => Election::duplicateVinMessage()]);
        }

        // A voter whose election held keeps the VIN session so they can add the result sheet straight away.
        if (! $request->boolean('election_held')) {
            $request->session()->forget(EnsureVinEntered::SESSION_KEY);
        }

        $request->session()->flash('report_submitted', true);

        // Only where and in which election: a shared card must never reveal how someone voted.
        $lga = PollingUnit::with('ward.lga.state')->findOrFail($request->integer('polling_unit_id'))->ward->lga;
        $request->session()->flash('share_card', [
            'lga' => $lga->name,
            'state' => $lga->state->name,
            'election' => $election->name,
        ]);

        return to_route('reports.submitted');
    }

    public function submitted(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->get('report_submitted')) {
            return to_route('home');
        }

        $report = $request->session()->has(EnsureVinEntered::SESSION_KEY)
            ? Election::current()?->reportFrom($request->session()->get(EnsureVinEntered::SESSION_KEY.'.hash'))
            : null;

        return Inertia::render('Report/Submitted', [
            'canAddResultSheet' => $report !== null && $report->resultSheet()->doesntExist(),
            'shareCard' => $request->session()->get('share_card'),
        ]);
    }
}
