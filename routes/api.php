<?php

use Illuminate\Support\Facades\Route;
use ClarionApp\Weather\Controllers\WeatherApiController;
use ClarionApp\Weather\Controllers\WeatherLocationController;
use ClarionApp\Weather\Controllers\WeatherDataController;
use ClarionApp\Weather\Controllers\WeatherAlertController;
use ClarionApp\Weather\Controllers\WeatherMinutelyController;
use ClarionApp\Weather\Controllers\OpenWeatherMapController;

Route::group(['middleware'=>['auth:api'], 'prefix'=>$this->routePrefix ], function () {
    // Weather API management routes
    Route::apiResource('weather-apis', WeatherApiController::class);
    
    // Additional weather API routes
    Route::prefix('weather-apis')->name('weather-apis.')->group(function () {
        Route::patch('{id}/activate', [WeatherApiController::class, 'activate'])->name('activate');
        Route::patch('{id}/deactivate', [WeatherApiController::class, 'deactivate'])->name('deactivate');
        Route::get('active', [WeatherApiController::class, 'active'])->name('active');
    });

    // Weather Location management routes
    // Note: specific routes must come before apiResource to avoid conflicts with {id} parameter
    Route::prefix('locations')->name('locations.')->group(function () {
        // Routes that don't follow the {id} pattern must come first
        Route::get('active', [WeatherLocationController::class, 'active'])->name('active');
        Route::get('favorites', [WeatherLocationController::class, 'favorites'])->name('favorites');
        Route::get('nearby', [WeatherLocationController::class, 'nearby'])->name('nearby');
        Route::get('search-geocode', [WeatherLocationController::class, 'searchGeocode'])->name('search-geocode');
        Route::post('from-geocode', [WeatherLocationController::class, 'createFromGeocode'])->name('from-geocode');
        
        // Routes with {id} parameter
        Route::patch('{id}/activate', [WeatherLocationController::class, 'activate'])->name('activate');
        Route::patch('{id}/deactivate', [WeatherLocationController::class, 'deactivate'])->name('deactivate');
        Route::patch('{id}/favorite', [WeatherLocationController::class, 'addToFavorites'])->name('add-favorite');
        Route::delete('{id}/favorite', [WeatherLocationController::class, 'removeFromFavorites'])->name('remove-favorite');
    });
    
    // Standard REST routes (these include the show route with {id} parameter)
    Route::apiResource('locations', WeatherLocationController::class);

    // Weather Data management routes
    Route::prefix('weather-data')->name('weather-data.')->group(function () {
        // Special endpoints that don't follow the {id} pattern
        Route::post('cleanup', [WeatherDataController::class, 'cleanup'])->name('cleanup');
        Route::get('{locationId}/current', [WeatherDataController::class, 'current'])->name('current');
        Route::get('{locationId}/hourly', [WeatherDataController::class, 'hourly'])->name('hourly');
        Route::get('{locationId}/daily', [WeatherDataController::class, 'daily'])->name('daily');
        Route::get('{locationId}/historical', [WeatherDataController::class, 'historical'])->name('historical');
    });
    
    // Standard REST routes for weather data
    Route::apiResource('weather-data', WeatherDataController::class);

    // Weather Alerts management routes
    Route::prefix('weather-alerts')->name('weather-alerts.')->group(function () {
        // Special endpoints that don't follow the {id} pattern
        Route::post('cleanup', [WeatherAlertController::class, 'cleanup'])->name('cleanup');
        Route::get('statistics', [WeatherAlertController::class, 'statistics'])->name('statistics');
        Route::get('severity/{severity}', [WeatherAlertController::class, 'bySeverity'])->name('by-severity');
        Route::get('{locationId}/active', [WeatherAlertController::class, 'active'])->name('active');
        
        // Routes with {id} parameter
        Route::patch('{id}/acknowledge', [WeatherAlertController::class, 'acknowledge'])->name('acknowledge');
        Route::patch('{id}/resolve', [WeatherAlertController::class, 'resolve'])->name('resolve');
    });
    
    // Standard REST routes for weather alerts
    Route::apiResource('weather-alerts', WeatherAlertController::class);

    // Weather Minutely data management routes
    Route::prefix('weather-minutely')->name('weather-minutely.')->group(function () {
        // Special endpoints that don't follow the {id} pattern
        Route::post('bulk', [WeatherMinutelyController::class, 'storeBulk'])->name('bulk');
        Route::post('cleanup', [WeatherMinutelyController::class, 'cleanup'])->name('cleanup');
        Route::get('{locationId}/next-hour', [WeatherMinutelyController::class, 'nextHour'])->name('next-hour');
        Route::get('{locationId}/precipitation', [WeatherMinutelyController::class, 'precipitation'])->name('precipitation');
        Route::get('{locationId}/recent', [WeatherMinutelyController::class, 'recent'])->name('recent');
    });
    
    // Standard REST routes for minutely data
    Route::apiResource('weather-minutely', WeatherMinutelyController::class);

    // OpenWeatherMap API integration routes
    Route::prefix('openweathermap')->name('openweathermap.')->group(function () {
        Route::post('fetch-current', [OpenWeatherMapController::class, 'fetchCurrent'])->name('fetch-current');
        Route::post('fetch-complete', [OpenWeatherMapController::class, 'fetchComplete'])->name('fetch-complete');
        Route::post('fetch-historical', [OpenWeatherMapController::class, 'fetchHistorical'])->name('fetch-historical');
    });
});
