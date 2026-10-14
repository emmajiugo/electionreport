<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The figures printed on the sheet, as typed by the voter who uploaded it. Both the live and the test
 * table change here together, because the two must stay identical.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['result_sheets', 'test_result_sheets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('registered_voters')->nullable();
                $table->unsignedInteger('accredited_voters')->nullable();
                $table->unsignedInteger('ballots_issued')->nullable();
                $table->unsignedInteger('unused_ballots')->nullable();
                $table->unsignedInteger('spoiled_ballots')->nullable();
                $table->unsignedInteger('rejected_ballots')->nullable();
                $table->unsignedInteger('total_valid_votes')->nullable();
                $table->unsignedInteger('total_used_ballots')->nullable();
                $table->json('party_votes')->nullable();
                $table->timestamp('figures_recorded_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['result_sheets', 'test_result_sheets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn([
                    'registered_voters',
                    'accredited_voters',
                    'ballots_issued',
                    'unused_ballots',
                    'spoiled_ballots',
                    'rejected_ballots',
                    'total_valid_votes',
                    'total_used_ballots',
                    'party_votes',
                    'figures_recorded_at',
                ]);
            });
        }
    }
};
