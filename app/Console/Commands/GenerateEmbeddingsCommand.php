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
    protected $signature = 'embed:all {--force : Re-generate embeddings even for items that already have them} {--stale : Only re-embed items where source content or model has changed}';

    protected $description = 'Generate and update AI embeddings for all hotels, room types, activities, packages, add-ons and FAQs';

    public function handle(GeminiService $geminiService)
    {
        $this->info('Starting AI Embedding Generation...');
        $force = $this->option('force');
        $stale = $this->option('stale');
        $model = 'text-embedding-001';

        // 1. Process Hotels
        $queryHotels = HotelModel::with('destination');
        if (! $force && ! $stale) {
            $queryHotels->whereNull('embedding');
        }
        $hotels = $queryHotels->get();

        $this->info("Processing {$hotels->count()} hotels...");
        foreach ($hotels as $hotel) {
            $destName = $hotel->destination?->name;
            $text = $geminiService->buildHotelEmbeddingText($hotel, $destName);
            $hash = $this->computeSourceHash($text, $model);

            if (! $force && ! $stale) {
                // Normal mode: only embed null embeddings
                $shouldEmbed = true;
            } elseif ($force) {
                $shouldEmbed = true;
            } else {
                // Stale mode: embed if hash or model differs
                $shouldEmbed = $hotel->embedding_source_hash !== $hash || $hotel->embedding_model !== $model;
            }

            if ($shouldEmbed) {
                $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);

                if ($vector) {
                    $hotel->embedding = $geminiService->formatVectorForDb($vector);
                    $hotel->embedding_source_hash = $hash;
                    $hotel->embedding_model = $model;
                    $hotel->embedded_at = now();
                    $hotel->save();
                    $this->line("  ✓ Embedded Hotel: {$hotel->hotel_name}");
                } else {
                    $this->error("  ✗ Failed Hotel: {$hotel->hotel_name}");
                }
            } else {
                $this->line("  ↻ Skipped Hotel (fresh): {$hotel->hotel_name}");
            }
        }

        // 2. Process Rooms
        $queryRooms = RoomType::with('hotel.destination');
        if (! $force && ! $stale) {
            $queryRooms->whereNull('embedding');
        }
        $rooms = $queryRooms->get();

        $this->info("Processing {$rooms->count()} room types...");
        foreach ($rooms as $room) {
            $hotelName = $room->hotel?->hotel_name;
            $destName = $room->hotel?->destination?->name;
            $text = $geminiService->buildRoomEmbeddingText($room, $hotelName, $destName);
            $hash = $this->computeSourceHash($text, $model);

            if (! $force && ! $stale) {
                $shouldEmbed = true;
            } elseif ($force) {
                $shouldEmbed = true;
            } else {
                $shouldEmbed = $room->embedding_source_hash !== $hash || $room->embedding_model !== $model;
            }

            if ($shouldEmbed) {
                $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $room->room_name);

                if ($vector) {
                    $room->embedding = $geminiService->formatVectorForDb($vector);
                    $room->embedding_source_hash = $hash;
                    $room->embedding_model = $model;
                    $room->embedded_at = now();
                    $room->save();
                    $this->line("  ✓ Embedded Room: {$room->room_name} ({$hotelName})");
                } else {
                    $this->error("  ✗ Failed Room: {$room->room_name}");
                }
            } else {
                $this->line("  ↻ Skipped Room (fresh): {$room->room_name} ({$hotelName})");
            }
        }

        // 3. Process Activities
        $queryActivities = ActivityModel::with('destination');
        if (! $force && ! $stale) {
            $queryActivities->whereNull('embedding');
        }
        $activities = $queryActivities->get();

        $this->info("Processing {$activities->count()} activities...");
        foreach ($activities as $activity) {
            $destName = $activity->destination?->name;
            $text = $geminiService->buildActivityEmbeddingText($activity, $destName);
            $hash = $this->computeSourceHash($text, $model);

            if (! $force && ! $stale) {
                $shouldEmbed = true;
            } elseif ($force) {
                $shouldEmbed = true;
            } else {
                $shouldEmbed = $activity->embedding_source_hash !== $hash || $activity->embedding_model !== $model;
            }

            if ($shouldEmbed) {
                $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $activity->activity_name);

                if ($vector) {
                    $activity->embedding = $geminiService->formatVectorForDb($vector);
                    $activity->embedding_source_hash = $hash;
                    $activity->embedding_model = $model;
                    $activity->embedded_at = now();
                    $activity->save();
                    $this->line("  ✓ Embedded Activity: {$activity->activity_name} ({$destName})");
                } else {
                    $this->error("  ✗ Failed Activity: {$activity->activity_name}");
                }
            } else {
                $this->line("  ↻ Skipped Activity (fresh): {$activity->activity_name} ({$destName})");
            }
        }

        // 4. Process Tour Packages
        $queryPackages = Package::with('destination');
        if (! $force && ! $stale) {
            $queryPackages->whereNull('embedding');
        }
        $packages = $queryPackages->get();

        $this->info("Processing {$packages->count()} tour packages...");
        foreach ($packages as $package) {
            $text = $geminiService->buildPackageEmbeddingText($package);
            $hash = $this->computeSourceHash($text, $model);

            if (! $force && ! $stale) {
                $shouldEmbed = true;
            } elseif ($force) {
                $shouldEmbed = true;
            } else {
                $shouldEmbed = $package->embedding_source_hash !== $hash || $package->embedding_model !== $model;
            }

            if ($shouldEmbed) {
                $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $package->name);

                if ($vector) {
                    $package->embedding = $geminiService->formatVectorForDb($vector);
                    $package->embedding_source_hash = $hash;
                    $package->embedding_model = $model;
                    $package->embedded_at = now();
                    $package->save();
                    $this->line("  ✓ Embedded Package: {$package->name}");
                } else {
                    $this->error("  ✗ Failed Package: {$package->name}");
                }
            } else {
                $this->line("  ↻ Skipped Package (fresh): {$package->name}");
            }
        }

        // 5. Process AddOns
        $queryAddOns = AddOnModel::with('destination');
        if (! $force && ! $stale) {
            $queryAddOns->whereNull('embedding');
        }
        $addons = $queryAddOns->get();

        $this->info("Processing {$addons->count()} add-ons...");
        foreach ($addons as $addon) {
            $destName = $addon->destination?->name;
            $text = $geminiService->buildAddOnEmbeddingText($addon, $destName);
            $hash = $this->computeSourceHash($text, $model);

            if (! $force && ! $stale) {
                $shouldEmbed = true;
            } elseif ($force) {
                $shouldEmbed = true;
            } else {
                $shouldEmbed = $addon->embedding_source_hash !== $hash || $addon->embedding_model !== $model;
            }

            if ($shouldEmbed) {
                $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $addon->name);

                if ($vector) {
                    $addon->embedding = $geminiService->formatVectorForDb($vector);
                    $addon->embedding_source_hash = $hash;
                    $addon->embedding_model = $model;
                    $addon->embedded_at = now();
                    $addon->save();
                    $this->line("  ✓ Embedded AddOn: {$addon->name} ({$destName})");
                } else {
                    $this->error("  ✗ Failed AddOn: {$addon->name}");
                }
            } else {
                $this->line("  ↻ Skipped AddOn (fresh): {$addon->name} ({$destName})");
            }
        }

        // 6. Process FAQs
        $queryFaqs = Faq::query();
        if (! $force && ! $stale) {
            $queryFaqs->whereNull('embedding');
        }
        $faqs = $queryFaqs->get();

        $this->info("Processing {$faqs->count()} FAQs...");
        foreach ($faqs as $faq) {
            $text = $geminiService->buildFaqEmbeddingText($faq);
            $hash = $this->computeSourceHash($text, $model);

            if (! $force && ! $stale) {
                $shouldEmbed = true;
            } elseif ($force) {
                $shouldEmbed = true;
            } else {
                $shouldEmbed = $faq->embedding_source_hash !== $hash || $faq->embedding_model !== $model;
            }

            if ($shouldEmbed) {
                $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $faq->question);

                if ($vector) {
                    $faq->embedding = $geminiService->formatVectorForDb($vector);
                    $faq->embedding_source_hash = $hash;
                    $faq->embedding_model = $model;
                    $faq->embedded_at = now();
                    $faq->save();
                    $this->line("  ✓ Embedded FAQ: {$faq->question}");
                } else {
                    $this->error("  ✗ Failed FAQ: {$faq->question}");
                }
            } else {
                $this->line("  ↻ Skipped FAQ (fresh): {$faq->question}");
            }
        }

        $this->info('AI Embedding process completed successfully!');

        return self::SUCCESS;
    }

    private function computeSourceHash(string $text, string $model): string
    {
        return sha1($text.$model);
    }
}
