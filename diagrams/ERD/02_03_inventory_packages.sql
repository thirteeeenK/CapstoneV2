-- =============================================================================
-- SunnyTrips - 02+03 Inventory + Packages (Merged Decomposed ERD)
-- Modules combined: destinations, hotels, rooms, activities, add_ons,
--                   packages, package_hotel, package_activity, weather_cache
-- Paste independently into psql or SQL-import ERD maker. ASCII-only.
-- Generated strictly from database/migrations/*.php (64 migrations)
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

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

CREATE TABLE IF NOT EXISTS weather_cache (
    id              BIGSERIAL PRIMARY KEY,
    cache_key       VARCHAR(255) NOT NULL UNIQUE,
    weather_data    JSON NOT NULL,
    fetched_at      TIMESTAMPTZ NULL,
    expires_at      TIMESTAMPTZ NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

-- FK summary:
-- hotels.destination_id -> destinations.id CASCADE
-- rooms.hotel_id -> hotels.id CASCADE
-- activities.destination_id -> destinations.id CASCADE
-- add_ons.destination_id -> destinations.id CASCADE
-- packages.destination_id -> destinations.id CASCADE
-- package_hotel: M:N packages <-> hotels
-- package_activity: M:N packages <-> activities
