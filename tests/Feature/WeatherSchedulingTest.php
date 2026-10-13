<?php

use App\Services\WeatherService;
use Carbon\Carbon;

it('converts UTC slots to Philippine dates and retains three hour rain volume', function () {
    $raw = ['list' => [['dt_txt' => '2026-10-15 18:00:00', 'main' => ['temp' => 28], 'rain' => ['3h' => 9]]]];
    $normalized = app(WeatherService::class)->normalize($raw);
    expect($normalized['slots'][0]['time'])->toBe('2026-10-16T02:00:00+08:00')
        ->and($normalized['slots'][0]['rain_3h'])->toBe(9)
        ->and($normalized['slots'][0]['rain_1h'])->toBe(3.0);
});

it('uses a daytime daily preview while retaining every forecast slot', function () {
    $raw = ['list' => [
        ['dt_txt' => '2026-10-15 00:00:00', 'main' => ['temp' => 24]],
        ['dt_txt' => '2026-10-15 03:00:00', 'main' => ['temp' => 29]],
        ['dt_txt' => '2026-10-15 06:00:00', 'main' => ['temp' => 31]],
    ]];
    $normalized = app(WeatherService::class)->normalize($raw);
    expect($normalized['daily'][0]['temp'])->toBe(29.0)->and($normalized['slots'])->toHaveCount(3);
});

it('requires forecast coverage across the whole scheduled window', function () {
    $service = app(WeatherService::class);
    $raw = ['list' => [
        ['dt_txt' => '2026-10-15 00:00:00'], ['dt_txt' => '2026-10-15 03:00:00'],
    ]];
    expect($service->forecastWindow($raw, Carbon::parse('2026-10-15 09:00', 'Asia/Manila'), Carbon::parse('2026-10-15 13:00', 'Asia/Manila'))['covered'])->toBeTrue();
    expect($service->forecastWindow($raw, Carbon::parse('2026-10-15 13:00', 'Asia/Manila'), Carbon::parse('2026-10-15 16:00', 'Asia/Manila'))['covered'])->toBeFalse();
});

it('keeps stale forecasts labelled and does not claim missing future weather', function () {
    $result = app(WeatherService::class)->forecastWindow(['_stale' => true, '_fetched_at' => '2026-10-13T08:00:00+08:00', 'list' => []], Carbon::parse('2026-10-22'), Carbon::parse('2026-10-23'));
    expect($result['covered'])->toBeFalse()->and($result['stale'])->toBeTrue()->and($result['slots'])->toBeEmpty();
});

it('treats malformed provider slots as unknown weather', function () {
    $service = app(WeatherService::class);
    expect($service->normalize(['list' => 'invalid'])['current'])->toBeNull()
        ->and($service->forecastSlots(['list' => [null, 'invalid', ['dt_txt' => 'not a date']]]))->toBeEmpty();
});
