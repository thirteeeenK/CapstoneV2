-- =============================================================================
-- SunnyTrips - 04 Commerce: Cart, Bookings & Payment (Decomposed ERD)
-- Module: cart_items, bookings, booking_items, booking_status_history,
--         booking_attachments, booking payment tables, qrph_payment_attempts,
--         promotions, lucky_offers, promotion_redemptions,
--         passenger_category_rules, payment_webhook_events, notifications
-- Paste independently: users/admins/destinations/trip tables as stubs.
-- Reconciled to 109 migrations (2026-11-17).
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

-- Stubs for cross-module FKs
CREATE TABLE IF NOT EXISTS users (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS admins (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS destinations (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS hotels (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS rooms (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS activities (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS packages (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS add_ons (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS trip_itineraries (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS trip_plan_items (id BIGSERIAL PRIMARY KEY);

CREATE TABLE IF NOT EXISTS bookings (
    id                          BIGSERIAL PRIMARY KEY,
    booking_code                VARCHAR(30) NOT NULL UNIQUE,
    user_id                     BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    booking_source              VARCHAR(30) NOT NULL DEFAULT 'customer_web',
    is_walk_in                  BOOLEAN NOT NULL DEFAULT FALSE,
    status                      VARCHAR(255) NOT NULL DEFAULT 'pending'
        CONSTRAINT bookings_status_check CHECK (status IN ('pending','approved','paid','completed','rejected','cancelled','expired','cancellation_requested','cancellation_denied')),
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
    payment_status              VARCHAR(255) NOT NULL DEFAULT 'unpaid'
        CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('unpaid','partial','paid','refunded')),
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
    updated_at                  TIMESTAMPTZ NULL
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
    availability_status VARCHAR(255) NOT NULL DEFAULT 'pending'
        CONSTRAINT booking_items_availability_check CHECK (availability_status IN ('pending','available','unavailable')),
    fulfillment_status  VARCHAR(12) NOT NULL DEFAULT 'pending',
    fulfilled_at        TIMESTAMPTZ NULL,
    fulfillment_admin_id BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    admin_note          TEXT NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
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
CREATE INDEX IF NOT EXISTS bsh_booking_to_status_index ON booking_status_history (booking_id, to_status);

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

CREATE TABLE IF NOT EXISTS cart_items (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token   VARCHAR(255) NULL,
    item_type       VARCHAR(255) NOT NULL, -- room|hotel|activity|package|add_on (polymorphic)
    item_id         BIGINT NOT NULL,       -- no FK; app resolves via item_type
    quantity        INTEGER NOT NULL DEFAULT 1,
    check_in_date   DATE NULL,
    check_out_date  DATE NULL,
    selected_pax    INTEGER NOT NULL DEFAULT 1,
    is_selected     BOOLEAN NOT NULL DEFAULT TRUE,
    notes           TEXT NULL,
    lucky_group_id  VARCHAR(36) NULL,
    room_option     VARCHAR(16) NULL,
    trip_itinerary_id BIGINT NULL REFERENCES trip_itineraries(id) ON DELETE RESTRICT,
    trip_plan_item_id BIGINT NULL REFERENCES trip_plan_items(id) ON DELETE SET NULL,
    lucky_offer_id  BIGINT NULL REFERENCES lucky_offers(id) ON DELETE SET NULL,
    rebooked_from_booking_id BIGINT NULL REFERENCES bookings(id) ON DELETE SET NULL,
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

CREATE TABLE IF NOT EXISTS passenger_category_rules (
    id              BIGSERIAL PRIMARY KEY,
    category_name   VARCHAR(255) NOT NULL UNIQUE,
    display_label   VARCHAR(255) NOT NULL,
    adjustment_type VARCHAR(255) NOT NULL DEFAULT 'none'
        CONSTRAINT pcr_adjustment_check CHECK (adjustment_type IN ('discount','surcharge','none')),
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
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

-- Notes:
-- cart_items / booking_items are polymorphic (item_type+item_id); ERD shows
-- dashed logical relations to rooms|hotels|activities|packages|add_ons.
-- bookings.user_id -> users.id SET NULL; reviewed_by_admin_id -> admins.id SET NULL
-- Payment plan: booking_payment_requests owns the Stripe/QR Ph quote for a
-- booking; booking_payment_transactions records verified receipts/refunds;
-- booking_payment_notices dedupes customer notifications; qrph_payment_attempts
-- tracks PayMongo QR Ph intents. payment_request_one_active guarantees one
-- in-flight request per booking.
-- booking_attachments holds admin/customer proofs linked to a booking (and
-- optionally a booking_item); uploaded_by_admin_id -> admins.id SET NULL.
-- Promotions: promotions -> promotion_redemptions -> lucky_offers. A lucky
-- offer is a generated, user-scoped bundle tied to one destination; redemption
-- allocations JSONB stores the per-item discount split. No promo codes are
-- issued in the current design.
-- cart_items may reference trip_itineraries / trip_plan_items (trip planner)
-- and lucky_offers; rebooked_from_booking_id supports rebooking a past trip.
