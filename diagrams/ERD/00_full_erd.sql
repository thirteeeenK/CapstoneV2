-- =============================================================================
-- SunnyTrips Capstone V2 - FULL ERD (PostgreSQL)
-- Generated strictly from database/migrations/*.php
-- Compatible with drawdb.app (Import -> PostgreSQL) & psql / DBeaver / pgAdmin.
-- Tables: 31 (+ Laravel system tables). 64 migrations reconciled.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1) CORE - Identity & Admin
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admins (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMPTZ NULL,
    password        VARCHAR(255) NOT NULL,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS users (
    id                      BIGSERIAL PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    email                   VARCHAR(255) NOT NULL UNIQUE,
    phone_number            VARCHAR(15) NULL,
    address                 VARCHAR(255) NULL,
    email_verified_at       TIMESTAMPTZ NULL,
    password                VARCHAR(255) NOT NULL,
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
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL
);

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
    images              VARCHAR(255) NULL,
    is_shown            BOOLEAN NOT NULL DEFAULT TRUE,
    embedding           VECTOR(3072) NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);

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
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);

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
    embedding       VECTOR(3072) NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

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
    embedding           VECTOR(3072) NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS package_hotel (
    id          BIGSERIAL PRIMARY KEY,
    package_id  BIGINT NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
    hotel_id    BIGINT NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);

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
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

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
    status                      VARCHAR(255) NOT NULL,
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
    payment_status              VARCHAR(255) NOT NULL,
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
    availability_status VARCHAR(255) NOT NULL,
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
CREATE INDEX IF NOT EXISTS booking_status_history_booking_to_status_index ON booking_status_history (booking_id, to_status);

CREATE TABLE IF NOT EXISTS passenger_category_rules (
    id              BIGSERIAL PRIMARY KEY,
    category_name   VARCHAR(255) NOT NULL UNIQUE,
    display_label   VARCHAR(255) NOT NULL,
    adjustment_type VARCHAR(255) NOT NULL,
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

-- ---------------------------------------------------------------------------
-- 5) CHATBOT & HUMAN HANDOFF
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

CREATE TABLE IF NOT EXISTS support_inquiries (
    id                  BIGSERIAL PRIMARY KEY,
    ticket_number       VARCHAR(255) NOT NULL UNIQUE,
    chat_session_id     BIGINT NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
    user_id             BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    assigned_admin_id   BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    status              VARCHAR(255) NOT NULL,
    requested_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_at         TIMESTAMPTZ NULL,
    returned_to_ai_at   TIMESTAMPTZ NULL,
    resolved_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS support_inquiries_status_admin_index ON support_inquiries (status, assigned_admin_id);

CREATE TABLE IF NOT EXISTS chatbot_abuse_reports (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message     TEXT NOT NULL,
    category    VARCHAR(255) NOT NULL,
    reason      TEXT NULL,
    status      VARCHAR(255) NOT NULL,
    reviewed_by BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS faqs (
    id          BIGSERIAL PRIMARY KEY,
    question    VARCHAR(255) NOT NULL,
    answer      TEXT NOT NULL,
    keywords    VARCHAR(255) NULL,
    category    VARCHAR(255) NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    embedding   VECTOR(3072) NULL,
    created_at  TIMESTAMPTZ NULL,
    updated_at  TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS faqs_active_sort_index ON faqs (is_active, sort_order);

-- ---------------------------------------------------------------------------
-- 6) REVIEWS, SUMMARIES & ONBOARDING / DSS
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
    sentiment               VARCHAR(20) NOT NULL,
    ground_truth_sentiment  VARCHAR(20) NULL,
    sentiment_score         DECIMAL(5,4) NOT NULL DEFAULT 0.5000,
    extracted_keywords      JSONB NOT NULL,
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
    top_positive_highlights JSONB NOT NULL,
    top_negative_highlights JSONB NOT NULL,
    most_frequent_keywords  JSONB NOT NULL,
    last_analyzed_at        TIMESTAMPTZ NULL,
    created_at              TIMESTAMPTZ NULL,
    updated_at              TIMESTAMPTZ NULL
);

-- ---------------------------------------------------------------------------
-- 7) PLATFORM - Legal, Cache, Jobs, Audit, Security
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS legal_documents (
    id          BIGSERIAL PRIMARY KEY,
    key         VARCHAR(50) NOT NULL UNIQUE,
    title       VARCHAR(150) NOT NULL,
    content     JSON NULL,
    version     VARCHAR(10) NOT NULL,
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
    guard           VARCHAR(20) NOT NULL,
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
