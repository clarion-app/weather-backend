<?php

namespace ClarionApp\Weather\Models;

use ClarionApp\EloquentMultiChainBridge\EloquentMultiChainBridge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherData extends Model
{
    use HasFactory, EloquentMultiChainBridge, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'weather_data';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'weather_location_id',
        'data_type',
        'data_timestamp',
        'forecast_timestamp',
        'timezone',
        'timezone_offset',
        
        // Temperature fields
        'temperature',
        'feels_like_temperature',
        'temperature_min',
        'temperature_max',
        'temperature_morning',
        'temperature_day',
        'temperature_evening',
        'temperature_night',
        'feels_like_morning',
        'feels_like_day',
        'feels_like_evening',
        'feels_like_night',
        
        // Atmospheric conditions
        'pressure',
        'humidity',
        'dew_point',
        'clouds',
        'uvi',
        'visibility',
        
        // Wind information
        'wind_speed',
        'wind_gust',
        'wind_direction',
        
        // Precipitation
        'precipitation_1h',
        'precipitation_3h',
        'rain_1h',
        'rain_3h',
        'snow_1h',
        'snow_3h',
        'rain_daily',
        'snow_daily',
        'precipitation_probability',
        
        // Sun and moon data
        'sunrise',
        'sunset',
        'moonrise',
        'moonset',
        'moon_phase',
        
        // Weather condition
        'weather_id',
        'weather_main',
        'weather_description',
        'weather_icon',
        
        // Units and summary
        'units',
        'summary',
        'weather_overview',
        
        // Raw data and metadata
        'raw_data',
        'api_source',
        'api_version',
        'is_forecast',
        'forecast_day',
        'forecast_hour',
        'data_quality_score',
        'is_verified',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'data_timestamp' => 'datetime',
        'forecast_timestamp' => 'datetime',
        'sunrise' => 'datetime',
        'sunset' => 'datetime',
        'moonrise' => 'datetime',
        'moonset' => 'datetime',
        'temperature' => 'decimal:4',
        'feels_like_temperature' => 'decimal:4',
        'temperature_min' => 'decimal:4',
        'temperature_max' => 'decimal:4',
        'temperature_morning' => 'decimal:4',
        'temperature_day' => 'decimal:4',
        'temperature_evening' => 'decimal:4',
        'temperature_night' => 'decimal:4',
        'feels_like_morning' => 'decimal:4',
        'feels_like_day' => 'decimal:4',
        'feels_like_evening' => 'decimal:4',
        'feels_like_night' => 'decimal:4',
        'pressure' => 'decimal:2',
        'dew_point' => 'decimal:4',
        'uvi' => 'decimal:2',
        'wind_speed' => 'decimal:4',
        'wind_gust' => 'decimal:4',
        'precipitation_1h' => 'decimal:4',
        'precipitation_3h' => 'decimal:4',
        'rain_1h' => 'decimal:4',
        'rain_3h' => 'decimal:4',
        'snow_1h' => 'decimal:4',
        'snow_3h' => 'decimal:4',
        'rain_daily' => 'decimal:4',
        'snow_daily' => 'decimal:4',
        'precipitation_probability' => 'decimal:4',
        'moon_phase' => 'decimal:4',
        'data_quality_score' => 'decimal:2',
        'humidity' => 'integer',
        'clouds' => 'integer',
        'visibility' => 'integer',
        'timezone_offset' => 'integer',
        'wind_direction' => 'integer',
        'weather_id' => 'integer',
        'forecast_day' => 'integer',
        'forecast_hour' => 'integer',
        'is_forecast' => 'boolean',
        'is_verified' => 'boolean',
        'raw_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the weather location that owns this weather data.
     */
    public function weatherLocation(): BelongsTo
    {
        return $this->belongsTo(WeatherLocation::class);
    }

    /**
     * Scope to get only current weather data.
     */
    public function scopeCurrent($query)
    {
        return $query->where('data_type', 'current');
    }

    /**
     * Scope to get only hourly forecast data.
     */
    public function scopeHourly($query)
    {
        return $query->where('data_type', 'hourly');
    }

    /**
     * Scope to get only daily forecast data.
     */
    public function scopeDaily($query)
    {
        return $query->where('data_type', 'daily');
    }

    /**
     * Scope to get only historical data.
     */
    public function scopeHistorical($query)
    {
        return $query->where('data_type', 'historical');
    }

    /**
     * Scope to get only forecast data.
     */
    public function scopeForecasts($query)
    {
        return $query->where('is_forecast', true);
    }

    /**
     * Scope to get only verified data.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get data for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('data_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent data within hours.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('data_timestamp', '>=', now()->subHours($hours));
    }

    /**
     * Get formatted temperature with unit.
     */
    public function getFormattedTemperatureAttribute(): string
    {
        if (!$this->temperature) {
            return 'N/A';
        }

        $unit = match($this->units) {
            'imperial' => '°F',
            'metric' => '°C',
            default => 'K'
        };

        return round($this->temperature, 1) . $unit;
    }

    /**
     * Get formatted wind information.
     */
    public function getFormattedWindAttribute(): string
    {
        if (!$this->wind_speed) {
            return 'N/A';
        }

        $unit = $this->units === 'imperial' ? 'mph' : 'm/s';
        $direction = $this->wind_direction ? $this->getWindDirection() : '';
        
        return round($this->wind_speed, 1) . " {$unit}" . ($direction ? " {$direction}" : '');
    }

    /**
     * Get wind direction from degrees.
     */
    public function getWindDirection(): string
    {
        if (!$this->wind_direction) {
            return '';
        }

        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = round($this->wind_direction / 22.5) % 16;
        
        return $directions[$index];
    }

    /**
     * Check if this data is current (within last hour).
     */
    public function isCurrentWeather(): bool
    {
        return $this->data_type === 'current' && 
               $this->data_timestamp->diffInHours(now()) <= 1;
    }

    /**
     * Check if this data is expired based on type.
     */
    public function isExpired(): bool
    {
        $now = now();
        
        return match($this->data_type) {
            'current' => $this->data_timestamp->diffInHours($now) > 1,
            'hourly' => $this->data_timestamp->isPast(),
            'daily' => $this->data_timestamp->startOfDay()->isPast(),
            'historical' => false, // Historical data doesn't expire
            default => true
        };
    }

    /**
     * Get human-readable data type.
     */
    public function getDataTypeNameAttribute(): string
    {
        return match($this->data_type) {
            'current' => 'Current Weather',
            'hourly' => 'Hourly Forecast',
            'daily' => 'Daily Forecast',
            'historical' => 'Historical Data',
            default => ucfirst($this->data_type)
        };
    }

    /**
     * Get formatted weather data for API responses.
     */
    public function getFormattedWeatherData(): array
    {
        $formatted = [
            'id' => $this->id,
            'location_id' => $this->weather_location_id,
            'data_type' => $this->data_type,
            'data_type_name' => $this->data_type_name,
            'timestamp' => $this->data_timestamp?->format('Y-m-d H:i:s'),
            'dt' => $this->data_timestamp?->timestamp,
            'forecast_timestamp' => $this->forecast_timestamp?->format('Y-m-d H:i:s'),
            'timezone' => $this->timezone,
            'timezone_offset' => $this->timezone_offset,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];

        // Temperature data
        if ($this->temperature !== null) {
            $formatted['temperature'] = (float) $this->temperature;
            $formatted['temp'] = (float) $this->temperature; // Alias for frontend compatibility
        }
        if ($this->feels_like_temperature !== null) {
            $formatted['feels_like_temperature'] = (float) $this->feels_like_temperature;
            $formatted['feels_like'] = (float) $this->feels_like_temperature; // Alias
        }

        // Daily temperature breakdown
        if ($this->temperature_min !== null) $formatted['temperature_min'] = (float) $this->temperature_min;
        if ($this->temperature_max !== null) $formatted['temperature_max'] = (float) $this->temperature_max;
        if ($this->temperature_morning !== null) $formatted['temperature_morning'] = (float) $this->temperature_morning;
        if ($this->temperature_day !== null) $formatted['temperature_day'] = (float) $this->temperature_day;
        if ($this->temperature_evening !== null) $formatted['temperature_evening'] = (float) $this->temperature_evening;
        if ($this->temperature_night !== null) $formatted['temperature_night'] = (float) $this->temperature_night;
        
        // Daily feels like temperatures
        if ($this->feels_like_morning !== null) $formatted['feels_like_morning'] = (float) $this->feels_like_morning;
        if ($this->feels_like_day !== null) $formatted['feels_like_day'] = (float) $this->feels_like_day;
        if ($this->feels_like_evening !== null) $formatted['feels_like_evening'] = (float) $this->feels_like_evening;
        if ($this->feels_like_night !== null) $formatted['feels_like_night'] = (float) $this->feels_like_night;

        // Atmospheric conditions
        if ($this->pressure !== null) $formatted['pressure'] = (float) $this->pressure;
        if ($this->humidity !== null) $formatted['humidity'] = (int) $this->humidity;
        if ($this->dew_point !== null) $formatted['dew_point'] = (float) $this->dew_point;
        if ($this->clouds !== null) $formatted['clouds'] = (int) $this->clouds;
        if ($this->uvi !== null) $formatted['uvi'] = (float) $this->uvi;
        if ($this->visibility !== null) $formatted['visibility'] = (int) $this->visibility;

        // Wind information
        if ($this->wind_speed !== null) $formatted['wind_speed'] = (float) $this->wind_speed;
        if ($this->wind_direction !== null) {
            $formatted['wind_direction'] = (int) $this->wind_direction;
            $formatted['wind_deg'] = (int) $this->wind_direction; // Alias
            $formatted['wind_direction_compass'] = $this->getWindDirection();
        }
        if ($this->wind_gust !== null) $formatted['wind_gust'] = (float) $this->wind_gust;

        // Precipitation
        if ($this->precipitation_1h !== null) $formatted['precipitation_1h'] = (float) $this->precipitation_1h;
        if ($this->precipitation_3h !== null) $formatted['precipitation_3h'] = (float) $this->precipitation_3h;
        if ($this->rain_1h !== null) $formatted['rain_1h'] = (float) $this->rain_1h;
        if ($this->rain_3h !== null) $formatted['rain_3h'] = (float) $this->rain_3h;
        if ($this->snow_1h !== null) $formatted['snow_1h'] = (float) $this->snow_1h;
        if ($this->snow_3h !== null) $formatted['snow_3h'] = (float) $this->snow_3h;
        if ($this->rain_daily !== null) $formatted['rain_daily'] = (float) $this->rain_daily;
        if ($this->snow_daily !== null) $formatted['snow_daily'] = (float) $this->snow_daily;
        if ($this->precipitation_probability !== null) {
            $formatted['precipitation_probability'] = (float) $this->precipitation_probability;
            $formatted['pop'] = (float) $this->precipitation_probability; // Alias
        }

        // Sun and moon data
        if ($this->sunrise) $formatted['sunrise'] = $this->sunrise->timestamp;
        if ($this->sunset) $formatted['sunset'] = $this->sunset->timestamp;
        if ($this->moonrise) $formatted['moonrise'] = $this->moonrise->timestamp;
        if ($this->moonset) $formatted['moonset'] = $this->moonset->timestamp;
        if ($this->moon_phase !== null) $formatted['moon_phase'] = (float) $this->moon_phase;

        // Weather condition
        if ($this->weather_id !== null) $formatted['weather_id'] = (int) $this->weather_id;
        if ($this->weather_main) $formatted['weather_main'] = $this->weather_main;
        if ($this->weather_description) $formatted['weather_description'] = $this->weather_description;
        if ($this->weather_icon) $formatted['weather_icon'] = $this->weather_icon;

        // Metadata
        if ($this->units) $formatted['units'] = $this->units;
        if ($this->summary) $formatted['summary'] = $this->summary;
        if ($this->weather_overview) $formatted['weather_overview'] = $this->weather_overview;
        if ($this->api_source) $formatted['api_source'] = $this->api_source;
        if ($this->api_version) $formatted['api_version'] = $this->api_version;
        if ($this->data_quality_score !== null) $formatted['data_quality_score'] = (float) $this->data_quality_score;
        if ($this->is_forecast !== null) $formatted['is_forecast'] = (bool) $this->is_forecast;
        if ($this->is_verified !== null) $formatted['is_verified'] = (bool) $this->is_verified;
        if ($this->forecast_day !== null) $formatted['forecast_day'] = (int) $this->forecast_day;
        if ($this->forecast_hour !== null) $formatted['forecast_hour'] = (int) $this->forecast_hour;

        // Include raw data if available
        if ($this->raw_data) $formatted['raw_data'] = $this->raw_data;

        // Add computed fields
        $formatted['formatted_temperature'] = $this->formatted_temperature;
        $formatted['formatted_wind'] = $this->formatted_wind;
        $formatted['is_current'] = $this->isCurrentWeather();
        $formatted['is_expired'] = $this->isExpired();

        return $formatted;
    }
}
