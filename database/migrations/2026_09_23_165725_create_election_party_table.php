<?php

use App\Models\Election;
use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('election_party', function (Blueprint $table) {
            $table->foreignIdFor(Election::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Party::class)->constrained()->cascadeOnDelete();

            $table->primary(['election_id', 'party_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('election_party');
    }
};
