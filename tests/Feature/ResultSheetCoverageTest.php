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

test('the dashboard counts polling units with sheets and how voters back them', function () {
    coverageScenario();

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page
        ->where('results.sheets.polling_units', 4)
        ->where('results.sheets.corroborated', 1)
        ->where('results.sheets.contested', 2)
        ->where('results.sheets.units', []));
});

test('a state lists its polling units with sheets, disputed ones first', function () {
    $units = coverageScenario();
    coverageSheet(PollingUnit::factory()->create());
    $state = $units['confirmed']->ward->lga->state;

    $this->get(route('results', ['state' => $state->id]))->assertInertia(fn (Assert $page) => $page
        ->where('results.sheets.polling_units', 4)
        ->has('results.sheets.units', 4)
        ->where('results.sheets.units.0.status', 'contested')
        ->where('results.sheets.units.1.status', 'contested')
        ->where('results.sheets.units.2.id', $units['confirmed']->id)
        ->where('results.sheets.units.2.status', 'corroborated')
        ->where('results.sheets.units.2.status_label', 'Confirmed by several voters')
        ->where('results.sheets.units.3.id', $units['unconfirmed']->id)
        ->where('results.sheets.units.3.status', 'unconfirmed'));
});

test('sheets from test mode are not counted in the live dashboard', function () {
    config(['electionreport.mode' => 'test']);
    coverageSheet(PollingUnit::factory()->create());
    config(['electionreport.mode' => 'live']);

    $this->get(route('results'))->assertInertia(fn (Assert $page) => $page->where('results.sheets.polling_units', 0));
});
