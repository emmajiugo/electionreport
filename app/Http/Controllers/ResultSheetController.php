<?php

namespace App\Http\Controllers;

use App\Enums\AppMode;
use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreResultSheetRequest;
use App\Models\Election;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\ResultSheetImage;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $pollingUnit = $report->pollingUnit()->with('ward.lga.state')->firstOrFail();

        return Inertia::render('ResultSheet/Create', [
            'election' => $election->only('id', 'name'),
            'maskedVin' => $request->session()->get(EnsureVinEntered::SESSION_KEY.'.masked'),
            'pollingUnit' => [
                'name' => $pollingUnit->name,
                'code' => $pollingUnit->code,
                'ward' => $pollingUnit->ward->name,
                'lga' => $pollingUnit->ward->lga->name,
                'state' => $pollingUnit->ward->lga->state->name,
            ],
            'sheets' => ResultSheet::query()
                ->where('election_id', $election->id)
                ->where('polling_unit_id', $pollingUnit->id)
                ->oldest()
                ->get()
                ->map(fn (ResultSheet $sheet): array => [
                    'id' => $sheet->id,
                    'url' => $sheet->url(),
                    'uploaded_at' => $sheet->created_at?->toIso8601String(),
                    'is_yours' => $sheet->report_id === $report->id,
                ]),
            'hasUploaded' => $report->resultSheet()->exists(),
        ]);
    }

    public function store(StoreResultSheetRequest $request): RedirectResponse
    {
        /** @var Report $report */
        $report = $request->report();
        $image = ResultSheetImage::fromUpload($request->file('result_sheet'));

        // Test-mode photos get their own prefix so they can be cleared without touching the real election's.
        $path = AppMode::current()->value."/result-sheets/{$report->election_id}/".Str::uuid().'.jpg';
        Storage::disk(ResultSheet::disk())->put($path, $image->jpeg, 'public');

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
