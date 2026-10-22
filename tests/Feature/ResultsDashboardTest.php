<?php

use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Models\Election;
use App\Models\Lga;
use App\Models\Party;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\State;
use App\Models\Ward;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->election = Election::factory()->create();
    [$this->apc, $this->lp, $this->pdp] = [
        Party::factory()->create(['acronym' => 'APC']),
        Party::factory()->create(['acronym' => 'LP']),
        Party::factory()->create(['acronym' => 'PDP']),
    ];
    $this->election->parties()->attach([$this->apc->id, $this->lp->id, $this->pdp->id]);

    $this->lagos = State::factory()->create(['name' => 'Lagos']);
    $this->ikeja = Lga::factory()->for($this->lagos)->create(['name' => 'Ikeja']);
    $this->ikejaUnit = PollingUnit::factory()->for(Ward::factory()->for($this->ikeja))->create();
    $this->surulereUnit = PollingUnit::factory()
        ->for(Ward::factory()->for(Lga::factory()->for($this->lagos)->create(['name' => 'Surulere'])))
        ->create();

    $this->kano = State::factory()->create(['name' => 'Kano']);
    $this->kanoUnit = PollingUnit::factory()->for(Ward::factory()->for(Lga::factory()->for($this->kano)))->create();
    PollingUnit::factory()->for(Ward::factory()->for(Lga::factory()->for($this->kano)))->create();

    $vote = fn (PollingUnit $unit, Party $party) => Report::factory()->for($this->election)->for($unit)
        ->create(['election_held' => true, 'voted' => true, 'party_id' => $party->id, 'not_voted_reason' => null]);

    $vote($this->ikejaUnit, $this->lp);
    $vote($this->ikejaUnit, $this->lp);
    $vote($this->surulereUnit, $this->apc);
    $vote($this->kanoUnit, $this->apc);

    Report::factory()->for($this->election)->for($this->ikejaUnit)
        ->create(['election_held' => true, 'voted' => false, 'not_voted_reason' => NotVotedReason::BvasCouldNotAccredit]);
    Report::factory()->for($this->election)->for($this->kanoUnit)
        ->create(['election_held' => false, 'voted' => null, 'not_voted_reason' => null, 'not_held_reason' => ElectionNotHeldReason::ViolenceOrInsecurity]);

    // Another election's reports must never leak into these results.
    Report::factory()->for(Election::factory()->closed()->create(['held_on' => '2023-02-25']))->for($this->kanoUnit)->create();
});

test('national results total every report for the open election', function () {
    $this->get(route('results'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Results/Index')
            ->where('election.id', $this->election->id)
            ->where('selectedState', null)
            ->where('results.summary', [
                'reports' => 6,
                'held' => 5,
                'not_held' => 1,
                'voted' => 4,
                'not_voted' => 1,
                'polling_units_reporting' => 3,
                'polling_units' => 4,
            ])
            ->where('results.parties.0.acronym', 'APC')
            ->where('results.parties.0.votes', 2)
            ->where('results.parties.1.acronym', 'LP')
            ->where('results.parties.1.votes', 2)
            ->where('results.parties.2.acronym', 'PDP')
            ->where('results.parties.2.votes', 0)
            ->where('results.notHeldReasons.0.value', ElectionNotHeldReason::ViolenceOrInsecurity->value)
            ->where('results.notHeldReasons.0.count', 1)
            ->where('results.notVotedReasons.0.value', NotVotedReason::BvasCouldNotAccredit->value)
            ->has('results.states', 2)
            ->where('results.states.0.name', 'Kano')
            ->where('results.states.0.reports', 2)
            ->where('results.states.0.not_held', 1)
            ->where('results.states.0.leading_party', 'APC')
            ->where('results.states.0.polling_units_reporting', 1)
            ->where('results.states.0.polling_units', 2)
            ->where('results.states.1.name', 'Lagos')
            ->where('results.states.1.reports', 4)
            ->where('results.states.1.polling_units_reporting', 2)
            ->where('results.states.1.polling_units', 2)
            ->where('results.states.1.not_voted', 1)
            ->where('results.states.1.leading_party', 'LP')
            ->where('results.lgas', []));
});

test('state results are scoped to that state and broken down by LGA', function () {
    $this->get(route('results', ['state' => $this->lagos->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedState.name', 'Lagos')
            ->where('results.summary.reports', 4)
            ->where('results.summary.polling_units', 2)
            ->where('results.summary.not_held', 0)
            ->where('results.parties.0.acronym', 'LP')
            ->where('results.parties.0.votes', 2)
            ->where('results.notHeldReasons.0.count', 0)
            ->has('results.states', 2)
            ->where('results.states.0.reports', 2)
            ->has('results.lgas', 2)
            ->where('results.lgas.0.name', 'Ikeja')
            ->where('results.lgas.0.reports', 3)
            ->where('results.lgas.0.leading_party', 'LP')
            ->where('results.lgas.0.polling_units_reporting', 1)
            ->where('results.lgas.0.polling_units', 1)
            ->where('results.lgas.1.name', 'Surulere')
            ->where('results.lgas.1.reports', 1));
});

test('an unknown state falls back to national results', function () {
    $this->get(route('results', ['state' => 999999]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedState', null)
            ->where('results.summary.reports', 6));
});

test('the most recent election is shown once reporting has closed', function () {
    $this->election->update(['is_accepting_reports' => false]);

    $this->get(route('results'))
        ->assertInertia(fn (Assert $page) => $page->where('election.id', $this->election->id));
});

test('the dashboard is empty when there are no elections', function () {
    Report::query()->delete();
    Election::query()->delete();

    $this->get(route('results'))
        ->assertInertia(fn (Assert $page) => $page->where('election', null)->where('results', null));
});
