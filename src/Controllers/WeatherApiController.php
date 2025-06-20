<?php

namespace ClarionApp\Weather\Controllers;

use ClarionApp\Weather\Models\WeatherApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WeatherApiController extends Controller
{
    /**
     * Display a listing of weather APIs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WeatherApi::query();

        // Filter by active status if provided
        if ($request->has('active')) {
            $isActive = filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Include soft deleted records if requested
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $weatherApis = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $weatherApis,
            'message' => 'Weather APIs retrieved successfully'
        ]);
    }

    /**
     * Store a newly created weather API.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'rate_limit_minutes' => 'nullable|integer|min:1|max:1440'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $weatherApi = WeatherApi::create($validator->validated());

        return response()->json([
            'data' => $weatherApi,
            'message' => 'Weather API created successfully'
        ], 201);
    }

    /**
     * Display the specified weather API.
     */
    public function show(string $id): JsonResponse
    {
        $weatherApi = WeatherApi::find($id);

        if (!$weatherApi) {
            return response()->json([
                'message' => 'Weather API not found'
            ], 404);
        }

        return response()->json([
            'data' => $weatherApi,
            'message' => 'Weather API retrieved successfully'
        ]);
    }

    /**
     * Update the specified weather API.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $weatherApi = WeatherApi::find($id);

        if (!$weatherApi) {
            return response()->json([
                'message' => 'Weather API not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'sometimes|required|url|max:255',
            'api_key' => 'sometimes|required|string|max:255',
            'name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'rate_limit_minutes' => 'nullable|integer|min:1|max:1440'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $weatherApi->update($validator->validated());

        return response()->json([
            'data' => $weatherApi->fresh(),
            'message' => 'Weather API updated successfully'
        ]);
    }

    /**
     * Remove the specified weather API (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $weatherApi = WeatherApi::find($id);

        if (!$weatherApi) {
            return response()->json([
                'message' => 'Weather API not found'
            ], 404);
        }

        $weatherApi->delete();

        return response()->json([
            'message' => 'Weather API deleted successfully'
        ]);
    }

    /**
     * Activate a weather API.
     */
    public function activate(string $id): JsonResponse
    {
        $weatherApi = WeatherApi::find($id);

        if (!$weatherApi) {
            return response()->json([
                'message' => 'Weather API not found'
            ], 404);
        }

        $weatherApi->activate();

        return response()->json([
            'data' => $weatherApi->fresh(),
            'message' => 'Weather API activated successfully'
        ]);
    }

    /**
     * Deactivate a weather API.
     */
    public function deactivate(string $id): JsonResponse
    {
        $weatherApi = WeatherApi::find($id);

        if (!$weatherApi) {
            return response()->json([
                'message' => 'Weather API not found'
            ], 404);
        }

        $weatherApi->deactivate();

        return response()->json([
            'data' => $weatherApi->fresh(),
            'message' => 'Weather API deactivated successfully'
        ]);
    }

    /**
     * Get only active weather APIs.
     */
    public function active(): JsonResponse
    {
        $weatherApis = WeatherApi::active()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $weatherApis,
            'message' => 'Active weather APIs retrieved successfully'
        ]);
    }
}
