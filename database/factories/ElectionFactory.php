<?php

namespace Database\Factories;

use App\Models\Election;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Election>
 */
class ElectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->year().' Presidential Election',
            'held_on' => fake()->dateTimeBetween('now', '+1 year'),
            'is_accepting_reports' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'is_accepting_reports' => false,
        ]);
    }
}
