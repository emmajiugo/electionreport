<?php

namespace Database\Seeders;

use App\Models\Party;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * Parties INEC accredited for the 2027 presidential election. Logos are read from
     * public/images/parties/{ACRONYM}.png; the ballot falls back to the acronym for any that are missing.
     */
    public function run(): void
    {
        $parties = [
            'AA' => 'Action Alliance',
            'AAC' => 'African Action Congress',
            'ADC' => 'African Democratic Congress',
            'ADP' => 'Action Democratic Party',
            'APC' => 'All Progressives Congress',
            'APM' => 'Allied Peoples Movement',
            'APP' => 'Action Peoples Party',
            'BP' => 'Boot Party',
            'DLA' => 'Democratic Leadership Alliance',
            'LP' => 'Labour Party',
            'NDC' => 'Nigeria Democratic Congress',
            'NDP' => 'National Democratic Party',
            'NRM' => 'National Rescue Movement',
            'PDP' => 'Peoples Democratic Party',
            'PRP' => 'Peoples Redemption Party',
            'SDP' => 'Social Democratic Party',
            'YPP' => 'Young Progressives Party',
            'ZLP' => 'Zenith Labour Party',
        ];

        foreach ($parties as $acronym => $name) {
            Party::query()->updateOrCreate(['acronym' => $acronym], [
                'name' => $name,
                'logo_path' => "images/parties/{$acronym}.png",
            ]);
        }
    }
}
