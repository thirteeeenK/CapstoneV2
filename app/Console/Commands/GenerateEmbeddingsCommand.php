<?php

namespace App\Console\Commands;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Console\Command;

class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'embed:all {--force : Re-generate embeddings even for items that already have them}';

    protected $description = 'Generate and update AI embeddings for all hotels, room types, activities, packages, add-ons and FAQs';

    public function handle(GeminiService $geminiService)
    {
        $this->info('Starting AI Embedding Generation...');
        $force = $this->option('force');

        // 1. Process Hotels
        $queryHotels = HotelModel::with('destination');
        if (! $force) {
            $queryHotels->whereNull('embedding');
        }
        $hotels = $queryHotels->get();

        $this->info("Processing {$hotels->count()} hotels...");
        foreach ($hotels as $hotel) {
            $destName = $hotel->destination?->name;
            $text = $geminiService->buildHotelEmbeddingText($hotel, $destName);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);

            if ($vector) {
                $hotel->embedding = $geminiService->formatVectorForDb($vector);
                $hotel->save();
                $this->line("  ✓ Embedded Hotel: {$hotel->hotel_name}");
            } else {
                $this->error("  ✗ Failed Hotel: {$hotel->hotel_name}");
            }
        }

        // 2. Process Rooms
        $queryRooms = RoomType::with('hotel.destination');
        if (! $force) {
            $queryRooms->whereNull('embedding');
        }
        $rooms = $queryRooms->get();

        $this->info("Processing {$rooms->count()} room types...");
        foreach ($rooms as $room) {
            $hotelName = $room->hotel?->hotel_name;
            $destName = $room->hotel?->destination?->name;
            $text = $geminiService->buildRoomEmbeddingText($room, $hotelName, $destName);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $room->room_name);

            if ($vector) {
                $room->embedding = $geminiService->formatVectorForDb($vector);
                $room->save();
                $this->line("  ✓ Embedded Room: {$room->room_name} ({$hotelName})");
            } else {
                $this->error("  ✗ Failed Room: {$room->room_name}");
            }
        }

        // 3. Process Activities
        $queryActivities = ActivityModel::with('destination');
        if (! $force) {
            $queryActivities->whereNull('embedding');
        }
        $activities = $queryActivities->get();

        $this->info("Processing {$activities->count()} activities...");
        foreach ($activities as $activity) {
            $destName = $activity->destination?->name;
            $text = $geminiService->buildActivityEmbeddingText($activity, $destName);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $activity->activity_name);

            if ($vector) {
                $activity->embedding = $geminiService->formatVectorForDb($vector);
                $activity->save();
                $this->line("  ✓ Embedded Activity: {$activity->activity_name} ({$destName})");
            } else {
                $this->error("  ✗ Failed Activity: {$activity->activity_name}");
            }
        }

        // 4. Process Tour Packages
        $queryPackages = Package::with('destination');
        if (! $force) {
            $queryPackages->whereNull('embedding');
        }
        $packages = $queryPackages->get();

        $this->info("Processing {$packages->count()} tour packages...");
        foreach ($packages as $package) {
            $text = $geminiService->buildPackageEmbeddingText($package);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $package->name);

            if ($vector) {
                $package->embedding = $geminiService->formatVectorForDb($vector);
                $package->save();
                $this->line("  ✓ Embedded Package: {$package->name}");
            } else {
                $this->error("  ✗ Failed Package: {$package->name}");
            }
        }

        // 5. Process AddOns
        $queryAddOns = AddOnModel::with('destination');
        if (! $force) {
            $queryAddOns->whereNull('embedding');
        }
        $addons = $queryAddOns->get();

        $this->info("Processing {$addons->count()} add-ons...");
        foreach ($addons as $addon) {
            $destName = $addon->destination?->name;
            $text = $geminiService->buildAddOnEmbeddingText($addon, $destName);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $addon->name);

            if ($vector) {
                $addon->embedding = $geminiService->formatVectorForDb($vector);
                $addon->save();
                $this->line("  ✓ Embedded AddOn: {$addon->name} ({$destName})");
            } else {
                $this->error("  ✗ Failed AddOn: {$addon->name}");
            }
        }

        // 6. Process FAQs
        $queryFaqs = Faq::query();
        if (! $force) {
            $queryFaqs->whereNull('embedding');
        }
        $faqs = $queryFaqs->get();

        $this->info("Processing {$faqs->count()} FAQs...");
        foreach ($faqs as $faq) {
            $text = $geminiService->buildFaqEmbeddingText($faq);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $faq->question);

            if ($vector) {
                $faq->embedding = $geminiService->formatVectorForDb($vector);
                $faq->save();
                $this->line("  ✓ Embedded FAQ: {$faq->question}");
            } else {
                $this->error("  ✗ Failed FAQ: {$faq->question}");
            }
        }

        $this->info('AI Embedding process completed successfully!');

        return self::SUCCESS;
    }
}
