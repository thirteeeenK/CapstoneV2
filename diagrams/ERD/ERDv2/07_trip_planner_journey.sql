-- =============================================================================
-- SunnyTrips - 07 Trip Planner & Journey (Decomposed ERD)
-- Module: trip_plans, trip_plan_items, trip_itineraries, trip_journey_messages
-- Reconciled against 109 migrations (2026-11-17).
-- Paste independently: users/destinations/bookings provided as stubs.
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS destinations (id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL);
CREATE TABLE IF NOT EXISTS bookings (id BIGSERIAL PRIMARY KEY, booking_code VARCHAR(30) NOT NULL UNIQUE);

CREATE TABLE IF NOT EXISTS trip_plans (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT NULL REFERENCES users(id) ON DELETE CASCADE,
    destination_id          BIGINT NOT NULL REFERENCES destinations(id) ON DELETE RESTRICT,
    title                   VARCHAR(255) NOT NULL,
    start_date              DATE NULL,
    end_date                DATE NULL,
    pax                     SMALLINT NOT NULL DEFAULT 2,
    mode                    VARCHAR(12) NOT NULL DEFAULT 'walking',
    revision                INTEGER NOT NULL DEFAULT 1,
    source                  VARCHAR(20) NOT NULL DEFAULT 'manual',
    status                  VARCHAR(20) NOT NULL DEFAULT 'dated',
    duration_days           SMALLINT NULL,
    context                 JSONB NULL,
    source_suggestion_uuid  UUID NULL,
    session_token           VARCHAR(64) NULL,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL,
    CONSTRAINT trip_plan_date_pair CHECK (
        (start_date IS NULL AND end_date IS NULL)
        OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date > start_date)
    ),
    CONSTRAINT trip_plan_owner CHECK (user_id IS NOT NULL OR session_token IS NOT NULL)
);
CREATE INDEX IF NOT EXISTS trip_plans_user_id_updated_at_index ON trip_plans (user_id, updated_at);
CREATE INDEX IF NOT EXISTS trip_plans_destination_id_index ON trip_plans (destination_id);
CREATE INDEX IF NOT EXISTS trip_plans_session_token_user_id_index ON trip_plans (session_token, user_id);
CREATE UNIQUE INDEX IF NOT EXISTS trip_plans_user_id_source_suggestion_uuid_unique
    ON trip_plans (user_id, source_suggestion_uuid);
CREATE UNIQUE INDEX IF NOT EXISTS trip_plans_guest_suggestion_unique
    ON trip_plans (session_token, source_suggestion_uuid)
    WHERE user_id IS NULL AND source_suggestion_uuid IS NOT NULL;

CREATE TABLE IF NOT EXISTS trip_plan_items (
    id              BIGSERIAL PRIMARY KEY,
    trip_plan_id    BIGINT NOT NULL REFERENCES trip_plans(id) ON DELETE CASCADE,
    item_type       VARCHAR(20) NOT NULL,   -- hotel | room | activity | package | add_on
    item_id         BIGINT NOT NULL,        -- polymorphic, no FK
    options         JSONB NOT NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS trip_plan_items_trip_plan_id_item_type_item_id_unique
    ON trip_plan_items (trip_plan_id, item_type, item_id);

CREATE TABLE IF NOT EXISTS trip_itineraries (
    id              BIGSERIAL PRIMARY KEY,
    trip_plan_id    BIGINT NOT NULL REFERENCES trip_plans(id) ON DELETE CASCADE,
    revision        INTEGER NOT NULL,
    snapshot        JSONB NOT NULL,
    transferred_at  TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS trip_itineraries_trip_plan_id_revision_index
    ON trip_itineraries (trip_plan_id, revision);

CREATE TABLE IF NOT EXISTS trip_journey_messages (
    id                  BIGSERIAL PRIMARY KEY,
    user_id             BIGINT NULL REFERENCES users(id) ON DELETE CASCADE,
    booking_id          BIGINT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    trip_plan_id        BIGINT NULL REFERENCES trip_plans(id) ON DELETE CASCADE,
    dedupe_key          VARCHAR(255) NOT NULL UNIQUE,
    kind                VARCHAR(20) NOT NULL,
    date_signature      VARCHAR(64) NULL,
    status              VARCHAR(12) NOT NULL DEFAULT 'pending',
    status_reason       VARCHAR(60) NULL,
    recipient_email     VARCHAR(150) NULL,
    service_group       VARCHAR(120) NULL,
    payload             JSONB NOT NULL,
    attempted_at        TIMESTAMPTZ NULL,
    sent_at             TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS trip_journey_messages_user_id_created_at_index
    ON trip_journey_messages (user_id, created_at);
CREATE INDEX IF NOT EXISTS trip_journey_messages_booking_id_index
    ON trip_journey_messages (booking_id);
CREATE INDEX IF NOT EXISTS trip_journey_messages_trip_plan_id_index
    ON trip_journey_messages (trip_plan_id);
CREATE INDEX IF NOT EXISTS trip_journey_messages_status_created_at_index
    ON trip_journey_messages (status, created_at);
CREATE INDEX IF NOT EXISTS trip_journey_messages_booking_id_service_group_created_at_index
    ON trip_journey_messages (booking_id, service_group, created_at);

-- Relationships:
-- trip_plans.user_id -> users.id CASCADE (nullable for guest plans)
-- trip_plans.destination_id -> destinations.id RESTRICT
-- trip_plan_items.trip_plan_id -> trip_plans.id CASCADE; item_id is polymorphic
-- trip_itineraries.trip_plan_id -> trip_plans.id CASCADE
-- trip_journey_messages.user_id -> users.id CASCADE (nullable for guest bookings)
-- trip_journey_messages.booking_id -> bookings.id CASCADE
-- trip_journey_messages.trip_plan_id -> trip_plans.id CASCADE
-- Guest plans are owned by session_token (Laravel session), claimed on login.
-- trip_plans_guest_suggestion_unique dedupes chatbot-suggested plans per guest;
-- trip_plan_owner guarantees every plan has a user or a guest session.
-- cart_items (module 04) reference trip_itineraries RESTRICT and
-- trip_plan_items SET NULL; bookings.trip_itineraries snapshots the plan at
-- checkout. trip_journey_messages powers reminder/marketing/follow-up email
-- dispatch with dedupe_key and recipient_email for admin-created guest bookings.
