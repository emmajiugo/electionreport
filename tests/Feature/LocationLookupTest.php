<?php

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\State;
use App\Models\Ward;

beforeEach(function () {
    $this->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => 'hash', 'masked' => 'masked']]);
});

test('location lookups require a VIN to have been entered', function () {
    $state = State::factory()->create();
    $this->flushSession();

    $this->getJson(route('locations.lgas', $state))->assertUnauthorized();
});

test('LGAs are listed for the chosen state only', function () {
    $state = State::factory()->create();
    Lga::factory()->for($state)->create(['name' => 'Ikeja']);
    Lga::factory()->create(['name' => 'Elsewhere']);

    $this->getJson(route('locations.lgas', $state))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertExactJson([['id' => $state->lgas()->sole()->id, 'name' => 'Ikeja']]);
});

test('wards are listed for the chosen LGA only', function () {
    $lga = Lga::factory()->create();
    $ward = Ward::factory()->for($lga)->create(['name' => 'Ward A']);
    Ward::factory()->create();

    $this->getJson(route('locations.wards', $lga))
        ->assertExactJson([['id' => $ward->id, 'name' => 'Ward A']]);
});

test('polling units are listed for the chosen ward with their INEC code', function () {
    $ward = Ward::factory()->create();
    $unit = PollingUnit::factory()->for($ward)->create(['code' => '24/10/01/003', 'name' => 'Town Hall']);
    PollingUnit::factory()->create();

    $this->getJson(route('locations.polling-units', $ward))
        ->assertExactJson([['id' => $unit->id, 'name' => '24/10/01/003 - Town Hall']]);
});

test('an unknown parent returns not found', function () {
    $this->getJson(route('locations.lgas', 999))->assertNotFound();
});
