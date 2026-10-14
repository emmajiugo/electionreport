<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ResultSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultSheet>
 */
class ResultSheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'election_id' => fn (array $attributes): int => Report::query()->whereKey($attributes['report_id'])->firstOrFail()->election_id,
            'polling_unit_id' => fn (array $attributes): int => Report::query()->whereKey($attributes['report_id'])->firstOrFail()->polling_unit_id,
            'path' => fn (): string => 'result-sheets/'.fake()->uuid().'.jpg',
            'image_hash' => fn (): string => fake()->regexify('[0-9a-f]{16}'),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
