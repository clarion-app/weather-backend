<?php

namespace ClarionApp\Weather;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use ClarionApp\Backend\ClarionPackageServiceProvider;
use ClarionApp\Weather\Jobs\WeatherDataUpdate;
use ClarionApp\Weather\Commands\WeatherUpdate;

class WeatherServiceProvider extends ClarionPackageServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();

        $this->commands([
            WeatherUpdate::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        parent::boot();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if(!$this->app->routesAreCached())
        {
            require __DIR__.'/../routes/api.php';
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->call(function() {
                // Check if queue workers are running before dispatching
                $result = shell_exec('pgrep -c -f "php artisan queue:work --queue=default"');
                if($result == "2\n")
                {
                    dispatch(new WeatherDataUpdate());
                }
            })->everyMinute();
        });
    }
}
