<?php

use App\Models\Report;
use App\Models\ResultSheet;
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
        Schema::create('result_sheet_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ResultSheet::class)->constrained();
            $table->foreignIdFor(Report::class)->unique()->constrained();
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
        Schema::dropIfExists('result_sheet_reviews');
    }
};
