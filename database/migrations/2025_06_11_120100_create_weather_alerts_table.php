<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weather_alerts', function (Blueprint $table) {
            // Primary key as UUID
            $table->uuid('id')->primary();
            
            // Foreign key to weather_locations table
            $table->foreignUuid('weather_location_id')
                  ->constrained('weather_locations')
                  ->onDelete('cascade');
            
            // Alert identification and source
            $table->string('sender_name')->nullable(); // Name of the alert source
            $table->string('event'); // Alert event name
            $table->string('alert_source', 100)->nullable(); // Source agency/organization
            $table->string('external_id')->nullable(); // External alert ID if available
            
            // Alert timing
            $table->timestamp('start_time'); // Start of alert (Unix timestamp from API)
            $table->timestamp('end_time'); // End of alert (Unix timestamp from API)
            $table->timestamp('issued_time')->nullable(); // When the alert was issued
            $table->timestamp('expires_time')->nullable(); // When the alert expires
            
            // Alert content
            $table->text('description'); // Full description of the alert
            $table->text('instructions')->nullable(); // Instructions for the public
            $table->json('tags')->nullable(); // Type of severe weather (array)
            
            // Alert severity and type
            $table->enum('severity', ['minor', 'moderate', 'severe', 'extreme', 'unknown'])
                  ->default('unknown');
            $table->enum('urgency', ['immediate', 'expected', 'future', 'past', 'unknown'])
                  ->default('unknown');
            $table->enum('certainty', ['observed', 'likely', 'possible', 'unlikely', 'unknown'])
                  ->default('unknown');
            
            // Geographic information
            $table->json('affected_areas')->nullable(); // Areas affected by the alert
            $table->decimal('latitude', 10, 8)->nullable(); // Center point of alert area
            $table->decimal('longitude', 11, 8)->nullable(); // Center point of alert area
            $table->json('polygon_coordinates')->nullable(); // Detailed geographic boundaries
            
            // Language and localization
            $table->string('language', 10)->default('en'); // Language of the alert
            $table->json('translations')->nullable(); // Translations in other languages
            
            // Alert status and processing
            $table->enum('status', ['active', 'expired', 'cancelled', 'replaced', 'archived'])
                  ->default('active')
                  ->index();
            $table->boolean('is_active')->default(true)->index(); // Quick active/inactive flag
            $table->boolean('is_processed')->default(false); // Has been processed by our system
            $table->timestamp('processed_at')->nullable(); // When it was processed
            
            // References to other alerts
            $table->uuid('replaces_alert_id')->nullable(); // If this alert replaces another
            $table->uuid('replaced_by_alert_id')->nullable(); // If this alert was replaced
            
            // Store raw API response for flexibility
            $table->json('raw_data')->nullable();
            
            // Metadata
            $table->string('api_source', 50)->default('openweathermap'); // Source of the data
            $table->string('api_version', 20)->nullable(); // API version used
            $table->timestamp('last_verified')->nullable(); // Last time alert was verified as active
            
            // Standard Laravel timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key constraints for alert relationships
            $table->foreign('replaces_alert_id')
                  ->references('id')
                  ->on('weather_alerts')
                  ->onDelete('set null');
            $table->foreign('replaced_by_alert_id')
                  ->references('id')
                  ->on('weather_alerts')
                  ->onDelete('set null');
            
            // Indexes for performance
            $table->index(['weather_location_id', 'status', 'start_time']);
            $table->index(['weather_location_id', 'is_active', 'start_time']);
            $table->index(['status', 'start_time', 'end_time']);
            $table->index(['event', 'start_time']);
            $table->index(['severity', 'start_time']);
            $table->index(['start_time', 'end_time']);
            $table->index('external_id');
            $table->index('issued_time');
            $table->index('expires_time');
            
            // Composite indexes for common queries
            $table->index(['weather_location_id', 'is_active', 'severity', 'start_time'], 'location_active_severity_time');
            $table->index(['is_active', 'status', 'start_time', 'end_time'], 'active_status_time_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_alerts');
    }
};
