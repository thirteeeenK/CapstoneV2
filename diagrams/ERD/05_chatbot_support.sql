-- =============================================================================
-- SunnyTrips - 05 Chatbot & Human Handoff (Decomposed ERD)
-- Module: chat_sessions, chat_messages, support_inquiries,
--         chatbot_abuse_reports, faqs
-- Paste independently: users/admins provided as stubs.
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS users  (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS admins (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);

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
    sender          VARCHAR(10) NOT NULL, -- user | bot | admin
    message         TEXT NOT NULL,
    context_data    JSONB NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS support_inquiries (
    id                  BIGSERIAL PRIMARY KEY,
    ticket_number       VARCHAR(255) NOT NULL UNIQUE,
    chat_session_id     BIGINT NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
    user_id             BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    assigned_admin_id   BIGINT NULL REFERENCES admins(id) ON DELETE SET NULL,
    status              VARCHAR(255) NOT NULL DEFAULT 'PENDING_ASSIGNMENT',
    requested_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    assigned_at         TIMESTAMPTZ NULL,
    returned_to_ai_at   TIMESTAMPTZ NULL,
    resolved_at         TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NULL,
    updated_at          TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS support_inquiries_status_admin_index ON support_inquiries (status, assigned_admin_id);
CREATE UNIQUE INDEX IF NOT EXISTS support_inquiries_single_active_per_session
    ON support_inquiries (chat_session_id) WHERE status IN ('PENDING_ASSIGNMENT','ASSIGNED');

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

-- Relationships:
-- chat_messages.chat_session_id -> chat_sessions.id CASCADE
-- support_inquiries.chat_session_id -> chat_sessions.id CASCADE
-- support_inquiries.user_id -> users.id SET NULL
-- support_inquiries.assigned_admin_id -> admins.id SET NULL
-- chatbot_abuse_reports.user_id -> users.id CASCADE; reviewed_by -> admins.id SET NULL
-- faqs is standalone; embedding used by RAG (GeminiService)
