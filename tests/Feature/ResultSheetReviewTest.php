<?php

use App\Enums\ReviewVerdict;
use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreResultSheetReviewRequest;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use App\Support\VoterIdentificationNumber;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const REVIEW_VIN = ['90F5', '4321'];

beforeEach(function () {
    Storage::fake('public');
    $this->election = Election::factory()->create();
    $this->pollingUnit = PollingUnit::factory()->create();
});

function enterVinForReview(): void
{
    $vin = VoterIdentificationNumber::fromParts(...REVIEW_VIN);

    test()->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $vin->hash(), 'masked' => $vin->masked(), 'report_id' => null]]);
}

/**
 * Files a report with the entered VIN and makes it this session's report, as VIN entry or reporting would.
 */
function reviewerReport(bool $electionHeld = true): Report
{
    $report = Report::factory()
        ->unless($electionHeld, fn ($factory) => $factory->electionNotHeld())
        ->for(test()->election)
        ->for(test()->pollingUnit)
        ->create(['vin_hash' => VoterIdentificationNumber::fromParts(...REVIEW_VIN)->hash()]);
    test()->withSession([EnsureVinEntered::REPORT_KEY => $report->id]);

    return $report;
}

function sheetAtReviewersUnit(): ResultSheet
{
    return ResultSheet::factory()->for(Report::factory()->for(test()->election)->for(test()->pollingUnit))->create();
}

test('reviewing a sheet needs an entered VIN', function () {
    $sheet = sheetAtReviewersUnit();

    $this->post(route('result-sheets.reviews.store', $sheet), ['verdict' => 'confirm'])->assertRedirect(route('vin.create'));

    expect(ResultSheetReview::count())->toBe(0);
});

test('a voter can confirm a sheet another voter uploaded for their polling unit', function () {
    enterVinForReview();
    $report = reviewerReport();
    $sheet = sheetAtReviewersUnit();

    $this->post(route('result-sheets.reviews.store', $sheet), ['verdict' => 'confirm'])
        ->assertRedirect(route('result-sheets.create'));

    $review = ResultSheetReview::sole();

    expect($review->result_sheet_id)->toBe($sheet->id)
        ->and($review->report_id)->toBe($report->id)
        ->and($review->verdict)->toBe(ReviewVerdict::Confirm);

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sheets.0.confirmations', 1)
        ->where('sheets.0.status', 'partly_confirmed')
        ->where('yourReview.result_sheet_id', $sheet->id)
        ->where('yourReview.verdict', 'confirm'));
});

test('a voter can dispute a sheet without uploading their own', function () {
    enterVinForReview();
    reviewerReport();
    $sheet = sheetAtReviewersUnit();

    $this->post(route('result-sheets.reviews.store', $sheet), ['verdict' => 'dispute'])
        ->assertRedirect(route('result-sheets.create'));

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sheets.0.disputes', 1)
        ->where('sheets.0.status', 'contested')
        ->where('sheets.0.status_label', 'Disputed by voters')
        ->where('hasUploaded', false));
});

test('a voter cannot review their own sheet', function () {
    enterVinForReview();
    $sheet = ResultSheet::factory()->for(reviewerReport())->create();

    $this->post(route('result-sheets.reviews.store', $sheet), ['verdict' => 'confirm'])->assertForbidden();
});

test('a voter cannot review a sheet from another polling unit', function () {
    enterVinForReview();
    reviewerReport();
    $elsewhere = ResultSheet::factory()->for(Report::factory()->for($this->election))->create();

    $this->post(route('result-sheets.reviews.store', $elsewhere), ['verdict' => 'confirm'])->assertForbidden();
});

test('a voter whose report says the election did not hold cannot review', function () {
    enterVinForReview();
    reviewerReport(electionHeld: false);

    $this->post(route('result-sheets.reviews.store', sheetAtReviewersUnit()), ['verdict' => 'confirm'])->assertForbidden();
});

test('a voter who has not reported cannot review', function () {
    enterVinForReview();

    $this->post(route('result-sheets.reviews.store', sheetAtReviewersUnit()), ['verdict' => 'confirm'])->assertForbidden();
});

test('a voter can review only one sheet', function () {
    enterVinForReview();
    $report = reviewerReport();
    ResultSheetReview::factory()->for(sheetAtReviewersUnit())->for($report)->create();

    $this->post(route('result-sheets.reviews.store', sheetAtReviewersUnit()), ['verdict' => 'dispute'])
        ->assertSessionHasErrors(['verdict' => StoreResultSheetReviewRequest::alreadyReviewedMessage()]);

    expect(ResultSheetReview::count())->toBe(1);
});

test('a review must confirm or dispute', function () {
    enterVinForReview();
    reviewerReport();

    $this->post(route('result-sheets.reviews.store', sheetAtReviewersUnit()), ['verdict' => 'maybe'])
        ->assertSessionHasErrors('verdict');

    expect(ResultSheetReview::count())->toBe(0);
});

test('a sheet confirmed by three voters is corroborated', function () {
    enterVinForReview();
    reviewerReport();
    ResultSheetReview::factory()->for(sheetAtReviewersUnit())->count(3)->create();

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sheets.0.status', 'corroborated')
        ->where('sheets.0.status_label', 'Confirmed by several voters'));
});

test('two well-supported sheets at one polling unit are both contested, the better supported first', function () {
    enterVinForReview();
    reviewerReport();
    $first = sheetAtReviewersUnit();
    $rival = sheetAtReviewersUnit();
    ResultSheetReview::factory()->for($first)->count(3)->create();
    ResultSheetReview::factory()->for($rival)->count(4)->create();

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sheets.0.id', $rival->id)
        ->where('sheets.0.status', 'contested')
        ->where('sheets.1.id', $first->id)
        ->where('sheets.1.status', 'contested'));
});
