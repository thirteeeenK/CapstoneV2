<?php

use App\Services\Chat\ChatCapabilities;

test('recognizes unsupported execution requests', function (string $message, string $operation) {
    expect((new ChatCapabilities)->detect($message))->toBe($operation);
})->with([
    ['book this hotel for me', 'book'], ['cancel my reservation', 'cancel'],
    ['refund my payment', 'refund'], ['email the hotel', 'contact'],
    ['watch the prices and notify me tomorrow', 'monitor'], ['add this to my basket', 'basket'],
]);

test('does not refuse supported lookups or process guidance as execution', function (string $message) {
    expect((new ChatCapabilities)->detect($message))->toBeNull();
})->with(['find hotels', 'check availability', 'make an itinerary', 'how do I book a hotel?', 'where can I pay?', 'what is the refund policy?']);
