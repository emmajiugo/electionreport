<?php

namespace App\Models;

use App\Enums\AppMode;
use Database\Factories\ResultSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A photo of the result sheet (Form EC8A) posted at a polling unit, uploaded by a voter who reported from it.
 *
 * @property int $id
 * @property int $election_id
 * @property int $polling_unit_id
 * @property int $report_id
 * @property string $path
 * @property string $image_hash
 * @property string $ip_hash
 * @property-read int $confirmations_count
 * @property-read int $disputes_count
 */
#[Fillable(['election_id', 'polling_unit_id', 'report_id', 'path', 'image_hash', 'ip_hash'])]
#[Hidden(['ip_hash'])]
class ResultSheet extends Model
{
    /** @use HasFactory<ResultSheetFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Election, $this>
     */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * @return BelongsTo<PollingUnit, $this>
     */
    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return HasMany<ResultSheetReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ResultSheetReview::class);
    }

    public function url(): string
    {
        return Storage::disk(self::disk())->url($this->path);
    }

    public static function disk(): string
    {
        return config('electionreport.result_sheet_disk');
    }

    /**
     * Test-mode sheets live in their own table, beside the test-mode reports they belong to.
     */
    public function getTable(): string
    {
        return AppMode::current()->isTest() ? 'test_result_sheets' : 'result_sheets';
    }
}
