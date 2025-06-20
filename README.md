# clarion-app/weather-backend

A Laravel package that fetches and stores weather data, providing comprehensive weather forecasting endpoints for frontend applications. This backend integrates with weather APIs (like OpenWeatherMap) to deliver current conditions, forecasts, alerts, and minutely precipitation data.

## Features

- **Multi-Location Support**: Manage multiple weather locations with favorites and active status
- **Comprehensive Weather Data**: Current, hourly, daily, and historical weather information
- **Weather Alerts**: Store and manage weather alerts with severity levels and acknowledgment
- **Minutely Precipitation**: Detailed precipitation forecasts for the next hour
- **Geocoding Integration**: Location search and creation from coordinates
- **Multiple Weather APIs**: Support for multiple weather service providers
- **Data Cleanup**: Automated cleanup of old weather data
- **Background Jobs**: Asynchronous weather data updates via Laravel queues
- **REST API**: Full RESTful API with authentication

## Database Schema

The package creates the following database tables:

- **weather_apis**: Configuration for weather service providers
- **weather_locations**: Geographic locations for weather monitoring
- **weather_data**: Current, hourly, daily, and historical weather data
- **weather_alerts**: Weather warnings and alerts
- **weather_minutely**: Minute-by-minute precipitation forecasts

## API Endpoints

### Weather APIs Management
- `GET /weather-apis` - List all weather APIs
- `POST /weather-apis` - Create new weather API configuration
- `GET /weather-apis/{id}` - Get specific weather API
- `PUT /weather-apis/{id}` - Update weather API configuration
- `DELETE /weather-apis/{id}` - Delete weather API
- `PATCH /weather-apis/{id}/activate` - Activate weather API
- `PATCH /weather-apis/{id}/deactivate` - Deactivate weather API
- `GET /weather-apis/active` - Get active weather APIs

### Location Management
- `GET /locations` - List all locations
- `POST /locations` - Create new location
- `GET /locations/{id}` - Get specific location
- `PUT /locations/{id}` - Update location
- `DELETE /locations/{id}` - Delete location
- `GET /locations/active` - Get active locations
- `GET /locations/favorites` - Get favorite locations
- `GET /locations/nearby` - Find nearby locations
- `GET /locations/search-geocode` - Search locations by geocoding
- `POST /locations/from-geocode` - Create location from geocode data
- `PATCH /locations/{id}/activate` - Activate location
- `PATCH /locations/{id}/deactivate` - Deactivate location
- `PATCH /locations/{id}/favorite` - Add location to favorites
- `DELETE /locations/{id}/favorite` - Remove location from favorites

### Weather Data
- `GET /weather-data` - List all weather data
- `POST /weather-data` - Create weather data entry
- `GET /weather-data/{id}` - Get specific weather data
- `PUT /weather-data/{id}` - Update weather data
- `DELETE /weather-data/{id}` - Delete weather data
- `GET /weather-data/{locationId}/current` - Get current weather for location
- `GET /weather-data/{locationId}/hourly` - Get hourly forecast for location
- `GET /weather-data/{locationId}/daily` - Get daily forecast for location
- `GET /weather-data/{locationId}/historical` - Get historical data for location
- `POST /weather-data/cleanup` - Clean up old weather data

### Weather Alerts
- `GET /weather-alerts` - List all weather alerts
- `POST /weather-alerts` - Create weather alert
- `GET /weather-alerts/{id}` - Get specific weather alert
- `PUT /weather-alerts/{id}` - Update weather alert
- `DELETE /weather-alerts/{id}` - Delete weather alert
- `GET /weather-alerts/{locationId}/active` - Get active alerts for location
- `GET /weather-alerts/statistics` - Get alert statistics
- `GET /weather-alerts/severity/{severity}` - Get alerts by severity
- `PATCH /weather-alerts/{id}/acknowledge` - Acknowledge alert
- `PATCH /weather-alerts/{id}/resolve` - Resolve alert
- `POST /weather-alerts/cleanup` - Clean up old alerts

### Minutely Precipitation
- `GET /weather-minutely` - List minutely data
- `POST /weather-minutely` - Create minutely data
- `GET /weather-minutely/{id}` - Get specific minutely data
- `PUT /weather-minutely/{id}` - Update minutely data
- `DELETE /weather-minutely/{id}` - Delete minutely data
- `GET /weather-minutely/{locationId}/next-hour` - Get next hour precipitation
- `GET /weather-minutely/{locationId}/precipitation` - Get precipitation data
- `GET /weather-minutely/{locationId}/recent` - Get recent minutely data
- `POST /weather-minutely/bulk` - Bulk create minutely data
- `POST /weather-minutely/cleanup` - Clean up old minutely data

### OpenWeatherMap Integration
- `POST /openweathermap/fetch-current` - Fetch current weather from OpenWeatherMap
- `POST /openweathermap/fetch-complete` - Fetch complete weather data
- `POST /openweathermap/fetch-historical` - Fetch historical weather data

## Commands

### Weather Update Command
Update weather data for all active locations:
```bash
php artisan weather:update
```

This command dispatches a job to update weather data asynchronously via Laravel's queue system.

## Configuration

The package integrates with the Clarion framework and includes custom prompts for AI-assisted operations:

- **Choose Operations**: Guides users to first get location IDs, then fetch weather data
- **Generate API Calls**: Provides context for proper API parameter usage

## Models

- **WeatherApi**: Weather service provider configurations
- **WeatherLocation**: Geographic locations with coordinates and metadata
- **WeatherData**: Weather measurements and forecasts
- **WeatherAlert**: Weather warnings and notifications
- **WeatherMinutely**: Minute-by-minute precipitation data

## Jobs

- **WeatherDataUpdate**: Background job for updating weather data from external APIs

## Authentication

All API endpoints require authentication via Laravel's `auth:api` middleware.

## Units Support

The package supports different unit systems for temperature, wind speed, and other measurements, configurable per location.

## Data Retention

The package includes cleanup functionality to manage data retention and prevent database bloat from historical weather data.

## License

MIT License

## Author

Tim Schwartz (tim@metaverse.systems)