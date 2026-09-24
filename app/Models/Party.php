<?php

namespace App\Models;

use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $acronym
 * @property string $name
 * @property string|null $logo_path
 * @property-read string|null $logo_url
 * @property-read ElectionParty $pivot Only set when loaded through Election::parties().
 */
#[Fillable(['acronym', 'name', 'logo_path'])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? asset($this->logo_path) : null);
    }
}
