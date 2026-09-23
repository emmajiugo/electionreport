<?php

namespace App\Models;

use Database\Factories\PollingUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ward_id
 * @property string $code
 * @property string $name
 */
#[Fillable(['ward_id', 'code', 'name'])]
class PollingUnit extends Model
{
    /** @use HasFactory<PollingUnitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}
