<?php

namespace ClarionApp\Weather\Commands;

use Illuminate\Console\Command;
use ClarionApp\Weather\Jobs\WeatherDataUpdate;

class WeatherUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weather:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add weather data update job to queue';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Dispatching weather data update job...');
        dispatch(new WeatherDataUpdate());
        $this->info('Weather data update job dispatched successfully!');
    }
}
