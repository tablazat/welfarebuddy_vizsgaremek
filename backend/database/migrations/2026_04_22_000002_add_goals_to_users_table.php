<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('step_goal_daily')->default(10000)->after('height_cm');
            $table->unsignedInteger('water_goal_ml')->default(2500)->after('step_goal_daily');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['step_goal_daily', 'water_goal_ml']);
        });
    }
};
