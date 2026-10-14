<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result sheet reviews made while the app runs in test mode. Must stay column-for-column identical
 * to the result_sheet_reviews table; a test compares the two.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_result_sheet_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_sheet_id')->constrained('test_result_sheets');
            $table->foreignId('report_id')->unique()->constrained('test_reports');
            $table->string('verdict');
            $table->char('ip_hash', 64)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_result_sheet_reviews');
    }
};
