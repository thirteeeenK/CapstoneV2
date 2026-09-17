<?php

namespace App\Console\Commands;

use App\Models\HotelModel;
use App\Models\Review;
use App\Services\HotelReviewsMdParser;
use App\Services\ReviewService;
use Illuminate\Console\Command;

class ImportHotelReviewsCommand extends Command
{
    protected $signature = 'reviews:import-hotel-md
        {--file=database/seeders/data/hotel_reviews.md : Path to the reviews MD file}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Import hotel-level manual reviews from the curated MD file (real third-party excerpts, hotel entity only)';

    public function handle(ReviewService $reviewService): int
    {
        $path = $this->option('file');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:\\\\/', (string) $path)) {
            $path = base_path($path);
        }

        ['reviews' => $reviews, 'errors' => $errors] = HotelReviewsMdParser::parse($path);

        foreach ($errors as $error) {
            $this->warn($error);
        }

        if ($reviews === []) {
            $this->error('No valid reviews parsed.');

            return self::FAILURE;
        }

        $hotels = HotelModel::pluck('id', 'hotel_name');
        $missing = [];
        $perHotel = [];
        $mapping = [];

        foreach ($reviews as $row) {
            if (! isset($hotels[$row['hotel']])) {
                $missing[$row['hotel']] = ($missing[$row['hotel']] ?? 0) + 1;

                continue;
            }
            $perHotel[$row['hotel']] = ($perHotel[$row['hotel']] ?? 0) + 1;
            $mapping[$row['rating_raw']] = $row['rating'];
        }

        ksort($perHotel);
        $this->info('Parsed '.count($reviews).' valid review(s) across '.count($perHotel).' matched hotel(s).');
        foreach ($perHotel as $hotel => $count) {
            $this->line("  {$count}x {$hotel}");
        }

        if ($missing !== []) {
            $this->warn('Skipped — hotel not in database (deleted or renamed):');
            foreach ($missing as $hotel => $count) {
                $this->warn("  {$count}x {$hotel}");
            }
        }

        $this->info('Rating mapping (raw => seeded int):');
        foreach ($mapping as $raw => $int) {
            $this->line("  {$raw} => {$int}");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing written.');

            return empty($errors) ? self::SUCCESS : self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach ($reviews as $index => $row) {
            if (! isset($hotels[$row['hotel']])) {
                $skipped++;

                continue;
            }

            $hotelId = $hotels[$row['hotel']];

            $exists = Review::where('hotel_id', $hotelId)
                ->where('reviewable_type', 'hotel')
                ->where('reviewable_id', $hotelId)
                ->where('reviewer_name', $row['reviewer'])
                ->where('comment', $row['comment'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $review = $reviewService->manualAdminStore(
                $row['reviewer'],
                'hotel',
                $hotelId,
                $row['rating'],
                $row['comment']
            );

            // Spread timestamps so summaries/feeds look organic, like ReviewSeeder.
            $review->created_at = now()->subDays($index + 1)->subHours($index % 13);
            $review->save();
            $created++;
        }

        $this->info("Imported {$created} review(s), skipped {$skipped} (missing hotel or duplicate).");
        $this->info('Sentiment + summary jobs are queued — run the queue worker, then verify review_summaries per hotel.');

        return self::SUCCESS;
    }
}
