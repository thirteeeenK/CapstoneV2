-- =============================================================================
-- SunnyTrips - 01 Identity, Access & Admin (Decomposed ERD)
-- Module: users, admins, sessions, tokens, legal, audit, security
-- Paste independently: includes CREATE EXTENSION vector for embeddings.
-- Stub tables at bottom let FKs resolve if other modules not pasted.
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

-- ---------------------------------------------------------------------------
-- Core identity
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admins (
    id                  BIGSERIAL PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    email               VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at   TIMESTAMPTZ NULL,
    password            VARCHAR(255) NOT NULL,
    remember_token      VARCHAR(100) NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS users (
    id                          BIGSERIAL PRIMARY KEY,
    name                        VARCHAR(255) NOT NULL,
    email                       VARCHAR(255) NOT NULL UNIQUE,
    phone_number                VARCHAR(15) NULL,
    address                     VARCHAR(255) NULL,
    email_verified_at           TIMESTAMPTZ NULL,
    password                    VARCHAR(255) NOT NULL,
    remember_token              VARCHAR(100) NULL,
    preferences_embedding       VECTOR(3072) NULL,
    chatbot_flag_count          INTEGER NOT NULL DEFAULT 0,
    ban_reason                  VARCHAR(255) NULL,
    ban_level                   VARCHAR(255) NULL,
    banned_at                   TIMESTAMPTZ NULL,
    ban_expires_at              TIMESTAMPTZ NULL,
    terms_accepted_at           TIMESTAMPTZ NULL,
    privacy_accepted_at         TIMESTAMPTZ NULL,
    ai_disclosure_accepted_at   TIMESTAMPTZ NULL,
    terms_version               VARCHAR(10) NULL,
    privacy_version             VARCHAR(10) NULL,
    ai_disclosure_version       VARCHAR(10) NULL,
    consent_ip_address          VARCHAR(45) NULL,
    consent_user_agent          VARCHAR(255) NULL,
    created_at                  TIMESTAMPTZ NULL,
    updated_at                  TIMESTAMPTZ NULL
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
-- Legal & compliance
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

-- ---------------------------------------------------------------------------
-- Security & audit (FK -> admins)
-- ---------------------------------------------------------------------------

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

-- System / queue tables (often co-located with platform)
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
    failed_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS failed_jobs_c_q_f_index ON failed_jobs (connection, queue, failed_at);

-- ---------------------------------------------------------------------------
-- STUBS - external FK targets (so diagram shows relationships when this file
-- is pasted alone; safe IF NOT EXISTS - full definitions live in other modules)
-- ---------------------------------------------------------------------------
-- bookings.user_id -> users.id, support_inquiries.assigned_admin_id -> admins.id
CREATE TABLE IF NOT EXISTS bookings (id BIGSERIAL PRIMARY KEY);
CREATE TABLE IF NOT EXISTS chat_sessions (id BIGSERIAL PRIMARY KEY);
