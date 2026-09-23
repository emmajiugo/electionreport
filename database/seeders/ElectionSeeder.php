<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\Party;
use Illuminate\Database\Seeder;

class ElectionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $election = Election::query()->updateOrCreate(
            ['name' => '2027 Presidential Election'],
            ['held_on' => '2027-02-20', 'is_accepting_reports' => true],
        );

        $election->parties()->sync(Party::query()->pluck('id'));
    }
}
