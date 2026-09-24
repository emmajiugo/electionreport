<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A party's place on an election's ballot, with the ticket it fielded.
 *
 * @property int $election_id
 * @property int $party_id
 * @property string|null $candidate
 * @property string|null $running_mate
 */
class ElectionParty extends Pivot
{
    protected $table = 'election_party';
}
