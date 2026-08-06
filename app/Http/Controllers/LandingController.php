<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    /**
     * Public landing page with the featured guest reviews highlight.
     */
    public function index(Request $request)
    {
        $platformSummary = $this->reviews->platformSummary();

        $featuredReviews = Review::published()
            ->featured()
            ->with(['user', 'reviewable'])
            ->latest()
            ->limit(3)
            ->get();

        return view('welcome', compact('platformSummary', 'featuredReviews'));
    }
}