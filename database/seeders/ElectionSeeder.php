<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\Party;
use Illuminate\Database\Seeder;

class ElectionSeeder extends Seeder
{
    /**
     * Seed the 2027 presidential election with INEC's accredited candidates.
     */
    public function run(): void
    {
        $election = Election::query()->updateOrCreate(
            ['name' => '2027 Presidential Election'],
            ['held_on' => '2027-02-20', 'is_accepting_reports' => true],
        );

        $candidates = [
            'APC' => ['Bola Tinubu', 'Kashim Shettima'],
            'ADC' => ['Atiku Abubakar', 'Chibuike Rotimi Amaechi'],
            'NDC' => ['Peter Gregory Obi', 'Musa Mohammed Rabiu Kwankwaso'],
            'LP' => ['Sunday Chibuzo Okereke', 'Hajja Bintu Konto'],
            'PDP' => ['Sandy Ojang Onor', 'Umaru Babangida'],
            'AA' => ['Rufai Adekunle Omo-Aje', 'Shehu Hussaini'],
            'ADP' => ['Aliyu Abbas-Bin', 'Chinazam Ike'],
            'APP' => ['Kabiru Yusuf', 'Peace Egobia Ofordile'],
            'AAC' => ['Omoyele Sowore', 'Haruna Garba Magashi'],
            'APM' => ['Oluseyi Abiodun Makinde', 'Musa Lawal Daura'],
            'BP' => ['Sunday Adenuga', 'Usman Turaki Mustapha'],
            'DLA' => ['Moses Olusoji Adebisi', 'Nafisat Usaku Abubakar'],
            'NDP' => ['Ada Elizabeth Fredrick Okwori', 'Uchenna Anthony Chukwuemeka'],
            'NRM' => ['Nkem Esther Okereke', 'Nasir Muhammed Sulaiman'],
            'PRP' => ['Donald Duke', 'Kabiru Rabiu'],
            'SDP' => ['Adewole Ebenezer Adebayo', 'Usman Muhammed Bugaje'],
            'YPP' => ['Peter Ada Agada', 'Patience Ndidi Key'],
            'ZLP' => ['Daniel Daberechukwu Nwanyanwu', 'Hassan Khalid'],
        ];

        $election->parties()->sync(
            Party::query()->whereIn('acronym', array_keys($candidates))->pluck('id', 'acronym')
                ->mapWithKeys(fn (int $id, string $acronym): array => [$id => [
                    'candidate' => $candidates[$acronym][0],
                    'running_mate' => $candidates[$acronym][1],
                ]])
                ->all(),
        );
    }
}
