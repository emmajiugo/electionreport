<?php

namespace App\Http\Controllers;

use App\Enums\ReviewVerdict;
use App\Http\Requests\StoreResultSheetReviewRequest;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ResultSheetReviewController extends Controller
{
    public function store(StoreResultSheetReviewRequest $request, ResultSheet $sheet): RedirectResponse
    {
        /** @var Report $report */
        $report = $request->report();

        try {
            $sheet->reviews()->create([
                'report_id' => $report->id,
                'verdict' => $request->enum('verdict', ReviewVerdict::class),
                'ip_hash' => VoterIdentificationNumber::hmac((string) $request->ip()),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two reviews from the same report raced past the validator's check.
            throw ValidationException::withMessages(['verdict' => StoreResultSheetReviewRequest::alreadyReviewedMessage()]);
        }

        return to_route('result-sheets.create');
    }
}
