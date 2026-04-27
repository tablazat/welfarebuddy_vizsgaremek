<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sleep_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained();
            $table->decimal("hours", 4, 2);
            $table->unsignedTinyInteger("quality")->nullable();
            $table->timestamp("recorded_at")->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sleep_records');
    }
};
