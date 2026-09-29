<?php

namespace App\Services\Chat;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Models\User;

/**
 * Deterministic catalog + conversation driver for the chatbot eval fixtures.
 *
 * Shared by `chatbot:eval` (offline: LLM stubbed via Http::fake) and
 * `chatbot:judge` (online: real Gemini, LLM-judged). Every seeded row is named
 * with a fixed "Eval " prefix and only tracked ids are deleted on cleanup, so a
 * run never reads or writes the real catalog. The harness itself does not fake
 * or make any HTTP call — the caller decides.
 */
class ChatEvalHarness
{
    private const DIMS = 3072;

    /** @var array<string, array<string, int>> catalog => name => id */
    private array $nameIds = [];

    /** @var int[] */
    private array $sessionIds = [];

    private ?User $evalUser = null;

    /** @var array<string, int[]> model class => ids */
    private array $seededIds = [];

    public function seed(): void
    {
        $this->purgeLeftovers();

        $boracay = $this->track(DestinationModel::factory()->create([
            'name' => 'Eval Boracay',
            'description' => 'Evaluation destination for deterministic chatbot retrieval.',
            'region' => 'Evaluation Region',
        ]));
        $elnido = $this->track(DestinationModel::factory()->create([
            'name' => 'Eval El Nido',
            'description' => 'Evaluation destination for deterministic chatbot retrieval.',
            'region' => 'Evaluation Region',
        ]));
        $this->track(DestinationModel::factory()->create([
            'name' => 'Eval Cebu',
            'description' => 'Evaluation destination with intentionally zero inventory.',
            'region' => 'Evaluation Region',
        ]));

        $palm = $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Palm Resort', 'destination_id' => $boracay->id,
            'type' => 'Resort', 'hotel_description' => 'Beachfront evaluation resort with family facilities.',
            'specific_address' => '1 Evaluation Beach Road', 'featured_amenities' => ['Pool', 'WiFi'],
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Palm Resort');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Bay Hotel', 'destination_id' => $boracay->id,
            'type' => 'Hotel', 'hotel_description' => 'Central evaluation hotel near local dining.',
            'specific_address' => '2 Evaluation Bay Road', 'featured_amenities' => ['WiFi'],
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.8),
        ]), 'Eval Bay Hotel');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Dunes Lodge', 'destination_id' => $boracay->id,
            'type' => 'Lodge', 'hotel_description' => 'Quiet evaluation lodge with garden views.',
            'specific_address' => '3 Evaluation Dunes Road', 'featured_amenities' => ['Garden'],
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.65),
        ]), 'Eval Dunes Lodge');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Cliff Lodge', 'destination_id' => $elnido->id,
            'type' => 'Lodge', 'hotel_description' => 'Evaluation cliff lodge for destination-scoping checks.',
            'specific_address' => '4 Evaluation Cliff Road', 'featured_amenities' => ['View Deck'],
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Cliff Lodge');
        $this->track(HotelModel::factory()->create([
            'hotel_name' => 'Eval Hidden Resort', 'destination_id' => $boracay->id,
            'type' => 'Resort', 'hotel_description' => 'Hidden evaluation property.',
            'specific_address' => '5 Evaluation Hidden Road', 'featured_amenities' => ['Pool'],
            'is_shown' => false, 'embedding' => $this->vectorString(0),
        ]), 'Eval Hidden Resort');

        $this->track(RoomType::factory()->create([
            'hotel_id' => $palm->id, 'room_name' => 'Eval Standard Room',
            'base_price' => 3000, 'base_occupancy' => 2, 'max_occupancy' => 2,
            'extra_person_fee' => 0, 'total_rooms' => 8, 'room_amenities' => ['WiFi', 'Air Conditioning'],
            'bed_configuration' => '1 Queen Bed', 'room_size' => '28 sqm',
            'description' => 'Standard evaluation room for up to two guests.', 'view_type' => 'Garden View',
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Standard Room');
        $this->track(RoomType::factory()->create([
            'hotel_id' => $palm->id, 'room_name' => 'Eval Family Suite',
            'base_price' => 4500, 'base_occupancy' => 2, 'max_occupancy' => 4,
            'extra_person_fee' => 500, 'total_rooms' => 4, 'room_amenities' => ['WiFi', 'Air Conditioning', 'Sofa Bed'],
            'bed_configuration' => '1 King Bed and 1 Sofa Bed', 'room_size' => '45 sqm',
            'description' => 'Family evaluation suite for up to four guests.', 'view_type' => 'Ocean View',
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.8),
        ]), 'Eval Family Suite');
        $this->track(RoomType::factory()->create([
            'hotel_id' => $palm->id, 'room_name' => 'Eval Flexible Room',
            'base_price' => 3500, 'base_occupancy' => 4, 'max_occupancy' => null,
            'extra_person_fee' => 0, 'total_rooms' => 3, 'room_amenities' => ['WiFi', 'Air Conditioning'],
            'bed_configuration' => '2 Double Beds', 'room_size' => '38 sqm',
            'description' => 'Flexible evaluation room with four included guests.', 'view_type' => 'Pool View',
            'is_shown' => true, 'embedding' => $this->gradedVectorString(0.65),
        ]), 'Eval Flexible Room');

        $this->track(ActivityModel::factory()->create([
            'activity_name' => 'Eval Paddle Tour', 'destination_id' => $boracay->id,
            'category' => 'Water Activity', 'activity_level' => 'Relaxing', 'rate' => '₱500/person',
            'duration' => '3 Hours', 'capacity' => 'Up to 10 guests', 'requirements' => 'Basic swimming ability.',
            'ideal_for' => 'Families', 'vibe_tags' => ['Water', 'Relaxing'],
            'description' => 'Guided evaluation paddle tour.', 'inclusions' => ['Guide', 'Life vest'],
            'exclusions' => [], 'itinerary' => [], 'notes' => 'Weather dependent.',
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Paddle Tour');
        $this->track(ActivityModel::factory()->create([
            'activity_name' => 'Eval Cliff Dive', 'destination_id' => $elnido->id,
            'category' => 'Adventure', 'activity_level' => 'Adventure', 'rate' => '₱800/person',
            'duration' => 'Half Day', 'capacity' => 'Up to 8 guests', 'requirements' => 'Minimum age 12.',
            'ideal_for' => 'Adventure travelers', 'vibe_tags' => ['Adventure'],
            'description' => 'Guided evaluation cliff dive activity.', 'inclusions' => ['Guide', 'Safety gear'],
            'exclusions' => [], 'itinerary' => [], 'notes' => 'Weather dependent.',
            'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Cliff Dive');

        $this->track(Package::factory()->create([
            'name' => 'Eval Weekend Escape', 'destination_id' => $boracay->id,
            'type' => 'Tour Package', 'price' => 12000, 'days' => 3, 'nights' => 2, 'min_pax' => 2,
            'generic_inclusions' => ['Hotel', 'Tour'], 'is_active' => true,
            'embedding' => $this->vectorString(0),
        ]), 'Eval Weekend Escape');

        $this->track(AddOnModel::factory()->create([
            'name' => 'Eval Airport Transfer', 'destination_id' => $boracay->id,
            'type' => 'Transfer', 'description' => 'Private evaluation airport transfer.',
            'inclusions' => ['Driver', 'Luggage assistance'],
            'pricing_tiers' => [['min_pax' => 1, 'max_pax' => 1, 'rate' => 1850], ['min_pax' => 2, 'max_pax' => 4, 'rate' => 1450]],
            'surcharges' => [], 'is_shown' => true, 'embedding' => $this->vectorString(0),
        ]), 'Eval Airport Transfer');

        $faq = Faq::create([
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept GCash, Maya, and all major credit cards.',
            'keywords' => 'payment, pay, bill, methods, gcash',
            'category' => 'Payments',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $faq->forceFill(['embedding' => $this->vectorString(0)])->save();
        $this->seededIds[Faq::class][] = $faq->id;

    }

    /**
     * Run one fixture case against the seeded catalog and return the raw
     * ChatbotService reply payload plus the measured handle() latency.
     * Multi-turn cases replay their `setup.prior_message` in the same session
     * first; that warm-up turn is excluded from the timing.
     *
     * @param  array<string, mixed>  $case
     * @return array{reply: array<string, mixed>, latency_ms: float}
     */
    public function ask(array $case): array
    {
        $user = ($case['auth'] ?? null) === 'personalized' ? $this->personalizedUser() : null;
        $session = ChatSession::create([
            'session_token' => ChatSession::generateToken(),
            'user_id' => $user?->id,
        ]);
        $this->sessionIds[] = $session->id;

        $service = app(ChatbotService::class);
        if (isset($case['setup']['prior_message'])) {
            $service->handle($session, $user, (string) $case['setup']['prior_message']);
        }

        $start = microtime(true);
        $reply = $service->handle($session, $user, (string) $case['query']);

        return ['reply' => $reply, 'latency_ms' => (microtime(true) - $start) * 1000];
    }

    /** Resolve fixture entity names to the seeded row ids. @return int[] */
    public function mapNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            foreach ($this->nameIds as $catalog) {
                if (isset($catalog[$name])) {
                    $ids[] = $catalog[$name];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** Delete only the rows this harness created. Safe to call without seed(). */
    public function cleanup(): void
    {
        if ($this->sessionIds !== []) {
            SupportInquiry::whereIn('chat_session_id', $this->sessionIds)->delete();
            ChatMessage::whereIn('chat_session_id', $this->sessionIds)->delete();
            ChatSession::whereIn('id', $this->sessionIds)->delete();
        }
        foreach (array_reverse($this->seededIds) as $class => $ids) {
            $class::whereIn('id', $ids)->delete();
        }
        $this->evalUser?->delete();
        $this->sessionIds = [];
        $this->seededIds = [];
        $this->evalUser = null;
    }

    /**
     * Delete rows a previous crashed run left behind, so seed() never hits
     * unique violations and no orphaned fixture rows pollute retrieval. Safe on
     * a clean database (matches nothing). The seeded FAQ carries a real
     * question, so it is identified by its synthetic one-hot embedding —
     * a genuine embedding never starts with "[1,0,".
     */
    public function purgeLeftovers(): void
    {
        $nameColumns = [
            DestinationModel::class => 'name',
            HotelModel::class => 'hotel_name',
            RoomType::class => 'room_name',
            ActivityModel::class => 'activity_name',
            Package::class => 'name',
            AddOnModel::class => 'name',
        ];
        foreach ($nameColumns as $class => $column) {
            $this->seededIds[$class] = array_merge(
                $this->seededIds[$class] ?? [],
                $class::where($column, 'like', 'Eval %')->pluck('id')->all()
            );
        }

        foreach (Faq::where('question', 'What payment methods do you accept?')->get(['id', 'embedding']) as $faq) {
            if (str_starts_with((string) $faq->embedding, '[1,0,')) {
                $this->seededIds[Faq::class][] = $faq->id;
            }
        }

        $user = User::where('email', 'eval-harness@example.com')->first();
        if ($user) {
            $this->sessionIds = array_merge(
                $this->sessionIds,
                ChatSession::where('user_id', $user->id)->pluck('id')->all()
            );
            $this->evalUser = $user;
        }

        $this->cleanup();
    }

    private function track(object $model, ?string $name = null): object
    {
        $this->seededIds[$model::class][] = $model->id;
        if ($name !== null) {
            $catalog = match ($model::class) {
                HotelModel::class => 'hotels',
                RoomType::class => 'rooms',
                ActivityModel::class => 'activities',
                Package::class => 'packages',
                AddOnModel::class => 'addons',
                default => 'misc',
            };
            $this->nameIds[$catalog][$name] = $model->id;
        }

        return $model;
    }

    /** Create the synthetic account only when a fixture actually needs it. */
    private function personalizedUser(): User
    {
        if ($this->evalUser !== null && User::whereKey($this->evalUser->id)->exists()) {
            return $this->evalUser;
        }

        $this->evalUser = User::factory()->create(['email' => 'eval-harness@example.com']);
        $this->evalUser->preferences_embedding = '['.implode(',', array_fill(0, self::DIMS, '0.01')).']';
        $this->evalUser->save();

        return $this->evalUser;
    }

    /**
     * One-hot 3072-dim vector, used to stub embedContent calls in offline runs.
     *
     * @return float[]
     */
    public function unitVector(int $hotIndex): array
    {
        $v = array_fill(0, self::DIMS, 0.0);
        $v[$hotIndex] = 1.0;

        return $v;
    }

    private function vectorString(int $hotIndex): string
    {
        $v = $this->unitVector($hotIndex);

        return '['.implode(',', $v).']';
    }

    private function gradedVectorString(float $x): string
    {
        $v = array_fill(0, self::DIMS, 0.0);
        $v[0] = $x;
        $v[1] = sqrt(max(0.0, 1.0 - $x * $x));

        return '['.implode(',', $v).']';
    }
}
