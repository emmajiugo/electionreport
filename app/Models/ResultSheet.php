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
use Illuminate\Support\Carbon;
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
 * @property int|null $registered_voters
 * @property int|null $accredited_voters
 * @property int|null $ballots_issued
 * @property int|null $unused_ballots
 * @property int|null $spoiled_ballots
 * @property int|null $rejected_ballots
 * @property int|null $total_valid_votes
 * @property int|null $total_used_ballots
 * @property array<int|string, int>|null $party_votes
 * @property Carbon|null $figures_recorded_at
 * @property-read int $confirmations_count
 * @property-read int $disputes_count
 */
#[Fillable([
    'election_id',
    'polling_unit_id',
    'report_id',
    'path',
    'image_hash',
    'ip_hash',
    'registered_voters',
    'accredited_voters',
    'ballots_issued',
    'unused_ballots',
    'spoiled_ballots',
    'rejected_ballots',
    'total_valid_votes',
    'total_used_ballots',
    'party_votes',
    'figures_recorded_at',
])]
#[Hidden(['ip_hash'])]
class ResultSheet extends Model
{
    /** @use HasFactory<ResultSheetFactory> */
    use HasFactory;

    /**
     * The totals on Form EC8A, in the order and wording of the printed form. Party votes are kept separately.
     *
     * @var array<string, string>
     */
    public const array FIGURES = [
        'registered_voters' => 'Number of voters on the register',
        'accredited_voters' => 'Number of accredited voters',
        'ballots_issued' => 'Number of ballot papers issued to the polling unit',
        'unused_ballots' => 'Number of unused ballot papers',
        'spoiled_ballots' => 'Number of spoiled ballot papers',
        'rejected_ballots' => 'Number of rejected ballots',
        'total_valid_votes' => 'Total valid votes',
        'total_used_ballots' => 'Total number of used ballot papers',
    ];

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

    public function hasFigures(): bool
    {
        return $this->figures_recorded_at !== null;
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_votes' => 'array',
            'figures_recorded_at' => 'datetime',
        ];
    }
}
