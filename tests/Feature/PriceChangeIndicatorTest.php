<?php

use App\Models\ActivityModel;
use App\Models\AdminAuditLog;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;

function priceChangeAudit(string $type, int $id, ?array $old, array $new, ?DateTimeInterface $at = null): void
{
    $at ??= now();
    DB::table('admin_audit_logs')->insert([
        'admin_id' => null,
        'auditable_type' => $type,
        'auditable_id' => $id,
        'old_values' => $old ? json_encode($old) : null,
        'new_values' => json_encode($new),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

beforeEach(function () {
    $this->package = Package::factory()->create(['price' => 5000]);
});

test('reports an up direction when the latest price edit raised the price', function () {
    priceChangeAudit('package', $this->package->id, ['price' => 4000], ['price' => 5000]);

    $changes = AdminAuditLog::recentPriceChanges('package', [$this->package->id], 'price');

    expect($changes)->toHaveKey($this->package->id)
        ->and($changes[$this->package->id]['dir'])->toBe('up')
        ->and($changes[$this->package->id]['old'])->toBe(4000.0)
        ->and($changes[$this->package->id]['date'])->toBe(now()->format('M d, Y'));
});

test('reports a down direction when the latest price edit lowered the price', function () {
    priceChangeAudit('package', $this->package->id, ['price' => 6000], ['price' => 5000]);

    $changes = AdminAuditLog::recentPriceChanges('package', [$this->package->id], 'price');

    expect($changes[$this->package->id]['dir'])->toBe('down');
});

test('parses freeform activity rates like the ones stored in the rate column', function () {
    priceChangeAudit('activity', 7, ['rate' => '₱900/person'], ['rate' => '₱1,200/person']);

    $changes = AdminAuditLog::recentPriceChanges('activity', [7], 'rate');

    expect($changes[7]['dir'])->toBe('up')
        ->and($changes[7]['old'])->toBe(900.0);
});

test('ignores price edits older than the 30 day window', function () {
    priceChangeAudit('package', $this->package->id, ['price' => 4000], ['price' => 5000], now()->subDays(31));

    expect(AdminAuditLog::recentPriceChanges('package', [$this->package->id], 'price'))->toBe([]);
});

test('ignores create rows that only carry the new price', function () {
    priceChangeAudit('package', $this->package->id, null, ['price' => 5000]);

    expect(AdminAuditLog::recentPriceChanges('package', [$this->package->id], 'price'))->toBe([]);
});

test('ignores edits where the price did not actually change', function () {
    priceChangeAudit('package', $this->package->id, ['price' => 5000], ['price' => 5000]);

    expect(AdminAuditLog::recentPriceChanges('package', [$this->package->id], 'price'))->toBe([]);
});

test('package catalog renders the increase badge beside the price', function () {
    priceChangeAudit('package', $this->package->id, ['price' => 4000], ['price' => 5000]);

    $this->get(route('packages.index'))
        ->assertOk()
        ->assertSee('>trending_up</span>', false)
        ->assertSee('Was ₱4,000.00 on '.now()->format('M d, Y'));
});

test('catalog renders no badge when there is no recent price edit', function () {
    $this->get(route('packages.index'))
        ->assertOk()
        ->assertDontSee('>trending_up</span>', false);
});

test('room preview endpoint includes the recent price change', function () {
    $destination = DestinationModel::factory()->create();
    $hotel = HotelModel::factory()->create(['destination_id' => $destination->id]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_price' => 10000]);
    priceChangeAudit('room', $room->id, ['base_price' => 9700], ['base_price' => 10000]);

    $response = $this->getJson("/rooms/{$room->id}/preview")->assertOk();

    expect($response->json('price_change.dir'))->toBe('up')
        ->and($response->json('price_change.old'))->toEqual(9700.0);
});

test('room preview endpoint returns a null price change without a recent edit', function () {
    $destination = DestinationModel::factory()->create();
    $hotel = HotelModel::factory()->create(['destination_id' => $destination->id]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_price' => 10000]);

    $this->getJson("/rooms/{$room->id}/preview")
        ->assertOk()
        ->assertJsonPath('price_change', null);
});

test('activity preview endpoint includes the recent price change', function () {
    $destination = DestinationModel::factory()->create();
    $activity = ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'rate' => '₱1,200/person',
    ]);
    priceChangeAudit('activity', $activity->id, ['rate' => '₱900/person'], ['rate' => '₱1,200/person']);

    $response = $this->getJson("/activities/{$activity->id}/preview")->assertOk();

    expect($response->json('price_change.dir'))->toBe('up')
        ->and($response->json('price_change.old'))->toEqual(900.0);
});

test('hotel show renders the room price change chip beside the card price', function () {
    $destination = DestinationModel::factory()->create();
    $hotel = HotelModel::factory()->create(['destination_id' => $destination->id, 'is_shown' => true]);
    $room = RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'is_shown' => true,
        'base_price' => 10000,
    ]);
    priceChangeAudit('room', $room->id, ['base_price' => 9700], ['base_price' => 10000]);

    $this->get(route('hotels.show', $hotel))
        ->assertOk()
        ->assertSee('>trending_up</span>', false)
        ->assertSee('Was ₱9,700.00 on '.now()->format('M d, Y'));
});

test('destination show renders the activity price change chip', function () {
    $destination = DestinationModel::factory()->create();
    $activity = ActivityModel::factory()->create([
        'destination_id' => $destination->id,
        'is_shown' => true,
        'rate' => '₱1,200/person',
    ]);
    priceChangeAudit('activity', $activity->id, ['rate' => '₱900/person'], ['rate' => '₱1,200/person']);

    $this->get(route('destinations.show', $destination))
        ->assertOk()
        ->assertSee('>trending_up</span>', false)
        ->assertSee('Was ₱900.00 on '.now()->format('M d, Y'));
});
