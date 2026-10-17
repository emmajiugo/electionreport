<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voters now give only the first and last four characters of their VIN, which two voters can share,
 * so one report per partial VIN is enforced per polling unit rather than per election.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['reports', 'test_reports'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(['election_id', 'polling_unit_id', 'vin_hash']);
                $table->index(['election_id', 'vin_hash']);
                $table->dropUnique(['election_id', 'vin_hash']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['reports', 'test_reports'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(['election_id', 'vin_hash']);
                $table->dropIndex(['election_id', 'vin_hash']);
                $table->dropUnique(['election_id', 'polling_unit_id', 'vin_hash']);
            });
        }
    }
};
