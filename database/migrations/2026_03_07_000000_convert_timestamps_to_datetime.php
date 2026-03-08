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
        Schema::table('weather_data', function (Blueprint $table) {
            $table->dateTime('data_timestamp')->change();
            $table->dateTime('forecast_timestamp')->nullable()->change();
            $table->dateTime('sunrise')->nullable()->change();
            $table->dateTime('sunset')->nullable()->change();
            $table->dateTime('moonrise')->nullable()->change();
            $table->dateTime('moonset')->nullable()->change();
        });

        Schema::table('weather_alerts', function (Blueprint $table) {
            $table->dateTime('start_time')->change();
            $table->dateTime('end_time')->change();
            $table->dateTime('issued_time')->nullable()->change();
            $table->dateTime('expires_time')->nullable()->change();
            $table->dateTime('processed_at')->nullable()->change();
            $table->dateTime('last_verified')->nullable()->change();
        });

        Schema::table('weather_minutely', function (Blueprint $table) {
            $table->dateTime('data_timestamp')->change();
            $table->dateTime('forecast_timestamp')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weather_data', function (Blueprint $table) {
            $table->timestamp('data_timestamp')->change();
            $table->timestamp('forecast_timestamp')->nullable()->change();
            $table->timestamp('sunrise')->nullable()->change();
            $table->timestamp('sunset')->nullable()->change();
            $table->timestamp('moonrise')->nullable()->change();
            $table->timestamp('moonset')->nullable()->change();
        });

        Schema::table('weather_alerts', function (Blueprint $table) {
            $table->timestamp('start_time')->change();
            $table->timestamp('end_time')->change();
            $table->timestamp('issued_time')->nullable()->change();
            $table->timestamp('expires_time')->nullable()->change();
            $table->timestamp('processed_at')->nullable()->change();
            $table->timestamp('last_verified')->nullable()->change();
        });

        Schema::table('weather_minutely', function (Blueprint $table) {
            $table->timestamp('data_timestamp')->change();
            $table->timestamp('forecast_timestamp')->nullable()->change();
        });
    }
};
