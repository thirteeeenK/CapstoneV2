-- =============================================================================
-- SunnyTrips - 04 Commerce: Cart, Bookings & Payment (Decomposed ERD)
-- Module: cart_items, bookings, booking_items, booking_status_history,
--         passenger_category_rules, payment_webhook_events, notifications
-- Paste independently: users/admins/destinations provided as stubs.
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
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS cart_items_item_type_item_id_index ON cart_items (item_type, item_id);
CREATE INDEX IF NOT EXISTS cart_items_session_token_index ON cart_items (session_token);
CREATE INDEX IF NOT EXISTS cart_items_lucky_group_id_index ON cart_items (lucky_group_id);

CREATE TABLE IF NOT EXISTS bookings (
    id                          BIGSERIAL PRIMARY KEY,
    booking_code                VARCHAR(30) NOT NULL UNIQUE,
    user_id                     BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
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
    admin_notes                 TEXT NULL,
    reviewed_by_admin_id        BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    total_amount                DECIMAL(12,2) NOT NULL,
    discount_amount             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
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
    contact_name                VARCHAR(150) NOT NULL,
    contact_email               VARCHAR(150) NOT NULL,
    contact_phone               VARCHAR(30) NOT NULL,
    special_requests            TEXT NULL,
    guest_manifest              JSON NULL,
    created_at                  TIMESTAMPTZ NULL,
    updated_at                  TIMESTAMPTZ NULL
);

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
    item_snapshot       JSON NULL,
    availability_status VARCHAR(255) NOT NULL DEFAULT 'pending'
        CONSTRAINT booking_items_availability_check CHECK (availability_status IN ('pending','available','unavailable')),
    admin_note          TEXT NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS booking_items_item_type_item_id_index ON booking_items (item_type, item_id);

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
