<?php

namespace App\Services\Support;

use App\Services\SystemSettingsService;
use Carbon\CarbonImmutable;

class SupportAvailabilityService
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    /** @return array{available: bool, message: string, schedule: array<string, mixed>, timezone: string} */
    public function status(): array
    {
        $support = $this->settings->section('support');
        $now = CarbonImmutable::now('Asia/Manila');
        $day = $support['schedule'][strtolower($now->format('D'))];
        $inHours = $day['enabled'] && ($day['all_day'] || (
            $now->format('H:i') >= $day['opens'] && $now->format('H:i') < $day['closes']
        ));

        return [
            'available' => $support['accept_requests'] && (! $support['enforce_hours'] || $inHours),
            'message' => $support['unavailable_message'], 'schedule' => $support['schedule'], 'timezone' => 'Asia/Manila',
        ];
    }

    public function summary(): string
    {
        $support = $this->settings->section('support');
        if (! $support['accept_requests']) {
            return 'New human-support requests are paused.';
        }
        $hours = [];
        foreach ($support['schedule'] as $day => $schedule) {
            if ($schedule['enabled']) {
                $window = $schedule['all_day'] ? 'all day' : $schedule['opens'].'–'.$schedule['closes'];
                $hours[$window][] = ucfirst($day);
            }
        }

        $labels = [];
        foreach ($hours as $window => $days) {
            $labels[] = (count($days) === 7 ? 'Daily' : implode(', ', $days)).' '.$window;
        }

        return 'Office hours (PHT): '.implode('; ', $labels).'.';
    }
}
