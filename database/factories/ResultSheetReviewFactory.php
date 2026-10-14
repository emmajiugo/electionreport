<?php

namespace Database\Factories;

use App\Enums\ReviewVerdict;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultSheetReview>
 */
class ResultSheetReviewFactory extends Factory
{
    /**
     * Define the model's default state. The reviewer reports from the same polling unit as the sheet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_sheet_id' => ResultSheet::factory(),
            'report_id' => function (array $attributes): int {
                $sheet = ResultSheet::query()->whereKey($attributes['result_sheet_id'])->firstOrFail();

                return Report::factory()->create([
                    'election_id' => $sheet->election_id,
                    'polling_unit_id' => $sheet->polling_unit_id,
                ])->id;
            },
            'verdict' => ReviewVerdict::Confirm,
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function dispute(): static
    {
        return $this->state(fn (): array => [
            'verdict' => ReviewVerdict::Dispute,
        ]);
    }
}
