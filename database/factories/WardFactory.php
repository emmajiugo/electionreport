<?php

namespace Database\Factories;

use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lga_id' => Lga::factory(),
            'code' => fake()->unique()->numerify('##'),
            'name' => fake()->streetName(),
        ];
    }
}
