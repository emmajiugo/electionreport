<?php

namespace App\Models;

use App\Enums\AppMode;
use App\Enums\ElectionNotHeldReason;
use App\Enums\NotVotedReason;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $election_id
 * @property int $polling_unit_id
 * @property string $vin_hash
 * @property string $vin_masked
 * @property bool $election_held
 * @property ElectionNotHeldReason|null $not_held_reason
 * @property bool|null $voted
 * @property NotVotedReason|null $not_voted_reason
 * @property int|null $party_id
 * @property string|null $reason_details
 * @property string $ip_hash
 * @property string|null $user_agent
 */
#[Fillable([
    'election_id',
    'polling_unit_id',
    'vin_hash',
    'vin_masked',
    'election_held',
    'not_held_reason',
    'voted',
    'not_voted_reason',
    'party_id',
    'reason_details',
    'ip_hash',
    'user_agent',
])]
#[Hidden(['vin_hash', 'ip_hash'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
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
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Test-mode reports live in their own table so they never mix with, or block VINs in, the real election.
     */
    public function getTable(): string
    {
        return AppMode::current()->isTest() ? 'test_reports' : 'reports';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'election_held' => 'boolean',
            'voted' => 'boolean',
            'not_held_reason' => ElectionNotHeldReason::class,
            'not_voted_reason' => NotVotedReason::class,
        ];
    }
}
