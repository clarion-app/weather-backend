<?php

namespace ClarionApp\Weather\Models;

use ClarionApp\EloquentMultiChainBridge\EloquentMultiChainBridge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeatherLocation extends Model
{
    use HasFactory, EloquentMultiChainBridge, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'weather_locations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'city',
        'state',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'is_active',
        'is_favorite',
        'timezone',
        'units',
        'geocoding_data',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
        'is_favorite' => 'boolean',
        'geocoding_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope to get only active locations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive locations.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get only favorite locations.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope to filter by country code.
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope to search locations by name, city, or country.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
              ->orWhereRaw('LOWER(city) LIKE ?', ['%' . strtolower($search) . '%'])
              ->orWhereRaw('LOWER(country) LIKE ?', ['%' . strtolower($search) . '%'])
              ->orWhereRaw('LOWER(state) LIKE ?', ['%' . strtolower($search) . '%']);
        });
    }

    /**
     * Scope to find locations within a radius of given coordinates.
     */
    public function scopeWithinRadius($query, float $latitude, float $longitude, float $radiusKm = 10)
    {
        // Using Haversine formula for distance calculation
        return $query->whereRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
            [$latitude, $longitude, $latitude, $radiusKm]
        );
    }

    /**
     * Check if the location is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if the location is marked as favorite.
     */
    public function isFavorite(): bool
    {
        return $this->is_favorite;
    }

    /**
     * Activate the location.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the location.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Add location to favorites.
     */
    public function addToFavorites(): bool
    {
        return $this->update(['is_favorite' => true]);
    }

    /**
     * Remove location from favorites.
     */
    public function removeFromFavorites(): bool
    {
        return $this->update(['is_favorite' => false]);
    }

    /**
     * Get the full location display name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = [$this->city];
        
        if ($this->state) {
            $parts[] = $this->state;
        }
        
        $parts[] = $this->country;
        
        return implode(', ', $parts);
    }

    /**
     * Get coordinates as array.
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
        ];
    }

    /**
     * Calculate distance to another location in kilometers.
     */
    public function distanceTo(WeatherLocation $location): float
    {
        return $this->calculateDistance(
            $this->latitude,
            $this->longitude,
            $location->latitude,
            $location->longitude
        );
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get formatted location data for API responses.
     */
    public function getFormattedLocation(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'full_name' => $this->full_name,
            'coordinates' => $this->coordinates,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'is_favorite' => $this->is_favorite,
            'units' => $this->units,
        ];
    }

    /**
     * Create location from geocoding API response.
     */
    public static function createFromGeocoding(array $geocodingData, ?string $customName = null): self
    {
        return self::create([
            'name' => $customName ?: ($geocodingData['local_names']['en'] ?? $geocodingData['name']),
            'city' => $geocodingData['name'],
            'state' => $geocodingData['state'] ?? null,
            'country' => $geocodingData['country'],
            'country_code' => $geocodingData['country'],
            'latitude' => $geocodingData['lat'],
            'longitude' => $geocodingData['lon'],
            'geocoding_data' => $geocodingData,
        ]);
    }
}
