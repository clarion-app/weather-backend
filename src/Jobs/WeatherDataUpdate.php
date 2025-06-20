<?php

namespace ClarionApp\Weather\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ClarionApp\Weather\Models\WeatherApi;
use ClarionApp\Weather\Models\WeatherLocation;
use ClarionApp\Weather\Models\WeatherData;
use ClarionApp\Weather\Models\WeatherAlert;
use ClarionApp\Weather\Models\WeatherMinutely;
use Carbon\Carbon;

class WeatherDataUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get all active weather locations
            $locations = WeatherLocation::where('is_active', true)->get();
            
            if ($locations->isEmpty()) {
                Log::info('No active weather locations found');
                return;
            }

            // Get the default OpenWeatherMap API configuration
            $api = WeatherApi::where('is_active', true)->first();

            if (!$api) {
                Log::error('No active weather API configuration found');
                return;
            }

            foreach ($locations as $location) {
                $this->updateLocationWeather($location, $api);
            }
        } catch (\Exception $e) {
            Log::error('Weather data update job failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update weather data for a specific location.
     */
    private function updateLocationWeather(WeatherLocation $location, WeatherApi $api): void
    {
        try {
            // Check if we should skip this location based on recent data timestamp
            if ($this->shouldSkipLocationUpdate($location, $api)) {
                return;
            }

            $units = $location->units ?? 'metric';
            $url = $api->url. "?lat={$location->latitude}&lon={$location->longitude}";
            $url.= "&appid={$api->api_key}&units={$units}";
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error("Failed to fetch weather data for location {$location->id}: " . $response->body());
                return;
            }

            $data = $response->json();

            // Update current weather data
            if (isset($data['current'])) {
                $this->updateCurrentWeather($location, $data['current']);
            }

            // Update hourly forecast data
            if (isset($data['hourly'])) {
                $this->updateHourlyForecast($location, $data['hourly']);
            }

            // Update daily forecast data
            if (isset($data['daily'])) {
                $this->updateDailyForecast($location, $data['daily']);
            }

            // Update weather alerts
            if (isset($data['alerts'])) {
                $this->updateWeatherAlerts($location, $data['alerts']);
            }

            // Update minutely precipitation data
            if (isset($data['minutely'])) {
                $this->updateMinutelyData($location, $data['minutely']);
            }
        } catch (\Exception $e) {
            Log::error("Failed to update weather data for location {$location->id}: " . $e->getMessage(), [
                'location_id' => $location->id,
                'exception' => $e
            ]);
        }
    }

    /**
     * Update current weather data.
     */
    private function updateCurrentWeather(WeatherLocation $location, array $currentData): void
    {
        // Delete old current weather data (keep only the most recent)
        WeatherData::where('weather_location_id', $location->id)
                   ->where('data_type', 'current')
                   ->where('created_at', '<', now()->subHours(1))
                   ->delete();

        WeatherData::create([
            'weather_location_id' => $location->id,
            'data_type' => 'current',
            'data_timestamp' => Carbon::createFromTimestamp($currentData['dt']),
            'temperature' => $currentData['temp'],
            'feels_like_temperature' => $currentData['feels_like'],
            'pressure' => $currentData['pressure'] ?? null,
            'humidity' => $currentData['humidity'] ?? null,
            'dew_point' => $currentData['dew_point'] ?? null,
            'uvi' => $currentData['uvi'] ?? null,
            'clouds' => $currentData['clouds'] ?? null,
            'visibility' => $currentData['visibility'] ?? null,
            'wind_speed' => $currentData['wind_speed'] ?? null,
            'wind_direction' => $currentData['wind_deg'] ?? null,
            'wind_gust' => $currentData['wind_gust'] ?? null,
            'weather_id' => $currentData['weather'][0]['id'] ?? null,
            'weather_main' => $currentData['weather'][0]['main'] ?? null,
            'weather_description' => $currentData['weather'][0]['description'] ?? null,
            'weather_icon' => $currentData['weather'][0]['icon'] ?? null,
            'sunrise' => isset($currentData['sunrise']) ? Carbon::createFromTimestamp($currentData['sunrise'], 'UTC') : null,
            'sunset' => isset($currentData['sunset']) ? Carbon::createFromTimestamp($currentData['sunset'], 'UTC') : null,
            'raw_data' => $currentData,
            'units' => $location->units ?? 'metric',
        ]);
    }

    /**
     * Update hourly forecast data.
     */
    private function updateHourlyForecast(WeatherLocation $location, array $hourlyData): void
    {
        // Delete old hourly data (older than 48 hours)
        WeatherData::where('weather_location_id', $location->id)
                   ->where('data_type', 'hourly')
                   ->where('data_timestamp', '<', now()->subHours(48))
                   ->delete();

        foreach ($hourlyData as $hour) {
            // Check if this hourly data already exists
            $existingData = WeatherData::where('weather_location_id', $location->id)
                                      ->where('data_type', 'hourly')
                                      ->where('data_timestamp', Carbon::createFromTimestamp($hour['dt']))
                                      ->first();

            if ($existingData) {
                // Update existing data
                $existingData->update([
                    'temperature' => $hour['temp'],
                    'feels_like_temperature' => $hour['feels_like'],
                    'pressure' => $hour['pressure'] ?? null,
                    'humidity' => $hour['humidity'] ?? null,
                    'dew_point' => $hour['dew_point'] ?? null,
                    'uvi' => $hour['uvi'] ?? null,
                    'clouds' => $hour['clouds'] ?? null,
                    'visibility' => $hour['visibility'] ?? null,
                    'wind_speed' => $hour['wind_speed'] ?? null,
                    'wind_direction' => $hour['wind_deg'] ?? null,
                    'wind_gust' => $hour['wind_gust'] ?? null,
                    'precipitation_probability' => $hour['pop'] ?? null,
                    'rain_1h' => $hour['rain']['1h'] ?? null,
                    'snow_1h' => $hour['snow']['1h'] ?? null,
                    'weather_id' => $hour['weather'][0]['id'] ?? null,
                    'weather_main' => $hour['weather'][0]['main'] ?? null,
                    'weather_description' => $hour['weather'][0]['description'] ?? null,
                    'weather_icon' => $hour['weather'][0]['icon'] ?? null,
                    'raw_data' => $hour,
                ]);
            } else {
                // Create new hourly data
                WeatherData::create([
                    'weather_location_id' => $location->id,
                    'data_type' => 'hourly',
                    'data_timestamp' => Carbon::createFromTimestamp($hour['dt'], 'UTC'),
                    'temperature' => $hour['temp'],
                    'feels_like_temperature' => $hour['feels_like'],
                    'pressure' => $hour['pressure'] ?? null,
                    'humidity' => $hour['humidity'] ?? null,
                    'dew_point' => $hour['dew_point'] ?? null,
                    'uvi' => $hour['uvi'] ?? null,
                    'clouds' => $hour['clouds'] ?? null,
                    'visibility' => $hour['visibility'] ?? null,
                    'wind_speed' => $hour['wind_speed'] ?? null,
                    'wind_direction' => $hour['wind_deg'] ?? null,
                    'wind_gust' => $hour['wind_gust'] ?? null,
                    'precipitation_probability' => $hour['pop'] ?? null,
                    'rain_1h' => $hour['rain']['1h'] ?? null,
                    'snow_1h' => $hour['snow']['1h'] ?? null,
                    'weather_id' => $hour['weather'][0]['id'] ?? null,
                    'weather_main' => $hour['weather'][0]['main'] ?? null,
                    'weather_description' => $hour['weather'][0]['description'] ?? null,
                    'weather_icon' => $hour['weather'][0]['icon'] ?? null,
                    'raw_data' => $hour,
                ]);
            }
        }
    }

    /**
     * Update daily forecast data.
     */
    private function updateDailyForecast(WeatherLocation $location, array $dailyData): void
    {
        // Delete old daily data (older than 8 days)
        WeatherData::where('weather_location_id', $location->id)
                   ->where('data_type', 'daily')
                   ->where('data_timestamp', '<', now()->subDays(8))
                   ->delete();

        foreach ($dailyData as $day) {
            // Check if this daily data already exists
            $existingData = WeatherData::where('weather_location_id', $location->id)
                                      ->where('data_type', 'daily')
                                      ->where('data_timestamp', Carbon::createFromTimestamp($day['dt']))
                                      ->first();

            if ($existingData) {
                // Update existing data
                $existingData->update([
                    'temperature_day' => $day['temp']['day'],
                    'temperature_min' => $day['temp']['min'],
                    'temperature_max' => $day['temp']['max'],
                    'temperature_night' => $day['temp']['night'],
                    'temperature_evening' => $day['temp']['eve'],
                    'temperature_morning' => $day['temp']['morn'],
                    'feels_like_day' => $day['feels_like']['day'],
                    'feels_like_night' => $day['feels_like']['night'],
                    'feels_like_evening' => $day['feels_like']['eve'],
                    'feels_like_morning' => $day['feels_like']['morn'],
                    'pressure' => $day['pressure'] ?? null,
                    'humidity' => $day['humidity'] ?? null,
                    'dew_point' => $day['dew_point'] ?? null,
                    'uvi' => $day['uvi'] ?? null,
                    'clouds' => $day['clouds'] ?? null,
                    'wind_speed' => $day['wind_speed'] ?? null,
                    'wind_direction' => $day['wind_deg'] ?? null,
                    'wind_gust' => $day['wind_gust'] ?? null,
                    'precipitation_probability' => $day['pop'] ?? null,
                    'rain_daily' => $day['rain'] ?? null,
                    'snow_daily' => $day['snow'] ?? null,
                    'weather_id' => $day['weather'][0]['id'] ?? null,
                    'weather_main' => $day['weather'][0]['main'] ?? null,
                    'weather_description' => $day['weather'][0]['description'] ?? null,
                    'weather_icon' => $day['weather'][0]['icon'] ?? null,
                    'sunrise' => isset($day['sunrise']) ? Carbon::createFromTimestamp($day['sunrise'], 'UTC') : null,
                    'sunset' => isset($day['sunset']) ? Carbon::createFromTimestamp($day['sunset'], 'UTC') : null,
                    'moonrise' => isset($day['moonrise']) ? Carbon::createFromTimestamp($day['moonrise'], 'UTC') : null,
                    'moonset' => isset($day['moonset']) ? Carbon::createFromTimestamp($day['moonset'], 'UTC') : null,
                    'moon_phase' => $day['moon_phase'] ?? null,
                    'raw_data' => $day,
                ]);
            } else {
                // Create new daily data
                WeatherData::create([
                    'weather_location_id' => $location->id,
                    'data_type' => 'daily',
                    'data_timestamp' => Carbon::createFromTimestamp($day['dt'], 'UTC'),
                    'temperature_day' => $day['temp']['day'],
                    'temperature_min' => $day['temp']['min'],
                    'temperature_max' => $day['temp']['max'],
                    'temperature_night' => $day['temp']['night'],
                    'temperature_evening' => $day['temp']['eve'],
                    'temperature_morning' => $day['temp']['morn'],
                    'feels_like_day' => $day['feels_like']['day'],
                    'feels_like_night' => $day['feels_like']['night'],
                    'feels_like_evening' => $day['feels_like']['eve'],
                    'feels_like_morning' => $day['feels_like']['morn'],
                    'pressure' => $day['pressure'] ?? null,
                    'humidity' => $day['humidity'] ?? null,
                    'dew_point' => $day['dew_point'] ?? null,
                    'uvi' => $day['uvi'] ?? null,
                    'clouds' => $day['clouds'] ?? null,
                    'wind_speed' => $day['wind_speed'] ?? null,
                    'wind_direction' => $day['wind_deg'] ?? null,
                    'wind_gust' => $day['wind_gust'] ?? null,
                    'precipitation_probability' => $day['pop'] ?? null,
                    'rain_daily' => $day['rain'] ?? null,
                    'snow_daily' => $day['snow'] ?? null,
                    'weather_id' => $day['weather'][0]['id'] ?? null,
                    'weather_main' => $day['weather'][0]['main'] ?? null,
                    'weather_description' => $day['weather'][0]['description'] ?? null,
                    'weather_icon' => $day['weather'][0]['icon'] ?? null,
                    'sunrise' => isset($day['sunrise']) ? Carbon::createFromTimestamp($day['sunrise'], 'UTC') : null,
                    'sunset' => isset($day['sunset']) ? Carbon::createFromTimestamp($day['sunset'], 'UTC') : null,
                    'moonrise' => isset($day['moonrise']) ? Carbon::createFromTimestamp($day['moonrise'], 'UTC') : null,
                    'moonset' => isset($day['moonset']) ? Carbon::createFromTimestamp($day['moonset'], 'UTC') : null,
                    'moon_phase' => $day['moon_phase'] ?? null,
                    'raw_data' => $day,
                ]);
            }
        }
    }

    /**
     * Update weather alerts.
     */
    private function updateWeatherAlerts(WeatherLocation $location, array $alertsData): void
    {
        // Delete old alerts that are no longer active
        WeatherAlert::where('weather_location_id', $location->id)
                   ->where('end_time', '<', now())
                   ->delete();

        foreach ($alertsData as $alert) {
            // Check if this alert already exists
            $existingAlert = WeatherAlert::where('weather_location_id', $location->id)
                                       ->where('sender_name', $alert['sender_name'])
                                       ->where('event', $alert['event'])
                                       ->where('start_time', Carbon::createFromTimestamp($alert['start']))
                                       ->first();

            if (!$existingAlert) {
                WeatherAlert::create([
                    'weather_location_id' => $location->id,
                    'sender_name' => $alert['sender_name'],
                    'event' => $alert['event'],
                    'start_time' => Carbon::createFromTimestamp($alert['start']),
                    'end_time' => Carbon::createFromTimestamp($alert['end']),
                    'description' => $alert['description'] ?? null,
                    'tags' => $alert['tags'] ?? null,
                    'raw_data' => $alert,
                ]);
            }
        }
    }

    /**
     * Update minutely precipitation data.
     */
    private function updateMinutelyData(WeatherLocation $location, array $minutelyData): void
    {
        // Delete old minutely data (older than 2 hours)
        WeatherMinutely::where('weather_location_id', $location->id)
                       ->where('data_timestamp', '<', now()->subHours(2))
                       ->delete();

        foreach ($minutelyData as $minute) {
            // Check if this minutely data already exists
            $existingData = WeatherMinutely::where('weather_location_id', $location->id)
                                          ->where('data_timestamp', Carbon::createFromTimestamp($minute['dt']))
                                          ->first();

            if (!$existingData) {
                WeatherMinutely::create([
                    'weather_location_id' => $location->id,
                    'data_timestamp' => Carbon::createFromTimestamp($minute['dt']),
                    'precipitation' => $minute['precipitation'],
                    'raw_data' => $minute,
                ]);
            }
        }
    }

    /**
     * Check if we should skip updating weather data for a location based on recent data timestamp.
     */
    private function shouldSkipLocationUpdate(WeatherLocation $location, WeatherApi $api): bool
    {
        // Get the most recent weather data for this location
        $mostRecentData = WeatherData::where('weather_location_id', $location->id)
                                   ->where('data_type', 'current')
                                   ->orderBy('created_at', 'desc')
                                   ->first();

        if (!$mostRecentData) {
            return false; // No previous data, proceed with update
        }

        $rateLimitMinutes = $api->rate_limit_minutes ?? 10; // Default to 10 minutes if not set
        $minimumWaitTime = now()->subMinutes($rateLimitMinutes);

        // Skip if the most recent data was created within the rate limit window
        return $mostRecentData->created_at > $minimumWaitTime;
    }
}
