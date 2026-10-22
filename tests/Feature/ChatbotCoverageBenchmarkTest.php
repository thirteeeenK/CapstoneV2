<?php

use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\User;
use Illuminate\Support\Facades\File;

function coverageFixture(): array
{
    return json_decode((string) File::get(base_path('tests/Fixtures/chatbot_coverage_v1.json')), true, flags: JSON_THROW_ON_ERROR);
}

test('the coverage benchmark keeps its planned size, allocation and held-out set', function () {
    $cases = coverageFixture()['cases'];
    $categories = array_count_values(array_column($cases, 'category'));

    expect(count($cases))->toBe(100)
        ->and(count(array_unique(array_column($cases, 'id'))))->toBe(100)
        ->and(count(array_filter($cases, fn (array $c): bool => $c['split'] === 'heldout')))->toBeGreaterThanOrEqual(20)
        ->and($categories)->toEqual([
            'catalog' => 15, 'multi_intent' => 15, 'follow_up' => 20, 'comparison' => 10,
            'constraints' => 15, 'guidance' => 15, 'reliability' => 10,
        ])
        ->and(array_unique(array_column($cases, 'lang')))->toEqualCanonicalizing(['en', 'fil', 'taglish']);
});

test('every record a coverage case names exists in the synthetic catalog', function () {
    $fixture = coverageFixture();
    $names = [];
    foreach (['hotels', 'rooms', 'activities', 'packages', 'addons'] as $group) {
        $names = array_merge($names, array_column($fixture['catalog'][$group], 'name'));
    }

    foreach ($fixture['cases'] as $case) {
        foreach ($case['turns'] as $turn) {
            foreach (['any', 'all', 'none'] as $key) {
                expect(array_diff($turn['expect'][$key] ?? [], $names))->toBe([], "{$case['id']} {$key}");
            }
            expect(array_diff($turn['hide'] ?? [], $names))->toBe([], "{$case['id']} hide");
        }
    }
});

test('a coverage run reports per-case results and removes everything it seeded', function () {
    $counts = fn (): array => [User::withTrashed()->count(), ChatSession::count(), HotelModel::count(), DestinationModel::count()];
    $before = $counts();

    $this->artisan('chatbot:coverage', ['--case' => ['cat-01', 'cmp-01', 'rel-06']])->assertExitCode(0);

    expect($counts())->toBe($before);
    $report = json_decode((string) File::get(collect(File::glob(storage_path('app/eval/chatbot-coverage-*.json')))->sortByDesc(fn ($f) => filemtime($f))->first()), true);
    expect($report['meta']['mode'])->toBe('offline_stubbed')
        ->and(array_column($report['cases'], 'id'))->toBe(['cat-01', 'cmp-01', 'rel-06'])
        ->and($report['summary']['checked_turns'])->toBe(3)
        ->and(collect($report['cases'])->firstWhere('id', 'cat-01')['passed'])->toBeTrue()
        // A second visitor's "the second one" must not resolve the first visitor's results.
        ->and(collect($report['cases'])->firstWhere('id', 'rel-06')['turns'][1]['cards'])->toBe([]);
});

test('the offline coverage benchmark passes every case', function () {
    $this->artisan('chatbot:coverage')->assertExitCode(0);

    $report = json_decode((string) File::get(collect(File::glob(storage_path('app/eval/chatbot-coverage-*.json')))->sortByDesc(fn ($f) => filemtime($f))->first()), true);
    $failed = collect($report['cases'])->reject(fn (array $case): bool => $case['passed'])->pluck('id')->all();
    expect($failed)->toBe([])
        ->and($report['summary']['unsupported_claim_turns'])->toBe(0);
})->group('benchmark');
