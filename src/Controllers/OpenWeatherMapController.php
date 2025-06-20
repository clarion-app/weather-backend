<?php

namespace ClarionApp\Weather\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use ClarionApp\Weather\Models\WeatherApi;
use ClarionApp\Weather\Models\WeatherLocation;
use ClarionApp\Weather\Models\WeatherData;
use ClarionApp\Weather\Models\WeatherAlert;
use ClarionApp\Weather\Models\WeatherMinutely;
use Carbon\Carbon;

class OpenWeatherMapController extends Controller
{
    /**
     * Fetch current weather data from OpenWeatherMap API.
     */
    public function fetchCurrent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|uuid|exists:weather_locations,id',
            'api_id' => 'sometimes|uuid|exists:weather_apis,id',
            'units' => 'sometimes|in:standard,metric,imperial',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($request->location_id);
            $api = $this->getWeatherApi($request->get('api_id'));
            $units = $request->get('units', 'metric'); // Default to metric (Celsius)
            
            // Check rate limiting
            if (!$this->checkRateLimit($api)) {
                return response()->json([
                    'error' => 'API rate limit exceeded',
                    'retry_after' => $this->getRateLimitRetryAfter($api)
                ], 429);
            }

            $response = Http::timeout(30)->get('https://api.openweathermap.org/data/3.0/onecall', [
                'lat' => $location->latitude,
                'lon' => $location->longitude,
                'appid' => $api->api_key,
                'exclude' => 'minutely,hourly,daily,alerts',
                'units' => $units,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Failed to fetch weather data',
                    'message' => $response->body()
                ], $response->status());
            }

            $data = $response->json();
            $currentData = $data['current'];

            // Store in database
            $weatherData = WeatherData::create([
                'weather_location_id' => $location->id,
                'data_type' => 'current',
                'data_timestamp' => Carbon::createFromTimestamp($currentData['dt'], 'UTC'),
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
                'units' => $units,
                'raw_data' => $currentData,
            ]);

            // Update rate limit cache
            $this->updateRateLimit($api);

            return response()->json([
                'message' => 'Current weather data fetched successfully',
                'location' => $location->getFormattedLocation(),
                'weather' => $weatherData->getFormattedWeatherData(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch current weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch complete weather data (current, hourly, daily, alerts) from OpenWeatherMap API.
     */
    public function fetchComplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|uuid|exists:weather_locations,id',
            'api_id' => 'sometimes|uuid|exists:weather_apis,id',
            'units' => 'sometimes|in:standard,metric,imperial',
            'exclude' => 'sometimes|array',
            'exclude.*' => 'in:minutely,hourly,daily,alerts',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($request->location_id);
            $api = $this->getWeatherApi($request->get('api_id'));
            $units = $request->get('units', 'metric'); // Default to metric (Celsius)
            
            // Check rate limiting
            if (!$this->checkRateLimit($api)) {
                return response()->json([
                    'error' => 'API rate limit exceeded',
                    'retry_after' => $this->getRateLimitRetryAfter($api)
                ], 429);
            }

            $exclude = $request->get('exclude', []);
            $excludeString = implode(',', $exclude);

            $response = Http::timeout(30)->get('https://api.openweathermap.org/data/3.0/onecall', [
                'lat' => $location->latitude,
                'lon' => $location->longitude,
                'appid' => $api->api_key,
                'exclude' => $excludeString,
                'units' => $units,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Failed to fetch weather data',
                    'message' => $response->body()
                ], $response->status());
            }

            $data = $response->json();
            $results = [];

            // Process current weather
            if (isset($data['current'])) {
                $results['current'] = $this->processCurrentWeather($location, $data['current'], $units);
            }

            // Process hourly forecast
            if (isset($data['hourly'])) {
                $results['hourly'] = $this->processHourlyWeather($location, $data['hourly'], $units);
            }

            // Process daily forecast
            if (isset($data['daily'])) {
                $results['daily'] = $this->processDailyWeather($location, $data['daily'], $units);
            }

            // Process minutely precipitation
            if (isset($data['minutely'])) {
                $results['minutely'] = $this->processMinutelyWeather($location, $data['minutely'], $units);
            }

            // Process alerts
            if (isset($data['alerts'])) {
                $results['alerts'] = $this->processWeatherAlerts($location, $data['alerts']);
            }

            // Update rate limit cache
            $this->updateRateLimit($api);

            return response()->json([
                'message' => 'Complete weather data fetched successfully',
                'location' => $location->getFormattedLocation(),
                'data' => $results,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch complete weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch historical weather data from OpenWeatherMap API.
     */
    public function fetchHistorical(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|uuid|exists:weather_locations,id',
            'api_id' => 'sometimes|uuid|exists:weather_apis,id',
            'units' => 'sometimes|in:standard,metric,imperial',
            'dt' => 'required|integer|min:1', // Unix timestamp
            'type' => 'sometimes|in:hour,day',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($request->location_id);
            $api = $this->getWeatherApi($request->get('api_id'));
            $units = $request->get('units', 'metric'); // Default to metric (Celsius)
            
            // Check rate limiting
            if (!$this->checkRateLimit($api)) {
                return response()->json([
                    'error' => 'API rate limit exceeded',
                    'retry_after' => $this->getRateLimitRetryAfter($api)
                ], 429);
            }

            $type = $request->get('type', 'hour');
            $endpoint = $type === 'day' ? 'onecall/day_summary' : 'onecall/timemachine';

            $params = [
                'lat' => $location->latitude,
                'lon' => $location->longitude,
                'appid' => $api->api_key,
                'dt' => $request->dt,
                'units' => $units,
            ];

            $response = Http::timeout(30)->get("https://api.openweathermap.org/data/3.0/{$endpoint}", $params);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Failed to fetch historical weather data',
                    'message' => $response->body()
                ], $response->status());
            }

            $data = $response->json();
            $results = [];

            if ($type === 'hour' && isset($data['data'])) {
                foreach ($data['data'] as $hourlyData) {
                    $results[] = $this->processHistoricalWeather($location, $hourlyData, $units);
                }
            } elseif ($type === 'day') {
                $results[] = $this->processHistoricalDayWeather($location, $data, $units);
            }

            // Update rate limit cache
            $this->updateRateLimit($api);

            return response()->json([
                'message' => 'Historical weather data fetched successfully',
                'location' => $location->getFormattedLocation(),
                'type' => $type,
                'data' => $results,
                'count' => count($results),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch historical weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the appropriate weather API configuration.
     */
    private function getWeatherApi(?string $apiId = null): WeatherApi
    {
        if ($apiId) {
            return WeatherApi::findOrFail($apiId);
        }

        // Get the first active API
        $api = WeatherApi::where('is_active', true)->first();
        
        if (!$api) {
            throw new \Exception('No active weather API configuration found');
        }

        return $api;
    }

    /**
     * Check if API rate limit allows new requests.
     */
    private function checkRateLimit(WeatherApi $api): bool
    {
        $cacheKey = "weather_api_rate_limit_{$api->id}";
        $lastCall = Cache::get($cacheKey);
        
        if (!$lastCall) {
            return true;
        }

        $rateLimitMinutes = $api->rate_limit_minutes ?? 10;
        return now()->diffInMinutes($lastCall) >= $rateLimitMinutes;
    }

    /**
     * Get the retry after time for rate limiting.
     */
    private function getRateLimitRetryAfter(WeatherApi $api): int
    {
        $cacheKey = "weather_api_rate_limit_{$api->id}";
        $lastCall = Cache::get($cacheKey);
        
        if (!$lastCall) {
            return 0;
        }

        $rateLimitMinutes = $api->rate_limit_minutes ?? 10;
        $remainingMinutes = $rateLimitMinutes - now()->diffInMinutes($lastCall);
        
        return max(0, $remainingMinutes * 60); // Return seconds
    }

    /**
     * Update the rate limit cache.
     */
    private function updateRateLimit(WeatherApi $api): void
    {
        $cacheKey = "weather_api_rate_limit_{$api->id}";
        $rateLimitMinutes = $api->rate_limit_minutes ?? 10;
        Cache::put($cacheKey, now(), now()->addMinutes($rateLimitMinutes));
    }

    /**
     * Process current weather data and store in database.
     */
    private function processCurrentWeather(WeatherLocation $location, array $data, string $units = 'metric'): array
    {
        $weatherData = WeatherData::create([
            'weather_location_id' => $location->id,
            'data_type' => 'current',
            'data_timestamp' => Carbon::createFromTimestamp($data['dt'], 'UTC'),
            'temperature' => $data['temp'],
            'feels_like_temperature' => $data['feels_like'],
            'pressure' => $data['pressure'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'dew_point' => $data['dew_point'] ?? null,
            'uvi' => $data['uvi'] ?? null,
            'clouds' => $data['clouds'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'wind_speed' => $data['wind_speed'] ?? null,
            'wind_direction' => $data['wind_deg'] ?? null,
            'wind_gust' => $data['wind_gust'] ?? null,
            'weather_id' => $data['weather'][0]['id'] ?? null,
            'weather_main' => $data['weather'][0]['main'] ?? null,
            'weather_description' => $data['weather'][0]['description'] ?? null,
            'weather_icon' => $data['weather'][0]['icon'] ?? null,
            'sunrise' => isset($data['sunrise']) ? Carbon::createFromTimestamp($data['sunrise'], 'UTC') : null,
            'sunset' => isset($data['sunset']) ? Carbon::createFromTimestamp($data['sunset'], 'UTC') : null,
            'units' => $units,
            'raw_data' => $data,
        ]);

        return $weatherData->getFormattedWeatherData();
    }

    /**
     * Process hourly weather data and store in database.
     */
    private function processHourlyWeather(WeatherLocation $location, array $hourlyData, string $units = 'metric'): array
    {
        $results = [];
        
        foreach ($hourlyData as $hour) {
            $weatherData = WeatherData::create([
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
                'weather_id' => $hour['weather'][0]['id'] ?? null,
                'weather_main' => $hour['weather'][0]['main'] ?? null,
                'weather_description' => $hour['weather'][0]['description'] ?? null,
                'weather_icon' => $hour['weather'][0]['icon'] ?? null,
                'precipitation_probability' => $hour['pop'] ?? null,
                'rain_1h' => $hour['rain']['1h'] ?? null,
                'snow_1h' => $hour['snow']['1h'] ?? null,
                'units' => $units,
                'raw_data' => $hour,
            ]);
            
            $results[] = $weatherData->getFormattedWeatherData();
        }
        
        return $results;
    }

    /**
     * Process daily weather data and store in database.
     */
    private function processDailyWeather(WeatherLocation $location, array $dailyData, string $units = 'metric'): array
    {
        $results = [];
        
        foreach ($dailyData as $day) {
            $weatherData = WeatherData::create([
                'weather_location_id' => $location->id,
                'data_type' => 'daily',
                'data_timestamp' => Carbon::createFromTimestamp($day['dt'], 'UTC'),
                'temperature_min' => $day['temp']['min'] ?? null,
                'temperature_max' => $day['temp']['max'] ?? null,
                'temperature_morning' => $day['temp']['morn'] ?? null,
                'temperature_day' => $day['temp']['day'] ?? null,
                'temperature_evening' => $day['temp']['eve'] ?? null,
                'temperature_night' => $day['temp']['night'] ?? null,
                'feels_like_morning' => $day['feels_like']['morn'] ?? null,
                'feels_like_day' => $day['feels_like']['day'] ?? null,
                'feels_like_evening' => $day['feels_like']['eve'] ?? null,
                'feels_like_night' => $day['feels_like']['night'] ?? null,
                'pressure' => $day['pressure'] ?? null,
                'humidity' => $day['humidity'] ?? null,
                'dew_point' => $day['dew_point'] ?? null,
                'uvi' => $day['uvi'] ?? null,
                'clouds' => $day['clouds'] ?? null,
                'wind_speed' => $day['wind_speed'] ?? null,
                'wind_direction' => $day['wind_deg'] ?? null,
                'wind_gust' => $day['wind_gust'] ?? null,
                'weather_id' => $day['weather'][0]['id'] ?? null,
                'weather_main' => $day['weather'][0]['main'] ?? null,
                'weather_description' => $day['weather'][0]['description'] ?? null,
                'weather_icon' => $day['weather'][0]['icon'] ?? null,
                'precipitation_probability' => $day['pop'] ?? null,
                'rain_daily' => $day['rain'] ?? null,
                'snow_daily' => $day['snow'] ?? null,
                'sunrise' => isset($day['sunrise']) ? Carbon::createFromTimestamp($day['sunrise'], 'UTC') : null,
                'sunset' => isset($day['sunset']) ? Carbon::createFromTimestamp($day['sunset'], 'UTC') : null,
                'moonrise' => isset($day['moonrise']) ? Carbon::createFromTimestamp($day['moonrise'], 'UTC') : null,
                'moonset' => isset($day['moonset']) ? Carbon::createFromTimestamp($day['moonset'], 'UTC') : null,
                'moon_phase' => $day['moon_phase'] ?? null,
                'units' => $units,
                'raw_data' => $day,
            ]);
            
            $results[] = $weatherData->getFormattedWeatherData();
        }
        
        return $results;
    }

    /**
     * Process minutely weather data and store in database.
     */
    private function processMinutelyWeather(WeatherLocation $location, array $minutelyData, string $units = 'metric'): array
    {
        $results = [];
        
        foreach ($minutelyData as $minute) {
            $minutelyWeather = WeatherMinutely::create([
                'weather_location_id' => $location->id,
                'data_timestamp' => Carbon::createFromTimestamp($minute['dt'], 'UTC'),
                'precipitation' => $minute['precipitation'] ?? 0,
                'units' => $units,
                'raw_data' => $minute,
            ]);
            
            $results[] = $minutelyWeather->getFormattedMinutelyData();
        }
        
        return $results;
    }

    /**
     * Process weather alerts and store in database.
     */
    private function processWeatherAlerts(WeatherLocation $location, array $alertsData): array
    {
        $results = [];
        
        foreach ($alertsData as $alert) {
            $weatherAlert = WeatherAlert::create([
                'weather_location_id' => $location->id,
                'sender_name' => $alert['sender_name'] ?? 'OpenWeatherMap',
                'event' => $alert['event'],
                'start_time' => Carbon::createFromTimestamp($alert['start'], 'UTC'),
                'end_time' => Carbon::createFromTimestamp($alert['end'], 'UTC'),
                'description' => $alert['description'],
                'tags' => $alert['tags'] ?? [],
                'raw_data' => $alert,
                'is_active' => true,
            ]);
            
            $results[] = $weatherAlert->getFormattedAlert();
        }
        
        return $results;
    }

    /**
     * Process historical weather data and store in database.
     */
    private function processHistoricalWeather(WeatherLocation $location, array $data, string $units = 'metric'): array
    {
        $weatherData = WeatherData::create([
            'weather_location_id' => $location->id,
            'data_type' => 'historical',
            'data_timestamp' => Carbon::createFromTimestamp($data['dt'], 'UTC'),
            'temperature' => $data['temp'],
            'feels_like_temperature' => $data['feels_like'],
            'pressure' => $data['pressure'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'dew_point' => $data['dew_point'] ?? null,
            'uvi' => $data['uvi'] ?? null,
            'clouds' => $data['clouds'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'wind_speed' => $data['wind_speed'] ?? null,
            'wind_direction' => $data['wind_deg'] ?? null,
            'wind_gust' => $data['wind_gust'] ?? null,
            'weather_id' => $data['weather'][0]['id'] ?? null,
            'weather_main' => $data['weather'][0]['main'] ?? null,
            'weather_description' => $data['weather'][0]['description'] ?? null,
            'weather_icon' => $data['weather'][0]['icon'] ?? null,
            'sunrise' => isset($data['sunrise']) ? Carbon::createFromTimestamp($data['sunrise'], 'UTC') : null,
            'sunset' => isset($data['sunset']) ? Carbon::createFromTimestamp($data['sunset'], 'UTC') : null,
            'units' => $units,
            'raw_data' => $data,
        ]);

        return $weatherData->getFormattedWeatherData();
    }

    /**
     * Process historical day summary weather data and store in database.
     */
    private function processHistoricalDayWeather(WeatherLocation $location, array $data, string $units = 'metric'): array
    {
        $weatherData = WeatherData::create([
            'weather_location_id' => $location->id,
            'data_type' => 'historical',
            'data_timestamp' => Carbon::createFromTimestamp($data['date'], 'UTC'),
            'temperature_min' => $data['temperature']['min'] ?? null,
            'temperature_max' => $data['temperature']['max'] ?? null,
            'temperature_morning' => $data['temperature']['morning'] ?? null,
            'temperature_day' => $data['temperature']['afternoon'] ?? null,
            'temperature_evening' => $data['temperature']['evening'] ?? null,
            'temperature_night' => $data['temperature']['night'] ?? null,
            'humidity' => $data['humidity']['afternoon'] ?? null,
            'pressure' => $data['pressure']['afternoon'] ?? null,
            'wind_speed' => $data['wind']['max']['speed'] ?? null,
            'clouds' => $data['cloud_cover']['afternoon'] ?? null,
            'precipitation_1h' => $data['precipitation']['total'] ?? null,
            'units' => $units,
            'raw_data' => $data,
        ]);

        return $weatherData->getFormattedWeatherData();
    }
}
