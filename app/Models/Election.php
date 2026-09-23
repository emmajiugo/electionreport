<?php

namespace App\Models;

use Database\Factories\ElectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $held_on
 * @property bool $is_accepting_reports
 */
#[Fillable(['name', 'held_on', 'is_accepting_reports'])]
class Election extends Model
{
    /** @use HasFactory<ElectionFactory> */
    use HasFactory;

    /**
     * The election currently open for reports, if any.
     */
    public static function current(): ?self
    {
        return self::query()->acceptingReports()->latest('held_on')->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function acceptingReports(Builder $query): void
    {
        $query->where('is_accepting_reports', true);
    }

    /**
     * Parties accredited by INEC to contest this election.
     *
     * @return BelongsToMany<Party, $this>
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function hasReportFrom(string $vinHash): bool
    {
        return $this->reports()->where('vin_hash', $vinHash)->exists();
    }

    public static function duplicateVinMessage(): string
    {
        return 'A report has already been submitted with this VIN.';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'is_accepting_reports' => 'boolean',
        ];
    }
}
