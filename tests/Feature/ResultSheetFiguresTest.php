<?php

use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreResultSheetFiguresRequest;
use App\Models\Election;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\VoterIdentificationNumber;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const FIGURES_VIN = '90F5B1C3E2950777777';

beforeEach(function () {
    Storage::fake('public');
    $this->election = Election::factory()->create();
    [$this->partyA, $this->partyB] = Party::factory()->count(2)->create()->all();
    $this->election->parties()->attach([$this->partyA->id, $this->partyB->id]);
    $this->pollingUnit = PollingUnit::factory()->create();
});

function enterVinForFigures(): void
{
    $vin = VoterIdentificationNumber::fromInput(FIGURES_VIN);

    test()->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $vin->hash(), 'masked' => $vin->masked()]]);
}

function figuresUploaderReport(): Report
{
    return Report::factory()->for(test()->election)->for(test()->pollingUnit)->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(FIGURES_VIN)->hash(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sheetFigures(array $overrides = []): array
{
    return [
        'registered_voters' => 750,
        'accredited_voters' => 312,
        'ballots_issued' => 750,
        'unused_ballots' => 436,
        'spoiled_ballots' => 2,
        'rejected_ballots' => 10,
        'total_valid_votes' => 302,
        'total_used_ballots' => 314,
        'party_votes' => [test()->partyA->id => 190, test()->partyB->id => 112],
        ...$overrides,
    ];
}

test('the uploader can record the figures printed on their sheet', function () {
    enterVinForFigures();
    $sheet = ResultSheet::factory()->for(figuresUploaderReport())->create();

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('canAddFigures', true)
        ->has('figureFields', count(ResultSheet::FIGURES))
        ->has('parties', 2)
        ->where('sheets.0.figures', null));

    $this->post(route('result-sheets.figures.store'), sheetFigures())
        ->assertRedirect(route('result-sheets.create'));

    $sheet->refresh();

    expect($sheet->accredited_voters)->toBe(312)
        ->and($sheet->total_valid_votes)->toBe(302)
        ->and($sheet->party_votes)->toEqual([$this->partyA->id => 190, $this->partyB->id => 112])
        ->and($sheet->hasFigures())->toBeTrue();

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('canAddFigures', false)
        ->where('sheets.0.figures.rejected_ballots', 10)
        ->where("sheets.0.figures.party_votes.{$this->partyA->id}", 190));
});

test('figures that do not add up are kept as printed', function () {
    enterVinForFigures();
    $sheet = ResultSheet::factory()->for(figuresUploaderReport())->create();

    $this->post(route('result-sheets.figures.store'), sheetFigures(['total_valid_votes' => 999]))
        ->assertRedirect(route('result-sheets.create'));

    expect($sheet->refresh()->total_valid_votes)->toBe(999);
});

test('figures can be recorded only once', function () {
    enterVinForFigures();
    $sheet = ResultSheet::factory()->for(figuresUploaderReport())->create();
    $this->post(route('result-sheets.figures.store'), sheetFigures());

    $this->post(route('result-sheets.figures.store'), sheetFigures(['accredited_voters' => 1]))
        ->assertSessionHasErrors(['figures' => StoreResultSheetFiguresRequest::alreadyRecordedMessage()]);

    expect($sheet->refresh()->accredited_voters)->toBe(312);
});

test('a voter without an uploaded sheet cannot record figures', function () {
    enterVinForFigures();
    figuresUploaderReport();
    ResultSheet::factory()->for(Report::factory()->for($this->election)->for($this->pollingUnit))->create();

    $this->post(route('result-sheets.figures.store'), sheetFigures())->assertForbidden();
});

test('recording figures needs an entered VIN', function () {
    $this->post(route('result-sheets.figures.store'), sheetFigures())->assertRedirect(route('home'));
});

test('unusable figures are rejected', function (Closure $case) {
    enterVinForFigures();
    $sheet = ResultSheet::factory()->for(figuresUploaderReport())->create();
    [$overrides, $field, $message] = $case();

    $this->post(route('result-sheets.figures.store'), sheetFigures($overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect($sheet->refresh()->hasFigures())->toBeFalse();
})->with([
    'a missing total' => fn () => [['rejected_ballots' => null], 'rejected_ballots', 'Enter this figure. Use 0 if the box on the sheet is empty.'],
    'a negative number' => fn () => [['spoiled_ballots' => -1], 'spoiled_ballots', 'Enter a whole number.'],
    'a fraction' => fn () => [['accredited_voters' => 3.5], 'accredited_voters', 'Enter a whole number.'],
    'an implausibly large number' => fn () => [['registered_voters' => 100_001], 'registered_voters', 'This number is too large.'],
    'a party left out' => fn () => [['party_votes' => [test()->partyA->id => 190]], 'party_votes.'.test()->partyB->id, 'Enter this figure. Use 0 if the box on the sheet is empty.'],
    'a party not on the ballot' => fn () => [['party_votes' => [test()->partyA->id => 1, test()->partyB->id => 1, Party::factory()->create()->id => 5]], 'party_votes', 'Enter votes only for the parties on the ballot.'],
]);
