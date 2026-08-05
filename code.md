<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\HotelModel;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    /**
     * Display the multi-step image-based onboarding wizard.
     * Step 1: Hotel Style Preference (High-End vs Affordable — images only)
     * Step 2: Activity Level Preference (Relaxing, Adventure, Extreme, etc. — images only)
     */
    public function index()
    {
        $user = Auth::user();

        // Group hotels by type for style preference (no names/destinations shown)
        $hotelsByType = HotelModel::select('id', 'type', 'images', 'embedding')
            ->get()
            ->groupBy(function ($hotel) {
                return str_contains(strtolower($hotel->type), 'luxury') ? 'High-End' : 'Affordable';
            });

        // Group activities by activity_level for experience preference (no names/destinations shown)
        $activitiesByLevel = ActivityModel::select('id', 'activity_level', 'category', 'images', 'embedding')
            ->get()
            ->groupBy('activity_level');

        // Fallback images keyed by type/level
        $fallbackImages = [
            // Hotel style fallbacks
            'High-End' => [
                'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=800&q=80',
            ],
            'Affordable' => [
                'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=800&q=80',
            ],
            // Activity level fallbacks
            'Relaxing' => [
                'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80',
            ],
            'Sightseeing' => [
                'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=800&q=80',
            ],
            'Adventure' => [
                'https://images.unsplash.com/photo-1530789253388-582c481c54b0?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=800&q=80',
            ],
            'Extreme' => [
                'https://images.unsplash.com/photo-1563299796-17596ed6b017?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=800&q=80',
            ],
            'Underwater' => [
                'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1682687982501-1e58ab814714?auto=format&fit=crop&w=800&q=80',
            ],
        ];

        // Build hotel cards — collect up to 4 images per type from DB, fill gaps with fallbacks
        $hotelCards = [];
        foreach (['High-End', 'Affordable'] as $style) {
            $dbImages = [];
            $hotelIds = [];
            foreach ($hotelsByType->get($style, collect()) as $hotel) {
                $imgs = is_array($hotel->images) ? $hotel->images : (json_decode($hotel->images, true) ?? []);
                foreach ($imgs as $img) {
                    if (count($dbImages) < 4) {
                        $dbImages[] = asset('storage/' . $img);
                    }
                }
                $hotelIds[] = $hotel->id;
            }
            // Fill with fallbacks if not enough DB images
            $needed = max(0, 3 - count($dbImages));
            $fills = array_slice($fallbackImages[$style] ?? [], 0, $needed);
            $displayImages = array_merge($dbImages, $fills);

            $hotelCards[] = [
                'style' => $style,
                'subtitle' => $style === 'High-End' ? 'Premium resorts & luxury stays' : 'Cozy stays & budget-friendly gems',
                'images' => array_slice($displayImages, 0, 4),
                'ids' => $hotelIds,
            ];
        }

        // Build activity cards — one card per activity level
        $activityCards = [];
        $levelMeta = [
            'Relaxing'    => ['emoji' => '🌴', 'subtitle' => 'Laid-back vibes & scenic views'],
            'Sightseeing' => ['emoji' => '📸', 'subtitle' => 'Explore landmarks & local culture'],
            'Adventure'   => ['emoji' => '🚵', 'subtitle' => 'Thrilling rides & outdoor fun'],
            'Extreme'     => ['emoji' => '⚡', 'subtitle' => 'High-adrenaline & daredevil stunts'],
            'Underwater'  => ['emoji' => '🪸', 'subtitle' => 'Dive deep & explore the ocean'],
        ];

        foreach ($levelMeta as $level => $meta) {
            $dbImages = [];
            $actIds = [];
            foreach ($activitiesByLevel->get($level, collect()) as $act) {
                $imgs = is_array($act->images) ? $act->images : (json_decode($act->images, true) ?? []);
                foreach ($imgs as $img) {
                    if (count($dbImages) < 4) {
                        $dbImages[] = asset('storage/' . $img);
                    }
                }
                $actIds[] = $act->id;
            }
            $needed = max(0, 2 - count($dbImages));
            $fills = array_slice($fallbackImages[$level] ?? [], 0, $needed);
            $displayImages = array_merge($dbImages, $fills);

            $activityCards[] = [
                'level' => $level,
                'emoji' => $meta['emoji'],
                'subtitle' => $meta['subtitle'],
                'images' => array_slice($displayImages, 0, 4),
                'ids' => $actIds,
            ];
        }

        return view('onboarding.index', compact(
            'user',
            'hotelCards',
            'activityCards'
        ));
    }

    /**
     * Process onboarding selections: combine hotel style + activity level embeddings
     * into a single user preference vector.
     */
    public function store(Request $request, GeminiService $geminiService)
    {
        $request->validate([
            'selected_hotels' => 'nullable|array',
            'selected_activities' => 'nullable|array',
        ]);

        $selectedVectors = [];

        // 1. Process selected hotel IDs
        if ($request->filled('selected_hotels')) {
            $hotels = HotelModel::whereIn('id', $request->selected_hotels)->get();
            foreach ($hotels as $hotel) {
                $vec = $this->parseVector($hotel->embedding);
                if (!$vec) {
                    $text = $geminiService->buildHotelEmbeddingText($hotel, $hotel->destination?->name);
                    $vec = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $hotel->hotel_name);
                }
                if ($vec) $selectedVectors[] = $vec;
            }
        }

        // 2. Process selected activity IDs
        if ($request->filled('selected_activities')) {
            $acts = ActivityModel::whereIn('id', $request->selected_activities)->get();
            foreach ($acts as $act) {
                $vec = $this->parseVector($act->embedding);
                if (!$vec) {
                    $text = $geminiService->buildActivityEmbeddingText($act, $act->destination?->name);
                    $vec = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $act->activity_name);
                }
                if ($vec) $selectedVectors[] = $vec;
            }
        }

        if (empty($selectedVectors)) {
            return redirect()->back()->withErrors(['Please select at least one preference to continue.']);
        }

        // Combine by element-wise average
        $dimension = count($selectedVectors[0]);
        $combined = array_fill(0, $dimension, 0.0);
        $count = count($selectedVectors);

        foreach ($selectedVectors as $vector) {
            foreach ($vector as $i => $val) {
                if ($i < $dimension) $combined[$i] += $val;
            }
        }
        foreach ($combined as $i => $val) {
            $combined[$i] = $val / $count;
        }

        $normalizedVector = $geminiService->normalizeVector($combined);

        $user = Auth::user();
        $user->preferences_embedding = $geminiService->formatVectorForDb($normalizedVector);
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Your travel preferences are locked in! Personalized recommendations are ready.');
    }

    /**
     * Reset user preferences and re-trigger onboarding.
     */
    public function reset()
    {
        $user = Auth::user();
        $user->preferences_embedding = null;
        $user->save();

        return redirect()->route('onboarding.index')->with('status', 'Preferences cleared. Let\'s set up your travel profile again.');
    }

    /**
     * Parse vector string / array into float array.
     */
    private function parseVector($raw): ?array
    {
        if (empty($raw)) return null;
        if (is_array($raw)) return $raw;

        if (is_string($raw)) {
            $clean = trim($raw, "[] \t\n\r");
            if (empty($clean)) return null;
            return array_map('floatval', explode(',', $clean));
        }

        return null;
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    /**
     * Display the user dashboard with AI-powered recommendations
     * grouped by destination, ranked via cosine similarity.
     */
    public function dashboard(GeminiService $geminiService)
    {
        $user = Auth::user();

        // Parse the user's preference embedding
        $userVector = $this->parseVector($user->preferences_embedding);

        // Fetch all destinations
        $destinations = DestinationModel::all();

        // Build per-destination recommendations
        $recommendations = [];

        foreach ($destinations as $destination) {
            // ── Hotels for this destination ──
            $hotels = HotelModel::where('destination_id', $destination->id)
                ->whereNotNull('embedding')
                ->with('destination')
                ->get();

            $rankedHotels = [];
            if ($userVector && $hotels->count() > 0) {
                $rankedHotels = $geminiService->rankRecommendations($userVector, $hotels, 5);
            } else {
                // Fallback: show all hotels without scoring
                foreach ($hotels as $hotel) {
                    $rankedHotels[] = ['item' => $hotel, 'score' => null];
                }
            }

            // ── Activities for this destination ──
            $activities = ActivityModel::where('destination_id', $destination->id)
                ->whereNotNull('embedding')
                ->with('destination')
                ->get();

            $rankedActivities = [];
            if ($userVector && $activities->count() > 0) {
                $rankedActivities = $geminiService->rankRecommendations($userVector, $activities, 5);
            } else {
                foreach ($activities as $act) {
                    $rankedActivities[] = ['item' => $act, 'score' => null];
                }
            }

            // ── Top Rooms from top-ranked hotels ──
            $topHotelIds = collect($rankedHotels)->pluck('item.id')->toArray();
            $rooms = RoomType::whereIn('hotel_id', $topHotelIds)
                ->with('hotel')
                ->get();

            $rankedRooms = [];
            if ($userVector && $rooms->count() > 0) {
                $rankedRooms = $geminiService->rankRecommendations($userVector, $rooms, 5);
            } else {
                foreach ($rooms as $room) {
                    $rankedRooms[] = ['item' => $room, 'score' => null];
                }
            }

            $recommendations[] = [
                'destination' => $destination,
                'hotels' => $rankedHotels,
                'activities' => $rankedActivities,
                'rooms' => $rankedRooms,
            ];
        }

        return view('dashboard', compact('user', 'recommendations'));
    }

    /**
     * Parse a vector from DB string or array form into a float array.
     */
    private function parseVector($raw): ?array
    {
        if (empty($raw)) return null;
        if (is_array($raw)) return $raw;

        if (is_string($raw)) {
            $clean = trim($raw, "[] \t\n\r");
            if (empty($clean)) return null;
            return array_map('floatval', explode(',', $clean));
        }

        return null;
    }
}
