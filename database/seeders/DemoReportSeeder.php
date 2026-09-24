<?php

namespace Database\Seeders;

use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use App\Models\Election;
use App\Models\Report;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fake reports for eyeballing the results dashboard. Not part of DatabaseSeeder; run it explicitly:
 * php artisan db:seed --class=DemoReportSeeder
 */
class DemoReportSeeder extends Seeder
{
    private const int REPORTS = 4000;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo reports must never be seeded in production.');
        }

        $election = Election::current() ?? throw new RuntimeException('Seed an open election first.');
        $parties = $election->parties()->pluck('parties.id', 'acronym');

        mt_srand(2027);

        $pollingUnits = DB::table('polling_units')
            ->join('wards', 'wards.id', '=', 'polling_units.ward_id')
            ->join('lgas', 'lgas.id', '=', 'wards.lga_id')
            ->inRandomOrder()
            ->limit(1500)
            ->get(['polling_units.id', 'lgas.state_id']);

        // Each state leans a different way so the map and state table have something to show.
        $stateLeanings = $pollingUnits->pluck('state_id')->unique()
            ->mapWithKeys(fn (int $stateId): array => [$stateId => [
                'APC' => mt_rand(10, 45),
                'NDC' => mt_rand(5, 40),
                'PDP' => mt_rand(0, 15),
                'LP' => mt_rand(0, 10),
                'ADC' => mt_rand(5, 35),
                'SDP' => mt_rand(0, 4),
                'AAC' => mt_rand(0, 4),
            ]]);
        $stateDisruption = $stateLeanings->map(fn (): int => mt_rand(2, 25));

        $now = now();
        $rows = [];

        for ($i = 0; $i < self::REPORTS; $i++) {
            $unit = $pollingUnits[mt_rand(0, $pollingUnits->count() - 1)];
            $held = mt_rand(1, 100) > $stateDisruption[$unit->state_id];
            $voted = $held && mt_rand(1, 100) > 15;

            $rows[] = [
                'election_id' => $election->id,
                'polling_unit_id' => $unit->id,
                'vin_hash' => VoterIdentificationNumber::hmac("demo-{$i}"),
                'vin_masked' => 'DEMO***********'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'election_held' => $held,
                'not_held_reason' => $held ? null : $this->pick([
                    ElectionNotHeldReason::OfficialsAbsent->value => 30,
                    ElectionNotHeldReason::MaterialsNotDelivered->value => 25,
                    ElectionNotHeldReason::ViolenceOrInsecurity->value => 20,
                    ElectionNotHeldReason::BvasNotWorking->value => 12,
                    ElectionNotHeldReason::UnitRelocatedWithoutNotice->value => 6,
                    ElectionNotHeldReason::Postponed->value => 5,
                    ElectionNotHeldReason::Other->value => 2,
                ]),
                'voted' => $held ? $voted : null,
                'not_voted_reason' => $held && ! $voted ? $this->pick([
                    NotVotedReason::BvasCouldNotAccredit->value => 30,
                    NotVotedReason::NameNotOnRegister->value => 22,
                    NotVotedReason::VotingClosedBeforeMyTurn->value => 18,
                    NotVotedReason::TurnedAwayOrIntimidated->value => 12,
                    NotVotedReason::OfficialsDemandedPayment->value => 6,
                    NotVotedReason::NoPvc->value => 5,
                    NotVotedReason::ChoseNotToVote->value => 5,
                    NotVotedReason::Other->value => 2,
                ]) : null,
                'party_id' => $voted ? $parties[$this->pick($stateLeanings[$unit->state_id])] ?? null : null,
                'reason_details' => null,
                'ip_hash' => VoterIdentificationNumber::hmac('demo-ip-'.mt_rand(1, 900)),
                'user_agent' => 'DemoReportSeeder',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Report::query()->where('user_agent', 'DemoReportSeeder')->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            Report::query()->insert($chunk);
        }

        Cache::flush();
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, max(1, array_sum($weights)));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }
}
