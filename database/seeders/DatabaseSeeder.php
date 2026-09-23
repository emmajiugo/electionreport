<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PartySeeder::class,
            ElectionSeeder::class,
        ]);

        // INEC's polling unit register (176,623 PUs), scraped Sep 2025 by github.com/JayCodist/inec-polling-units-scraper.
        Artisan::call('locations:import', ['path' => database_path('data/locations.json.gz')]);
    }
}
