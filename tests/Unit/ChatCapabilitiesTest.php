<?php

use App\Services\Chat\ChatCapabilities;

test('recognizes unsupported execution requests', function (string $message, string $operation) {
    expect((new ChatCapabilities)->detect($message))->toBe($operation);
})->with([
    ['book this hotel for me', 'book'], ['cancel my reservation', 'cancel'],
    ['refund my payment', 'refund'], ['email the hotel', 'contact'],
    ['watch the prices and notify me tomorrow', 'monitor'], ['add this to my basket', 'basket'],
    ['save this itinerary to my trip plan', 'trip_plan'],
]);

test('does not refuse supported lookups or process guidance as execution', function (string $message) {
    expect((new ChatCapabilities)->detect($message))->toBeNull();
})->with(['find hotels', 'check availability', 'make an itinerary', 'how do I book a hotel?', 'where can I pay?', 'what is the refund policy?']);

test('a general capability question is recognised but a concrete request is not', function (string $message, bool $expected) {
    expect((new ChatCapabilities)->isCapabilityQuestion($message))->toBe($expected);
})->with([
    ['what can you do?', true], ['How can you help me?', true], ['ano ang kaya mo?', true],
    ['what can you do about my booking?', false], ['find hotels in Boracay', false],
]);

test('the capability overview states both what is supported and what is not', function () {
    expect((new ChatCapabilities)->overview())->toContain('hotels')->toContain('cannot')->toContain('human support agent');
});
