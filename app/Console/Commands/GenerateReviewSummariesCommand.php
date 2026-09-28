<?php

namespace App\Console\Commands;

use App\Jobs\UpdateEntityReviewSummaryJob;
use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class GenerateReviewSummariesCommand extends Command
{
    protected $signature = 'summary:generate
        {--type=all : Entity scope: all, hotel, or room}
        {--hotel= : Exact hotel name or numeric hotel ID}
        {--room= : Exact room name or numeric room ID}
        {--force : Regenerate entities that already have an AI summary}
        {--sync : Generate immediately instead of adding queue jobs}
        {--dry-run : Show planned work without writing or dispatching jobs}';

    protected $description = 'Generate missing AI review summaries for hotels and room types with eligible published reviews';

    /**
     * @var array<int, string>
     */
    private const ANALYZED_SENTIMENTS = [
        Review::SENTIMENT_POSITIVE,
        Review::SENTIMENT_NEUTRAL,
        Review::SENTIMENT_NEGATIVE,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = strtolower((string) $this->option('type'));
        $hotelFilter = $this->option('hotel');
        $roomFilter = $this->option('room');

        if (! in_array($type, ['all', 'hotel', 'room'], true)) {
            $this->error('The --type option must be one of: all, hotel, room.');

            return self::FAILURE;
        }

        if ($type === 'hotel' && $roomFilter !== null) {
            $this->error('The --room option can only be used with --type=room.');

            return self::FAILURE;
        }

        if ($type === 'all' && $roomFilter !== null) {
            $this->error('Use --type=room when targeting a specific room.');

            return self::FAILURE;
        }

        $hotel = $this->findHotel($hotelFilter);
        if ($hotelFilter !== null && $hotel === null) {
            return self::FAILURE;
        }

        $room = $this->findRoom($roomFilter);
        if ($roomFilter !== null && $room === null) {
            return self::FAILURE;
        }

        if ($hotel && $room && $room->hotel_id !== $hotel->id) {
            $this->error('The selected room does not belong to the selected hotel.');

            return self::FAILURE;
        }

        $stats = [
            'generated' => 0,
            'queued' => 0,
            'would_generate' => 0,
            'skipped_existing' => 0,
            'skipped_no_reviews' => 0,
            'skipped_pending_only' => 0,
            'failed' => 0,
        ];

        if ($this->option('dry-run')) {
            $this->info('Dry run only: no summaries will be written and no jobs will be dispatched.');
        } elseif ($this->option('sync')) {
            $this->warn('Running synchronously. This can take time because each eligible entity calls the AI service.');
        }

        if ($type === 'all' || $type === 'hotel') {
            $this->generateHotels($hotel, $stats);
        }

        if ($type === 'all' || $type === 'room') {
            $this->generateRooms($hotel, $room, $stats);
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: generated=%d queued=%d would_generate=%d skipped_existing=%d skipped_no_reviews=%d skipped_pending_only=%d failed=%d',
            $stats['generated'],
            $stats['queued'],
            $stats['would_generate'],
            $stats['skipped_existing'],
            $stats['skipped_no_reviews'],
            $stats['skipped_pending_only'],
            $stats['failed']
        ));

        if (! $this->option('dry-run') && ! $this->option('sync') && $stats['queued'] > 0) {
            $this->line('Start a worker to process queued summaries: php artisan queue:work --stop-when-empty');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function generateHotels(?HotelModel $hotel, array &$stats): void
    {
        $query = HotelModel::query()
            ->select(['id', 'hotel_name'])
            ->with('reviewSummary')
            ->withCount([
                'reviews as published_reviews_count' => fn ($query) => $query->published(),
                'reviews as analyzed_reviews_count' => fn ($query) => $query->published()
                    ->whereIn('sentiment', self::ANALYZED_SENTIMENTS),
            ]);

        if ($hotel) {
            $query->whereKey($hotel->id);
        }

        foreach ($query->lazyById(100) as $target) {
            $this->processTarget($target, 'hotel', $target->hotel_name, $stats);
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function generateRooms(?HotelModel $hotel, ?RoomType $room, array &$stats): void
    {
        $query = RoomType::query()
            ->select(['id', 'hotel_id', 'room_name'])
            ->with(['hotel:id,hotel_name', 'reviewSummary'])
            ->withCount([
                'reviews as published_reviews_count' => fn ($query) => $query->published(),
                'reviews as analyzed_reviews_count' => fn ($query) => $query->published()
                    ->whereIn('sentiment', self::ANALYZED_SENTIMENTS),
            ]);

        if ($hotel) {
            $query->where('hotel_id', $hotel->id);
        }

        if ($room) {
            $query->whereKey($room->id);
        }

        foreach ($query->lazyById(100) as $target) {
            $label = $target->room_name.' at '.($target->hotel?->hotel_name ?? 'Unknown hotel');
            $this->processTarget($target, 'room', $label, $stats);
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function processTarget(Model $target, string $entityType, string $label, array &$stats): void
    {
        $publishedCount = (int) $target->published_reviews_count;
        $analyzedCount = (int) $target->analyzed_reviews_count;

        if ($publishedCount === 0) {
            $stats['skipped_no_reviews']++;
            $this->line("Skipped {$entityType} #{$target->id} ({$label}): no published reviews.");

            return;
        }

        if ($analyzedCount === 0) {
            $stats['skipped_pending_only']++;
            $this->line("Skipped {$entityType} #{$target->id} ({$label}): published reviews are not sentiment-analyzed yet.");

            return;
        }

        $currentSummary = trim((string) ($target->reviewSummary?->ai_summary_text ?? ''));
        if ($currentSummary !== '' && ! $this->option('force')) {
            $stats['skipped_existing']++;
            $this->line("Skipped {$entityType} #{$target->id} ({$label}): AI summary already exists.");

            return;
        }

        $action = $currentSummary === '' ? 'generate' : 'regenerate';

        if ($this->option('dry-run')) {
            $this->line("Would {$action} {$entityType} #{$target->id} ({$label}) from {$analyzedCount} analyzed review(s).");
            $stats['would_generate']++;

            return;
        }

        $this->line(ucfirst($action)." {$entityType} #{$target->id} ({$label}) from {$analyzedCount} analyzed review(s).");

        $force = $currentSummary === '' || (bool) $this->option('force');

        try {
            $job = new UpdateEntityReviewSummaryJob($entityType, $target->id, $label, $force);

            if ($this->option('sync')) {
                dispatch_sync($job);

                $stored = trim((string) ReviewSummary::where('summarizable_type', $entityType)
                    ->where('summarizable_id', $target->id)
                    ->value('ai_summary_text'));

                if ($stored === '') {
                    $stats['failed']++;
                    $this->error("No AI summary was stored for {$entityType} #{$target->id} ({$label}).");

                    return;
                }

                $stats['generated']++;

                return;
            }

            dispatch($job);
            $stats['queued']++;
        } catch (Throwable $exception) {
            $stats['failed']++;
            $this->error("Failed to {$action} {$entityType} #{$target->id} ({$label}): {$exception->getMessage()}");
        }
    }

    private function findHotel(mixed $filter): ?HotelModel
    {
        if ($filter === null) {
            return null;
        }

        $query = HotelModel::query();
        if (ctype_digit((string) $filter)) {
            $hotel = $query->find((int) $filter);
            if (! $hotel) {
                $this->error("Hotel ID {$filter} was not found.");
            }

            return $hotel;
        }

        $matches = $query->whereRaw('LOWER(hotel_name) = ?', [mb_strtolower((string) $filter)])->get();

        return $this->singleMatch($matches, 'hotel', (string) $filter);
    }

    private function findRoom(mixed $filter): ?RoomType
    {
        if ($filter === null) {
            return null;
        }

        $query = RoomType::query();
        if (ctype_digit((string) $filter)) {
            $room = $query->find((int) $filter);
            if (! $room) {
                $this->error("Room ID {$filter} was not found.");
            }

            return $room;
        }

        $matches = $query->whereRaw('LOWER(room_name) = ?', [mb_strtolower((string) $filter)])->get();

        return $this->singleMatch($matches, 'room', (string) $filter);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $matches
     * @return TModel|null
     */
    private function singleMatch($matches, string $entityName, string $filter): ?Model
    {
        if ($matches->isEmpty()) {
            $this->error("No {$entityName} exactly matching \"{$filter}\" was found.");

            return null;
        }

        if ($matches->count() > 1) {
            $this->error("More than one {$entityName} exactly matches \"{$filter}\". Use its numeric ID instead.");

            return null;
        }

        return $matches->first();
    }
}
