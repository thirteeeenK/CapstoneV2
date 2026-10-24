<?php

use App\Services\TripDurationPolicy;
use Illuminate\Validation\ValidationException;

it('applies the days and nights calendar contract across month, year and leap-day boundaries', function (string $start, int $days, string $end) {
    expect(TripDurationPolicy::endDate($start, $days))->toBe($end)
        ->and(TripDurationPolicy::days($start, $end))->toBe($days)
        ->and(TripDurationPolicy::nights($days))->toBe($days - 1);
})->with([
    '6 days / 5 nights' => ['2026-10-26', 6, '2026-10-31'],
    'month boundary' => ['2026-10-30', 4, '2026-11-02'],
    'year boundary' => ['2026-12-30', 4, '2027-01-02'],
    'leap day' => ['2028-02-27', 4, '2028-03-01'],
    'one day' => ['2026-11-01', 1, '2026-11-01'],
]);

it('rejects dates that do not cover the selected duration and out-of-range lengths', function () {
    expect(fn () => TripDurationPolicy::assertMatches('2026-10-26', '2026-10-28', 6))->toThrow(ValidationException::class, 'Depart on Oct 31, 2026')
        ->and(fn () => TripDurationPolicy::assertDays(1, true))->toThrow(ValidationException::class, 'at least 2 days')
        ->and(fn () => TripDurationPolicy::assertDays(31, false))->toThrow(ValidationException::class)
        ->and(TripDurationPolicy::label(6))->toBe('6 days, 5 nights');
    TripDurationPolicy::assertMatches('2026-10-26', '2026-10-31', 6);
    TripDurationPolicy::assertDays(1, false);
});
