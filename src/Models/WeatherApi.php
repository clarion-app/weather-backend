<?php

namespace ClarionApp\Weather\Models;

use ClarionApp\EloquentMultiChainBridge\EloquentMultiChainBridge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeatherApi extends Model
{
    use HasFactory, EloquentMultiChainBridge, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'weather_apis';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'url',
        'api_key',
        'name',
        'is_active',
        'rate_limit_minutes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope to get only active APIs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive APIs.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get the masked API key for display purposes.
     */
    public function getMaskedApiKeyAttribute(): string
    {
        if (empty($this->api_key)) {
            return '';
        }

        $keyLength = strlen($this->api_key);
        if ($keyLength <= 8) {
            return str_repeat('*', $keyLength);
        }

        return substr($this->api_key, 0, 4) . str_repeat('*', $keyLength - 8) . substr($this->api_key, -4);
    }

    /**
     * Check if the API is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Activate the API.
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the API.
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
}
