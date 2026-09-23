<?php

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\State;
use App\Models\Ward;

test('locations are imported from JSON and re-importing updates rather than duplicates', function () {
    $path = database_path('data/locations.sample.json');

    $this->artisan('locations:import', ['path' => $path])->assertSuccessful();

    $counts = [State::count(), Lga::count(), Ward::count(), PollingUnit::count()];
    expect($counts)->toBe([3, 8, 16, 48]);

    State::query()->where('code', '24')->update(['name' => 'Renamed']);

    $this->artisan('locations:import', ['path' => $path])->assertSuccessful();

    expect([State::count(), Lga::count(), Ward::count(), PollingUnit::count()])->toBe($counts)
        ->and(State::query()->where('code', '24')->value('name'))->toBe('Lagos');
});

test('locations can be imported from a gzipped file', function () {
    $path = tempnam(sys_get_temp_dir(), 'locations').'.json.gz';
    file_put_contents($path, gzencode((string) file_get_contents(database_path('data/locations.sample.json'))));

    $this->artisan('locations:import', ['path' => $path])->assertSuccessful();

    expect(PollingUnit::count())->toBe(48);

    unlink($path);
});

test('import fails for a missing file', function () {
    $this->artisan('locations:import', ['path' => '/nope.json'])->assertFailed();
});

test('import fails for invalid JSON', function () {
    $path = tempnam(sys_get_temp_dir(), 'locations');
    file_put_contents($path, '{not json');

    $this->artisan('locations:import', ['path' => $path])->assertFailed();

    unlink($path);
});
