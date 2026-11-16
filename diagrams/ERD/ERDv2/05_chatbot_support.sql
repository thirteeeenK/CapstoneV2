-- =============================================================================
-- SunnyTrips - 05 Chatbot & Human Handoff (Decomposed ERD)
-- Module: chat_sessions, chat_messages, support_inquiries, chatbot_abuse_reports,
--         faqs, chat_feedback, knowledge_articles
-- Reconciled against 109 migrations (2026-11-17).
-- Paste independently: users/admins/destinations provided as stubs.
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS users  (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS admins (id BIGSERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS destinations (id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL);

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
    requested_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
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

-- Relationships:
-- chat_messages.chat_session_id -> chat_sessions.id CASCADE
-- support_inquiries.chat_session_id -> chat_sessions.id CASCADE
-- support_inquiries.user_id -> users.id SET NULL
-- support_inquiries.assigned_admin_id -> admins.id SET NULL
-- chatbot_abuse_reports.user_id -> users.id CASCADE; reviewed_by -> admins.id SET NULL
-- chat_feedback.chat_session_id -> chat_sessions.id SET NULL; user_id -> users.id SET NULL
-- knowledge_articles.destination_id -> destinations.id SET NULL
-- faqs is standalone; embedding used by RAG (GeminiService). knowledge_articles
-- extends the RAG corpus with destination-scoped editorial content; both carry
-- embedding_source_hash/model/embedded_at so `embed:all` can skip unchanged rows.
-- support_inquiries_single_active_per_session prevents two open tickets per chat
-- session; initiated_by distinguishes user-requested vs admin-initiated handoff.
