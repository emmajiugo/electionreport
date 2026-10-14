<?php

use App\Models\Election;
use App\Models\PollingUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result sheets uploaded while the app runs in test mode. Must stay column-for-column identical to
 * the result_sheets table; a test compares the two.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_result_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Election::class)->constrained();
            $table->foreignIdFor(PollingUnit::class)->constrained();
            $table->foreignId('report_id')->unique()->constrained('test_reports');
            $table->string('path');
            $table->char('image_hash', 16)->index();
            $table->char('ip_hash', 64)->index();
            $table->timestamps();

            $table->index(['election_id', 'polling_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_result_sheets');
    }
};
