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
        Schema::create('weather_minutely', function (Blueprint $table) {
            // Primary key as UUID
            $table->uuid('id')->primary();
            
            // Foreign key to weather_locations table
            $table->foreignUuid('weather_location_id')
                  ->constrained('weather_locations')
                  ->onDelete('cascade');
            
            // Timing information
            $table->timestamp('data_timestamp'); // The minute timestamp (dt field from API)
            $table->timestamp('forecast_timestamp')->nullable(); // When this forecast was made
            
            // Precipitation data (main focus of minutely data)
            $table->decimal('precipitation', 8, 4)->default(0); // mm/h precipitation
            $table->decimal('rain', 8, 4)->nullable(); // mm/h rain if specified separately
            $table->decimal('snow', 8, 4)->nullable(); // mm/h snow if specified separately
            
            // Optional additional data that might be available
            $table->decimal('temperature', 8, 4)->nullable(); // Temperature if available
            $table->tinyInteger('humidity')->nullable(); // Humidity if available
            $table->decimal('pressure', 8, 2)->nullable(); // Pressure if available
            $table->decimal('wind_speed', 8, 4)->nullable(); // Wind speed if available
            $table->smallInteger('wind_direction')->nullable(); // Wind direction if available
            
            // Data quality and metadata
            $table->enum('units', ['standard', 'metric', 'imperial'])
                  ->default('metric');
            $table->string('api_source', 50)->default('openweathermap');
            $table->string('api_version', 20)->nullable();
            $table->boolean('is_forecast')->default(true); // Minutely data is typically forecast
            $table->tinyInteger('forecast_minute')->nullable(); // Minute offset (0-60)
            
            // Store raw API response for this minute
            $table->json('raw_data')->nullable();
            
            // Standard Laravel timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance (minutely data is queried frequently and in time ranges)
            $table->index(['weather_location_id', 'data_timestamp']);
            $table->index(['weather_location_id', 'forecast_timestamp', 'data_timestamp'], 'location_forecast_data_time');
            $table->index(['data_timestamp', 'precipitation']); // For precipitation queries
            $table->index('forecast_timestamp');
            
            // Composite index for common queries
            $table->index(['weather_location_id', 'is_forecast', 'data_timestamp'], 'location_forecast_time');
            
            // Unique constraint to prevent duplicate entries for same location and time
            $table->unique(['weather_location_id', 'data_timestamp', 'forecast_timestamp'], 'unique_location_time_forecast');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_minutely');
    }
};
