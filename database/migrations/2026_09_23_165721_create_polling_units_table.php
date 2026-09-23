<?php

use App\Models\Ward;
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
        Schema::create('polling_units', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Ward::class)->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestamps();

            $table->unique(['ward_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polling_units');
    }
};
