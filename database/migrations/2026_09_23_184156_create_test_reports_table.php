<?php

use App\Models\Election;
use App\Models\Party;
use App\Models\PollingUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports submitted while the app runs in test mode. Must stay column-for-column identical to
 * the reports table; a test compares the two.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Election::class)->constrained();
            $table->foreignIdFor(PollingUnit::class)->constrained();
            $table->char('vin_hash', 64);
            $table->string('vin_masked');
            $table->boolean('election_held');
            $table->string('not_held_reason')->nullable();
            $table->boolean('voted')->nullable();
            $table->string('not_voted_reason')->nullable();
            $table->foreignIdFor(Party::class)->nullable()->constrained();
            $table->text('reason_details')->nullable();
            $table->char('ip_hash', 64)->index();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->unique(['election_id', 'vin_hash']);
            $table->index(['election_id', 'polling_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_reports');
    }
};
