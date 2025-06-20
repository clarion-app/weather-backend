<?php

namespace ClarionApp\Weather\Controllers;

use ClarionApp\Weather\Models\WeatherLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WeatherLocationController extends Controller
{
    /**
     * Display a listing of weather locations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WeatherLocation::query();

        // Filter by active status if provided
        if ($request->has('active')) {
            $isActive = filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Filter by favorite status if provided
        if ($request->has('favorite')) {
            $isFavorite = filter_var($request->get('favorite'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_favorite', $isFavorite);
        }

        // Filter by country code if provided
        if ($request->has('country')) {
            $query->byCountry($request->get('country'));
        }

        // Search functionality
        if ($request->has('search')) {
            $query->search($request->get('search'));
        }

        // Include soft deleted records if requested
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $locations = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $locations,
            'message' => 'Weather locations retrieved successfully'
        ]);
    }

    /**
     * Store a newly created weather location.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'country_code' => 'required|string|size:2',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'timezone' => 'nullable|string|max:255',
            'units' => 'nullable|in:metric,imperial',
            'geocoding_data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        // Check for duplicate location
        $existingLocation = WeatherLocation::where('latitude', $request->latitude)
            ->where('longitude', $request->longitude)
            ->first();

        if ($existingLocation) {
            return response()->json([
                'message' => 'A location with these coordinates already exists',
                'existing_location' => $existingLocation
            ], 409);
        }

        $location = WeatherLocation::create($validator->validated());

        return response()->json([
            'data' => $location,
            'message' => 'Weather location created successfully'
        ], 201);
    }

    /**
     * Display the specified weather location.
     */
    public function show(string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        return response()->json([
            'data' => $location,
            'message' => 'Weather location retrieved successfully'
        ]);
    }

    /**
     * Update the specified weather location.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'sometimes|required|string|max:255',
            'country_code' => 'sometimes|required|string|size:2',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'timezone' => 'nullable|string|max:255',
            'units' => 'nullable|in:metric,imperial',
            'geocoding_data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $location->update($validator->validated());

        return response()->json([
            'data' => $location->fresh(),
            'message' => 'Weather location updated successfully'
        ]);
    }

    /**
     * Remove the specified weather location (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        $location->delete();

        return response()->json([
            'message' => 'Weather location deleted successfully'
        ]);
    }

    /**
     * Add location to favorites.
     */
    public function addToFavorites(string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        $location->addToFavorites();

        return response()->json([
            'data' => $location->fresh(),
            'message' => 'Location added to favorites successfully'
        ]);
    }

    /**
     * Remove location from favorites.
     */
    public function removeFromFavorites(string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        $location->removeFromFavorites();

        return response()->json([
            'data' => $location->fresh(),
            'message' => 'Location removed from favorites successfully'
        ]);
    }

    /**
     * Activate a weather location.
     */
    public function activate(string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        $location->activate();

        return response()->json([
            'data' => $location->fresh(),
            'message' => 'Weather location activated successfully'
        ]);
    }

    /**
     * Deactivate a weather location.
     */
    public function deactivate(string $id): JsonResponse
    {
        $location = WeatherLocation::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Weather location not found'
            ], 404);
        }

        $location->deactivate();

        return response()->json([
            'data' => $location->fresh(),
            'message' => 'Weather location deactivated successfully'
        ]);
    }

    /**
     * Get only favorite locations.
     */
    public function favorites(): JsonResponse
    {
        $locations = WeatherLocation::favorites()->active()->orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $locations,
            'message' => 'Favorite weather locations retrieved successfully'
        ]);
    }

    /**
     * Get only active locations.
     */
    public function active(): JsonResponse
    {
        $locations = WeatherLocation::active()->orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $locations,
            'message' => 'Active weather locations retrieved successfully'
        ]);
    }

    /**
     * Search locations using OpenWeatherMap Geocoding API.
     */
    public function searchGeocode(Request $request): JsonResponse
    {
        Log::info('Geocoding search request received', [
            'query' => $request->get('query'),
            'limit' => $request->get('limit', 5)
        ]);
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        // Get API key from active weather API configuration
        $weatherApi = \ClarionApp\Weather\Models\WeatherApi::active()->first();
        
        if (!$weatherApi) {
            return response()->json([
                'message' => 'No active weather API configuration found'
            ], 503);
        }

        $query = $request->get('query');
        $limit = $request->get('limit', 5);

        try {
            $response = Http::get('http://api.openweathermap.org/geo/1.0/direct', [
                'q' => $query,
                'limit' => $limit,
                'appid' => $weatherApi->api_key
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Failed to fetch geocoding data',
                    'error' => $response->body()
                ], $response->status());
            }

            $results = $response->json();

            return response()->json([
                'data' => $results,
                'message' => 'Geocoding search completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error occurred while searching locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create location from geocoding result.
     */
    public function createFromGeocode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'geocoding_data' => 'required|array',
            'geocoding_data.name' => 'required|string',
            'geocoding_data.country' => 'required|string',
            'geocoding_data.lat' => 'required|numeric',
            'geocoding_data.lon' => 'required|numeric',
            'custom_name' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $geocodingData = $request->get('geocoding_data');
        $customName = $request->get('custom_name');

        // Check for duplicate location
        $existingLocation = WeatherLocation::where('latitude', $geocodingData['lat'])
            ->where('longitude', $geocodingData['lon'])
            ->first();

        if ($existingLocation) {
            return response()->json([
                'message' => 'A location with these coordinates already exists',
                'existing_location' => $existingLocation
            ], 409);
        }

        try {
            $location = WeatherLocation::createFromGeocoding($geocodingData, $customName);

            return response()->json([
                'data' => $location,
                'message' => 'Weather location created from geocoding data successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error occurred while creating location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find locations within a radius of coordinates.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        $radius = $request->get('radius', 10); // Default 10km radius

        $locations = WeatherLocation::active()
            ->withinRadius($latitude, $longitude, $radius)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'data' => $locations,
            'message' => 'Nearby weather locations retrieved successfully',
            'search_criteria' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_km' => $radius
            ]
        ]);
    }
}
