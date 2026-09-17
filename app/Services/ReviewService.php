<?php

namespace App\Services;

use App\Concerns\ResolvesImages;
use App\Jobs\ProcessReviewSentimentJob;
use App\Jobs\UpdateEntityReviewSummaryJob;
use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReviewService
{
    use ResolvesImages;

    /**
     * Map of BookingItem item_type => reviewable class.
     * Add-ons/transfers are intentionally not reviewable per the spec.
     */
    public const REVIEWABLE_ITEM_TYPES = [
        'room' => RoomType::class,
        'activity' => ActivityModel::class,
        'package' => Package::class,
    ];

    /**
     * Resolve the reviewable entity + direct FK columns from a booking item.
     *
     * @return array{type: string, id: int, hotel_id: ?int, room_id: ?int, activity_id: ?int, package_id: ?int, label: string}|null
     */
    public function resolveReviewableFromItem(BookingItem $item): ?array
    {
        $class = self::REVIEWABLE_ITEM_TYPES[$item->item_type] ?? null;

        if (! $class) {
            return null;
        }

        $target = $item->itemable;

        if (! $target) {
            return null;
        }

        $payload = [
            'type' => (new $class)->getMorphClass(),
            'id' => (int) $target->getKey(),
            'hotel_id' => null,
            'room_id' => null,
            'activity_id' => null,
            'package_id' => null,
            'label' => $target->hotel_name ?? $target->room_name ?? $target->activity_name ?? $target->name ?? 'Listing',
        ];

        if ($target instanceof RoomType) {
            $payload['hotel_id'] = $target->hotel_id;
            $payload['room_id'] = $target->getKey();
        } elseif ($target instanceof ActivityModel) {
            $payload['activity_id'] = $target->getKey();
        } elseif ($target instanceof Package) {
            $payload['package_id'] = $target->getKey();
        }

        return $payload;
    }

    /**
     * Validate that a booking item is eligible for review submission by this user.
     */
    public function assertEligible(User $user, Booking $booking, ?int $bookingItemId = null): void
    {
        abort_unless($booking->user_id === $user->id, 403, 'This booking does not belong to you.');
        abort_unless($booking->status === Booking::STATUS_COMPLETED, 422, 'Only completed bookings can be reviewed.');

        if ($bookingItemId) {
            abort_if(
                Review::where('booking_id', $booking->id)->where('booking_item_id', $bookingItemId)->exists(),
                422,
                'This item has already been reviewed.'
            );
        } else {
            abort_if(Review::where('booking_id', $booking->id)->whereNull('booking_item_id')->exists(), 422, 'This booking has already been reviewed.');
        }
    }

    /**
     * Store a verified review, then kick off async AI sentiment + summary jobs.
     *
     * @param  array<int, UploadedFile>|null  $imageFiles
     */
    public function store(User $user, Booking $booking, int $rating, string $comment, ?int $bookingItemId = null, ?array $imageFiles = null): Review
    {
        $this->assertEligible($user, $booking, $bookingItemId);

        $item = null;

        if ($bookingItemId) {
            $item = $booking->items()->whereKey($bookingItemId)->first();
        }

        if (! $item) {
            $item = $booking->items()
                ->whereIn('item_type', array_keys(self::REVIEWABLE_ITEM_TYPES))
                ->first();
        }

        abort_unless($item !== null, 422, 'This booking has no reviewable items.');

        $target = $this->resolveReviewableFromItem($item);

        abort_unless($target !== null, 422, 'This booking has no reviewable items.');

        $imagePaths = $this->storeReviewImages($imageFiles);

        $review = Review::create([
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'user_id' => $user->id,
            'reviewable_type' => $target['type'],
            'reviewable_id' => $target['id'],
            'hotel_id' => $target['hotel_id'],
            'room_id' => $target['room_id'],
            'activity_id' => $target['activity_id'],
            'package_id' => $target['package_id'],
            'rating' => $rating,
            'comment' => $comment,
            'images' => $imagePaths,
            'sentiment' => Review::SENTIMENT_NEUTRAL,
            'sentiment_score' => 0.5000,
            'extracted_keywords' => [],
            'is_verified_booking' => true,
            'is_published' => true,
        ]);

        dispatch(new ProcessReviewSentimentJob($review->id));

        dispatch(new UpdateEntityReviewSummaryJob($target['type'], $target['id'], $target['label']));
        dispatch(new UpdateEntityReviewSummaryJob(ReviewSummary::PLATFORM_OVERALL_TYPE, null, 'SunnyTrips Overall Platform'));

        return $review->fresh(['user']);
    }

    /**
     * Admin-authored backfill review for a past booking that was never
     * reviewed. Bypasses user ownership checks but still requires a
     * completed booking with no existing review.
     *
     * @param  array<int, UploadedFile>|null  $imageFiles
     */
    public function adminStore(Booking $booking, int $rating, string $comment, ?int $bookingItemId = null, ?User $user = null, ?array $imageFiles = null): Review
    {
        abort_unless($booking->status === Booking::STATUS_COMPLETED, 422, 'Only completed bookings can be reviewed.');

        if ($bookingItemId) {
            abort_if(
                Review::where('booking_id', $booking->id)->where('booking_item_id', $bookingItemId)->exists(),
                422,
                'This item has already been reviewed.'
            );
        }

        $reviewer = $user ?? $booking->user;

        abort_unless($reviewer !== null, 422, 'This booking has no guest account to attribute the review to.');

        $reviewedItemIds = Review::where('booking_id', $booking->id)->pluck('booking_item_id')->filter();

        $item = null;

        if ($bookingItemId) {
            $item = $booking->items()->whereKey($bookingItemId)->first();
        }

        if (! $item) {
            $item = $booking->items()
                ->whereIn('item_type', array_keys(self::REVIEWABLE_ITEM_TYPES))
                ->whereNotIn('id', $reviewedItemIds)
                ->first();
        }

        abort_unless($item !== null, 422, 'This booking has no unreviewed items.');

        $target = $this->resolveReviewableFromItem($item);

        abort_unless($target !== null, 422, 'This booking has no reviewable items.');

        $imagePaths = $this->storeReviewImages($imageFiles);

        $review = Review::create([
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'user_id' => $reviewer->id,
            'reviewable_type' => $target['type'],
            'reviewable_id' => $target['id'],
            'hotel_id' => $target['hotel_id'],
            'room_id' => $target['room_id'],
            'activity_id' => $target['activity_id'],
            'package_id' => $target['package_id'],
            'rating' => $rating,
            'comment' => $comment,
            'images' => $imagePaths,
            'sentiment' => Review::SENTIMENT_NEUTRAL,
            'sentiment_score' => 0.5000,
            'extracted_keywords' => [],
            'is_verified_booking' => true,
            'is_published' => true,
        ]);

        dispatch(new ProcessReviewSentimentJob($review->id));

        dispatch(new UpdateEntityReviewSummaryJob($target['type'], $target['id'], $target['label']));
        dispatch(new UpdateEntityReviewSummaryJob(ReviewSummary::PLATFORM_OVERALL_TYPE, null, 'SunnyTrips Overall Platform'));

        return $review->fresh(['user']);
    }

    /**
     * Manually authored review by an admin for pre-system bookings
     * or external feedback. No booking or user record required.
     *
     * @param  array<int, UploadedFile>|null  $imageFiles
     */
    public function manualAdminStore(string $reviewerName, string $entityType, int $entityId, int $rating, string $comment, ?array $imageFiles = null): Review
    {
        $class = Relation::getMorphedModel($entityType) ?? $entityType;

        abort_unless(class_exists($class), 422, "Unknown entity type: {$entityType}");

        $entity = $class::find($entityId);

        abort_unless($entity !== null, 422, "Entity not found: {$entityType} #{$entityId}");

        $label = $entity->hotel_name ?? $entity->room_name ?? $entity->activity_name ?? $entity->name ?? 'Listing';

        $payload = [
            'type' => $entityType,
            'id' => $entityId,
            'hotel_id' => null,
            'room_id' => null,
            'activity_id' => null,
            'package_id' => null,
            'label' => $label,
        ];

        if ($entity instanceof RoomType) {
            $payload['hotel_id'] = $entity->hotel_id;
            $payload['room_id'] = $entity->getKey();
        } elseif ($entity instanceof ActivityModel) {
            $payload['activity_id'] = $entity->getKey();
        } elseif ($entity instanceof Package) {
            $payload['package_id'] = $entity->getKey();
        } elseif ($entity instanceof HotelModel) {
            $payload['hotel_id'] = $entity->getKey();
        }

        $imagePaths = $this->storeReviewImages($imageFiles);

        $review = Review::create([
            'booking_id' => null,
            'user_id' => null,
            'reviewer_name' => $reviewerName,
            'reviewable_type' => $payload['type'],
            'reviewable_id' => $payload['id'],
            'hotel_id' => $payload['hotel_id'],
            'room_id' => $payload['room_id'],
            'activity_id' => $payload['activity_id'],
            'package_id' => $payload['package_id'],
            'rating' => $rating,
            'comment' => $comment,
            'images' => $imagePaths,
            'sentiment' => Review::SENTIMENT_NEUTRAL,
            'sentiment_score' => 0.5000,
            'extracted_keywords' => [],
            'is_verified_booking' => false,
            'is_published' => true,
        ]);

        dispatch(new ProcessReviewSentimentJob($review->id));

        dispatch(new UpdateEntityReviewSummaryJob($payload['type'], $payload['id'], $payload['label']));
        dispatch(new UpdateEntityReviewSummaryJob(ReviewSummary::PLATFORM_OVERALL_TYPE, null, 'SunnyTrips Overall Platform'));

        return $review;
    }

    /**
     * Completed bookings of the user with per-item review status for the modal picker.
     * Returns all completed bookings; each reviewable item includes an is_reviewed flag.
     */
    public function eligibleBookings(User $user): Collection
    {
        return Booking::with(['items'])
            ->where('user_id', $user->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->latest()
            ->get()
            ->map(function (Booking $booking) {
                $items = $booking->items
                    ->filter(fn ($item) => array_key_exists($item->item_type, self::REVIEWABLE_ITEM_TYPES))
                    ->map(function (BookingItem $item) {
                        $review = Review::where('booking_id', $item->booking_id)
                            ->where('booking_item_id', $item->id)
                            ->first();

                        return [
                            'id' => $item->id,
                            'item_type' => $item->item_type,
                            'item_title' => $item->item_title,
                            'item_subtitle' => $item->item_subtitle,
                            'hotel_name' => $item->hotel_name,
                            'check_in_date' => $item->check_in_date?->format('Y-m-d'),
                            'check_out_date' => $item->check_out_date?->format('Y-m-d'),
                            'is_reviewed' => $review !== null,
                            'existing_review' => $review ? [
                                'id' => $review->id,
                                'rating' => $review->rating,
                                'comment' => $review->comment,
                            ] : null,
                        ];
                    })
                    ->values();

                $hasUnreviewed = $items->contains('is_reviewed', false);

                return [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'reviewable_items' => $items,
                    'has_unreviewed_items' => $hasUnreviewed,
                ];
            })
            ->filter(fn ($b) => $b['has_unreviewed_items'])
            ->values();
    }

    /**
     * Published reviews feed for the /reviews discovery hub.
     */
    public function feed(string $tab = 'all', ?string $sentiment = null, int $minRating = 0, string $sort = 'recent', int $limit = 100): Collection
    {
        $query = Review::published()->with(['user', 'reviewable']);

        if ($tab !== 'all') {
            $typeMap = [
                'hotels' => [(new HotelModel)->getMorphClass(), (new RoomType)->getMorphClass()],
                'rooms' => [(new RoomType)->getMorphClass()],
                'activities' => [(new ActivityModel)->getMorphClass()],
                'packages' => [(new Package)->getMorphClass()],
            ];

            $classes = $typeMap[$tab] ?? null;
            if ($classes) {
                $query->whereIn('reviewable_type', $classes);
            }
        }

        if ($sentiment && in_array($sentiment, [Review::SENTIMENT_POSITIVE, Review::SENTIMENT_NEUTRAL, Review::SENTIMENT_NEGATIVE], true)) {
            $query->where('sentiment', $sentiment);
        }

        if ($minRating > 0) {
            $query->where('rating', $minRating);
        }

        switch ($sort) {
            case 'highest':
                $query->orderByDesc('rating')->orderByDesc('id');
                break;
            case 'lowest':
                $query->orderBy('rating')->orderByDesc('id');
                break;
            case 'helpful':
                $query->latest();
                break;
            default:
                $query->latest();
        }

        $reviews = $query->limit($limit)->get();

        if ($sort === 'helpful') {
            $reviews = $reviews->sortByDesc(fn (Review $review) => count($review->extracted_keywords ?? []))->values();
        }

        return $reviews->map(fn (Review $review) => $this->presentForFeed($review));
    }

    /**
     * Paginated variant of feed() for the /reviews hub: every filter runs in
     * SQL so all published reviews are reachable across pages (no 200 cap).
     *
     * @return LengthAwarePaginator<int, array>
     */
    public function feedPaginated(string $tab = 'all', ?string $sentiment = null, int $minRating = 0, string $sort = 'recent', ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        $query = Review::published()->with(['user', 'reviewable']);

        if ($tab !== 'all') {
            $typeMap = [
                'hotels' => [(new HotelModel)->getMorphClass(), (new RoomType)->getMorphClass()],
                'rooms' => [(new RoomType)->getMorphClass()],
                'activities' => [(new ActivityModel)->getMorphClass()],
                'packages' => [(new Package)->getMorphClass()],
            ];

            $classes = $typeMap[$tab] ?? null;
            if ($classes) {
                $query->whereIn('reviewable_type', $classes);
            }
        }

        if ($sentiment && in_array($sentiment, [Review::SENTIMENT_POSITIVE, Review::SENTIMENT_NEUTRAL, Review::SENTIMENT_NEGATIVE], true)) {
            $query->where('sentiment', $sentiment);
        }

        if ($minRating > 0) {
            $query->where('rating', $minRating);
        }

        if (trim((string) $search) !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $search)).'%';

            $query->where(function ($q) use ($like) {
                $q->where('comment', 'ilike', $like)
                    ->orWhere('reviewer_name', 'ilike', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $like))
                    ->orWhereHasMorph('reviewable', [HotelModel::class, RoomType::class, ActivityModel::class, Package::class], function (EloquentBuilder $mq, string $type) use ($like) {
                        match ($type) {
                            HotelModel::class => $mq->where('hotel_name', 'ilike', $like),
                            RoomType::class => $mq->where('room_name', 'ilike', $like)
                                ->orWhereHas('hotel', fn ($h) => $h->where('hotel_name', 'ilike', $like)),
                            ActivityModel::class => $mq->where('activity_name', 'ilike', $like),
                            Package::class => $mq->where('name', 'ilike', $like),
                        };
                    });
            });
        }

        switch ($sort) {
            case 'highest':
                $query->orderByDesc('rating')->orderByDesc('id');
                break;
            case 'lowest':
                $query->orderBy('rating')->orderByDesc('id');
                break;
            case 'helpful':
                $query->orderByRaw('COALESCE(jsonb_array_length(extracted_keywords), 0) DESC')->orderByDesc('id');
                break;
            default:
                $query->latest();
        }

        $paginator = $query->paginate($perPage);
        $paginator->setCollection($paginator->getCollection()->map(fn (Review $review) => $this->presentForFeed($review)));

        return $paginator;
    }

    /**
     * Present a review as a flat, JSON-friendly payload for the hub + modals.
     */
    public function presentForFeed(Review $review): array
    {
        $target = $review->reviewable;

        $label = 'Listing';
        $location = null;

        if ($target instanceof RoomType) {
            $label = $target->room_name;
            $location = $target->hotel?->hotel_name;
        } elseif ($target instanceof HotelModel) {
            $label = $target->hotel_name;
            $location = $target->destination?->name;
        } elseif ($target instanceof ActivityModel) {
            $label = $target->activity_name;
            $location = $target->destination?->name;
        } elseif ($target instanceof Package) {
            $label = $target->name;
            $location = $target->destination?->name;
        }

        $images = $review->images ?? [];

        return [
            'id' => $review->id,
            'entity_type' => $review->reviewable_type,
            'entity_label' => $label,
            'entity_location' => $location,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'images' => array_map(fn (?string $p) => $p ? self::resolveImg($p) : null, $images),
            'images_raw' => $images,
            'sentiment' => $review->sentiment,
            'sentiment_score' => (float) $review->sentiment_score,
            'keywords' => $review->extracted_keywords ?? [],
            'reviewer_alias' => $review->reviewer_alias,
            'is_verified_booking' => (bool) $review->is_verified_booking,
            'created_at' => $review->created_at?->toIso8601String(),
            'created_at_label' => $review->created_at?->format('M j, Y'),
        ];
    }

    /**
     * Latest published reviews for an entity (used on detail views).
     */
    public function recentFor(string $entityType, int $entityId, int $limit = 5): Collection
    {
        return Review::published()
            ->ofEntity($entityType, $entityId)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Review $review) => $this->presentForFeed($review));
    }

    /**
     * Persist up to 3 review images to the configured disk.
     *
     * @param  array<int, UploadedFile>|null  $files
     * @return array<int, string>
     */
    protected function storeReviewImages(?array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $disk = config('filesystems.reviews_disk', config('filesystems.default', 'public'));
        $paths = [];

        foreach (array_slice($files, 0, 3) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $paths[] = $file->store('reviews', $disk);
        }

        return $paths;
    }

    /**
     * Delete review image files from storage.
     *
     * @param  array<int, string>|null  $paths
     */
    public function deleteReviewImages(?array $paths): void
    {
        if (empty($paths)) {
            return;
        }

        $disk = config('filesystems.reviews_disk', config('filesystems.default', 'public'));

        foreach ($paths as $path) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Summary cache record for an entity (null = no reviews yet).
     */
    public function summaryFor(string $entityType, ?int $entityId = null): ?ReviewSummary
    {
        return ReviewSummary::where('summarizable_type', $entityType)
            ->where('summarizable_id', $entityId)
            ->first();
    }

    /**
     * Platform-wide overall summary.
     */
    public function platformSummary(): ?ReviewSummary
    {
        return $this->summaryFor(ReviewSummary::PLATFORM_OVERALL_TYPE);
    }
}
