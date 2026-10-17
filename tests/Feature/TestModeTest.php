<?php

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Rules\Turnstile;
use App\Support\ResultSheetImage;
use App\Support\VoterIdentificationNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const TESTER_VIN = ['90F5', '3456'];

beforeEach(function () {
    $this->election = Election::factory()->create();
    $this->party = Party::factory()->create();
    $this->election->parties()->attach($this->party);
    $this->pollingUnit = PollingUnit::factory()->create();

    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
});

function useMode(string $mode): void
{
    config(['electionreport.mode' => $mode]);
}

/**
 * @param  array{string, string}  $vin  the first and last characters of the VIN
 */
function submitReportAs(array $vin): void
{
    $hashed = VoterIdentificationNumber::fromParts(...$vin);

    test()->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $hashed->hash(), 'masked' => $hashed->masked(), 'report_id' => null]])
        ->post(route('reports.store'), [
            'polling_unit_id' => test()->pollingUnit->id,
            'election_held' => true,
            'voted' => true,
            'party_id' => test()->party->id,
        ])
        ->assertRedirect(route('reports.submitted'));
}

test('reports submitted in test mode are kept apart from live reports', function () {
    useMode('test');

    submitReportAs(TESTER_VIN);

    expect(DB::table('test_reports')->count())->toBe(1)
        ->and(DB::table('reports')->count())->toBe(0);
});

test('reports submitted in live mode go to the live table', function () {
    submitReportAs(TESTER_VIN);

    expect(DB::table('reports')->count())->toBe(1)
        ->and(DB::table('test_reports')->count())->toBe(0);
});

test('a VIN used while testing can still report once the app goes live', function () {
    useMode('test');
    submitReportAs(TESTER_VIN);

    useMode('live');

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin_first' => TESTER_VIN[0], 'vin_last' => TESTER_VIN[1]])
        ->assertRedirect(route('reports.create'));
});

test('the dashboard shows only the data for the current mode', function () {
    useMode('test');
    Report::factory()->for($this->election)->for($this->pollingUnit)->count(3)->create();
    useMode('live');
    Report::factory()->for($this->election)->for($this->pollingUnit)->create();

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page->where('results.summary.reports', 1));

    useMode('test');

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page->where('results.summary.reports', 3));
});

test('pages know whether the app is in test mode', function (string $configured, string $shared) {
    useMode($configured);
    config(['electionreport.feedback_url' => 'https://forms.example/feedback']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('mode', $shared)
        ->where('feedbackUrl', $shared === 'test' ? 'https://forms.example/feedback' : null));
})->with([
    'test' => ['test', 'test'],
    'live' => ['live', 'live'],
    'unrecognised value falls back to test' => ['production', 'test'],
    'empty value falls back to test' => ['', 'test'],
]);

test('each test table matches its live table', function (string $live) {
    $columns = fn (string $table) => collect(Schema::getColumns($table))
        ->map(fn (array $column) => [$column['name'], $column['type_name'], $column['nullable']])
        ->all();

    expect($columns("test_{$live}"))->toBe($columns($live));
})->with(['reports', 'result_sheets', 'result_sheet_reviews']);

test('a practice result sheet saves the sample in place of the photo, in the test table', function () {
    Storage::fake('public');
    useMode('test');
    submitReportAs(TESTER_VIN);

    // The photo is sent anyway, as an old or tampered client might, to show it is never stored.
    $this->post(route('result-sheets.store'), [
        'sample_result_sheet' => true,
        'result_sheet' => UploadedFile::fake()->image('sheet.jpg', 800, 600),
    ])->assertRedirect(route('result-sheets.create'));

    expect(DB::table('test_result_sheets')->count())->toBe(1)
        ->and(DB::table('result_sheets')->count())->toBe(0)
        ->and(DB::table('test_result_sheets')->value('path'))->toBe(ResultSheetImage::SAMPLE_PATH)
        ->and(Storage::disk('public')->allFiles())->toBe([]);

    $this->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sheets.0.url', asset(ResultSheetImage::SAMPLE_PATH))
        ->where('hasUploaded', true));
});

test('a practice upload without the sample flag is rejected', function () {
    Storage::fake('public');
    useMode('test');
    submitReportAs(TESTER_VIN);

    $this->post(route('result-sheets.store'), ['result_sheet' => UploadedFile::fake()->image('sheet.jpg', 800, 600)])
        ->assertSessionHasErrors('sample_result_sheet');

    expect(DB::table('test_result_sheets')->count())->toBe(0);
});

test('a practice report can attach the sample result sheet without uploading the photo', function () {
    Storage::fake('public');
    useMode('test');
    $hashed = VoterIdentificationNumber::fromParts(...TESTER_VIN);

    $this->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $hashed->hash(), 'masked' => $hashed->masked(), 'report_id' => null]])
        ->post(route('reports.store'), [
            'polling_unit_id' => $this->pollingUnit->id,
            'election_held' => true,
            'voted' => true,
            'party_id' => $this->party->id,
            'sample_result_sheet' => true,
            'result_sheet' => UploadedFile::fake()->image('sheet.jpg', 800, 600),
        ])
        ->assertRedirect(route('reports.submitted'));

    expect(DB::table('test_result_sheets')->value('path'))->toBe(ResultSheetImage::SAMPLE_PATH)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('the sample result sheet cannot be sent once the app is live', function () {
    useMode('live');
    submitReportAs(TESTER_VIN);

    $this->post(route('result-sheets.store'), ['sample_result_sheet' => true])
        ->assertSessionHasErrors(['result_sheet', 'sample_result_sheet']);

    expect(DB::table('result_sheets')->count())->toBe(0);
});
