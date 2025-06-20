<?php

namespace ClarionApp\Weather\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class WeatherAlert extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'weather_location_id',
        'sender_name',
        'event',
        'start_time',
        'end_time',
        'description',
        'tags',
        'severity',
        'urgency',
        'certainty',
        'affected_areas',
        'raw_data',
        'is_active',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'tags' => 'array',
        'affected_areas' => 'array',
        'raw_data' => 'array',
        'is_active' => 'boolean',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected $dates = [
        'start_time',
        'end_time',
        'acknowledged_at',
        'resolved_at',
        'deleted_at',
    ];

    /**
     * Get the weather location that owns the alert.
     */
    public function weatherLocation(): BelongsTo
    {
        return $this->belongsTo(WeatherLocation::class);
    }

    /**
     * Scope a query to only include active alerts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now());
    }

    /**
     * Scope a query to only include current alerts (within time window).
     */
    public function scopeCurrent($query)
    {
        return $query->where('start_time', '<=', now())
                    ->where('end_time', '>=', now());
    }

    /**
     * Scope a query to only include alerts by severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope a query to only include alerts by event type.
     */
    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope a query to only include unacknowledged alerts.
     */
    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    /**
     * Check if the alert is currently active.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active && 
               $this->start_time <= now() && 
               $this->end_time >= now();
    }

    /**
     * Check if the alert has expired.
     */
    public function hasExpired(): bool
    {
        return $this->end_time < now();
    }

    /**
     * Get the duration of the alert in minutes.
     */
    public function getDurationMinutes(): int
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Get the time remaining until the alert expires.
     */
    public function getTimeRemaining(): ?Carbon
    {
        if ($this->hasExpired()) {
            return null;
        }

        return $this->end_time;
    }

    /**
     * Mark the alert as acknowledged.
     */
    public function acknowledge(): void
    {
        $this->update(['acknowledged_at' => now()]);
    }

    /**
     * Mark the alert as resolved.
     */
    public function resolve(): void
    {
        $this->update([
            'resolved_at' => now(),
            'is_active' => false,
        ]);
    }

    /**
     * Get the severity level as a numeric value for sorting.
     */
    public function getSeverityLevel(): int
    {
        return match($this->severity) {
            'minor' => 1,
            'moderate' => 2,
            'severe' => 3,
            'extreme' => 4,
            default => 0,
        };
    }

    /**
     * Get the urgency level as a numeric value for sorting.
     */
    public function getUrgencyLevel(): int
    {
        return match($this->urgency) {
            'past' => 1,
            'future' => 2,
            'expected' => 3,
            'immediate' => 4,
            default => 0,
        };
    }

    /**
     * Get the certainty level as a numeric value for sorting.
     */
    public function getCertaintyLevel(): int
    {
        return match($this->certainty) {
            'unknown' => 1,
            'unlikely' => 2,
            'possible' => 3,
            'likely' => 4,
            'observed' => 5,
            default => 0,
        };
    }

    /**
     * Format the alert for display.
     */
    public function getFormattedAlert(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'severity' => $this->severity,
            'urgency' => $this->urgency,
            'certainty' => $this->certainty,
            'description' => $this->description,
            'start_time' => $this->start_time->format('Y-m-d H:i:s'),
            'end_time' => $this->end_time->format('Y-m-d H:i:s'),
            'duration_minutes' => $this->getDurationMinutes(),
            'is_active' => $this->isCurrentlyActive(),
            'has_expired' => $this->hasExpired(),
            'is_acknowledged' => !is_null($this->acknowledged_at),
            'sender' => $this->sender_name,
            'tags' => $this->tags,
            'areas' => $this->affected_areas,
        ];
    }
}
