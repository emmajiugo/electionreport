<?php

use App\Models\Election;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use App\Models\Ward;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->election = Election::factory()->create();
});

function coverageSheet(PollingUnit $pollingUnit): ResultSheet
{
    return ResultSheet::factory()->for(Report::factory()->for(test()->election)->for($pollingUnit))->create();
}

/**
 * One polling unit in each state a sheet can be in, all in the same state.
 *
 * @return array{confirmed: PollingUnit, disputed: PollingUnit, unconfirmed: PollingUnit, rivals: PollingUnit}
 */
function coverageScenario(): array
{
    $confirmed = PollingUnit::factory()->create(['name' => 'Confirmed Unit']);
    $sameState = fn () => PollingUnit::factory()->for(
        Ward::factory()->for(Lga::factory()->for($confirmed->ward->lga->state))
    );
    $disputed = $sameState()->create(['name' => 'Disputed Unit']);
    $unconfirmed = $sameState()->create(['name' => 'Unconfirmed Unit']);
    $rivals = $sameState()->create(['name' => 'Rivals Unit']);

    ResultSheetReview::factory()->for(coverageSheet($confirmed))->count(3)->create();
    ResultSheetReview::factory()->dispute()->for(coverageSheet($disputed))->create();
    coverageSheet($unconfirmed);
    ResultSheetReview::factory()->for(coverageSheet($rivals))->count(3)->create();
    ResultSheetReview::factory()->for(coverageSheet($rivals))->count(3)->create();

    return compact('confirmed', 'disputed', 'unconfirmed', 'rivals');
}

test('the dashboard counts polling units with sheets and how voters back them, without listing them', function () {
    coverageScenario();

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.sheets.polling_units', 4)
        ->where('results.sheets.corroborated', 1)
        ->where('results.sheets.contested', 2)
        ->missing('results.sheets.units'));
});

test('anyone can list the polling units with sheets nationally, with their state', function () {
    $units = coverageScenario();
    $elsewhere = PollingUnit::factory()->create();
    coverageSheet($elsewhere);

    $this->get(route('result-sheets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ResultSheet/Index')
            ->where('election.id', $this->election->id)
            ->where('selectedState', null)
            ->has('states', 2)
            ->where('sheets.polling_units', 5)
            ->has('sheets.units', 5)
            ->where('sheets.units.2.id', $units['confirmed']->id)
            ->where('sheets.units.2.state', $units['confirmed']->ward->lga->state->name)
            ->where('sheets.units', fn ($rows) => $rows->firstWhere('id', $elsewhere->id)['state'] === $elsewhere->ward->lga->state->name));
});

test('a state lists its polling units with sheets, disputed ones first', function () {
    $units = coverageScenario();
    coverageSheet(PollingUnit::factory()->create());
    $state = $units['confirmed']->ward->lga->state;

    $this->get(route('result-sheets.index', ['state' => $state->id]))->assertInertia(fn (Assert $page) => $page
        ->where('selectedState', ['id' => $state->id, 'name' => $state->name])
        ->where('sheets.polling_units', 4)
        ->has('sheets.units', 4)
        ->where('sheets.units.0.status', 'contested')
        ->where('sheets.units.1.status', 'contested')
        ->where('sheets.units.2.id', $units['confirmed']->id)
        ->where('sheets.units.2.status', 'corroborated')
        ->where('sheets.units.2.status_label', 'Confirmed by several voters')
        ->where('sheets.units.3.id', $units['unconfirmed']->id)
        ->where('sheets.units.3.status', 'unconfirmed'));
});

test('the result sheets page lists nothing when there is no election', function () {
    $this->election->delete();

    $this->get(route('result-sheets.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('election', null)
        ->where('sheets', null));
});

test('sheets from test mode are not counted in the live dashboard', function () {
    config(['electionreport.mode' => 'test']);
    coverageSheet(PollingUnit::factory()->create());
    config(['electionreport.mode' => 'live']);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page->where('results.sheets.polling_units', 0));
});
