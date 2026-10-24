<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * One calendar-day contract for preference and planner trips, in Asia/Manila:
 * nights = days − 1, end = start + nights, and day k falls on start + (k − 1).
 * Dates are compared as calendar dates, never as elapsed hours. Lucky keeps
 * its own 1–7 night limits.
 */
final class TripDurationPolicy
{
    public const MIN_DAYS = 1;

    public const MAX_DAYS = 30;

    /** A dated trip needs one night; an undated one-day plan stays an activity-only draft. */
    public const MIN_DATED_DAYS = 2;

    public static function endDate(string $start, int $days): string
    {
        return self::date($start)->addDays(max(0, $days - 1))->toDateString();
    }

    public static function days(string $start, string $end): int
    {
        return (int) self::date($start)->diffInDays(self::date($end), false) + 1;
    }

    public static function nights(int $days): int
    {
        return max(0, $days - 1);
    }

    public static function label(int $days): string
    {
        $nights = self::nights($days);

        return $days.' day'.($days === 1 ? '' : 's').', '.$nights.' night'.($nights === 1 ? '' : 's');
    }

    /**
     * Reject a date range that does not cover the selected duration exactly,
     * rather than silently replacing the duration with the range.
     *
     * @throws ValidationException
     */
    public static function assertMatches(string $start, string $end, int $days, string $field = 'check_out_date'): void
    {
        $covered = self::days($start, $end);
        if ($covered !== $days) {
            throw ValidationException::withMessages([$field => 'Those dates cover '.max(0, $covered).' day'.($covered === 1 ? '' : 's').', but the trip is '.self::label($days)
                .'. Depart on '.Carbon::parse(self::endDate($start, $days))->format('M j, Y').', or change the trip length first.']);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertDays(int $days, bool $dated, string $field = 'days'): void
    {
        if ($days < ($dated ? self::MIN_DATED_DAYS : self::MIN_DAYS) || $days > self::MAX_DAYS) {
            throw ValidationException::withMessages([$field => $dated && $days < self::MIN_DATED_DAYS
                ? 'A dated trip needs at least 2 days (1 night). Keep the dates undecided for a one-day activity plan.'
                : 'Trip length must be between 1 and '.self::MAX_DAYS.' days.']);
        }
    }

    private static function date(string $value): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Manila');
    }
}
