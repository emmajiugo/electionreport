<?php

namespace Database\Seeders;

use App\Models\Party;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * Parties that fielded presidential candidates in 2023. Check INEC's final list for the
     * election being monitored before going live. Logos are read from public/images/parties/{ACRONYM}.png;
     * the ballot falls back to the acronym for any that are missing.
     */
    public function run(): void
    {
        $parties = [
            'A' => 'Accord',
            'AA' => 'Action Alliance',
            'AAC' => 'African Action Congress',
            'ADC' => 'African Democratic Congress',
            'ADP' => 'Action Democratic Party',
            'APC' => 'All Progressives Congress',
            'APGA' => 'All Progressives Grand Alliance',
            'APM' => 'Allied Peoples Movement',
            'APP' => 'Action Peoples Party',
            'BP' => 'Boot Party',
            'LP' => 'Labour Party',
            'NNPP' => 'New Nigeria Peoples Party',
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
