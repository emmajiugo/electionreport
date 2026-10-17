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
     * @return BelongsToMany<Party, $this, ElectionParty>
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class)->using(ElectionParty::class)->withPivot('candidate', 'running_mate');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * The report a VIN session filed or came back for. A partial VIN alone can match reports at
     * several polling units, so the session holds the report's ID rather than looking it up by hash.
     */
    public function reportFrom(?int $reportId): ?Report
    {
        return $reportId === null ? null : $this->reports()->find($reportId);
    }

    /**
     * Per polling unit, because two voters can share the first and last characters of their VINs.
     */
    public function hasReportFrom(string $vinHash, int $pollingUnitId): bool
    {
        return $this->reports()->where('polling_unit_id', $pollingUnitId)->where('vin_hash', $vinHash)->exists();
    }

    public static function duplicateVinMessage(): string
    {
        return 'A report has already been submitted with this VIN at this polling unit.';
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
