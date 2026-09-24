<?php

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
        Schema::table('election_party', function (Blueprint $table) {
            $table->string('candidate')->nullable();
            $table->string('running_mate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('election_party', function (Blueprint $table) {
            $table->dropColumn(['candidate', 'running_mate']);
        });
    }
};
