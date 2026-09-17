<?php

use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RecommendationHit;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function trackSession(User $user, string $token, array $clicks = []): void
{
    RecommendationHit::create([
        'user_id' => $user->id,
        'session_token' => $token,
        'mode' => RecommendationHit::MODE_AI,
        'entity_type' => RecommendationHit::TYPE_IMPRESSION,
    ]);

    foreach ($clicks as $click) {
        RecommendationHit::create([
            'user_id' => $user->id,
            'session_token' => $token,
        ] + $click);
    }
}

it('stores a click beacon for a known session token', function () {
    $user = User::factory()->create();
    trackSession($user, 'tok-1');

    $this->actingAs($user)->postJson(route('recommendations.click'), [
        'session_token' => 'tok-1',
        'mode' => 'ai',
        'entity_type' => 'hotel',
        'entity_id' => 4,
        'rank' => 1,
    ])->assertNoContent();

    expect(RecommendationHit::where('entity_type', 'hotel')->count())->toBe(1);
});

it('ignores beacons for unknown session tokens', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('recommendations.click'), [
        'session_token' => 'stale-token',
        'mode' => 'ai',
        'entity_type' => 'hotel',
        'entity_id' => 4,
        'rank' => 1,
    ])->assertNoContent();

    expect(RecommendationHit::count())->toBe(0);
});

it('stores a nav-exit beacon without entity or rank', function () {
    $user = User::factory()->create();
    trackSession($user, 'tok-nav');

    $this->actingAs($user)->postJson(route('recommendations.click'), [
        'session_token' => 'tok-nav',
        'mode' => 'ai',
        'entity_type' => 'nav',
    ])->assertNoContent();

    expect(RecommendationHit::where('entity_type', 'nav')->count())->toBe(1);
});

it('computes HR@5 across sessions', function () {
    $user = User::factory()->create();
    $hotel = fn ($rank) => ['mode' => 'ai', 'entity_type' => 'hotel', 'entity_id' => 1, 'rank' => $rank];

    trackSession($user, 's1', [$hotel(2)]);
    trackSession($user, 's2', [$hotel(5)]);
    trackSession($user, 's3', [['mode' => 'default', 'entity_type' => 'hotel', 'entity_id' => 2, 'rank' => 1]]);
    trackSession($user, 's4');
    // Nav-exit only (sidebar/packages, no card click) = MISS, not excluded.
    trackSession($user, 's5', [['mode' => 'ai', 'entity_type' => 'nav']]);
    // Nav exit followed by an AI card click still HITs.
    trackSession($user, 's6', [['mode' => 'ai', 'entity_type' => 'nav'], $hotel(1)]);

    // AI: 3 hit / 6 sessions = 0.5 | default: 1 / 6 = 0.1667
    // Click-conditional: s1,s2,s6 HIT, s3,s5 MISS, s4 (unclicked) excluded = 0.6
    $this->artisan('recommendation:evaluate')
        ->expectsOutputToContain('0.5')
        ->expectsOutputToContain('0.1667')
        ->expectsOutputToContain('0.6000 (3 HIT / 5 clicked sessions')
        ->expectsOutputToContain('HIT')
        ->expectsOutputToContain('MISS')
        ->assertSuccessful();
});

it('exports the per-session verdict record to CSV', function () {
    $user = User::factory()->create();

    trackSession($user, 'e1', [['mode' => 'ai', 'entity_type' => 'hotel', 'entity_id' => 1, 'rank' => 2]]);
    trackSession($user, 'e2');
    trackSession($user, 'e3', [['mode' => 'default', 'entity_type' => 'activity', 'entity_id' => 7, 'rank' => 1]]);
    trackSession($user, 'e4', [['mode' => 'ai', 'entity_type' => 'nav']]);

    $path = tempnam(sys_get_temp_dir(), 'hr').'.csv';

    $this->artisan('recommendation:evaluate', ['--export' => $path])->assertSuccessful();

    $csv = file_get_contents($path);
    unlink($path);

    // e1 HIT, e3 MISS (clicked but nothing AI), e4 MISS (nav exit only),
    // e2 excluded (no clicks at all)
    expect($csv)->toContain('e1')
        ->toContain('HIT')
        ->toContain('ai/hotel #1 r2')
        ->toContain('e3')
        ->toContain('MISS')
        ->toContain('default/activity #7 r1')
        ->toContain('e4')
        ->toContain('ai/nav exit')
        ->not->toContain(',e2,');
});

it('reuses one session per user across reloads and stops after the first click', function () {
    Http::fake();

    DestinationModel::factory()->create(['name' => 'Boracay']);
    HotelModel::factory()->create([
        'is_shown' => true,
        'embedding' => '['.implode(',', array_fill(0, 3072, '0.01')).']',
    ]);

    $user = onboardedUser();

    $first = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    preg_match('/data-rec-token="([^"]*)"/', $first, $m);
    $token = $m[1] ?? '';

    expect($token)->not->toBe('')
        ->and(RecommendationHit::where('entity_type', 'impression')->count())->toBe(1);

    // Reload: same token, no second impression row.
    $second = $this->get(route('dashboard'))->assertOk()->getContent();
    preg_match('/data-rec-token="([^"]*)"/', $second, $m2);

    expect($m2[1] ?? '')->toBe($token)
        ->and(RecommendationHit::where('entity_type', 'impression')->count())->toBe(1);

    $this->postJson(route('recommendations.click'), [
        'session_token' => $token,
        'mode' => 'ai',
        'entity_type' => 'hotel',
        'entity_id' => 1,
        'rank' => 1,
    ])->assertNoContent();

    // After the first click: detection stops for this user.
    $third = $this->get(route('dashboard'))->assertOk()->getContent();
    preg_match('/data-rec-token="([^"]*)"/', $third, $m3);

    expect($m3[1] ?? '')->toBe('')
        ->and(RecommendationHit::where('entity_type', 'impression')->count())->toBe(1)
        ->and(RecommendationHit::count())->toBe(2);
});

it('stores nothing on a second click from the same user', function () {
    $user = User::factory()->create();
    trackSession($user, 'tok-once');

    $this->actingAs($user)->postJson(route('recommendations.click'), [
        'session_token' => 'tok-once',
        'mode' => 'ai',
        'entity_type' => 'hotel',
        'entity_id' => 4,
        'rank' => 1,
    ])->assertNoContent();

    $this->postJson(route('recommendations.click'), [
        'session_token' => 'tok-once',
        'mode' => 'ai',
        'entity_type' => 'activity',
        'entity_id' => 9,
        'rank' => 2,
    ])->assertNoContent();

    expect(RecommendationHit::whereIn('entity_type', ['hotel', 'activity', 'nav'])->count())->toBe(1);
});

it('clears all tracking rows with recommendation:reset', function () {
    $user = User::factory()->create();
    trackSession($user, 'tok-reset', [['mode' => 'ai', 'entity_type' => 'hotel', 'entity_id' => 1, 'rank' => 1]]);

    expect(RecommendationHit::count())->toBe(2);

    $this->artisan('recommendation:reset', ['--force' => true])->assertSuccessful();

    expect(RecommendationHit::count())->toBe(0);
});
