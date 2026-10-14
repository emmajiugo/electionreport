<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Support\VoterIdentificationNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'election_id' => Election::factory(),
            'polling_unit_id' => PollingUnit::factory(),
            'vin_hash' => fn (array $attributes): string => VoterIdentificationNumber::fromInput(fake()->unique()->bothify(str_repeat('?#', 10)))->hash(),
            'vin_masked' => '90F5***********1234',
            'election_held' => true,
            'voted' => false,
            'not_voted_reason' => 'chose_not_to_vote',
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function electionNotHeld(): static
    {
        return $this->state(fn (): array => [
            'election_held' => false,
            'not_held_reason' => 'officials_absent',
            'voted' => null,
            'not_voted_reason' => null,
        ]);
    }
}
