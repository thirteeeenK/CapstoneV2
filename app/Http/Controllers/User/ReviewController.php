<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService)
    {
    }

    /**
     * Dedicated public discovery hub: browse, filter & sort verified reviews.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all');
        if (!in_array($tab, ['all', 'hotels', 'rooms', 'activities', 'packages'], true)) {
            $tab = 'all';
        }

        $sentiment = $request->query('sentiment');
        if (!in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
            $sentiment = null;
        }

        $minRating = (int) $request->query('rating', 0);

        $sort = $request->query('sort', 'recent');
        if (!in_array($sort, ['recent', 'highest', 'lowest', 'helpful'], true)) {
            $sort = 'recent';
        }

        $reviews = $this->reviewService->feed($tab, $sentiment, $minRating, $sort, 200);
        $platform = $this->reviewService->platformSummary();

        return view('reviews.index', compact('tab', 'sentiment', 'minRating', 'sort', 'reviews', 'platform'));
    }

    /**
     * JSON payload of the user's completed bookings that are still reviewable.
     */
    public function eligible()
    {
        return response()->json([
            'success' => true,
            'bookings' => $this->reviewService->eligibleBookings(Auth::user()),
        ]);
    }

    /**
     * Submit a verified review for a completed booking.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')],
            'booking_item_id' => ['required', 'integer', Rule::exists('booking_items', 'id')],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        try {
            $review = $this->reviewService->store(
                Auth::user(),
                $booking,
                $validated['rating'],
                trim($validated['comment']),
                $validated['booking_item_id'],
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid booking.', 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your review has been published.',
            'review' => $this->reviewService->presentForFeed($review->load('reviewable')),
        ], 201);
    }
}