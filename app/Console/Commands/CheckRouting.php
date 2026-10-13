<?php

namespace App\Console\Commands;

use App\Services\RoutingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('routing:check {--mode=walking : walking or driving} {--from=11.957148,121.927043 : Latitude,longitude} {--to=11.9658,121.9255 : Latitude,longitude}')]
#[Description('Check the configured Valhalla service and a real Boracay route')]
class CheckRouting extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RoutingService $routing): int
    {
        if (! $routing->health()) {
            $this->error('Valhalla is not configured or is not ready. Set VALHALLA_URL and check the service logs.');

            return self::FAILURE;
        }
        $points = [];
        foreach (['from', 'to'] as $option) {
            $parts = explode(',', (string) $this->option($option));
            if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                $this->error('Coordinates must be latitude,longitude.');

                return self::FAILURE;
            }
            $points[$option] = ['lat' => (float) $parts[0], 'lng' => (float) $parts[1]];
        }
        $route = $routing->route($points['from'], $points['to'], (string) $this->option('mode'));
        if (! $route['available']) {
            $this->error('Route unavailable: '.$route['status']);

            return self::FAILURE;
        }
        $this->info(sprintf('Valhalla: %s route, %.2f km, %d min, %d geometry points.', $route['mode'], $route['distance_km'], $route['duration_minutes'], count($route['geometry']['coordinates'])));

        return self::SUCCESS;
    }
}
