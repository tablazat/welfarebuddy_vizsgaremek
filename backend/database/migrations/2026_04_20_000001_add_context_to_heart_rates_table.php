<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heart_rates', function (Blueprint $table) {
            $table->string('context', 20)->nullable()->after('heart_rate');
        });
    }

    public function down(): void
    {
        Schema::table('heart_rates', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
