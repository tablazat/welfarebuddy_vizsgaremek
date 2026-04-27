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
        Schema::create('blood_pressures', function (Blueprint $table) {
            $table->id();
            $table->integer("systolic");
            $table->integer("diastolic");
            $table->foreignId("user_id")->constrained();
            $table->timestamp("recorded_at")->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blood_pressures');
    }
};
