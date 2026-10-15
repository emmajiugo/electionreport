<?php

use App\Models\Election;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use App\Models\Ward;
use App\Support\ReportCorroboration;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->election = Election::factory()->create();
});

function agreeingReports(PollingUnit $pollingUnit, int $count, bool $held = true): void
{
    Report::factory()
        ->for(test()->election)
        ->for($pollingUnit)
        ->when(! $held, fn ($factory) => $factory->electionNotHeld())
        ->count($count)
        ->create();
}

function unitInLga(Lga $lga, string $name): PollingUnit
{
    return PollingUnit::factory()->for(Ward::factory()->for($lga)->state(['name' => "{$name} Ward"]))->create(['name' => $name]);
}

test('a polling unit counts once enough of its reports agree the election held', function () {
    $corroborated = PollingUnit::factory()->create();
    $tooFew = PollingUnit::factory()->create();
    agreeingReports($corroborated, ReportCorroboration::AGREEING_REPORTS);
    agreeingReports($tooFew, ReportCorroboration::AGREEING_REPORTS - 1);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->has('results.standings.states', 1)
        ->where('results.standings.states.0.id', $corroborated->ward->lga->state->id)
        ->where('results.standings.states.0.polling_units', 1)
        ->where('results.standings.states.0.rank', 1));
});

test('reports agreeing the election did not hold corroborate each other too', function () {
    agreeingReports(PollingUnit::factory()->create(), ReportCorroboration::AGREEING_REPORTS, held: false);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states.0.polling_units', 1));
});

test('reports that disagree do not corroborate each other', function () {
    $pollingUnit = PollingUnit::factory()->create();
    agreeingReports($pollingUnit, ReportCorroboration::AGREEING_REPORTS - 1);
    agreeingReports($pollingUnit, ReportCorroboration::AGREEING_REPORTS - 1, held: false);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states', []));
});

test('a polling unit with a confirmed result sheet counts without enough agreeing reports', function () {
    $pollingUnit = PollingUnit::factory()->create();
    $sheet = ResultSheet::factory()->for(Report::factory()->for($this->election)->for($pollingUnit))->create();

    // Reviewers who said the election did not hold, so the reports alone never reach agreement.
    foreach (range(1, 3) as $reviewer) {
        ResultSheetReview::factory()->for($sheet)->create([
            'report_id' => Report::factory()->for($this->election)->for($pollingUnit)->electionNotHeld(),
        ]);
    }

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states.0.polling_units', 1));
});

test('flooding one polling unit with reports adds only one to its area', function () {
    $flooded = PollingUnit::factory()->create();
    $neighbour = unitInLga($flooded->ward->lga, 'Neighbour Unit');
    agreeingReports($flooded, 20);
    agreeingReports($neighbour, ReportCorroboration::AGREEING_REPORTS);

    $this->get(route('results', ['state' => $flooded->ward->lga->state->id]))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states.0.polling_units', 2)
        ->where('results.standings.lgas.0.polling_units', 2));
});

test('a state ranks its LGAs and wards by corroborated polling units, ties alphabetically', function () {
    $leader = Lga::factory()->create(['name' => 'Zaria']);
    $state = $leader->state;
    $tiedFirst = Lga::factory()->for($state)->create(['name' => 'Agege']);
    $tiedSecond = Lga::factory()->for($state)->create(['name' => 'Badagry']);

    foreach (['One', 'Two'] as $name) {
        agreeingReports(unitInLga($leader, "Zaria {$name}"), ReportCorroboration::AGREEING_REPORTS);
    }
    agreeingReports(unitInLga($tiedSecond, 'Badagry One'), ReportCorroboration::AGREEING_REPORTS);
    agreeingReports(unitInLga($tiedFirst, 'Agege One'), ReportCorroboration::AGREEING_REPORTS);

    $this->get(route('results', ['state' => $state->id]))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.lgas', [
            ['id' => $leader->id, 'name' => 'Zaria', 'polling_units' => 2, 'rank' => 1],
            ['id' => $tiedFirst->id, 'name' => 'Agege', 'polling_units' => 1, 'rank' => 2],
            ['id' => $tiedSecond->id, 'name' => 'Badagry', 'polling_units' => 1, 'rank' => 2],
        ])
        ->has('results.standings.wards', 4)
        ->where('results.standings.wards.0', [
            'id' => $tiedFirst->wards()->sole()->id,
            'name' => 'Agege One Ward',
            'lga' => 'Agege',
            'polling_units' => 1,
            'rank' => 1,
        ]));
});

test('the national view does not list LGAs or wards', function () {
    agreeingReports(PollingUnit::factory()->create(), ReportCorroboration::AGREEING_REPORTS);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.lgas', [])
        ->where('results.standings.wards', []));
});

test('reports from another election do not count', function () {
    $pollingUnit = PollingUnit::factory()->create();
    Report::factory()->for(Election::factory()->closed())->for($pollingUnit)->count(ReportCorroboration::AGREEING_REPORTS)->create();

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states', []));
});

test('practice reports never count toward live standings', function () {
    config(['electionreport.mode' => 'test']);
    agreeingReports(PollingUnit::factory()->create(), ReportCorroboration::AGREEING_REPORTS);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states.0.polling_units', 1));

    config(['electionreport.mode' => 'live']);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.standings.states', []));
});
