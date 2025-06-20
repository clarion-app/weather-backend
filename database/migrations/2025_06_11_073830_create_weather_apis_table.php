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
        Schema::create('weather_apis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url')->comment('The URL of the weather API endpoint');
            $table->string('api_key')->comment('The API key for accessing the weather service');
            $table->string('name')->nullable()->comment('Optional name/description for the API');
            $table->boolean('is_active')->default(true)->comment('Whether this API configuration is active');
            $table->unsignedSmallInteger('rate_limit_minutes')->default(10)->comment('Minimum minutes between API calls to respect rate limits');
            $table->timestamps();
            $table->softDeletes();
            
            // Add indexes for performance
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_apis');
    }
};
