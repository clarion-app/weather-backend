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
        Schema::table('weather_locations', function (Blueprint $table) {
            $table->enum('units', ['metric', 'imperial'])
                  ->default('metric')
                  ->after('timezone')
                  ->comment('Units system for this location (metric or imperial)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weather_locations', function (Blueprint $table) {
            $table->dropColumn('units');
        });
    }
};
