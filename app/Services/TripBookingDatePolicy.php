<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

class TripBookingDatePolicy
{
    public const TIMEZONE = 'Asia/Manila';

    public const LEAD_DAYS = 2;

    public function minimumArrivalDate(): string
    {
        return now(self::TIMEZONE)->startOfDay()->addDays(self::LEAD_DAYS)->toDateString();
    }

    public function message(): string
    {
        return 'Arrival must be at least two calendar days ahead in Philippine time. The earliest arrival is '.$this->minimumArrivalDate().'. Change the trip dates if needed.';
    }

    public function assertBookableArrival(?string $arrival, string $field = 'start_date'): void
    {
        $parsed = $arrival && preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $arrival, new DateTimeZone(self::TIMEZONE))
            : false;
        if (! $parsed || $parsed->format('Y-m-d') !== $arrival || $arrival < $this->minimumArrivalDate()) {
            throw ValidationException::withMessages([$field => $this->message()]);
        }
    }

    /** @return array{minimumArrival: string, serverNow: string, timeZone: string, leadDays: int} */
    public function frontendConfig(): array
    {
        return [
            'minimumArrival' => $this->minimumArrivalDate(),
            'serverNow' => now(self::TIMEZONE)->toIso8601String(),
            'timeZone' => self::TIMEZONE,
            'leadDays' => self::LEAD_DAYS,
        ];
    }
}
