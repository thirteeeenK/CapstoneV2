# SunnyTrips — Unified Reviews, Sentiment Analysis & Review Summarization Specification

> **Document Type:** Feature Architecture & AI DSS Specification  
> **Target Platform:** SunnyTrips Travel Booking Capstone V2  
> **Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Blade + Tailwind CSS + Alpine.js, Google Gemini API  

---

## 1. Overview & Business Value

The **Unified Reviews System** allows verified guests to submit reviews for **Hotels & Rooms**, **Activities**, and **Packages** following a completed booking. Beyond traditional 5-star ratings and textual feedback, the system integrates an **AI-powered Decision Support System (DSS)** that automatically performs **Sentiment Analysis** and **Multi-Level Review Summarization**.

### Key Objectives
1. **Reduce Information Overload:** Distill hundreds of user comments into concise, bulleted summaries of pros and cons at the Room, Hotel, Activity, and Platform level.
2. **Ensure Authenticity:** Only users with a `completed` booking can submit a review, preventing fake reviews.
3. **Single Dynamic Form:** Provide a single, clean "Leave a Review" modal/component that dynamically adjusts based on the booked item type.
4. **Dedicated Discovery Page (`/reviews`):** Provide a centralized hub where prospective travelers can search, filter, and explore verified traveler feedback across all listings.
5. **AI DSS Insights for Admin & Chatbot:** Supply platform administrators with operational sentiment analytics and enable the SunnyTrips AI Chatbot to answer guest inquiries using Retrieval-Augmented Generation (RAG).

---

## 2. Dynamic Single Review Form Architecture

Rather than creating separate review forms for hotels, rooms, and activities, SunnyTrips uses **one unified Review Form component** (`resources/views/components/review-modal.blade.php`).

```
                              ┌──────────────────────────────────┐
                              │  User selects "Leave a Review"   │
                              │     from Completed Booking       │
                              └─────────────────┬────────────────┘
                                                │ Passes booking_id
                                                ▼
                              ┌──────────────────────────────────┐
                              │     Dynamic Review Modal         │
                              └─────────────────┬────────────────┘
                                                │
                 ┌──────────────────────────────┼──────────────────────────────┐
                 ▼                              ▼                              ▼
      [Hotel & Room Booking]           [Activity Booking]            [Package Booking]
     • Hotel Name (Auto-filled)     • Activity Name (Auto-filled)  • Package Name (Auto-filled)
     • Room Name (Auto-filled)      • Location (Auto-filled)       • Inclusions (Auto-filled)
```

### Form Fields & Validation

| Field Name | Type | Display / Input Behavior | Validation Rule |
| :--- | :--- | :--- | :--- |
| `booking_id` | Hidden Input | Auto-populated from current completed booking | `required\|exists:bookings,id` |
| `hotel_name` | Read-only Text | Auto-filled if booking contains a hotel room | Display only |
| `room_name` | Read-only Text | Auto-filled if reviewing a specific room | Display only |
| `activity_name` | Read-only Text | Auto-filled if reviewing an activity | Display only |
| `rating` | Interactive Stars | 1 to 5 star selector (Alpine.js hover & click) | `required\|integer\|min:1\|max:5` |
| `comment` | Textarea | Text field with character counter (min 10, max 1000) | `required\|string\|min:10\|max:1000` |
| `reviewer_alias` | Read-only Text | First name + Last initial for guest privacy (e.g., "John D.") | Auto-generated from user |
| `created_at` | Timestamp | Server auto-generated | Server timestamp |

---

## 3. Database Architecture & Schema

To support polymorphic dynamic listings while preserving ultra-fast relational querying (e.g., fetching all reviews for a specific room or hotel without heavy JOINs), the database separates **Individual Reviews** from **Aggregated AI Summaries**.

### 3.1 `reviews` Table

Stores individual user feedback, star ratings, and review-level sentiment output.

```sql
CREATE TABLE reviews (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT UNIQUE NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Polymorphic target (HotelModel, RoomType, ActivityModel, Package)
    reviewable_type VARCHAR(255) NOT NULL,
    reviewable_id BIGINT NOT NULL,
    
    -- Direct relational foreign keys for fast filtering & indexing
    hotel_id BIGINT NULL REFERENCES hotels(id) ON DELETE CASCADE,
    room_id BIGINT NULL REFERENCES room_types(id) ON DELETE CASCADE,
    activity_id BIGINT NULL REFERENCES activities(id) ON DELETE CASCADE,
    package_id BIGINT NULL REFERENCES packages(id) ON DELETE CASCADE,
    
    rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NOT NULL,
    
    -- AI Sentiment Analysis Outputs
    sentiment VARCHAR(20) DEFAULT 'neutral', -- 'positive', 'neutral', 'negative'
    sentiment_score DECIMAL(5,4) DEFAULT 0.5000, -- Confidence score (0.0000 to 1.0000)
    extracted_keywords JSONB DEFAULT '[]'::jsonb, -- e.g. ["spacious room", "clean", "slow wifi"]
    
    is_verified_booking BOOLEAN DEFAULT TRUE,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_reviews_reviewable ON reviews(reviewable_type, reviewable_id);
CREATE INDEX idx_reviews_hotel_room ON reviews(hotel_id, room_id);
CREATE INDEX idx_reviews_activity ON reviews(activity_id);
CREATE INDEX idx_reviews_rating_sentiment ON reviews(rating, sentiment);
```

### 3.2 `review_summaries` Table

Caches aggregated statistics and AI-generated summaries per listing level (Room, Hotel, Activity, Package, and Platform-wide Overall).

```sql
CREATE TABLE review_summaries (
    id BIGSERIAL PRIMARY KEY,
    
    -- Polymorphic summarizable entity OR 'SunnyTripsOverall'
    summarizable_type VARCHAR(255) NOT NULL, 
    summarizable_id BIGINT NULL, -- NULL for platform-wide overall summary
    
    total_reviews INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    positive_percentage DECIMAL(5,2) DEFAULT 0.00,
    neutral_percentage DECIMAL(5,2) DEFAULT 0.00,
    negative_percentage DECIMAL(5,2) DEFAULT 0.00,
    
    -- AI Generated Insights
    ai_summary_text TEXT NULL, -- Bulleted summary of feedback
    top_positive_highlights JSONB DEFAULT '[]'::jsonb, -- Top praised features
    top_negative_highlights JSONB DEFAULT '[]'::jsonb, -- Common guest complaints
    most_frequent_keywords JSONB DEFAULT '[]'::jsonb, -- Keyword frequency count
    
    last_analyzed_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_review_summaries_unique 
ON review_summaries (summarizable_type, COALESCE(summarizable_id, 0));
```

---

## 4. Sentiment Analysis & AI Summarization Engine (DSS)

SunnyTrips utilizes **Google Gemini API** (`text-embedding-001` and Gemini Flash via `GeminiService`) as its primary AI engine.

```
┌────────────────────────┐
│ User Submits Review    │
└───────────┬────────────┘
            │
            ▼
┌────────────────────────┐
│ Save Review to DB      │
└───────────┬────────────┘
            │
            ▼
┌────────────────────────────────────────────────────────┐
│ Dispatch Async Queue Job:                              │
│ ProcessReviewSentimentJob                              │
└───────────┬────────────────────────────────────────────┘
            │
            ├───► 1. Send comment to Gemini API for Sentiment & Keywords
            │     • Sentiment: Positive (😊), Neutral (😐), or Negative (😞)
            │     • Confidence Score: 0.96
            │     • Keywords: ["ocean view", "clean sheets", "quiet AC"]
            │     • Save back to `reviews` table
            │
            └───► 2. Trigger Entity Summary Update:
                  UpdateEntityReviewSummaryJob
                  • Recalculate average rating & sentiment %
                  • Fetch last 20-50 reviews for target Room/Hotel/Activity
                  • Prompt Gemini to generate 4-bullet point consensus summary
                  • Store in `review_summaries` table
```

### 4.1 Single Review Sentiment Prompt (Gemini API)

```json
{
  "system_instruction": "You are an expert NLP sentiment analysis model for SunnyTrips travel platform. Analyze the user review and respond ONLY with valid JSON matching the specified schema.",
  "prompt": "Review Text: 'The ocean view room was breathtaking and spotless! Staff were super polite. Wi-Fi was a bit spotty in the evening though.'",
  "expected_response_schema": {
    "sentiment": "positive",
    "confidence_score": 0.94,
    "extracted_keywords": ["ocean view", "spotless room", "polite staff", "spotty wifi"]
  }
}
```

### 4.2 Multi-Level Summarization Levels

| Summarization Scope | Target Entity | Where It Appears | Example Output |
| :--- | :--- | :--- | :--- |
| **Room Level** | `RoomType` | Room Modal / Details View | *"Guests love the spacious balcony and beachfront view. Air conditioning is quiet, but water pressure in the shower can be low during peak hours."* |
| **Hotel Level** | `HotelModel` | Hotel Overview Tab | *"Highly rated for friendly staff, spotless lobby, and great breakfast buffet. Internet speed in secondary building receives mixed feedback."* |
| **Activity Level**| `ActivityModel`| Activity Details View | *"Travelers praise the tour guide's punctuality and clear instructions. Highly recommended for families, though boat waiting times can take 20 mins."* |
| **Platform Level**| `SunnyTripsOverall`| `/reviews` Page & Admin Dashboard | *"92% positive sentiment across 1,200+ bookings. Users consistently praise seamless booking, clean hotel partners, and responsive island guides."* |

---

## 5. UI / UX Design Specifications

All components use SunnyTrips' Tailwind CSS design tokens (`ocean`, `sand`, `ink`, `coral`) and Google Typography (`Sora` for display headers, `DM Sans` for body text).

### 5.1 Hotel & Room Details View (`/hotels/{id}`)

Each hotel and specific room card incorporates a **DSS Feedback Summary Box**:

```
┌────────────────────────────────────────────────────────────────────────┐
│  Deluxe Ocean View Room — Guest Reviews & Sentiment                    │
│                                                                        │
│  ★★⭐⭐⭐ 4.8 / 5.0 (42 Verified Guest Reviews)                           │
│  [ 😊 92% Positive ]   [ 😐 5% Neutral ]   [ 😞 3% Negative ]          │
│                                                                        │
│  🤖 AI Summary Consensus:                                              │
│  • Guests praise the panoramic ocean sunset views and comfortable king bed.│
│  • Room cleanliness and daily housekeeping received top marks.         │
│  • Wi-Fi connection is fast for remote work.                          │
│  • Minor Note: High floor rooms require taking the central elevator.   │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │ ★★★★★ (5.0) — John D. (Verified Booking)            Aug 4, 2026   │  │
│  │ "Super ganda ng room! Clean bed, working AC, and ang bait ng     │  │
│  │ staff. Will definitely book again next summer."                  │  │
│  │ Tags: [clean bed] [friendly staff] [ocean view]                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Dedicated All-Reviews Page (`/reviews`)

A standalone page accessible from the main navigation header:

- **Filter Tabs:** All Reviews \| Hotels & Rooms \| Activities \| Packages
- **Rating Filter:** All Ratings \| 5 Stars ⭐⭐⭐⭐⭐ \| 4+ Stars \| 3 Stars or lower
- **Sentiment Filter:** All Sentiments \| Positive 😊 \| Neutral 😐 \| Negative 😞
- **Search Bar:** Real-time filter by Hotel Name, Room Name, or Activity Name.
- **Sorting Options:** Most Recent, Highest Rating, Lowest Rating, Most Helpful.

### 5.3 Admin Dashboard Review Analytics (`/admin/reviews`)

The Admin Panel includes operational DSS metrics:
- **Total Reviews Counter** & **Average Platform Rating**
- **Sentiment Distribution Chart:** Donut chart of Positive vs Neutral vs Negative ratings.
- **Top Rated Listings Leaderboard:** Top 5 Hotels, Rooms, and Activities.
- **Needs Improvement Alert Panel:** Listings with negative sentiment spikes (>15% negative) get flagged for admin review.
- **Keyword Cloud:** Visual frequency of tags like `clean`, `bad wifi`, `delayed transfer`, `friendly guide`.

---

## 6. Cold Start Strategy & Panel Presentation (Seeding)

To solve the **Cold Start Problem** during defense or initial client deployment, SunnyTrips uses a dedicated database seeder:

```bash
php artisan db:seed --class=ReviewSeeder
```

### Seeder Capabilities:
1. Generates 50+ realistic verified reviews across existing seeded Hotels, Room Types, and Activities.
2. Mixes Tagalog-English ("Taglish") and English reviews to test Gemini's multilingual sentiment detection.
3. Pre-calculates `sentiment`, `sentiment_score`, `extracted_keywords`, and populates `review_summaries` records.
4. Ensures panel presentation demonstrates full AI functionality out of the box.

---

## 7. AI Chatbot Integration (RAG Pattern)

When users ask the SunnyTrips AI Chatbot questions like:
- *"Is the Deluxe Ocean View Room in Villa Maria clean?"*
- *"What do guests say about the Island Hopping Tour?"*

The chatbot executes a database lookup tool to pull `review_summaries` and recent `reviews` for that entity, injecting them into the system prompt:

```
[SYSTEM PROMPT CONTEXT]
Entity: Deluxe Ocean View Room (Villa Maria Resort)
Average Rating: 4.8 / 5.0 (92% Positive)
AI Summary: Guests praise the panoramic ocean sunset views, comfortable king bed, and room cleanliness.
Recent Review snippet: "Super clean bed and working AC..."

[BOT RESPONSE]
"Yes! Guests love the Deluxe Ocean View Room at Villa Maria Resort, giving it a 4.8 out of 5 stars with 92% positive reviews! Reviewers specifically highlight the spotless cleanliness, comfortable king bed, and breathtaking ocean sunset views."
```

---

## 8. Summary Checklist of Next Steps

- [ ] Create Database Migration: `2026_08_07_000001_create_reviews_and_summaries_tables.php`
- [ ] Create Models: `app/Models/Review.php` and `app/Models/ReviewSummary.php`
- [ ] Implement `GeminiService` sentiment analysis & summarization helper methods
- [ ] Create Review Modal Component: `resources/views/components/review-modal.blade.php`
- [ ] Create Dedicated Page: `routes/reviewRoute.php` & `app/Http/Controllers/User/ReviewController.php` (`/reviews`)
- [ ] Add Admin Sentiment Analytics view: `app/Http/Controllers/Admin/AdminReviewController.php`
- [ ] Build Database Seeder: `database/seeders/ReviewSeeder.php` for panel presentation
- [ ] Add Pest Automated Unit & Feature Tests in `tests/Feature/ReviewTest.php`
