<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService) {}

    /**
     * Dedicated public discovery hub: browse, filter & sort verified reviews.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all');
        if (! in_array($tab, ['all', 'hotels', 'rooms', 'activities', 'packages'], true)) {
            $tab = 'all';
        }

        $sentiment = $request->query('sentiment');
        if (! in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
            $sentiment = null;
        }

        $minRating = (int) $request->query('rating', 0);

        $sort = $request->query('sort', 'recent');
        if (! in_array($sort, ['recent', 'highest', 'lowest', 'helpful'], true)) {
            $sort = 'recent';
        }

        $search = trim((string) $request->query('search', ''));

        $paginator = $this->reviewService->feedPaginated($tab, $sentiment, $minRating, $sort, $search !== '' ? $search : null, 12);
        $reviews = $paginator->getCollection();
        $platform = $this->reviewService->platformSummary();
        $totalPublished = $paginator->total();

        return view('reviews.index', compact('tab', 'sentiment', 'minRating', 'sort', 'search', 'reviews', 'paginator', 'platform', 'totalPublished'));
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
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        // ponytail: optional 3×3MB, local public disk default, R2 when REVIEWS_DISK=r2
        $imageFiles = $request->hasFile('images') ? array_slice((array) $request->file('images'), 0, 3) : null;

        try {
            $review = $this->reviewService->store(
                Auth::user(),
                $booking,
                $validated['rating'],
                trim($validated['comment']),
                $validated['booking_item_id'],
                $imageFiles,
            );
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid booking.', 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your review has been published.',
            'review' => $this->reviewService->presentForFeed($review->load('reviewable')),
        ], 201);
    }
}
