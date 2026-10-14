<?php

use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
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
        Schema::create('result_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Election::class)->constrained();
            $table->foreignIdFor(PollingUnit::class)->constrained();
            $table->foreignIdFor(Report::class)->unique()->constrained();
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
        Schema::dropIfExists('result_sheets');
    }
};
