<?php

use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\VoterIdentificationNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

const VIN = '90F5B1C3E2950123456';

beforeEach(function () {
    $this->election = Election::factory()->create();
    $this->party = Party::factory()->create();
    $this->election->parties()->attach($this->party, ['candidate' => 'Ada Obi', 'running_mate' => 'Musa Bello']);
    $this->pollingUnit = PollingUnit::factory()->create();
});

function enterVin(string $vin = VIN): void
{
    $vin = VoterIdentificationNumber::fromInput($vin);

    test()->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $vin->hash(), 'masked' => $vin->masked()]]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function votedReport(array $overrides = []): array
{
    return [
        'polling_unit_id' => test()->pollingUnit->id,
        'election_held' => true,
        'voted' => true,
        'party_id' => test()->party->id,
        ...$overrides,
    ];
}

test('the report form lists the open election, its parties and reasons', function () {
    enterVin();

    $this->get(route('reports.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report/Create')
            ->where('election.id', $this->election->id)
            ->where('maskedVin', '90F5***********3456')
            ->has('parties', 1)
            ->where('parties.0.acronym', $this->party->acronym)
            ->where('parties.0.candidate', 'Ada Obi')
            ->where('parties.0.running_mate', 'Musa Bello')
            ->has('states', 1)
            ->has('notHeldReasons', count(ElectionNotHeldReason::cases()))
            ->has('notVotedReasons', count(NotVotedReason::cases())));
});

test('party logos are sent as public URLs', function () {
    enterVin();
    $this->party->update(['logo_path' => 'images/parties/APC.png']);

    $this->get(route('reports.create'))
        ->assertInertia(fn (Assert $page) => $page->where('parties.0.logo_url', asset('images/parties/APC.png')));
});

test('story mode gets the same election, parties and reasons as the report form', function () {
    enterVin();
    $formProps = $this->get(route('reports.create'))->viewData('page')['props'];

    $this->get(route('reports.play'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Play/Show'));

    expect($this->get(route('reports.play'))->viewData('page')['props'])->toEqual($formProps);
});

test('reporting routes cannot be reached without entering a VIN', function () {
    $this->get(route('reports.create'))->assertRedirect(route('home'));
    $this->get(route('reports.play'))->assertRedirect(route('home'));
    $this->post(route('reports.store'), votedReport())->assertRedirect(route('home'));

    expect(Report::count())->toBe(0);
});

test('a voter can report who they voted for without the raw VIN being stored', function () {
    enterVin();

    $this->post(route('reports.store'), votedReport())->assertRedirect(route('reports.submitted'));

    $report = Report::sole();

    expect($report->party_id)->toBe($this->party->id)
        ->and($report->voted)->toBeTrue()
        ->and($report->vin_hash)->toBe(VoterIdentificationNumber::fromInput(VIN)->hash())
        ->and($report->vin_masked)->toBe('90F5***********3456')
        ->and($report->ip_hash)->not->toBe('127.0.0.1')
        ->and(json_encode(Report::query()->toBase()->first()))->not->toContain(VIN);
});

test('reporting the election did not hold ends the VIN session and shows the thank you page once', function () {
    enterVin();

    $this->post(route('reports.store'), [
        'polling_unit_id' => $this->pollingUnit->id,
        'election_held' => false,
        'not_held_reason' => ElectionNotHeldReason::OfficialsAbsent->value,
    ])->assertSessionMissing(EnsureVinEntered::SESSION_KEY);

    $this->get(route('reports.submitted'))->assertInertia(fn (Assert $page) => $page
        ->component('Report/Submitted')
        ->where('canAddResultSheet', false));
    $this->get(route('reports.submitted'))->assertRedirect(route('home'));
    $this->get(route('reports.create'))->assertRedirect(route('home'));
});

test('reporting the election held keeps the VIN session so the voter can add the result sheet', function () {
    enterVin();

    $this->post(route('reports.store'), votedReport())->assertSessionHas(EnsureVinEntered::SESSION_KEY);

    $this->get(route('reports.submitted'))->assertInertia(fn (Assert $page) => $page->where('canAddResultSheet', true));
    $this->get(route('reports.create'))->assertRedirect(route('result-sheets.create'));
    $this->get(route('reports.play'))->assertRedirect(route('result-sheets.create'));
});

test('the thank you page cannot be opened directly', function () {
    $this->get(route('reports.submitted'))->assertRedirect(route('home'));
});

test('a voter can report that the election did not hold', function () {
    enterVin();

    $this->post(route('reports.store'), [
        'polling_unit_id' => $this->pollingUnit->id,
        'election_held' => false,
        'not_held_reason' => ElectionNotHeldReason::MaterialsNotDelivered->value,
    ])->assertRedirect(route('reports.submitted'));

    expect(Report::sole())
        ->election_held->toBeFalse()
        ->not_held_reason->toBe(ElectionNotHeldReason::MaterialsNotDelivered)
        ->voted->toBeNull();
});

test('choosing other as the reason requires details', function () {
    enterVin();
    $report = [
        'polling_unit_id' => $this->pollingUnit->id,
        'election_held' => true,
        'voted' => false,
        'not_voted_reason' => NotVotedReason::Other->value,
    ];

    $this->post(route('reports.store'), $report)->assertSessionHasErrors('reason_details');

    $this->post(route('reports.store'), [...$report, 'reason_details' => 'Agents seized the ballot box'])
        ->assertRedirect(route('reports.submitted'));

    expect(Report::sole()->reason_details)->toBe('Agents seized the ballot box');
});

test('a VIN that reported after entering it cannot report again', function () {
    enterVin();
    Report::factory()->for($this->election)->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(VIN)->hash(),
    ]);

    $this->post(route('reports.store'), votedReport())
        ->assertSessionHasErrors(['vin' => Election::duplicateVinMessage()]);

    expect(Report::count())->toBe(1);
});

test('invalid report input is rejected', function (array $overrides, string $field) {
    enterVin();

    $this->post(route('reports.store'), votedReport($overrides))->assertSessionHasErrors($field);

    expect(Report::count())->toBe(0);
})->with([
    'unknown polling unit' => [['polling_unit_id' => 999999], 'polling_unit_id'],
    'party not on the ballot' => [fn () => ['party_id' => Party::factory()->create()->id], 'party_id'],
    'reason given alongside a vote' => [['not_voted_reason' => NotVotedReason::NoPvc->value], 'not_voted_reason'],
    'unknown reason' => [['voted' => false, 'party_id' => null, 'not_voted_reason' => 'bribed'], 'not_voted_reason'],
    'honeypot filled' => [['website' => 'https://spam.example'], 'website'],
]);

test('reports are rejected when no election is accepting them', function () {
    enterVin();
    $this->election->update(['is_accepting_reports' => false]);

    $this->post(route('reports.store'), votedReport())->assertSessionHasErrors('election');

    expect(Report::count())->toBe(0);
});

test('report submissions are rate limited per session, not per shared IP', function () {
    // Reporting the election did not hold ends each voter's VIN session, so the next voter on this session starts clean.
    $notHeldReport = [
        'polling_unit_id' => $this->pollingUnit->id,
        'election_held' => false,
        'not_held_reason' => ElectionNotHeldReason::OfficialsAbsent->value,
    ];
    $this->withCookie(config('session.cookie'), Str::random(40));

    foreach (range(1, 10) as $attempt) {
        enterVin(str_pad((string) $attempt, 19, 'A'));
        $this->post(route('reports.store'), $notHeldReport);
    }

    enterVin(str_repeat('B', 19));
    $this->post(route('reports.store'), $notHeldReport)->assertTooManyRequests();

    $this->withCookie(config('session.cookie'), Str::random(40))
        ->post(route('reports.store'), $notHeldReport)
        ->assertRedirect(route('reports.submitted'));

    expect(Report::count())->toBe(11);
});

test('a voter can attach the result sheet to their report', function () {
    Storage::fake('public');
    enterVin();

    $this->post(route('reports.store'), votedReport(['result_sheet' => UploadedFile::fake()->image('sheet.jpg', 800, 600)]))
        ->assertRedirect(route('reports.submitted'));

    $sheet = ResultSheet::sole();

    expect($sheet->report_id)->toBe(Report::sole()->id)
        ->and($sheet->polling_unit_id)->toBe($this->pollingUnit->id);
    Storage::disk('public')->assertExists($sheet->path);

    $this->get(route('reports.submitted'))->assertInertia(fn (Assert $page) => $page->where('canAddResultSheet', false));
});

test('a result sheet cannot be attached to a report that the election did not hold', function () {
    Storage::fake('public');
    enterVin();

    $this->post(route('reports.store'), [
        'polling_unit_id' => $this->pollingUnit->id,
        'election_held' => false,
        'not_held_reason' => ElectionNotHeldReason::OfficialsAbsent->value,
        'result_sheet' => UploadedFile::fake()->image('sheet.jpg', 800, 600),
    ])->assertSessionHasErrors('result_sheet');

    expect(Report::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('an unusable result sheet photo holds back the whole report', function () {
    Storage::fake('public');
    enterVin();

    $this->post(route('reports.store'), votedReport(['result_sheet' => UploadedFile::fake()->image('sheet.jpg', 2001, 100)]))
        ->assertSessionHasErrors(['result_sheet' => 'This photo is too large. Please try again.']);

    expect(Report::count())->toBe(0)
        ->and(ResultSheet::count())->toBe(0);
});
