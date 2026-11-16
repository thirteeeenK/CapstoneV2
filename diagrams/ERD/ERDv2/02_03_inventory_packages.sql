-- =============================================================================
-- SunnyTrips - 02+03 Inventory + Packages (Merged Decomposed ERD)
-- Modules combined: destinations, hotels, rooms, activities, add_ons,
--                   packages, package_hotel, package_activity, package_addon,
--                   weather_cache
-- Paste independently into psql or SQL-import ERD maker. ASCII-only.
-- Generated strictly from database/migrations/*.php (109 migrations)
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
    check_in_time       TIME NULL,
    check_out_time      TIME NULL,
    images              VARCHAR(255) NULL,
    is_shown            BOOLEAN NOT NULL DEFAULT TRUE,
    embedding           VECTOR(3072) NULL,
    embedding_source_hash VARCHAR(255) NULL,
    embedding_model     VARCHAR(255) NULL,
    embedded_at         TIMESTAMPTZ NULL,
    -- search_tsv is GENERATED ALWAYS AS (to_tsvector('simple', ...)) STORED;
    -- GIN index hotels_search_tsv_idx ON hotels USING GIN (search_tsv).
    search_tsv          TSVECTOR NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
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
    -- search_tsv is GENERATED ALWAYS AS (to_tsvector('simple', ...)) STORED;
    -- GIN index activities_search_tsv_idx ON activities USING GIN (search_tsv).
    search_tsv      TSVECTOR NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS activities_visible_destination_idx
    ON activities (destination_id) WHERE is_shown = TRUE AND embedding IS NOT NULL;

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
    -- search_tsv is GENERATED ALWAYS AS (to_tsvector('simple', ...)) STORED;
    -- GIN index packages_search_tsv_idx ON packages USING GIN (search_tsv).
    search_tsv          TSVECTOR NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
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
-- package_hotel: M:N packages <-> hotels (optional room_type_id -> rooms.id SET NULL)
-- package_activity: M:N packages <-> activities
-- package_addon: M:N packages <-> add_ons
-- hotels/rooms/activities/add_ons/packages carry embedding + embedding_source_hash,
-- embedding_model, embedded_at; hotels/activities/packages also carry generated search_tsv.
