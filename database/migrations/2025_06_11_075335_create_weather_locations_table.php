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
        Schema::create('weather_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->comment('Display name for the location');
            $table->string('city')->comment('City name');
            $table->string('state')->nullable()->comment('State/province (if applicable)');
            $table->string('country')->comment('Country name');
            $table->string('country_code', 2)->comment('ISO 3166 country code');
            $table->decimal('latitude', 10, 7)->comment('Latitude coordinate');
            $table->decimal('longitude', 10, 7)->comment('Longitude coordinate');
            $table->boolean('is_active')->default(true)->comment('Whether this location is actively monitored');
            $table->boolean('is_favorite')->default(false)->comment('Whether this location is marked as favorite');
            $table->string('timezone')->nullable()->comment('Timezone identifier');
            $table->json('geocoding_data')->nullable()->comment('Raw geocoding response data from API');
            $table->timestamps();
            $table->softDeletes();
            
            // Add indexes for performance
            $table->index('is_active');
            $table->index('is_favorite');
            $table->index('deleted_at');
            $table->index(['latitude', 'longitude']);
            $table->index('country_code');
            
            // Unique constraint to prevent duplicate locations
            $table->unique(['latitude', 'longitude', 'deleted_at'], 'unique_active_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_locations');
    }
};
