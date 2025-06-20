<?php

namespace ClarionApp\Weather\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use ClarionApp\Weather\Models\WeatherData;
use ClarionApp\Weather\Models\WeatherLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeatherDataController extends Controller
{
    /**
     * Display a listing of weather data.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'sometimes|uuid|exists:weather_locations,id',
            'data_type' => 'sometimes|in:current,hourly,daily,historical',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = WeatherData::with('weatherLocation');
        
        // Filter by location
        if ($request->has('location_id')) {
            $query->where('weather_location_id', $request->location_id);
        }

        // Filter by data type
        if ($request->has('data_type')) {
            $query->where('data_type', $request->data_type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('data_timestamp', '>=', Carbon::parse($request->start_date));
        }
        
        if ($request->has('end_date')) {
            $query->where('data_timestamp', '<=', Carbon::parse($request->end_date));
        }

        // Apply limit
        $limit = $request->get('limit', 100);
        $weatherData = $query->orderBy('data_timestamp', 'desc')->limit($limit)->get();

        return response()->json([
            'data' => $weatherData->map(function ($item) {
                return $item->getFormattedWeatherData();
            }),
            'count' => $weatherData->count(),
        ]);
    }

    /**
     * Store new weather data.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'weather_location_id' => 'required|uuid|exists:weather_locations,id',
            'data_type' => 'required|in:current,hourly,daily,historical',
            'data_timestamp' => 'required|date',
            'temperature' => 'required|numeric',
            'feels_like_temperature' => 'nullable|numeric',
            'pressure' => 'nullable|numeric',
            'humidity' => 'nullable|integer|min:0|max:100',
            'dew_point' => 'nullable|numeric',
            'uvi' => 'nullable|numeric|min:0',
            'clouds' => 'nullable|integer|min:0|max:100',
            'visibility' => 'nullable|integer|min:0',
            'wind_speed' => 'nullable|numeric|min:0',
            'wind_direction' => 'nullable|integer|min:0|max:360',
            'wind_gust' => 'nullable|numeric|min:0',
            'weather_id' => 'nullable|integer',
            'weather_main' => 'nullable|string|max:100',
            'weather_description' => 'nullable|string|max:255',
            'weather_icon' => 'nullable|string|max:10',
            'rain_1h' => 'nullable|numeric|min:0',
            'rain_3h' => 'nullable|numeric|min:0',
            'snow_1h' => 'nullable|numeric|min:0',
            'snow_3h' => 'nullable|numeric|min:0',
            'rain_daily' => 'nullable|numeric|min:0',
            'snow_daily' => 'nullable|numeric|min:0',
            'precipitation_probability' => 'nullable|numeric|min:0|max:1',
            'sunrise' => 'nullable|date',
            'sunset' => 'nullable|date',
            'moonrise' => 'nullable|date',
            'moonset' => 'nullable|date',
            'moon_phase' => 'nullable|numeric|min:0|max:1',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
            'temperature_morning' => 'nullable|numeric',
            'temperature_day' => 'nullable|numeric',
            'temperature_evening' => 'nullable|numeric',
            'temperature_night' => 'nullable|numeric',
            'feels_like_morning' => 'nullable|numeric',
            'feels_like_day' => 'nullable|numeric',
            'feels_like_evening' => 'nullable|numeric',
            'feels_like_night' => 'nullable|numeric',
            'raw_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $weatherData = WeatherData::create($validator->validated());

            return response()->json([
                'message' => 'Weather data created successfully',
                'data' => $weatherData->getFormattedWeatherData()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified weather data.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $weatherData = WeatherData::with('weatherLocation')->findOrFail($id);
            
            return response()->json([
                'data' => $weatherData->getFormattedWeatherData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather data not found'
            ], 404);
        }
    }

    /**
     * Update the specified weather data.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $weatherData = WeatherData::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather data not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'temperature' => 'sometimes|numeric',
            'feels_like_temperature' => 'sometimes|nullable|numeric',
            'pressure' => 'sometimes|nullable|numeric',
            'humidity' => 'sometimes|nullable|integer|min:0|max:100',
            'dew_point' => 'sometimes|nullable|numeric',
            'uvi' => 'sometimes|nullable|numeric|min:0',
            'clouds' => 'sometimes|nullable|integer|min:0|max:100',
            'visibility' => 'sometimes|nullable|integer|min:0',
            'wind_speed' => 'sometimes|nullable|numeric|min:0',
            'wind_direction' => 'sometimes|nullable|integer|min:0|max:360',
            'wind_gust' => 'sometimes|nullable|numeric|min:0',
            'weather_id' => 'sometimes|nullable|integer',
            'weather_main' => 'sometimes|nullable|string|max:100',
            'weather_description' => 'sometimes|nullable|string|max:255',
            'weather_icon' => 'sometimes|nullable|string|max:10',
            'rain_1h' => 'sometimes|nullable|numeric|min:0',
            'rain_3h' => 'sometimes|nullable|numeric|min:0',
            'snow_1h' => 'sometimes|nullable|numeric|min:0',
            'snow_3h' => 'sometimes|nullable|numeric|min:0',
            'rain_daily' => 'sometimes|nullable|numeric|min:0',
            'snow_daily' => 'sometimes|nullable|numeric|min:0',
            'precipitation_probability' => 'sometimes|nullable|numeric|min:0|max:1',
            'raw_data' => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $weatherData->update($validator->validated());

            return response()->json([
                'message' => 'Weather data updated successfully',
                'data' => $weatherData->fresh()->getFormattedWeatherData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified weather data.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $weatherData = WeatherData::findOrFail($id);
            $weatherData->delete();

            return response()->json([
                'message' => 'Weather data deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather data not found'
            ], 404);
        }
    }

    /**
     * Get current weather for a location.
     */
    public function current(string $locationId): JsonResponse
    {
        try {
            $location = WeatherLocation::findOrFail($locationId);
            $currentWeather = WeatherData::where('weather_location_id', $locationId)
                ->current()
                ->latest('data_timestamp')
                ->first();

            if (!$currentWeather) {
                return response()->json([
                    'error' => 'No current weather data found for this location'
                ], 404);
            }

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'weather' => $currentWeather->getFormattedWeatherData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Location not found'
            ], 404);
        }
    }

    /**
     * Get hourly forecast for a location.
     */
    public function hourly(Request $request, string $locationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'sometimes|integer|min:1|max:48',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($locationId);
            $hours = $request->get('hours', 24);
            
            $hourlyWeather = WeatherData::where('weather_location_id', $locationId)
                ->hourly()
                ->where('data_timestamp', '>=', now())
                ->orderBy('data_timestamp')
                ->limit($hours)
                ->get();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'forecast' => $hourlyWeather->map(function ($item) {
                    return $item->getFormattedWeatherData();
                }),
                'count' => $hourlyWeather->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Get daily forecast for a location.
     */
    public function daily(Request $request, string $locationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'sometimes|integer|min:1|max:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($locationId);
            $days = $request->get('days', 7);
            
            $dailyWeather = WeatherData::where('weather_location_id', $locationId)
                ->daily()
                ->where('data_timestamp', '>=', now()->startOfDay())
                ->orderBy('data_timestamp')
                ->limit($days)
                ->get();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'forecast' => $dailyWeather->map(function ($item) {
                    return $item->getFormattedWeatherData();
                }),
                'count' => $dailyWeather->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Get historical weather data for a location.
     */
    public function historical(Request $request, string $locationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($locationId);
            
            $historicalWeather = WeatherData::where('weather_location_id', $locationId)
                ->historical()
                ->whereBetween('data_timestamp', [$request->start_date, $request->end_date])
                ->orderBy('data_timestamp')
                ->get();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'historical_data' => $historicalWeather->map(function ($item) {
                    return $item->getFormattedWeatherData();
                }),
                'count' => $historicalWeather->count(),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Clean up old weather data.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_old' => 'sometimes|integer|min:1',
            'data_type' => 'sometimes|in:current,hourly,daily,historical',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $daysOld = $request->get('days_old', 30);
        $cutoffDate = now()->subDays($daysOld);
        
        $query = WeatherData::where('data_timestamp', '<', $cutoffDate);
        
        if ($request->has('data_type')) {
            $query->where('data_type', $request->data_type);
        }

        $deletedCount = $query->count();
        $query->delete();

        return response()->json([
            'message' => 'Weather data cleanup completed',
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
        ]);
    }
}
