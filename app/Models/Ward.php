<?php

namespace App\Models;

use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $lga_id
 * @property string $code
 * @property string $name
 */
#[Fillable(['lga_id', 'code', 'name'])]
class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Lga, $this>
     */
    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * @return HasMany<PollingUnit, $this>
     */
    public function pollingUnits(): HasMany
    {
        return $this->hasMany(PollingUnit::class);
    }
}
