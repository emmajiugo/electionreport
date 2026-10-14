<?php

use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('anyone can see a polling unit\'s sheets without entering a VIN', function () {
    Storage::fake('public');
    $election = Election::factory()->create();
    $pollingUnit = PollingUnit::factory()->create();
    $sheet = ResultSheet::factory()->for(Report::factory()->for($election)->for($pollingUnit))->create();
    ResultSheetReview::factory()->dispute()->for($sheet)->create();
    ResultSheet::factory()->for(Report::factory()->for($election))->create();

    $this->get(route('polling-units.result-sheets', $pollingUnit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ResultSheet/Show')
            ->where('pollingUnit.name', $pollingUnit->name)
            ->has('sheets', 1)
            ->where('sheets.0.id', $sheet->id)
            ->where('sheets.0.status', 'contested')
            ->where('sheets.0.is_yours', false));
});

test('an unknown polling unit is not found', function () {
    $this->get('/polling-units/999999/result-sheets')->assertNotFound();
});
