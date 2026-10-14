<?php

namespace App\Http\Controllers;

use App\Enums\ResultSheetStatus;
use App\Enums\ReviewVerdict;
use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreResultSheetRequest;
use App\Models\Election;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\ResultSheetImage;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\Eloquent\Builder;
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
            'sheets' => $this->sheetsAt($report),
            'hasUploaded' => $report->resultSheet()->exists(),
            'canAddFigures' => $report->resultSheet()->whereNull('figures_recorded_at')->exists(),
            'figureFields' => collect(ResultSheet::FIGURES)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
                ->values(),
            'parties' => $election->parties()->orderBy('acronym')->get(['parties.id', 'acronym', 'name']),
            'yourReview' => $report->resultSheetReview()->first()?->only('result_sheet_id', 'verdict'),
        ]);
    }

    /**
     * The polling unit's sheets, best supported first, so a voter sees the leading sheet before any rival.
     *
     * @return array<int, array{id: int, url: string, uploaded_at: ?string, is_yours: bool, confirmations: int, disputes: int, status: string, status_label: string, figures: array<string, mixed>|null}>
     */
    private function sheetsAt(Report $report): array
    {
        $sheets = ResultSheet::query()
            ->where('election_id', $report->election_id)
            ->where('polling_unit_id', $report->polling_unit_id)
            ->withCount([
                'reviews as confirmations_count' => fn (Builder $query) => $query->where('verdict', ReviewVerdict::Confirm),
                'reviews as disputes_count' => fn (Builder $query) => $query->where('verdict', ReviewVerdict::Dispute),
            ])
            ->get()
            ->sortBy([['confirmations_count', 'desc'], ['id', 'asc']]);

        $reachedThreshold = $sheets->filter(
            fn (ResultSheet $sheet): bool => $sheet->confirmations_count >= ResultSheetStatus::CORROBORATION_THRESHOLD,
        );

        return $sheets->map(function (ResultSheet $sheet) use ($report, $reachedThreshold): array {
            $status = ResultSheetStatus::fromReviews(
                $sheet->confirmations_count,
                $sheet->disputes_count,
                $reachedThreshold->contains(fn (ResultSheet $rival): bool => $rival->id !== $sheet->id),
            );

            return [
                'id' => $sheet->id,
                'url' => $sheet->url(),
                'uploaded_at' => $sheet->created_at?->toIso8601String(),
                'is_yours' => $sheet->report_id === $report->id,
                'confirmations' => $sheet->confirmations_count,
                'disputes' => $sheet->disputes_count,
                'status' => $status->value,
                'status_label' => $status->label(),
                'figures' => $sheet->hasFigures()
                    ? [...$sheet->only(array_keys(ResultSheet::FIGURES)), 'party_votes' => $sheet->party_votes]
                    : null,
            ];
        })->values()->all();
    }

    public function store(StoreResultSheetRequest $request): RedirectResponse
    {
        /** @var Report $report */
        $report = $request->report();
        $image = ResultSheetImage::fromUpload($request->file('result_sheet'));
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
