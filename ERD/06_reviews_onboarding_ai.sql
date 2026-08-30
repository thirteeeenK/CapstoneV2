-- =============================================================================
-- SunnyTrips - 06 Reviews, Onboarding & AI/DSS (Decomposed ERD)
-- Module: reviews, review_summaries, user_preferences, onboarding_options
-- + vector embeddings (users/hotels/rooms/activities/packages/faqs)
-- Paste independently: users/bookings/booking_items/destinations as stubs.
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

-- Stubs for FKs whose full tables live in other modules
CREATE TABLE IF NOT EXISTS users (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS admins (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS destinations (id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL);
CREATE TABLE IF NOT EXISTS hotels (id BIGSERIAL PRIMARY KEY, hotel_name VARCHAR(255) NOT NULL);
CREATE TABLE IF NOT EXISTS bookings (id BIGSERIAL PRIMARY KEY, booking_code VARCHAR(30) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS booking_items (id BIGSERIAL PRIMARY KEY);

-- Hotels/rooms/activities/packages embeddings are defined in 02/03; re-declared
-- here as stubs only if you paste this file alone. If combined, IF NOT EXISTS
-- prevents collision. Vector columns are shown for DSS context.

CREATE TABLE IF NOT EXISTS onboarding_options (
    id          BIGSERIAL PRIMARY KEY,
    type        VARCHAR(255) NOT NULL, -- vibe | traveler_type | amenity
    name        VARCHAR(255) NOT NULL,
    icon        VARCHAR(255) NULL,
    description VARCHAR(255) NULL,
    image_path  VARCHAR(255) NULL,
    image_url   VARCHAR(2048) NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL,
    CONSTRAINT onboarding_options_type_name_unique UNIQUE (type, name)
);

CREATE TABLE IF NOT EXISTS user_preferences (
    id                              BIGSERIAL PRIMARY KEY,
    user_id                         BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    destination                     VARCHAR(255) NULL,
    traveler_type                   VARCHAR(255) NULL,
    vibes                           JSONB NULL,
    amenities                       JSONB NULL,
    activities                      JSONB NULL,
    notes                           TEXT NULL,
    recommendation_explanations     JSONB NULL, -- per-destination 2-3 sentence overviews
    created_at                      TIMESTAMPTZ NULL,
    updated_at                      TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS reviews (
    id                      BIGSERIAL PRIMARY KEY,
    booking_id              BIGINT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    booking_item_id         BIGINT NULL REFERENCES booking_items(id) ON DELETE CASCADE,
    user_id                 BIGINT NULL REFERENCES users(id) ON DELETE CASCADE,
    reviewer_name           VARCHAR(255) NULL,
    reviewable_type         VARCHAR(255) NOT NULL, -- hotel|room|activity|package|add_on
    reviewable_id           BIGINT NOT NULL,       -- polymorphic
    hotel_id                BIGINT NULL,
    room_id                 BIGINT NULL,
    activity_id             BIGINT NULL,
    package_id              BIGINT NULL,
    rating                  SMALLINT NOT NULL DEFAULT 5
        CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5),
    comment                 TEXT NOT NULL,
    sentiment               VARCHAR(20) NOT NULL DEFAULT 'neutral',
    ground_truth_sentiment  VARCHAR(20) NULL,
    sentiment_score         DECIMAL(5,4) NOT NULL DEFAULT 0.5000,
    extracted_keywords      JSONB NOT NULL DEFAULT '[]'::jsonb,
    is_verified_booking     BOOLEAN NOT NULL DEFAULT TRUE,
    is_published            BOOLEAN NOT NULL DEFAULT TRUE,
    is_featured             BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL,
    CONSTRAINT reviews_booking_item_unique UNIQUE (booking_id, booking_item_id)
);
CREATE INDEX IF NOT EXISTS reviews_reviewable_index ON reviews (reviewable_type, reviewable_id);
CREATE INDEX IF NOT EXISTS reviews_hotel_room_index ON reviews (hotel_id, room_id);
CREATE INDEX IF NOT EXISTS reviews_activity_index ON reviews (activity_id);
CREATE INDEX IF NOT EXISTS reviews_package_index ON reviews (package_id);
CREATE INDEX IF NOT EXISTS reviews_rating_sentiment_index ON reviews (rating, sentiment);
CREATE INDEX IF NOT EXISTS reviews_is_featured_index ON reviews (is_featured);

CREATE TABLE IF NOT EXISTS review_summaries (
    id                      BIGSERIAL PRIMARY KEY,
    summarizable_type       VARCHAR(255) NOT NULL,
    summarizable_id         BIGINT NULL,
    total_reviews           INTEGER NOT NULL DEFAULT 0,
    average_rating          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    positive_percentage     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    neutral_percentage      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    negative_percentage     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    ai_summary_text         TEXT NULL,
    top_positive_highlights JSONB NOT NULL DEFAULT '[]'::jsonb,
    top_negative_highlights JSONB NOT NULL DEFAULT '[]'::jsonb,
    most_frequent_keywords  JSONB NOT NULL DEFAULT '[]'::jsonb,
    last_analyzed_at        TIMESTAMPTZ NULL,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_review_summaries_unique
    ON review_summaries (summarizable_type, COALESCE(summarizable_id, 0));

-- DSS context (informational; full tables in 02/03):
-- users.preferences_embedding VECTOR(3072)  - queried by RecommendationController
-- hotels.embedding, rooms.embedding, activities.embedding,
-- add_ons.embedding, packages.embedding, faqs.embedding -> pgvector <=> cosine search
-- user_preferences.recommendation_explanations caches batched Gemini overviews per destination
