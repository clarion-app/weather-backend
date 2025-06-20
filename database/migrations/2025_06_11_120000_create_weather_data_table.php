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
        Schema::create('weather_data', function (Blueprint $table) {
            // Primary key as UUID
            $table->uuid('id')->primary();
            
            // Foreign key to weather_locations table
            $table->foreignUuid('weather_location_id')
                  ->constrained('weather_locations')
                  ->onDelete('cascade');
            
            // Data type and timing information
            $table->enum('data_type', ['current', 'hourly', 'daily', 'historical'])
                  ->index();
            $table->timestamp('data_timestamp'); // The actual weather data timestamp (dt field from API)
            $table->timestamp('forecast_timestamp')->nullable(); // When this forecast was made
            $table->string('timezone', 100)->nullable();
            $table->integer('timezone_offset')->nullable(); // Shift in seconds from UTC
            
            // Core weather measurements
            $table->decimal('temperature', 8, 4)->nullable(); // Kelvin/Celsius/Fahrenheit
            $table->decimal('feels_like_temperature', 8, 4)->nullable();
            $table->decimal('temperature_min', 8, 4)->nullable(); // For daily data
            $table->decimal('temperature_max', 8, 4)->nullable(); // For daily data
            $table->decimal('temperature_morning', 8, 4)->nullable(); // For daily data
            $table->decimal('temperature_day', 8, 4)->nullable(); // For daily data
            $table->decimal('temperature_evening', 8, 4)->nullable(); // For daily data
            $table->decimal('temperature_night', 8, 4)->nullable(); // For daily data
            
            // Feels like temperatures for daily data
            $table->decimal('feels_like_morning', 8, 4)->nullable();
            $table->decimal('feels_like_day', 8, 4)->nullable();
            $table->decimal('feels_like_evening', 8, 4)->nullable();
            $table->decimal('feels_like_night', 8, 4)->nullable();
            
            // Atmospheric conditions
            $table->decimal('pressure', 8, 2)->nullable(); // hPa
            $table->tinyInteger('humidity')->nullable(); // %
            $table->decimal('dew_point', 8, 4)->nullable();
            $table->tinyInteger('clouds')->nullable(); // %
            $table->decimal('uvi', 5, 2)->nullable(); // UV index
            $table->integer('visibility')->nullable(); // metres
            
            // Wind information
            $table->decimal('wind_speed', 8, 4)->nullable(); // m/s or mph
            $table->decimal('wind_gust', 8, 4)->nullable(); // m/s or mph
            $table->smallInteger('wind_direction')->nullable(); // degrees (0-360)
            
            // Precipitation
            $table->decimal('precipitation_1h', 8, 4)->nullable(); // mm/h
            $table->decimal('precipitation_3h', 8, 4)->nullable(); // mm/3h
            $table->decimal('rain_1h', 8, 4)->nullable(); // mm/h
            $table->decimal('rain_3h', 8, 4)->nullable(); // mm/3h
            $table->decimal('snow_1h', 8, 4)->nullable(); // mm/h
            $table->decimal('snow_3h', 8, 4)->nullable(); // mm/3h
            $table->decimal('rain_daily', 8, 4)->nullable(); // mm for daily data
            $table->decimal('snow_daily', 8, 4)->nullable(); // mm for daily data
            $table->decimal('precipitation_probability', 5, 4)->nullable(); // 0-1 (0% to 100%)
            
            // Sun and moon data (for daily data)
            $table->timestamp('sunrise')->nullable();
            $table->timestamp('sunset')->nullable();
            $table->timestamp('moonrise')->nullable();
            $table->timestamp('moonset')->nullable();
            $table->decimal('moon_phase', 5, 4)->nullable(); // 0-1
            
            // Weather condition
            $table->smallInteger('weather_id')->nullable(); // OpenWeatherMap weather condition id
            $table->string('weather_main', 50)->nullable(); // Rain, Snow, Clouds, etc.
            $table->string('weather_description')->nullable(); // Full description
            $table->string('weather_icon', 10)->nullable(); // Icon code
            
            // Units of measurement used
            $table->enum('units', ['standard', 'metric', 'imperial'])
                  ->default('metric');
            
            // Summary (for daily data and weather overview)
            $table->text('summary')->nullable(); // Human-readable summary
            $table->text('weather_overview')->nullable(); // AI-generated overview
            
            // Store raw API response for flexibility
            $table->json('raw_data')->nullable();
            
            // Metadata
            $table->string('api_source', 50)->default('openweathermap'); // Source of the data
            $table->string('api_version', 20)->nullable(); // API version used
            $table->boolean('is_forecast')->default(false); // Is this forecast data or current/historical
            $table->tinyInteger('forecast_day')->nullable(); // Day number for daily forecasts (1-8)
            $table->tinyInteger('forecast_hour')->nullable(); // Hour offset for hourly forecasts
            
            // Quality and reliability
            $table->decimal('data_quality_score', 3, 2)->nullable(); // 0-1 quality score
            $table->boolean('is_verified')->default(false); // Has this data been verified
            
            // Standard Laravel timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['weather_location_id', 'data_type', 'data_timestamp']);
            $table->index(['data_type', 'data_timestamp']);
            $table->index(['weather_location_id', 'is_forecast', 'data_timestamp'], 'location_forecast_time');
            $table->index(['data_timestamp', 'data_type']);
            $table->index('forecast_timestamp');
            $table->index(['weather_location_id', 'forecast_day']); // For daily forecasts
            $table->index(['weather_location_id', 'forecast_hour']); // For hourly forecasts
            $table->index('weather_id'); // For weather condition queries
            
            // Composite indexes for common queries
            $table->index(['weather_location_id', 'data_type', 'is_forecast', 'data_timestamp'], 'weather_location_type_forecast_time');
            $table->index(['data_type', 'is_forecast', 'data_timestamp'], 'type_forecast_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_data');
    }
};
