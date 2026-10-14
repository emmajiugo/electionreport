<?php

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Rules\Turnstile;
use App\Support\VoterIdentificationNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const TESTER_VIN = '90F5B1C3E2950123456';

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

function submitReportAs(string $vin): void
{
    $hashed = VoterIdentificationNumber::fromInput($vin);

    test()->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $hashed->hash(), 'masked' => $hashed->masked()]])
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

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => TESTER_VIN])
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
})->with(['reports', 'result_sheets']);

test('result sheets uploaded in test mode are kept apart from live ones', function () {
    Storage::fake('public');
    useMode('test');
    submitReportAs(TESTER_VIN);

    $this->post(route('result-sheets.store'), ['result_sheet' => UploadedFile::fake()->image('sheet.jpg', 800, 600)])
        ->assertRedirect(route('result-sheets.create'));

    expect(DB::table('test_result_sheets')->count())->toBe(1)
        ->and(DB::table('result_sheets')->count())->toBe(0)
        ->and(DB::table('test_result_sheets')->value('path'))->toStartWith('test/result-sheets/');
});
