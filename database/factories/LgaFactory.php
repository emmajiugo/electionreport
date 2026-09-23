<?php

namespace Database\Factories;

use App\Models\Lga;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lga>
 */
class LgaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'state_id' => State::factory(),
            'code' => fake()->unique()->numerify('##'),
            'name' => fake()->city(),
        ];
    }
}
