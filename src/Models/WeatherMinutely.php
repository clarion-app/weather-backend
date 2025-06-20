<?php

namespace ClarionApp\Weather\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class WeatherMinutely extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'weather_minutely';

    protected $fillable = [
        'weather_location_id',
        'data_timestamp',
        'forecast_timestamp',
        'precipitation',
        'rain',
        'snow',
        'precipitation_type',
        'precipitation_probability',
        'temperature',
        'humidity',
        'pressure',
        'wind_speed',
        'wind_direction',
        'units',
        'api_source',
        'api_version',
        'is_forecast',
        'forecast_minute',
        'raw_data',
    ];

    protected $casts = [
        'data_timestamp' => 'datetime',
        'forecast_timestamp' => 'datetime',
        'precipitation' => 'decimal:4',
        'rain' => 'decimal:4',
        'snow' => 'decimal:4',
        'precipitation_probability' => 'decimal:2',
        'temperature' => 'decimal:2',
        'humidity' => 'integer',
        'pressure' => 'decimal:2',
        'wind_speed' => 'decimal:4',
        'wind_direction' => 'integer',
        'forecast_minute' => 'integer',
        'is_forecast' => 'boolean',
        'raw_data' => 'array',
    ];

    protected $dates = [
        'data_timestamp',
        'forecast_timestamp',
        'deleted_at',
    ];

    /**
     * Get the weather location that owns the minutely data.
     */
    public function weatherLocation(): BelongsTo
    {
        return $this->belongsTo(WeatherLocation::class);
    }

    /**
     * Scope a query to only include data for a specific time range.
     */
    public function scopeTimeRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('data_timestamp', [$start, $end]);
    }

    /**
     * Scope a query to only include data for the next hour.
     */
    public function scopeNextHour($query)
    {
        $now = now();
        return $query->whereBetween('data_timestamp', [$now, $now->copy()->addHour()]);
    }

    /**
     * Scope a query to only include data with precipitation.
     */
    public function scopeWithPrecipitation($query)
    {
        return $query->where('precipitation', '>', 0);
    }

    /**
     * Scope a query to only include data by precipitation type.
     */
    public function scopeByPrecipitationType($query, string $type)
    {
        return $query->where('precipitation_type', $type);
    }

    /**
     * Scope a query to only include recent data (last 2 hours).
     */
    public function scopeRecent($query)
    {
        return $query->where('data_timestamp', '>=', now()->subHours(2));
    }

    /**
     * Check if there's precipitation at this minute.
     */
    public function hasPrecipitation(): bool
    {
        return $this->precipitation > 0;
    }

    /**
     * Get precipitation intensity level.
     */
    public function getPrecipitationIntensity(): string
    {
        if ($this->precipitation <= 0) {
            return 'none';
        } elseif ($this->precipitation <= 0.1) {
            return 'light';
        } elseif ($this->precipitation <= 0.3) {
            return 'moderate';
        } elseif ($this->precipitation <= 0.6) {
            return 'heavy';
        } else {
            return 'very_heavy';
        }
    }

    /**
     * Get precipitation probability as percentage.
     */
    public function getPrecipitationProbabilityPercent(): int
    {
        return (int) round($this->precipitation_probability * 100);
    }

    /**
     * Get formatted temperature.
     */
    public function getFormattedTemperature(string $unit = 'celsius'): string
    {
        $temp = $this->temperature;
        
        if ($unit === 'fahrenheit') {
            $temp = ($temp * 9/5) + 32;
        }
        
        $symbol = $unit === 'fahrenheit' ? '°F' : '°C';
        return round($temp, 1) . $symbol;
    }

    /**
     * Get formatted feels like temperature.
     */
    public function getFormattedFeelsLike(string $unit = 'celsius'): string
    {
        $temp = $this->feels_like;
        
        if ($unit === 'fahrenheit') {
            $temp = ($temp * 9/5) + 32;
        }
        
        $symbol = $unit === 'fahrenheit' ? '°F' : '°C';
        return round($temp, 1) . $symbol;
    }

    /**
     * Get formatted precipitation amount.
     */
    public function getFormattedPrecipitation(): string
    {
        if ($this->precipitation <= 0) {
            return '0 mm';
        }
        
        return number_format($this->precipitation, 1) . ' mm';
    }

    /**
     * Get visibility in km or miles.
     */
    public function getFormattedVisibility(string $unit = 'km'): string
    {
        $visibility = $this->visibility / 1000; // Convert from meters to km
        
        if ($unit === 'miles') {
            $visibility = $visibility * 0.621371;
            return number_format($visibility, 1) . ' mi';
        }
        
        return number_format($visibility, 1) . ' km';
    }

    /**
     * Get the time difference from now in minutes.
     */
    public function getMinutesFromNow(): int
    {
        return now()->diffInMinutes($this->data_timestamp, false);
    }

    /**
     * Check if this data point is in the future.
     */
    public function isFuture(): bool
    {
        return $this->data_timestamp > now();
    }

    /**
     * Check if this data point is in the past.
     */
    public function isPast(): bool
    {
        return $this->data_timestamp < now();
    }

    /**
     * Format the minutely data for display.
     */
    public function getFormattedMinutelyData(): array
    {
        return [
            'id' => $this->id,
            'time' => $this->data_timestamp->format('H:i'),
            'datetime' => $this->data_timestamp->format('Y-m-d H:i:s'),
            'minutes_from_now' => $this->getMinutesFromNow(),
            'precipitation' => [
                'amount' => $this->getFormattedPrecipitation(),
                'amount_mm' => (float) $this->precipitation,
                'type' => $this->precipitation_type,
                'intensity' => $this->getPrecipitationIntensity(),
                'probability' => $this->getPrecipitationProbabilityPercent(),
            ],
            'temperature' => [
                'celsius' => $this->getFormattedTemperature('celsius'),
                'fahrenheit' => $this->getFormattedTemperature('fahrenheit'),
                'raw' => (float) $this->temperature,
            ],
            'feels_like' => [
                'celsius' => $this->getFormattedFeelsLike('celsius'),
                'fahrenheit' => $this->getFormattedFeelsLike('fahrenheit'),
                'raw' => (float) $this->feels_like,
            ],
            'humidity' => $this->humidity,
            'visibility' => [
                'km' => $this->getFormattedVisibility('km'),
                'miles' => $this->getFormattedVisibility('miles'),
                'meters' => $this->visibility,
            ],
            'is_future' => $this->isFuture(),
            'is_past' => $this->isPast(),
        ];
    }

    /**
     * Get precipitation chart data for the next hour.
     */
    public static function getPrecipitationChart(WeatherLocation $location): array
    {
        $data = static::where('weather_location_id', $location->id)
            ->nextHour()
            ->orderBy('data_timestamp')
            ->get();

        return $data->map(function ($item) {
            return [
                'time' => $item->data_timestamp->format('H:i'),
                'minutes' => $item->getMinutesFromNow(),
                'precipitation' => (float) $item->precipitation,
                'probability' => $item->getPrecipitationProbabilityPercent(),
                'intensity' => $item->getPrecipitationIntensity(),
            ];
        })->toArray();
    }
}
