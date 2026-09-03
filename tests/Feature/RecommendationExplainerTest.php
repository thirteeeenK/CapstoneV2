<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Http;

function embeddingVector(): string
{
    return '['.implode(',', array_fill(0, 3072, '0.01')).']';
}

it('persists structured preferences when onboarding is completed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'vibes' => ['Beachfront', 'Luxury & Spa'],
            'destination' => 'Boracay',
            'traveler_type' => 'Couple / Honeymoon',
            'amenities' => ['Spa Services', 'Infinity Pool'],
            'notes' => 'Quiet villa with sunset views',
        ])
        ->assertRedirect(route('onboarding.processing'));

    $this->assertDatabaseHas('user_preferences', [
        'user_id' => $user->id,
        'destination' => 'Boracay',
        'traveler_type' => 'Couple / Honeymoon',
    ]);

    $preference = UserPreference::where('user_id', $user->id)->first();
    expect($preference->vibes)->toContain('Beachfront', 'Luxury & Spa')
        ->and($preference->amenities)->toContain('Spa Services', 'Infinity Pool')
        ->and($preference->notes)->toBe('Quiet villa with sunset views');
});

it('removes structured preferences when onboarding is skipped', function () {
    $user = onboardedUser();
    $user->userPreference()->create([
        'destination' => 'Boracay',
        'traveler_type' => 'Couple / Honeymoon',
        'vibes' => ['Beachfront'],
        'amenities' => ['Spa Services'],
        'notes' => null,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.skip'))
        ->assertRedirect(route('dashboard'));

    expect(UserPreference::where('user_id', $user->id)->exists())->toBeFalse();
});

it('shows a section-level fallback explanation on the dashboard', function () {
    config(['services.gemini.api_key' => null]);

    $destination = DestinationModel::factory()->create();
    $user = onboardedUser();
    $user->userPreference()->create([
        'destination' => $destination->name,
        'traveler_type' => 'Couple / Honeymoon',
        'vibes' => ['Beachfront', 'Adventure & Thrills'],
        'amenities' => ['Spa Services'],
        'notes' => null,
    ]);

    $hotel = HotelModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'vibe_tags' => ['Beachfront', 'Luxury & Spa'],
        'featured_amenities' => ['Spa Services'],
    ]);

    $activity = ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'vibe_tags' => ['Adventure & Thrills'],
        'category' => 'Water Activity',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Based on your preference for Beachfront & Spa Services')
        ->assertSee('these top stays')
        ->assertSee('Based on your preference for Adventure & Thrills')
        ->assertSee('these top experiences')
        ->assertDontSee('% Match')
        ->assertDontSee('Matches your');
});

it('shows gemini-generated section overviews when the api is available', function () {
    config(['services.gemini.api_key' => 'fake-key']);

    $destination = DestinationModel::factory()->create();
    $user = onboardedUser();
    $user->userPreference()->create([
        'destination' => $destination->name,
        'traveler_type' => 'Solo Traveler',
        'vibes' => ['Nature & Eco'],
        'amenities' => ['Island Hopping'],
        'notes' => null,
    ]);

    $hotel = HotelModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'vibe_tags' => ['Nature & Eco'],
    ]);

    $activity = ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'vibe_tags' => ['Nature & Eco'],
        'category' => 'Island Hopping',
    ]);

    Http::fake([
        '*generativelanguage.googleapis.com*' => Http::response(json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'hotels' => 'Custom Gemini hotel overview',
                                    'activities' => 'Custom Gemini activity overview',
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ]), 200),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Custom Gemini hotel overview')
        ->assertSee('Custom Gemini activity overview')
        ->assertDontSee('% Match')
        ->assertDontSee('Matches your');
});

it('shows a personalization prompt when no structured preferences exist', function () {
    config(['services.gemini.api_key' => null]);

    $destination = DestinationModel::factory()->create();
    $user = onboardedUser();

    HotelModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
    ]);

    ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'category' => 'Water Activity',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Complete your travel profile to see why these stays were chosen for you.')
        ->assertSee('Complete your travel profile to see why these experiences were chosen for you.');
});

it('persists explanations and reuses them without calling gemini again', function () {
    config(['services.gemini.api_key' => 'fake-key']);

    $destination = DestinationModel::factory()->create();
    $user = onboardedUser();
    $user->userPreference()->create([
        'destination' => $destination->name,
        'traveler_type' => 'Solo Traveler',
        'vibes' => ['Nature & Eco'],
        'amenities' => ['Island Hopping'],
        'notes' => null,
    ]);

    HotelModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'vibe_tags' => ['Nature & Eco'],
    ]);

    ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'category' => 'Island Hopping',
    ]);

    Http::fake([
        '*generateContent*' => Http::response(json_encode([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode([
                    'hotels' => 'Persisted hotel overview',
                    'activities' => 'Persisted activity overview',
                ])]]]],
            ],
        ]), 200),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Persisted hotel overview');

    $calls = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'generateContent'));
    expect($calls->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Persisted hotel overview');

    $calls = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'generateContent'));
    expect($calls->count())->toBe(1);
});

it('regenerates explanations after preferences are updated', function () {
    config(['services.gemini.api_key' => 'fake-key']);

    $destination = DestinationModel::factory()->create();
    $user = onboardedUser();
    $user->userPreference()->create([
        'destination' => $destination->name,
        'traveler_type' => 'Solo Traveler',
        'vibes' => ['Nature & Eco'],
        'amenities' => ['Island Hopping'],
        'notes' => null,
    ]);

    HotelModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'vibe_tags' => ['Nature & Eco'],
    ]);

    ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'embedding' => embeddingVector(),
        'category' => 'Island Hopping',
    ]);

    Http::fake([
        '*generateContent*' => function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'] ?? '';
            $hotels = str_contains($prompt, 'Adventure & Thrills') ? 'Regenerated overview' : 'First saved overview';

            return Http::response(json_encode([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode([
                        'hotels' => $hotels,
                        'activities' => 'activity overview',
                    ])]]]],
                ],
            ]), 200);
        },
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('First saved overview');

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'vibes' => ['Adventure & Thrills'],
            'destination' => $destination->name,
            'traveler_type' => 'Solo Traveler',
            'amenities' => ['Scuba Diving'],
        ])
        ->assertRedirect(route('onboarding.processing'));

    $user->refresh();
    $user->preferences_embedding = embeddingVector();
    $user->save();

    $saved = UserPreference::where('user_id', $user->id)->first();
    expect($saved->recommendation_explanations)->toBeNull();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Regenerated overview');
});
