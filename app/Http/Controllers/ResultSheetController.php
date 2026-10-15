<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreResultSheetRequest;
use App\Models\Election;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\PollingUnitSheets;
use App\Support\ResultSheetImage;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResultSheetController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $election = Election::current();
        $report = $election?->reportFrom($request->session()->get(EnsureVinEntered::SESSION_KEY.'.hash'));

        if ($report === null) {
            return to_route('reports.create');
        }

        if (! $report->election_held) {
            return to_route('home');
        }

        return Inertia::render('ResultSheet/Create', [
            'election' => $election->only('id', 'name'),
            'maskedVin' => $request->session()->get(EnsureVinEntered::SESSION_KEY.'.masked'),
            'pollingUnit' => PollingUnitSheets::pollingUnit($report->pollingUnit),
            'sheets' => PollingUnitSheets::for($election, $report->polling_unit_id, $report),
            'hasUploaded' => $report->resultSheet()->exists(),
            'canAddFigures' => $report->resultSheet()->whereNull('figures_recorded_at')->exists(),
            'figureFields' => PollingUnitSheets::figureFields(),
            'parties' => $election->parties()->orderBy('acronym')->get(['parties.id', 'acronym', 'name']),
            'yourReview' => $report->resultSheetReview()->first()?->only('result_sheet_id', 'verdict'),
        ]);
    }

    public function store(StoreResultSheetRequest $request): RedirectResponse
    {
        /** @var Report $report */
        $report = $request->report();
        /** @var ResultSheetImage $image */
        $image = ResultSheetImage::fromRequest($request);
        $path = $image->store($report->election_id);

        try {
            $report->resultSheet()->create([
                'election_id' => $report->election_id,
                'polling_unit_id' => $report->polling_unit_id,
                'path' => $path,
                'image_hash' => $image->hash,
                'ip_hash' => VoterIdentificationNumber::hmac((string) $request->ip()),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two uploads from the same report raced past the validator's check.
            Storage::disk(ResultSheet::disk())->delete($path);

            throw ValidationException::withMessages(['result_sheet' => StoreResultSheetRequest::alreadyUploadedMessage()]);
        }

        return to_route('result-sheets.create');
    }
}
