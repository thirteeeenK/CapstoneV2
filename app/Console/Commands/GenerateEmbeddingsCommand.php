<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\ActivityModel;
use App\Services\GeminiService;

class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'embed:all {--force : Re-generate embeddings even for items that already have them}';

    protected $description = 'Generate and update AI embeddings for all hotels, room types, and activities';

    public function handle(GeminiService $geminiService)
    {
        $this->info('Starting AI Embedding Generation...');
        $force = $this->option('force');

        // 1. Process Hotels
        $queryHotels = HotelModel::with('destination');
        if (!$force) {
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
        if (!$force) {
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
        if (!$force) {
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

        $this->info('AI Embedding process completed successfully!');
        return Command::SUCCESS;
    }
}
