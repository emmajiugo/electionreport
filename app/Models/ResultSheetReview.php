<?php

namespace App\Models;

use App\Enums\AppMode;
use App\Enums\ReviewVerdict;
use Database\Factories\ResultSheetReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voter confirming or disputing a result sheet that someone else uploaded for their polling unit.
 *
 * @property int $id
 * @property int $result_sheet_id
 * @property int $report_id
 * @property ReviewVerdict $verdict
 * @property string $ip_hash
 */
#[Fillable(['result_sheet_id', 'report_id', 'verdict', 'ip_hash'])]
#[Hidden(['ip_hash'])]
class ResultSheetReview extends Model
{
    /** @use HasFactory<ResultSheetReviewFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ResultSheet, $this>
     */
    public function resultSheet(): BelongsTo
    {
        return $this->belongsTo(ResultSheet::class);
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Test-mode reviews live in their own table, beside the test-mode sheets they review.
     */
    public function getTable(): string
    {
        return AppMode::current()->isTest() ? 'test_result_sheet_reviews' : 'result_sheet_reviews';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verdict' => ReviewVerdict::class,
        ];
    }
}
