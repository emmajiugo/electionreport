<?php

namespace Database\Factories;

use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollingUnit>
 */
class PollingUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'code' => fake()->unique()->numerify('###'),
            'name' => fake()->streetAddress(),
        ];
    }
}
