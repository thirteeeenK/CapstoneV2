<?php

use App\Models\AdminModel;
use App\Models\QrphPaymentAttempt;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** @return array<string, mixed> */
function paymongoIntentFixture(QrphPaymentAttempt $attempt, string $status = 'awaiting_next_action'): array
{
    $attributes = [
        'amount' => $attempt->amount, 'currency' => 'PHP', 'livemode' => $attempt->livemode,
        'status' => $status, 'client_key' => 'fixture_client_key',
        'metadata' => ['booking_code' => $attempt->booking->booking_code, 'attempt_id' => $attempt->uuid],
        'next_action' => ['code' => [
            'id' => $attempt->qr_code_id ?? 'qr_fixture',
            'image_url' => $attempt->qr_image ?? 'data:image/png;base64,'.base64_encode('fixture'),
            'test_url' => 'https://secure-authentication.paymongo.com/fixture',
        ]],
        'payments' => [],
    ];
    if ($status === 'succeeded') {
        $attributes['payments'][] = [
            'id' => 'pay_fixture'.$attempt->id, 'type' => 'payment',
            'attributes' => [
                'status' => 'paid', 'payment_intent_id' => $attempt->intent_id,
                'amount' => $attempt->amount, 'currency' => 'PHP', 'livemode' => $attempt->livemode,
                'source' => ['id' => $attempt->method_id, 'type' => 'qrph'],
            ],
        ];
    }

    return ['data' => ['id' => $attempt->intent_id ?? ($attempt->livemode ? 'pi_livefixture' : 'pi_fixture'), 'type' => 'payment_intent', 'attributes' => $attributes]];
}

function fakePaymongoCreation(): void
{
    Http::fake(function ($request) {
        $attempt = QrphPaymentAttempt::latest('id')->firstOrFail();
        if ($request->url() === 'https://api.paymongo.com/v1/payment_methods') {
            return Http::response([
                'data' => ['id' => 'pm_fixture', 'type' => 'payment_method', 'attributes' => ['type' => 'qrph']],
            ]);
        }
        if ($request->url() === 'https://api.paymongo.com/v1/payment_intents') {
            return Http::response(paymongoIntentFixture($attempt, 'awaiting_payment_method'));
        }
        if (str_starts_with($request->url(), 'https://api.paymongo.com/v1/payment_intents/') && str_ends_with($request->url(), '/attach')) {
            return Http::response(paymongoIntentFixture($attempt));
        }
        throw new LogicException('Unexpected HTTP request in QR Ph fixture.');
    });
}

/** @return array{0: string, 1: array<string, string>} */
function paymongoWebhookFixture(QrphPaymentAttempt $attempt, string $type = 'payment.paid', string $eventId = 'evt_fixture'): array
{
    $resource = $type === 'qrph.expired'
        ? ['id' => $attempt->qr_code_id, 'type' => 'qrph', 'attributes' => ['payment_intent_id' => $attempt->intent_id]]
        : paymongoIntentFixture($attempt, 'succeeded')['data']['attributes']['payments'][0];
    if ($type === 'payment.failed') {
        $resource['attributes']['status'] = 'failed';
    }
    $payload = json_encode(['data' => ['id' => $eventId, 'type' => 'event', 'attributes' => [
        'type' => $type, 'livemode' => $attempt->livemode, 'data' => $resource,
    ]]], JSON_THROW_ON_ERROR);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('services.'.($attempt->livemode ? 'paymongo' : 'qrph').'.webhook_secret'));
    $field = $attempt->livemode ? 'li' : 'te';

    return [$payload, ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},{$field}={$signature}"]];
}

function onboardedUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->preferences_embedding = '['.implode(',', array_fill(0, 3072, '0.01')).']';
    $user->save();

    return $user;
}

function settingsAdmin(array $attributes = []): AdminModel
{
    return AdminModel::create(array_merge([
        'name' => 'Settings Admin', 'email' => fake()->unique()->safeEmail(),
        'password' => 'password', 'two_factor_required' => false,
    ], $attributes));
}

function unitVectorString(int $hotIndex, int $dims = 3072): string
{
    $v = array_fill(0, $dims, 0.0);
    $v[$hotIndex] = 1.0;

    return '['.implode(',', $v).']';
}

function unitVector(int $hotIndex, int $dims = 3072): array
{
    $v = array_fill(0, $dims, 0.0);
    $v[$hotIndex] = 1.0;

    return $v;
}

function mockGemini(?array $queryVector, string $chatReply = 'Here are the ATV activities I found in Boracay!'): void
{
    $mock = Mockery::mock(GeminiService::class)->makePartial();
    $mock->shouldReceive('generateEmbedding')->andReturn($queryVector);
    $mock->shouldReceive('generateChatResponse')->andReturn($chatReply);
    app()->instance(GeminiService::class, $mock);
}
