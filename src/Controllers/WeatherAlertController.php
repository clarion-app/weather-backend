<?php

namespace ClarionApp\Weather\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use ClarionApp\Weather\Models\WeatherAlert;
use ClarionApp\Weather\Models\WeatherLocation;
use Carbon\Carbon;

class WeatherAlertController extends Controller
{
    /**
     * Display a listing of weather alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'sometimes|uuid|exists:weather_locations,id',
            'severity' => 'sometimes|in:Minor,Moderate,Severe,Extreme',
            'event' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'acknowledged' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = WeatherAlert::with('weatherLocation');
        
        // Filter by location
        if ($request->has('location_id')) {
            $query->where('weather_location_id', $request->location_id);
        }

        // Filter by severity
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by event type
        if ($request->has('event')) {
            $query->where('event', 'like', '%' . $request->event . '%');
        }

        // Filter by active status
        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Filter by acknowledgment status
        if ($request->has('acknowledged')) {
            if ($request->boolean('acknowledged')) {
                $query->whereNotNull('acknowledged_at');
            } else {
                $query->unacknowledged();
            }
        }

        // Apply limit
        $limit = $request->get('limit', 100);
        $alerts = $query->orderBy('start_time', 'desc')->limit($limit)->get();

        return response()->json([
            'data' => $alerts->map(function ($alert) {
                return $alert->getFormattedAlert();
            }),
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Store a new weather alert.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'weather_location_id' => 'required|uuid|exists:weather_locations,id',
            'sender_name' => 'required|string|max:255',
            'event' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'description' => 'required|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'severity' => 'required|in:minor,moderate,severe,extreme,unknown',
            'urgency' => 'required|in:immediate,expected,future,past,unknown',
            'certainty' => 'required|in:observed,likely,possible,unlikely,unknown',
            'affected_areas' => 'nullable|array',
            'raw_data' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $alertData = $validator->validated();
            $alertData['is_active'] = $alertData['is_active'] ?? true;
            
            $alert = WeatherAlert::create($alertData);

            return response()->json([
                'message' => 'Weather alert created successfully',
                'data' => $alert->getFormattedAlert()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create weather alert',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified weather alert.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $alert = WeatherAlert::with('weatherLocation')->findOrFail($id);
            
            return response()->json([
                'data' => $alert->getFormattedAlert()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather alert not found'
            ], 404);
        }
    }

    /**
     * Update the specified weather alert.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $alert = WeatherAlert::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather alert not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'sender_name' => 'sometimes|string|max:255',
            'event' => 'sometimes|string|max:255',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'description' => 'sometimes|string',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:100',
            'severity' => 'sometimes|in:minor,moderate,severe,extreme,unknown',
            'urgency' => 'sometimes|in:immediate,expected,future,past,unknown',
            'certainty' => 'sometimes|in:observed,likely,possible,unlikely,unknown',
            'affected_areas' => 'sometimes|nullable|array',
            'raw_data' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $alert->update($validator->validated());

            return response()->json([
                'message' => 'Weather alert updated successfully',
                'data' => $alert->fresh()->getFormattedAlert()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update weather alert',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified weather alert.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $alert = WeatherAlert::findOrFail($id);
            $alert->delete();

            return response()->json([
                'message' => 'Weather alert deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather alert not found'
            ], 404);
        }
    }

    /**
     * Get active alerts for a location.
     */
    public function active(string $locationId): JsonResponse
    {
        try {
            $location = WeatherLocation::findOrFail($locationId);
            $activeAlerts = WeatherAlert::where('weather_location_id', $locationId)
                ->active()
                ->orderBy('severity')
                ->get();

            return response()->json([
                'location' => $location->getFormattedLocation(),
                'alerts' => $activeAlerts->map(function ($alert) {
                    return $alert->getFormattedAlert();
                }),
                'count' => $activeAlerts->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Weather location not found'
            ], 404);
        }
    }

    /**
     * Get alerts by severity level.
     */
    public function bySeverity(Request $request, string $severity): JsonResponse
    {
        if (!in_array($severity, ['Minor', 'Moderate', 'Severe', 'Extreme'])) {
            return response()->json([
                'error' => 'Invalid severity level'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'location_id' => 'sometimes|uuid|exists:weather_locations,id',
            'is_active' => 'sometimes|boolean',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = WeatherAlert::with('weatherLocation')->bySeverity($severity);
        
        if ($request->has('location_id')) {
            $query->where('weather_location_id', $request->location_id);
        }

        if ($request->has('is_active') && $request->boolean('is_active')) {
            $query->active();
        }

        $limit = $request->get('limit', 100);
        $alerts = $query->orderBy('start_time', 'desc')->limit($limit)->get();

        return response()->json([
            'severity' => $severity,
            'alerts' => $alerts->map(function ($alert) {
                return $alert->getFormattedAlert();
            }),
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledge(string $id): JsonResponse
    {
        try {
            $alert = WeatherAlert::findOrFail($id);
            $alert->acknowledge();

            return response()->json([
                'message' => 'Weather alert acknowledged successfully',
                'data' => $alert->fresh()->getFormattedAlert()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather alert not found'
            ], 404);
        }
    }

    /**
     * Resolve an alert.
     */
    public function resolve(string $id): JsonResponse
    {
        try {
            $alert = WeatherAlert::findOrFail($id);
            $alert->resolve();

            return response()->json([
                'message' => 'Weather alert resolved successfully',
                'data' => $alert->fresh()->getFormattedAlert()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Weather alert not found'
            ], 404);
        }
    }

    /**
     * Get alert statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'sometimes|uuid|exists:weather_locations,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = WeatherAlert::query();
        
        if ($request->has('location_id')) {
            $query->where('weather_location_id', $request->location_id);
        }

        if ($request->has('start_date')) {
            $query->where('start_time', '>=', Carbon::parse($request->start_date));
        }
        
        if ($request->has('end_date')) {
            $query->where('start_time', '<=', Carbon::parse($request->end_date));
        }

        $totalAlerts = $query->count();
        $activeAlerts = $query->clone()->active()->count();
        $acknowledgedAlerts = $query->clone()->whereNotNull('acknowledged_at')->count();
        $resolvedAlerts = $query->clone()->whereNotNull('resolved_at')->count();

        // Count by severity
        $severityStats = [
            'Minor' => $query->clone()->bySeverity('Minor')->count(),
            'Moderate' => $query->clone()->bySeverity('Moderate')->count(),
            'Severe' => $query->clone()->bySeverity('Severe')->count(),
            'Extreme' => $query->clone()->bySeverity('Extreme')->count(),
        ];

        // Count by event type
        $eventStats = $query->clone()
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->pluck('count', 'event')
            ->toArray();

        return response()->json([
            'statistics' => [
                'total_alerts' => $totalAlerts,
                'active_alerts' => $activeAlerts,
                'acknowledged_alerts' => $acknowledgedAlerts,
                'resolved_alerts' => $resolvedAlerts,
                'by_severity' => $severityStats,
                'by_event_type' => $eventStats,
            ],
            'filters' => $request->only(['location_id', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Clean up old resolved alerts.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_old' => 'sometimes|integer|min:1',
            'resolved_only' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $daysOld = $request->get('days_old', 30);
        $cutoffDate = now()->subDays($daysOld);
        
        $query = WeatherAlert::where('end_time', '<', $cutoffDate);
        
        if ($request->boolean('resolved_only', true)) {
            $query->whereNotNull('resolved_at');
        }

        $deletedCount = $query->count();
        $query->delete();

        return response()->json([
            'message' => 'Weather alerts cleanup completed',
            'deleted_alerts' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
        ]);
    }
}
