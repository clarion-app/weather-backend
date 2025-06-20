<?php

namespace ClarionApp\Weather\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use ClarionApp\Weather\Models\WeatherMinutely;
use ClarionApp\Weather\Models\WeatherLocation;
use Carbon\Carbon;

class WeatherMinutelyController extends Controller
{
    /**
     * Display a listing of minutely weather data.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'sometimes|uuid|exists:weather_locations,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after_or_equal:start_time',
            'with_precipitation' => 'sometimes|boolean',
            'precipitation_type' => 'sometimes|in:rain,snow,sleet,hail',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = WeatherMinutely::with('weatherLocation');
        
        // Filter by location
        if ($request->has('location_id')) {
            $query->where('weather_location_id', $request->location_id);
        }

        // Filter by time range
        if ($request->has('start_time') && $request->has('end_time')) {
            $query->timeRange(
                Carbon::parse($request->start_time),
                Carbon::parse($request->end_time)
            );
        } elseif ($request->has('start_time')) {
            $query->where('data_timestamp', '>=', Carbon::parse($request->start_time));
        } elseif ($request->has('end_time')) {
            $query->where('data_timestamp', '<=', Carbon::parse($request->end_time));
        }

        // Filter by precipitation
        if ($request->boolean('with_precipitation')) {
            $query->withPrecipitation();
        }

        // Filter by precipitation type
        if ($request->has('precipitation_type')) {
            $query->byPrecipitationType($request->precipitation_type);
        }

        // Apply limit
        $limit = $request->get('limit', 60); // Default to 1 hour of data
        $minutelyData = $query->orderBy('data_timestamp')->limit($limit)->get();

        return response()->json([
            'data' => $minutelyData->map(function ($item) {
                return $item->getFormattedMinutelyData();
            }),
            'count' => $minutelyData->count(),
        ]);
    }

    /**
     * Store new minutely weather data.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'weather_location_id' => 'required|uuid|exists:weather_locations,id',
            'data_timestamp' => 'required|date',
            'precipitation' => 'required|numeric|min:0',
            'precipitation_type' => 'nullable|in:rain,snow,sleet,hail',
            'precipitation_probability' => 'nullable|numeric|min:0|max:1',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|integer|min:0|max:100',
            'pressure' => 'nullable|numeric',
            'wind_speed' => 'nullable|numeric|min:0',
            'wind_direction' => 'nullable|integer|min:0|max:360',
            'raw_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $minutelyData = WeatherMinutely::create($validator->validated());

            return response()->json([
                'message' => 'Minutely weather data created successfully',
                'data' => $minutelyData->getFormattedMinutelyData()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create minutely weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store multiple minutely weather data points in bulk.
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'weather_location_id' => 'required|uuid|exists:weather_locations,id',
            'data' => 'required|array|min:1|max:61', // Max 61 minutes of data
            'data.*.data_timestamp' => 'required|date',
            'data.*.precipitation' => 'required|numeric|min:0',
            'data.*.precipitation_type' => 'nullable|in:rain,snow,sleet,hail',
            'data.*.precipitation_probability' => 'nullable|numeric|min:0|max:1',
            'data.*.temperature' => 'nullable|numeric',
            'data.*.feels_like' => 'nullable|numeric',
            'data.*.humidity' => 'nullable|integer|min:0|max:100',
            'data.*.visibility' => 'nullable|integer|min:0',
            'data.*.raw_minutely_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $locationId = $request->weather_location_id;
            $minutelyDataPoints = [];
            
            foreach ($request->data as $dataPoint) {
                $dataPoint['weather_location_id'] = $locationId;
                $minutelyDataPoints[] = WeatherMinutely::create($dataPoint);
            }

            return response()->json([
                'message' => 'Bulk minutely weather data created successfully',
                'data' => collect($minutelyDataPoints)->map(function ($item) {
                    return $item->getFormattedMinutelyData();
                }),
                'count' => count($minutelyDataPoints),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create bulk minutely weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified minutely weather data.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $minutelyData = WeatherMinutely::with('weatherLocation')->findOrFail($id);
            
            return response()->json([
                'data' => $minutelyData->getFormattedMinutelyData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Minutely weather data not found'
            ], 404);
        }
    }

    /**
     * Update the specified minutely weather data.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $minutelyData = WeatherMinutely::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Minutely weather data not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'precipitation' => 'sometimes|numeric|min:0',
            'precipitation_type' => 'sometimes|nullable|in:rain,snow,sleet,hail',
            'precipitation_probability' => 'sometimes|nullable|numeric|min:0|max:1',
            'temperature' => 'sometimes|nullable|numeric',
            'feels_like' => 'sometimes|nullable|numeric',
            'humidity' => 'sometimes|nullable|integer|min:0|max:100',
            'visibility' => 'sometimes|nullable|integer|min:0',
            'raw_minutely_data' => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $minutelyData->update($validator->validated());

            return response()->json([
                'message' => 'Minutely weather data updated successfully',
                'data' => $minutelyData->fresh()->getFormattedMinutelyData()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update minutely weather data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified minutely weather data.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $minutelyData = WeatherMinutely::findOrFail($id);
            $minutelyData->delete();

            return response()->json([
                'message' => 'Minutely weather data deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Minutely weather data not found'
            ], 404);
        }
    }

    /**
     * Get next hour precipitation forecast for a location.
     */
    public function nextHour(string $locationId): JsonResponse
    {
        try {
            $location = WeatherLocation::findOrFail($locationId);
            $nextHourData = WeatherMinutely::where('weather_location_id', $locationId)
                ->nextHour()
                ->orderBy('data_timestamp')
                ->get();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'forecast' => $nextHourData->map(function ($item) {
                    return $item->getFormattedMinutelyData();
                }),
                'count' => $nextHourData->count(),
                'precipitation_chart' => WeatherMinutely::getPrecipitationChart($location),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Get precipitation forecast for a specific time range.
     */
    public function precipitation(Request $request, string $locationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after_or_equal:start_time',
            'minutes' => 'sometimes|integer|min:1|max:120', // Max 2 hours
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $location = WeatherLocation::findOrFail($locationId);
            
            $query = WeatherMinutely::where('weather_location_id', $locationId);
            
            if ($request->has('start_time') && $request->has('end_time')) {
                $query->timeRange(
                    Carbon::parse($request->start_time),
                    Carbon::parse($request->end_time)
                );
            } elseif ($request->has('minutes')) {
                $minutes = $request->get('minutes', 60);
                $query->timeRange(now(), now()->addMinutes($minutes));
            } else {
                $query->nextHour();
            }
            
            $precipitationData = $query->orderBy('data_timestamp')->get();
            
            // Calculate summary statistics
            $totalPrecipitation = $precipitationData->sum('precipitation');
            $maxPrecipitation = $precipitationData->max('precipitation');
            $avgProbability = $precipitationData->avg('precipitation_probability');
            $precipitationMinutes = $precipitationData->where('precipitation', '>', 0)->count();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'precipitation_forecast' => $precipitationData->map(function ($item) {
                    return $item->getFormattedMinutelyData();
                }),
                'summary' => [
                    'total_precipitation_mm' => round($totalPrecipitation, 2),
                    'max_precipitation_mm' => round($maxPrecipitation, 2),
                    'avg_probability_percent' => round($avgProbability * 100, 1),
                    'minutes_with_precipitation' => $precipitationMinutes,
                    'total_minutes' => $precipitationData->count(),
                ],
                'chart_data' => WeatherMinutely::getPrecipitationChart($location),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Get recent precipitation data for a location.
     */
    public function recent(string $locationId): JsonResponse
    {
        try {
            $location = WeatherLocation::findOrFail($locationId);
            $recentData = WeatherMinutely::where('weather_location_id', $locationId)
                ->recent()
                ->orderBy('data_timestamp', 'desc')
                ->get();

            // Group by hour for easier analysis
            $hourlyGroups = $recentData->groupBy(function ($item) {
                return $item->data_timestamp->format('Y-m-d H:00');
            });

            $hourlyStats = $hourlyGroups->map(function ($group, $hour) {
                return [
                    'hour' => $hour,
                    'total_precipitation' => $group->sum('precipitation'),
                    'max_precipitation' => $group->max('precipitation'),
                    'avg_probability' => $group->avg('precipitation_probability'),
                    'minutes_with_precipitation' => $group->where('precipitation', '>', 0)->count(),
                    'data_points' => $group->count(),
                ];
            })->values();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'recent_data' => $recentData->map(function ($item) {
                    return $item->getFormattedMinutelyData();
                }),
                'hourly_summary' => $hourlyStats,
                'count' => $recentData->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Clean up old minutely data.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours_old' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $hoursOld = $request->get('hours_old', 6); // Default to 6 hours
        $cutoffTime = now()->subHours($hoursOld);
        
        $deletedCount = WeatherMinutely::where('data_timestamp', '<', $cutoffTime)->count();
        WeatherMinutely::where('data_timestamp', '<', $cutoffTime)->delete();

        return response()->json([
            'message' => 'Minutely weather data cleanup completed',
            'deleted_records' => $deletedCount,
            'cutoff_time' => $cutoffTime->format('Y-m-d H:i:s'),
        ]);
    }
}
