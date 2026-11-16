-- =============================================================================
-- SunnyTrips Capstone V2 - FULL ERD (PostgreSQL)
-- Generated strictly from database/migrations/*.php
-- Compatible with drawdb.app (Import -> PostgreSQL) & psql / DBeaver / pgAdmin.
-- Tables: 57 (49 application + 8 Laravel system). 109 migrations reconciled.
-- Last reconciled: 2026-11-17 (search_tsv migration included).
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

-- ---------------------------------------------------------------------------
-- 1) CORE - Identity & Admin
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admins (
    id                      BIGSERIAL PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    email                   VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at       TIMESTAMPTZ NULL,
    password                VARCHAR(255) NOT NULL,
    remember_token          VARCHAR(100) NULL,
    two_factor_secret       TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMPTZ NULL,
    two_factor_required     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS users (
    id                      BIGSERIAL PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    email                   VARCHAR(255) NOT NULL UNIQUE,
    google_id               VARCHAR(255) NULL UNIQUE,
    avatar                  VARCHAR(255) NULL,
    phone_number            VARCHAR(15) NULL,
    address                 VARCHAR(255) NULL,
    email_verified_at       TIMESTAMPTZ NULL,
    password                VARCHAR(255) NULL,
    remember_token          VARCHAR(100) NULL,
    preferences_embedding   VECTOR(3072) NULL,
    chatbot_flag_count      INTEGER NOT NULL DEFAULT 0,
    ban_reason              VARCHAR(255) NULL,
    ban_level               VARCHAR(255) NULL,
    banned_at               TIMESTAMPTZ NULL,
    ban_expires_at          TIMESTAMPTZ NULL,
    terms_accepted_at       TIMESTAMPTZ NULL,
    privacy_accepted_at     TIMESTAMPTZ NULL,
    ai_disclosure_accepted_at TIMESTAMPTZ NULL,
    terms_version           VARCHAR(10) NULL,
    privacy_version         VARCHAR(10) NULL,
    ai_disclosure_version   VARCHAR(10) NULL,
    consent_ip_address      VARCHAR(45) NULL,
    consent_user_agent      VARCHAR(255) NULL,
    trip_reminders_enabled  BOOLEAN NOT NULL DEFAULT TRUE,
    trip_marketing_enabled  BOOLEAN NOT NULL DEFAULT FALSE,
    lucky_attempts_reset_at TIMESTAMPTZ NULL,
    deleted_at              TIMESTAMPTZ NULL,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS users_deleted_at_index ON users (deleted_at);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email       VARCHAR(255) PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS admin_password_reset_tokens (
    email       VARCHAR(255) PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id              VARCHAR(255) PRIMARY KEY,
    user_id         BIGINT NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    payload         TEXT NOT NULL,
    last_activity   INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id);
CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity);

-- ---------------------------------------------------------------------------
-- 2) DESTINATIONS & INVENTORY
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS destinations (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    region      VARCHAR(255) NULL,
    description TEXT NULL,
    image       VARCHAR(255) NULL,
    latitude    DECIMAL(10,8) NULL,
    longitude   DECIMAL(11,8) NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS hotels (
    id                  BIGSERIAL PRIMARY KEY,
    hotel_name          VARCHAR(255) NOT NULL,
    destination_id      BIGINT NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    type                VARCHAR(255) NOT NULL,
    vibe_tags           JSON NULL,
    featured_amenities  JSON NULL,
    hotel_description   TEXT NOT NULL,
    specific_address    VARCHAR(255) NOT NULL,
    latitude            DECIMAL(10,8) NOT NULL,
    longitude           DECIMAL(11,8) NOT NULL,
    check_in_time       TIME NULL,
    check_out_time      TIME NULL,
    images              VARCHAR(255) NULL,
    is_shown            BOOLEAN NOT NULL DEFAULT TRUE,
    embedding           VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model     VARCHAR(255) NULL,
    embedded_at         TIMESTAMPTZ NULL,
    -- search_tsv is GENERATED ALWAYS AS (to_tsvector('simple',
    --   coalesce(hotel_name,'') || ' ' || coalesce(type,'') || ' ' ||
    --   coalesce(hotel_description,'') || ' ' || coalesce(vibe_tags::text,'') || ' ' ||
    --   coalesce(featured_amenities::text,''))) STORED.
    search_tsv          TSVECTOR NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
-- CREATE INDEX hotels_search_tsv_idx ON hotels USING GIN (search_tsv);
CREATE INDEX IF NOT EXISTS hotels_visible_destination_idx
    ON hotels (destination_id) WHERE is_shown = TRUE AND embedding IS NOT NULL;

CREATE TABLE IF NOT EXISTS rooms (
    id                  BIGSERIAL PRIMARY KEY,
    hotel_id            BIGINT NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,
    total_rooms         INTEGER NOT NULL DEFAULT 1,
    room_name           VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    view_type           VARCHAR(255) NULL,
    ideal_for           VARCHAR(255) NULL,
    ideal_guest         VARCHAR(255) NULL,
    additional_notes    TEXT NULL,
    occupancy           INTEGER NULL,
    base_occupancy      INTEGER NULL DEFAULT 2,
    max_occupancy       INTEGER NULL,
    bed_configuration   VARCHAR(255) NULL,
    room_size           VARCHAR(255) NULL,
    room_amenities      TEXT NOT NULL,
    base_price          DECIMAL(10,2) NOT NULL,
    extra_person_fee    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    images              JSON NULL,
    is_shown            BOOLEAN NOT NULL DEFAULT TRUE,
    embedding           VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model     VARCHAR(255) NULL,
    embedded_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS rooms_visible_hotel_idx
    ON rooms (hotel_id) WHERE is_shown = TRUE AND embedding IS NOT NULL;

CREATE TABLE IF NOT EXISTS activities (
    id              BIGSERIAL PRIMARY KEY,
    destination_id  BIGINT NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    activity_name   VARCHAR(255) NOT NULL,
    category        VARCHAR(255) NOT NULL,
    activity_level  VARCHAR(255) NOT NULL,
    rate            VARCHAR(255) NOT NULL,
    duration        VARCHAR(255) NULL,
    capacity        VARCHAR(255) NULL,
    requirements    TEXT NULL,
    ideal_for       VARCHAR(255) NULL,
    vibe_tags       JSON NULL,
    description     TEXT NULL,
    inclusions      JSON NULL,
    exclusions      JSON NULL,
    itinerary       JSON NULL,
    notes           TEXT NULL,
    images          JSON NULL,
    is_shown        BOOLEAN NOT NULL DEFAULT TRUE,
    latitude        DECIMAL(10,8) NULL,
    longitude       DECIMAL(11,8) NULL,
    specific_address VARCHAR(255) NULL,
    scheduling      JSONB NULL,
    embedding       VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model VARCHAR(255) NULL,
    embedded_at     TIMESTAMPTZ NULL,
    -- search_tsv is GENERATED ALWAYS AS (to_tsvector('simple',
    --   coalesce(activity_name,'') || ' ' || coalesce(category,'') || ' ' ||
    --   coalesce(activity_level,'') || ' ' || coalesce(ideal_for,'') || ' ' ||
    --   coalesce(description,'') || ' ' || coalesce(vibe_tags::text,'') || ' ' ||
    --   coalesce(inclusions::text,''))) STORED.
    search_tsv      TSVECTOR NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
-- CREATE INDEX activities_search_tsv_idx ON activities USING GIN (search_tsv);
CREATE INDEX IF NOT EXISTS activities_visible_destination_idx
    ON activities (destination_id) WHERE is_shown = TRUE AND embedding IS NOT NULL;

CREATE TABLE IF NOT EXISTS weather_cache (
    id              BIGSERIAL PRIMARY KEY,
    cache_key       VARCHAR(255) NOT NULL UNIQUE,
    weather_data    JSON NOT NULL,
    fetched_at      TIMESTAMPTZ NULL,
    expires_at      TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

-- ---------------------------------------------------------------------------
-- 3) PACKAGES & ADD-ONS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS packages (
    id                  BIGSERIAL PRIMARY KEY,
    destination_id      BIGINT NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    name                VARCHAR(255) NOT NULL,
    type                VARCHAR(255) NULL,
    price               DECIMAL(10,2) NOT NULL,
    days                INTEGER NULL,
    nights              INTEGER NULL,
    min_pax             INTEGER NOT NULL DEFAULT 2,
    valid_from          DATE NULL,
    valid_to            DATE NULL,
    generic_inclusions  JSON NULL,
    images              JSON NULL,
    separate_room_fee   DECIMAL(10,2) NULL,
    embedding           VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model     VARCHAR(255) NULL,
    embedded_at         TIMESTAMPTZ NULL,
    -- search_tsv is GENERATED ALWAYS AS (to_tsvector('simple',
    --   coalesce(name,'') || ' ' || coalesce(type,'') || ' ' ||
    --   coalesce(generic_inclusions::text,''))) STORED.
    search_tsv          TSVECTOR NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
-- CREATE INDEX packages_search_tsv_idx ON packages USING GIN (search_tsv);
CREATE INDEX IF NOT EXISTS packages_active_destination_idx
    ON packages (destination_id) WHERE is_active = TRUE AND embedding IS NOT NULL;

CREATE TABLE IF NOT EXISTS package_hotel (
    id          BIGSERIAL PRIMARY KEY,
    package_id  BIGINT NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
    hotel_id    BIGINT NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,
    room_type_id BIGINT NULL REFERENCES rooms(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS package_hotel_room_type_id_index ON package_hotel (room_type_id);
CREATE UNIQUE INDEX IF NOT EXISTS package_hotel_package_hotel_room_unique
    ON package_hotel (package_id, hotel_id, COALESCE(room_type_id, -1));

CREATE TABLE IF NOT EXISTS package_activity (
    id          BIGSERIAL PRIMARY KEY,
    package_id  BIGINT NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
    activity_id BIGINT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS add_ons (
    id              BIGSERIAL PRIMARY KEY,
    destination_id  BIGINT NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    name            VARCHAR(255) NOT NULL,
    type            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    inclusions      JSON NULL,
    pricing_tiers   JSON NULL,
    surcharges      JSON NULL,
    is_shown        BOOLEAN NOT NULL DEFAULT TRUE,
    embedding       VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model VARCHAR(255) NULL,
    embedded_at     TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS add_ons_visible_destination_idx
    ON add_ons (destination_id) WHERE is_shown = TRUE AND embedding IS NOT NULL;

CREATE TABLE IF NOT EXISTS package_addon (
    id          BIGSERIAL PRIMARY KEY,
    package_id  BIGINT NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
    add_on_id   BIGINT NOT NULL REFERENCES add_ons(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS package_addon_package_id_index ON package_addon (package_id);
CREATE INDEX IF NOT EXISTS package_addon_add_on_id_index ON package_addon (add_on_id);
CREATE UNIQUE INDEX IF NOT EXISTS package_addon_package_id_add_on_id_unique
    ON package_addon (package_id, add_on_id);

-- ---------------------------------------------------------------------------
-- 4) COMMERCE - Cart, Bookings, Payments
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS cart_items (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token   VARCHAR(255) NULL,
    item_type       VARCHAR(255) NOT NULL,
    item_id         BIGINT NOT NULL,
    quantity        INTEGER NOT NULL DEFAULT 1,
    check_in_date   DATE NULL,
    check_out_date  DATE NULL,
    selected_pax    INTEGER NOT NULL DEFAULT 1,
    is_selected     BOOLEAN NOT NULL DEFAULT TRUE,
    notes           TEXT NULL,
    lucky_group_id  VARCHAR(36) NULL,
    room_option     VARCHAR(16) NULL,
    trip_itinerary_id BIGINT NULL,
    trip_plan_item_id BIGINT NULL,
    lucky_offer_id  BIGINT NULL,
    rebooked_from_booking_id BIGINT NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS cart_items_item_type_item_id_index ON cart_items (item_type, item_id);
CREATE INDEX IF NOT EXISTS cart_items_session_token_index ON cart_items (session_token);
CREATE INDEX IF NOT EXISTS cart_items_lucky_group_id_index ON cart_items (lucky_group_id);
CREATE INDEX IF NOT EXISTS cart_items_user_id_index ON cart_items (user_id);
CREATE INDEX IF NOT EXISTS cart_items_trip_itinerary_id_index ON cart_items (trip_itinerary_id);
CREATE INDEX IF NOT EXISTS cart_items_trip_plan_item_id_index ON cart_items (trip_plan_item_id);
CREATE INDEX IF NOT EXISTS cart_items_lucky_offer_id_index ON cart_items (lucky_offer_id);
CREATE UNIQUE INDEX IF NOT EXISTS cart_items_trip_unique
    ON cart_items (trip_itinerary_id, trip_plan_item_id);

CREATE TABLE IF NOT EXISTS bookings (
    id                          BIGSERIAL PRIMARY KEY,
    booking_code                VARCHAR(30) NOT NULL UNIQUE,
    user_id                     BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    booking_source              VARCHAR(30) NOT NULL DEFAULT 'customer_web',
    is_walk_in                  BOOLEAN NOT NULL DEFAULT FALSE,
    status                      VARCHAR(255) NOT NULL DEFAULT 'pending',
    approved_at                 TIMESTAMPTZ NULL,
    payment_deadline            TIMESTAMPTZ NULL,
    paid_at                     TIMESTAMPTZ NULL,
    rejected_at                 TIMESTAMPTZ NULL,
    rejection_reason            TEXT NULL,
    cancelled_at                TIMESTAMPTZ NULL,
    cancellation_reason         TEXT NULL,
    cancellation_request_reason TEXT NULL,
    cancellation_requested_at   TIMESTAMPTZ NULL,
    cancellation_requested_from VARCHAR(30) NULL,
    expired_at                  TIMESTAMPTZ NULL,
    trip_itineraries            JSONB NULL,
    trip_end_override           TIMESTAMPTZ NULL,
    admin_notes                 TEXT NULL,
    reviewed_by_admin_id        BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    booked_by_admin_id          BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    total_amount                DECIMAL(12,2) NOT NULL,
    discount_amount             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    promotion_discount_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount                  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    admin_discount_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    admin_surcharge_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_adjustment_reason     TEXT NULL,
    price_adjusted_at           TIMESTAMPTZ NULL,
    net_amount                  DECIMAL(12,2) NOT NULL,
    payment_status              VARCHAR(255) NOT NULL DEFAULT 'unpaid',
    payment_method              VARCHAR(50) NULL,
    payment_reference           VARCHAR(100) NULL,
    gateway                     VARCHAR(20) NULL,
    gateway_reference           VARCHAR(150) NULL,
    payment_url                 TEXT NULL,
    gateway_data                JSONB NULL,
    payment_plan_version        SMALLINT NULL,
    payment_plan                VARCHAR(20) NULL,
    payment_environment         VARCHAR(8) NULL,
    payment_policy              JSONB NULL,
    deposit_required_cents      BIGINT NULL,
    deposit_secured_at          TIMESTAMPTZ NULL,
    balance_due_at              TIMESTAMPTZ NULL,
    balance_overdue_at          TIMESTAMPTZ NULL,
    rebooked_from_booking_id    BIGINT NULL REFERENCES bookings(id) ON DELETE SET NULL,
    contact_name                VARCHAR(150) NOT NULL,
    contact_email               VARCHAR(150) NOT NULL,
    contact_phone               VARCHAR(30) NOT NULL,
    special_requests            TEXT NULL,
    guest_manifest              JSON NULL,
    created_at                  TIMESTAMPTZ NULL,
    updated_at                  TIMESTAMPTZ NULL,
    CONSTRAINT bookings_status_check CHECK (status IN (
        'pending', 'approved', 'paid', 'completed', 'rejected',
        'cancelled', 'expired', 'cancellation_requested', 'cancellation_denied'
    )),
    CONSTRAINT bookings_payment_status_check CHECK (payment_status IN (
        'unpaid', 'partial', 'paid', 'refunded'
    ))
);
CREATE INDEX IF NOT EXISTS bookings_status_payment_deadline_index ON bookings (status, payment_deadline);
CREATE INDEX IF NOT EXISTS bookings_user_id_status_index ON bookings (user_id, status);
CREATE INDEX IF NOT EXISTS bookings_status_balance_due_at_index ON bookings (status, balance_due_at);
CREATE INDEX IF NOT EXISTS bookings_rebooked_from_booking_id_index ON bookings (rebooked_from_booking_id);

CREATE TABLE IF NOT EXISTS booking_items (
    id                  BIGSERIAL PRIMARY KEY,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    item_type           VARCHAR(50) NOT NULL,
    item_id             BIGINT NOT NULL,
    item_title          VARCHAR(255) NOT NULL,
    item_subtitle       VARCHAR(255) NULL,
    hotel_name          VARCHAR(255) NULL,
    unit_price          DECIMAL(12,2) NOT NULL,
    quantity            INTEGER NOT NULL DEFAULT 1,
    selected_pax        INTEGER NOT NULL DEFAULT 1,
    check_in_date       DATE NULL,
    check_out_date      DATE NULL,
    nights              INTEGER NOT NULL DEFAULT 1,
    subtotal            DECIMAL(12,2) NOT NULL,
    promotion_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    item_snapshot       JSON NULL,
    availability_status VARCHAR(255) NOT NULL DEFAULT 'pending',
    fulfillment_status  VARCHAR(12) NOT NULL DEFAULT 'pending',
    fulfilled_at        TIMESTAMPTZ NULL,
    fulfillment_admin_id BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    admin_note          TEXT NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL,
    CONSTRAINT booking_items_availability_status_check CHECK (availability_status IN (
        'pending', 'available', 'unavailable'
    ))
);
CREATE INDEX IF NOT EXISTS booking_items_item_type_item_id_index ON booking_items (item_type, item_id);
CREATE INDEX IF NOT EXISTS booking_items_fulfillment_admin_id_index ON booking_items (fulfillment_admin_id);

CREATE TABLE IF NOT EXISTS booking_attachments (
    id                  BIGSERIAL PRIMARY KEY,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    booking_item_id     BIGINT NULL REFERENCES booking_items(id) ON DELETE SET NULL,
    kind                VARCHAR(20) NOT NULL DEFAULT 'other',
    label               VARCHAR(120) NOT NULL,
    path                VARCHAR(255) NOT NULL,
    mime                VARCHAR(100) NULL,
    size                INTEGER NOT NULL DEFAULT 0,
    uploaded_by_admin_id BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS booking_attachments_booking_id_index ON booking_attachments (booking_id);

CREATE TABLE IF NOT EXISTS booking_status_history (
    id          BIGSERIAL PRIMARY KEY,
    booking_id  BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    from_status VARCHAR(30) NULL,
    to_status   VARCHAR(30) NOT NULL,
    note        TEXT NULL,
    actor_type  VARCHAR(50) NULL,
    actor_id    BIGINT NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS booking_status_history_booking_to_status_index ON booking_status_history (booking_id, to_status);

CREATE TABLE IF NOT EXISTS booking_payment_requests (
    id                  BIGSERIAL PRIMARY KEY,
    uuid                UUID NOT NULL UNIQUE,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    purpose             VARCHAR(12) NOT NULL,
    amount              BIGINT NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'PHP',
    gateway             VARCHAR(20) NOT NULL,
    livemode            BOOLEAN NOT NULL,
    quote_amount        BIGINT NOT NULL,
    status              VARCHAR(30) NOT NULL DEFAULT 'creating',
    provider_reference  VARCHAR(150) NULL,
    checkout_url        TEXT NULL,
    due_at              TIMESTAMPTZ NOT NULL,
    confirmed_at        TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL,
    CONSTRAINT payment_request_amount_positive CHECK (amount > 0 AND quote_amount > 0)
);
CREATE INDEX IF NOT EXISTS booking_payment_requests_booking_id_id_index
    ON booking_payment_requests (booking_id, id);
CREATE INDEX IF NOT EXISTS booking_payment_requests_status_updated_at_index
    ON booking_payment_requests (status, updated_at);
CREATE UNIQUE INDEX IF NOT EXISTS payment_request_provider_unique
    ON booking_payment_requests (gateway, livemode, provider_reference);
CREATE UNIQUE INDEX IF NOT EXISTS payment_request_one_active
    ON booking_payment_requests (booking_id)
    WHERE status IN ('creating', 'pending', 'awaiting_verification', 'processing', 'review_required');

CREATE TABLE IF NOT EXISTS booking_payments (
    id                  BIGSERIAL PRIMARY KEY,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    provider            VARCHAR(30) NOT NULL DEFAULT 'gotyme',
    sender_reference    VARCHAR(50) NOT NULL,
    normalized_reference VARCHAR(50) NOT NULL,
    claimed_amount      DECIMAL(12,2) NOT NULL,
    verified_amount     DECIMAL(12,2) NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'submitted',
    submitted_at        TIMESTAMP NOT NULL,
    verified_at         TIMESTAMP NULL,
    verified_by_admin_id BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    admin_note          VARCHAR(500) NULL,
    booking_payment_request_id BIGINT NULL REFERENCES booking_payment_requests(id) ON DELETE RESTRICT,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS booking_payments_provider_reference_unique
    ON booking_payments (provider, normalized_reference);
CREATE INDEX IF NOT EXISTS booking_payments_booking_status_index
    ON booking_payments (booking_id, status);
CREATE INDEX IF NOT EXISTS booking_payments_verifier_index
    ON booking_payments (verified_by_admin_id);
CREATE INDEX IF NOT EXISTS booking_payments_booking_payment_request_id_index
    ON booking_payments (booking_payment_request_id);

CREATE TABLE IF NOT EXISTS booking_payment_transactions (
    id                  BIGSERIAL PRIMARY KEY,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    booking_payment_request_id BIGINT NULL REFERENCES booking_payment_requests(id) ON DELETE RESTRICT,
    original_transaction_id BIGINT NULL REFERENCES booking_payment_transactions(id) ON DELETE RESTRICT,
    gateway             VARCHAR(20) NOT NULL,
    livemode            BOOLEAN NOT NULL,
    source_reference    VARCHAR(160) NOT NULL,
    kind                VARCHAR(12) NOT NULL DEFAULT 'receipt',
    amount              BIGINT NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'PHP',
    allocated           BOOLEAN NOT NULL DEFAULT TRUE,
    occurred_at         TIMESTAMPTZ NOT NULL,
    verified_by_admin_id BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    note                VARCHAR(500) NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL,
    CONSTRAINT payment_transaction_amount_positive CHECK (amount > 0)
);
CREATE UNIQUE INDEX IF NOT EXISTS payment_transaction_source_unique
    ON booking_payment_transactions (gateway, livemode, source_reference);
CREATE INDEX IF NOT EXISTS booking_payment_transactions_booking_id_id_index
    ON booking_payment_transactions (booking_id, id);
CREATE INDEX IF NOT EXISTS booking_payment_transactions_booking_payment_request_id_index
    ON booking_payment_transactions (booking_payment_request_id);
CREATE INDEX IF NOT EXISTS booking_payment_transactions_original_transaction_id_index
    ON booking_payment_transactions (original_transaction_id);
CREATE INDEX IF NOT EXISTS booking_payment_transactions_verified_by_admin_id_index
    ON booking_payment_transactions (verified_by_admin_id);
CREATE INDEX IF NOT EXISTS booking_payment_transactions_livemode_occurred_at_index
    ON booking_payment_transactions (livemode, occurred_at);

CREATE TABLE IF NOT EXISTS booking_payment_notices (
    id                  BIGSERIAL PRIMARY KEY,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    dedupe_key          VARCHAR(180) NOT NULL UNIQUE,
    kind                VARCHAR(30) NOT NULL,
    booking_payment_transaction_id BIGINT NULL REFERENCES booking_payment_transactions(id) ON DELETE RESTRICT,
    sent_at             TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS booking_payment_notices_sent_at_id_index
    ON booking_payment_notices (sent_at, id);
CREATE INDEX IF NOT EXISTS booking_payment_notices_booking_id_index
    ON booking_payment_notices (booking_id);
CREATE INDEX IF NOT EXISTS booking_payment_notices_booking_payment_transaction_id_index
    ON booking_payment_notices (booking_payment_transaction_id);

CREATE TABLE IF NOT EXISTS qrph_payment_attempts (
    id                  BIGSERIAL PRIMARY KEY,
    uuid                UUID NOT NULL UNIQUE,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
    intent_id           VARCHAR(150) NULL UNIQUE,
    method_id           VARCHAR(150) NULL,
    method_generation   SMALLINT NOT NULL DEFAULT 1,
    qr_code_id          VARCHAR(150) NULL,
    payment_id          VARCHAR(150) NULL UNIQUE,
    amount              BIGINT NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'PHP',
    livemode            BOOLEAN NOT NULL,
    status              VARCHAR(24) NOT NULL DEFAULT 'creating',
    qr_image            TEXT NULL,
    test_url            TEXT NULL,
    expires_at          TIMESTAMPTZ NULL,
    confirmed_at        TIMESTAMPTZ NULL,
    last_checked_at     TIMESTAMPTZ NULL,
    booking_payment_request_id BIGINT NULL REFERENCES booking_payment_requests(id) ON DELETE RESTRICT,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS qrph_payment_attempts_qr_code_id_index ON qrph_payment_attempts (qr_code_id);
CREATE INDEX IF NOT EXISTS qrph_payment_attempts_booking_id_id_index
    ON qrph_payment_attempts (booking_id, id);
CREATE INDEX IF NOT EXISTS qrph_payment_attempts_status_last_checked_at_index
    ON qrph_payment_attempts (status, last_checked_at);
CREATE INDEX IF NOT EXISTS qrph_payment_attempts_booking_payment_request_id_index
    ON qrph_payment_attempts (booking_payment_request_id);

CREATE TABLE IF NOT EXISTS passenger_category_rules (
    id              BIGSERIAL PRIMARY KEY,
    category_name   VARCHAR(255) NOT NULL UNIQUE,
    display_label   VARCHAR(255) NOT NULL,
    adjustment_type VARCHAR(255) NOT NULL DEFAULT 'none',
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL,
    CONSTRAINT passenger_category_rules_adjustment_type_check CHECK (adjustment_type IN (
        'discount', 'surcharge', 'none'
    ))
);

CREATE TABLE IF NOT EXISTS payment_webhook_events (
    id              BIGSERIAL PRIMARY KEY,
    gateway         VARCHAR(32) NOT NULL,
    event_id        VARCHAR(255) NOT NULL,
    booking_code    VARCHAR(64) NOT NULL,
    processed_at    TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL,
    CONSTRAINT payment_webhook_gateway_event_unique UNIQUE (gateway, event_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id              UUID PRIMARY KEY,
    type            VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id   BIGINT NOT NULL,
    data            TEXT NOT NULL,
    read_at         TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS notifications_notifiable_index ON notifications (notifiable_type, notifiable_id);

-- ---------------------------------------------------------------------------
-- 5) PROMOTIONS & LUCKY REWARDS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS promotions (
    id                      BIGSERIAL PRIMARY KEY,
    name                    VARCHAR(120) NOT NULL,
    is_active               BOOLEAN NOT NULL DEFAULT FALSE,
    discount_type           VARCHAR(10) NOT NULL,
    percent_off             SMALLINT NULL,
    fixed_off_cents         BIGINT NULL,
    max_discount_cents      BIGINT NULL,
    min_eligible_cents      BIGINT NOT NULL DEFAULT 0,
    redeem_starts_at        TIMESTAMPTZ NULL,
    redeem_ends_at          TIMESTAMPTZ NULL,
    travel_starts_on        DATE NULL,
    travel_ends_on          DATE NULL,
    destination_ids         JSONB NULL,
    min_nights              SMALLINT NULL,
    max_nights              SMALLINT NULL,
    min_pax                 SMALLINT NULL,
    max_pax                 SMALLINT NULL,
    total_redemption_limit  INTEGER NULL,
    per_traveler_limit      INTEGER NOT NULL DEFAULT 1,
    offer_valid_hours       SMALLINT NOT NULL DEFAULT 72,
    award_weight            SMALLINT NOT NULL DEFAULT 1,
    win_percent             SMALLINT NOT NULL DEFAULT 80,
    duration_days           SMALLINT NULL,
    terms                   TEXT NULL,
    revision                INTEGER NOT NULL DEFAULT 1,
    created_by_admin_id     BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    updated_by_admin_id     BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL,
    CONSTRAINT promotions_discount_shape CHECK (
        (discount_type = 'percent' AND percent_off BETWEEN 1 AND 100 AND fixed_off_cents IS NULL)
        OR (discount_type = 'fixed' AND fixed_off_cents > 0 AND percent_off IS NULL)
    )
);
CREATE INDEX IF NOT EXISTS promotions_is_active_index ON promotions (is_active);

CREATE TABLE IF NOT EXISTS lucky_offers (
    id                      BIGSERIAL PRIMARY KEY,
    uuid                    UUID NOT NULL UNIQUE,
    user_id                 BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    promotion_id            BIGINT NULL REFERENCES promotions(id) ON DELETE SET NULL,
    promotion_revision      INTEGER NULL,
    terms                   JSONB NULL,
    destination_id          BIGINT NOT NULL REFERENCES destinations(id) ON DELETE CASCADE,
    room_id                 BIGINT NOT NULL,
    activity_ids            JSONB NOT NULL,
    nights                  SMALLINT NOT NULL,
    pax                     SMALLINT NOT NULL,
    check_in_date           DATE NOT NULL,
    check_out_date          DATE NOT NULL,
    max_budget              DECIMAL(12,2) NOT NULL,
    budget_exceeded         BOOLEAN NOT NULL DEFAULT FALSE,
    quoted_total_cents      BIGINT NOT NULL,
    estimated_discount_cents BIGINT NOT NULL DEFAULT 0,
    selection_signature     VARCHAR(64) NOT NULL,
    lucky_group_id          UUID NULL,
    accepted_at             TIMESTAMPTZ NULL,
    expires_at              TIMESTAMPTZ NULL,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS lucky_offers_user_id_created_at_index
    ON lucky_offers (user_id, created_at);
CREATE INDEX IF NOT EXISTS lucky_offers_promotion_id_user_id_created_at_index
    ON lucky_offers (promotion_id, user_id, created_at);

CREATE TABLE IF NOT EXISTS promotion_redemptions (
    id                  BIGSERIAL PRIMARY KEY,
    promotion_id        BIGINT NOT NULL REFERENCES promotions(id) ON DELETE RESTRICT,
    lucky_offer_id      BIGINT NOT NULL UNIQUE REFERENCES lucky_offers(id) ON DELETE RESTRICT,
    booking_id          BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    user_id             BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    status              VARCHAR(12) NOT NULL,
    eligible_cents      BIGINT NOT NULL,
    discount_cents      BIGINT NOT NULL,
    allocations         JSONB NOT NULL,
    terms               JSONB NOT NULL,
    status_reason       VARCHAR(160) NULL,
    reserved_at         TIMESTAMPTZ NOT NULL,
    redeemed_at         TIMESTAMPTZ NULL,
    released_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS promotion_redemptions_promotion_id_status_index
    ON promotion_redemptions (promotion_id, status);
CREATE INDEX IF NOT EXISTS promotion_redemptions_promotion_id_user_id_status_index
    ON promotion_redemptions (promotion_id, user_id, status);
CREATE INDEX IF NOT EXISTS promotion_redemptions_booking_id_index
    ON promotion_redemptions (booking_id);

-- ---------------------------------------------------------------------------
-- 6) CHATBOT & HUMAN HANDOFF
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS chat_sessions (
    id              BIGSERIAL PRIMARY KEY,
    session_token   VARCHAR(255) NOT NULL UNIQUE,
    user_id         BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    metadata        JSONB NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id              BIGSERIAL PRIMARY KEY,
    chat_session_id BIGINT NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
    sender          VARCHAR(10) NOT NULL,
    message         TEXT NOT NULL,
    context_data    JSONB NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS chat_messages_session_created_idx
    ON chat_messages (chat_session_id, created_at, id);

CREATE TABLE IF NOT EXISTS support_inquiries (
    id                  BIGSERIAL PRIMARY KEY,
    ticket_number       VARCHAR(255) NOT NULL UNIQUE,
    chat_session_id     BIGINT NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
    user_id             BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    assigned_admin_id   BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    status              VARCHAR(255) NOT NULL DEFAULT 'PENDING_ASSIGNMENT',
    initiated_by        VARCHAR(255) NOT NULL DEFAULT 'user',
    requested_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_at         TIMESTAMPTZ NULL,
    returned_to_ai_at   TIMESTAMPTZ NULL,
    resolved_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS support_inquiries_status_admin_index ON support_inquiries (status, assigned_admin_id);
CREATE INDEX IF NOT EXISTS support_inquiries_user_status_idx ON support_inquiries (user_id, status);
CREATE UNIQUE INDEX IF NOT EXISTS support_inquiries_single_active_per_session
    ON support_inquiries (chat_session_id)
    WHERE status IN ('PENDING_ASSIGNMENT', 'HUMAN_SUPPORT_ACTIVE');

CREATE TABLE IF NOT EXISTS chatbot_abuse_reports (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message     TEXT NOT NULL,
    category    VARCHAR(255) NOT NULL,
    reason      TEXT NULL,
    status      VARCHAR(255) NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS faqs (
    id                  BIGSERIAL PRIMARY KEY,
    question            VARCHAR(255) NOT NULL,
    answer              TEXT NOT NULL,
    keywords            VARCHAR(500) NULL,
    category            VARCHAR(255) NULL,
    source_label        VARCHAR(255) NULL,
    source_url          VARCHAR(2048) NULL,
    reviewed_at         DATE NULL,
    reviewed_by         VARCHAR(255) NULL,
    sort_order          INTEGER NOT NULL DEFAULT 0,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    show_on_landing     BOOLEAN NOT NULL DEFAULT TRUE,
    embedding           VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model     VARCHAR(255) NULL,
    embedded_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS faqs_is_active_sort_order_index ON faqs (is_active, sort_order);
CREATE INDEX IF NOT EXISTS faqs_active_sort_idx
    ON faqs (sort_order) WHERE is_active = TRUE AND embedding IS NOT NULL;

CREATE TABLE IF NOT EXISTS chat_feedback (
    id              BIGSERIAL PRIMARY KEY,
    trace_id        VARCHAR(255) NOT NULL,
    chat_session_id BIGINT NULL REFERENCES chat_sessions(id) ON DELETE SET NULL,
    user_id         BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    helpful         BOOLEAN NOT NULL,
    reason          VARCHAR(30) NULL,
    task_id         VARCHAR(20) NULL,
    expected_answer TEXT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS chat_feedback_trace_id_index ON chat_feedback (trace_id);
CREATE INDEX IF NOT EXISTS chat_feedback_reason_index ON chat_feedback (reason);

CREATE TABLE IF NOT EXISTS knowledge_articles (
    id                  BIGSERIAL PRIMARY KEY,
    title               VARCHAR(255) NOT NULL,
    body                TEXT NOT NULL,
    category            VARCHAR(50) NOT NULL,
    destination_id      BIGINT NULL REFERENCES destinations(id) ON DELETE SET NULL,
    source_label        VARCHAR(255) NOT NULL,
    source_url          VARCHAR(2048) NULL,
    reviewed_at         DATE NULL,
    expires_at          DATE NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',
    reviewed_by         VARCHAR(255) NULL,
    embedding           VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model     VARCHAR(255) NULL,
    embedded_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS knowledge_articles_status_destination_id_index
    ON knowledge_articles (status, destination_id);
CREATE INDEX IF NOT EXISTS knowledge_articles_destination_id_index
    ON knowledge_articles (destination_id);

-- ---------------------------------------------------------------------------
-- 7) REVIEWS, SUMMARIES & ONBOARDING / DSS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS onboarding_options (
    id          BIGSERIAL PRIMARY KEY,
    type        VARCHAR(255) NOT NULL,
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
    recommendation_explanations     JSONB NULL,
    created_at                      TIMESTAMPTZ NULL,
    updated_at                      TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS reviews (
    id                      BIGSERIAL PRIMARY KEY,
    booking_id              BIGINT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    booking_item_id         BIGINT NULL REFERENCES booking_items(id) ON DELETE CASCADE,
    user_id                 BIGINT NULL REFERENCES users(id) ON DELETE CASCADE,
    reviewer_name           VARCHAR(255) NULL,
    reviewable_type         VARCHAR(255) NOT NULL,
    reviewable_id           BIGINT NOT NULL,
    hotel_id                BIGINT NULL,
    room_id                 BIGINT NULL,
    activity_id             BIGINT NULL,
    package_id              BIGINT NULL,
    rating                  SMALLINT NOT NULL DEFAULT 5,
    comment                 TEXT NOT NULL,
    images                  JSONB NOT NULL DEFAULT '[]'::jsonb,
    sentiment               VARCHAR(20) NOT NULL DEFAULT 'neutral',
    ground_truth_sentiment  VARCHAR(20) NULL,
    sentiment_score         DECIMAL(5,4) NOT NULL DEFAULT 0.5000,
    extracted_keywords      JSONB NOT NULL DEFAULT '[]'::jsonb,
    is_verified_booking     BOOLEAN NOT NULL DEFAULT TRUE,
    is_published            BOOLEAN NOT NULL DEFAULT TRUE,
    is_featured             BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL,
    CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5),
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

CREATE TABLE IF NOT EXISTS recommendation_hits (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token   VARCHAR(64) NOT NULL,
    mode            VARCHAR(16) NOT NULL,
    entity_type     VARCHAR(16) NOT NULL,
    entity_id       BIGINT NULL,
    rank            SMALLINT NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS recommendation_hits_user_id_session_token_index
    ON recommendation_hits (user_id, session_token);

-- ---------------------------------------------------------------------------
-- 8) TRIP PLANNER & JOURNEY
-- ---------------------------------------------------------------------------

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
    item_type       VARCHAR(20) NOT NULL,
    item_id         BIGINT NOT NULL,
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

-- ---------------------------------------------------------------------------
-- 9) PLATFORM - Legal, Cache, Jobs, Audit, Security
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS legal_documents (
    id          BIGSERIAL PRIMARY KEY,
    key         VARCHAR(50) NOT NULL UNIQUE,
    title       VARCHAR(150) NOT NULL,
    content     JSON NULL,
    version     VARCHAR(10) NOT NULL DEFAULT '1.0',
    updated_by  VARCHAR(100) NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS cache (
    key         VARCHAR(255) PRIMARY KEY,
    value       TEXT NOT NULL,
    expiration  BIGINT NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration);

CREATE TABLE IF NOT EXISTS cache_locks (
    key         VARCHAR(255) PRIMARY KEY,
    owner       VARCHAR(255) NOT NULL,
    expiration  BIGINT NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE IF NOT EXISTS jobs (
    id              BIGSERIAL PRIMARY KEY,
    queue           VARCHAR(255) NOT NULL,
    payload         TEXT NOT NULL,
    attempts        SMALLINT NOT NULL,
    reserved_at     INTEGER NULL,
    available_at    INTEGER NOT NULL,
    created_at      INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id              VARCHAR(255) PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    total_jobs      INTEGER NOT NULL,
    pending_jobs    INTEGER NOT NULL,
    failed_jobs     INTEGER NOT NULL,
    failed_job_ids  TEXT NOT NULL,
    options         TEXT NULL,
    cancelled_at    INTEGER NULL,
    created_at      INTEGER NOT NULL,
    finished_at     INTEGER NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id          BIGSERIAL PRIMARY KEY,
    uuid        VARCHAR(255) NOT NULL UNIQUE,
    connection  VARCHAR(255) NOT NULL,
    queue       VARCHAR(255) NOT NULL,
    payload     TEXT NOT NULL,
    exception   TEXT NOT NULL,
    failed_at   TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS failed_jobs_connection_queue_failed_at_index
    ON failed_jobs (connection, queue, failed_at);

CREATE TABLE IF NOT EXISTS failed_login_attempts (
    id              BIGSERIAL PRIMARY KEY,
    email           VARCHAR(255) NULL,
    ip_address      VARCHAR(45) NOT NULL,
    guard           VARCHAR(20) NOT NULL DEFAULT 'web',
    attempted_at    TIMESTAMPTZ NOT NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS failed_login_email_index ON failed_login_attempts (email);
CREATE INDEX IF NOT EXISTS failed_login_ip_index ON failed_login_attempts (ip_address);

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id              BIGSERIAL PRIMARY KEY,
    admin_id        BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    auditable_type  VARCHAR(100) NOT NULL,
    auditable_id    BIGINT NOT NULL,
    old_values      JSONB NULL,
    new_values      JSONB NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS admin_audit_admin_index ON admin_audit_logs (admin_id);
CREATE INDEX IF NOT EXISTS admin_audit_auditable_index ON admin_audit_logs (auditable_type, auditable_id);

CREATE TABLE IF NOT EXISTS ip_bans (
    id          BIGSERIAL PRIMARY KEY,
    ip_address  VARCHAR(45) NOT NULL,
    ban_level   VARCHAR(20) NOT NULL,
    reason      VARCHAR(255) NULL,
    banned_at   TIMESTAMPTZ NULL,
    expires_at  TIMESTAMPTZ NULL,
    banned_by   BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS ip_bans_ip_index ON ip_bans (ip_address);
CREATE INDEX IF NOT EXISTS ip_bans_level_index ON ip_bans (ban_level);

CREATE TABLE IF NOT EXISTS system_settings (
    id          BIGSERIAL PRIMARY KEY,
    section     VARCHAR(32) NOT NULL UNIQUE,
    values      JSONB NOT NULL,
    revision    INTEGER NOT NULL DEFAULT 0,
    updated_by  BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS system_settings_updated_by_index ON system_settings (updated_by);

-- ---------------------------------------------------------------------------
-- FORWARD-REFERENCE FOREIGN KEYS
-- cart_items is declared before the trip and promotion sections, so its
-- nullable reference columns are wired here once every target table exists.
-- ---------------------------------------------------------------------------
ALTER TABLE cart_items
    ADD CONSTRAINT cart_items_trip_itinerary_id_foreign
    FOREIGN KEY (trip_itinerary_id) REFERENCES trip_itineraries(id) ON DELETE RESTRICT;
ALTER TABLE cart_items
    ADD CONSTRAINT cart_items_trip_plan_item_id_foreign
    FOREIGN KEY (trip_plan_item_id) REFERENCES trip_plan_items(id) ON DELETE SET NULL;
ALTER TABLE cart_items
    ADD CONSTRAINT cart_items_lucky_offer_id_foreign
    FOREIGN KEY (lucky_offer_id) REFERENCES lucky_offers(id) ON DELETE SET NULL;
ALTER TABLE cart_items
    ADD CONSTRAINT cart_items_rebooked_from_booking_id_foreign
    FOREIGN KEY (rebooked_from_booking_id) REFERENCES bookings(id) ON DELETE SET NULL;
