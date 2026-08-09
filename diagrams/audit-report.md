# Diagram Suite Audit Report — `diagrams/` folder

- **Date:** 2026-08-09
- **Scope:** all 7 diagram files in `C:\xampp\htdocs\SunnyTripsCapstoneV2\diagrams\` (DFD Level 0 → ERD Full)
- **Method:** every table, column, FK, and relationship verified against the live schema source of truth: 51 migration files and 23 models in `database/migrations/` + `app/Models/`, the morph map in `app/Providers/AppServiceProvider.php`, and Postgres CHECK/enum constraints.
- **Note:** this is a read-only audit. No diagram file was modified.

## Verdict summary

| # | File | Verdict |
|---|------|---------|
| 1 | `dfd_level_0.xml` (context) | **Good** — 3 minor gaps |
| 2 | `dfd_level_1.xml` (Level 1, 8 processes) | **Partial** — good skeleton, ~15 missing flows / 3 missing external entities |
| 3 | `dfd_level_2.xml` (chatbot pipeline) | **Poor** — all 3 data stores orphaned, no reply edge, no Gemini entity |
| 4 | `erd_a_catalog_reviews.xml` | **Poor** — most drawn columns do not exist in schema |
| 5 | `erd_b_booking_payment.xml` | **Poor** — invented columns, wrong table/column names |
| 6 | `erd_c_chat_support.xml` | **Poor** — invented columns + one invalid relationship |
| 7 | `erd_full.xml` | **Poor** — title says 22 entities, only 14 drawn; 11 domain tables missing |

---

## 1. `dfd_level_0.xml` — Context Diagram (GOOD)

Entities and flows are accurate: Customer/Guest, Admin/Support Agent, Google Gemini API, OpenWeatherMap API, Leaflet Map Services; customer → auth/preferences, search/basket, chat/support; system → recommendations, chatbot responses, booking reference; admin ↔ inventory/FAQ/claims; Gemini ⇄ embeddings/responses; weather query; geolocation.

**Gaps:**
1. **Stripe Payment Gateway missing** — checkout sessions (`StripeDriver`) and webhook (`PaymentWebhookController`) drive the booking payment lifecycle.
2. **Mail/SMTP server missing** — `BookingNotification` emails (RequestReceived, Paid, Approved, Rejected, Cancelled, Expired) leave the system.
3. No explicit "catalog browse results" flow back to the customer — only "Vector Matches & Recommendations" covers search output.

## 2. `dfd_level_1.xml` — Level 1 (GOOD structure, INCOMPLETE)

All 8 processes (1.0 User Auth/Onboarding, 2.0 Catalog & RAG Indexing, 3.0 Cart & Lucky Bundle, 4.0 Booking Engine, 5.0 Hybrid Chatbot, 6.0 Support Queue, 7.0 Review Engine, 8.0 Admin Ops) map to real code. The 10 stores map to real tables (users; catalog; embedding columns; cart_items + lucky_group_id; bookings + guest_manifest; chat_sessions/chat_messages; support_inquiries; chatbot_abuse_reports; reviews + review_summaries; faqs + legal_documents).

**Missing external entities:** Stripe, OpenWeatherMap, SMTP — a regression vs Level 0 (which includes weather/map, but Level 1 has no process that owns them).

**Missing / unbalanced flows:**
1. **D3 (vector embeddings) is write-only** — nothing reads it. Real reads happen in P3 (cosine ranking via `rankRecommendations`) and P5 (RAG vector search via `GeminiService::searchRoomsHybrid`; packages via pgvector `<=>`).
2. **P2 (Indexing Engine) has zero inputs** — no `Admin→P2` / `P8→P2` for catalog CRUD, and no `P2⇄Gemini` (embeddings come from Gemini via `embed:all`).
3. **P5 (Chatbot) misses most flows** — no reads of D1 (user preference vector), D2 (catalog), D3 (vectors); no `P5⇄Gemini` (query embedding + response generation); no reply edge back to user; no `P5→D4` (chat "Add to Trip Basket" action cards); no `P5→P6` handoff trigger.
4. **D7 (support queue) has no reads** — claiming/assignment/resume requires `D7→P6`; agent messages persist into D6 (`chat_messages`) and need `P6→D6` + `P6→user`.
5. **No return-to-user edges** — chat replies, bundle/cart contents, agent messages are all request-only.
6. **P8→D2 missing** (admin catalog CRUD — images, visibility) and **P8→D1 missing** (ban flow must update `users.ban_*`).
7. Features absent at Level 1: expiry sweep (`booking:expire`), booking status notifications, explore/weather/map process.
8. Minor: flow numbering scheme (1–5, 7–8, 6 unused); P4 label mentions "Price Adjustment Workflow" while the adjustment edges live in P8 (double coverage).

## 3. `dfd_level_2.xml` — Chatbot Pipeline 5.1–5.7 (POOR)

The sub-process order 5.1 Rate Limiter → 5.5 Handoff Gatekeeper → 5.2 History Restorer → 5.3 Intent Router → 5.4 Hybrid Search → 5.6 Gemini Synthesizer → 5.7 Persistence matches `ChatbotService` / `IntentRouter` runtime order (as a Gane-Sarson DFD, a process must have input flows).

**Issues:**
1. **All 3 data stores (D8 Abuse, D6 Chat Sessions, D3 pgvector) are orphaned** — zero edges connect to them.
2. **No Gemini API entity** — 5.4 (embed query) and 5.6 (generateContent) need the external Gemini entity + bidirectional flows.
3. **No reply edge back to the customer** — the pipeline ends at 5.7; the chat response is never drawn returning to the "Customer Chat Prompt" external entity.
4. No flow for chat action cards → cart (Add to Trip Basket).
5. Cosmetic: numbering is 5.1 → 5.5 → 5.2 → … (5.5 used as phase 2, out of numeric order).

## 4. `erd_a_catalog_reviews.xml` — Sub-ERD A (POOR)

| Table | Diagram shows | Real schema |
|---|---|---|
| DESTINATIONS | `vibe`, `island_group`, `is_shown` | none of these exist; has `image` (missing) |
| HOTELS | `name`, `description`, `address`, `rating`, `vibe` | `hotel_name`, `hotel_description`, `specific_address`, no `rating` column, `vibe_tags` json; missing `type`, `featured_amenities`, `latitude/longitude`, `images`, `embedding` |
| ROOMS | `name`, `capacity`, `price_per_night` | `room_name`, `occupancy`/`base_occupancy`/`max_occupancy`, `base_price`; missing `images`, `is_shown`, room attribute columns |
| ACTIVITIES | `name`, `price` | `activity_name`, `rate` (string); missing `activity_level`, `description`, `images`, `is_shown`, lat/lng |
| PACKAGES | `title`, `base_price`, `duration_days` | `name`, `price`, `days`, `nights` |
| PACKAGE_HOTEL | `room_type_id`, `nights` | pivot is only `(package_id, hotel_id)` — both invented |
| PACKAGE_ACTIVITY | `day_number` | column does not exist — `(package_id, activity_id)` only |
| REVIEWS | `is_approved` | `is_active`/`is_published`; missing `booking_id`, `sentiment`, `sentiment_score`, `extracted_keywords`, denormalized FKs, `name_override` |
| REVIEW_SUMMARIES | `reviewable_*`, `rating_distribution` | `summarizable_type`/`summarizable_id`, no `rating_distribution`; real: percentages + `ai_summary_text` + highlights |

**Also missing:** `add_ons` table (full catalog entity), `faqs` (has `embedding`), relationships catalogue↔packages (many-to-many via pivots), destinations→packages, hotels/packages→reviews morph. Only 3 relationships drawn (dest→hotels, hotels→rooms, dest→activities).

## 5. `erd_b_booking_payment.xml` — Sub-ERD B (POOR)

| Table | Diagram shows | Real schema |
|---|---|---|
| USERS | `role`, `ban_level : integer` | no `role` column exists; de facto role = admins table separation; `ban_level` is **string** (`warning`/`temporary`/`permanent`) |
| CART_ITEMS | `itemable_type`/`itemable_id`, `price : decimal` | columns are `item_type`/`item_id` (morph short keys); **no `price` column**; missing `session_token`, `quantity`, `selected_pax`, `is_selected`, `notes` |
| BOOKINGS | `booking_reference`, `guest_name`, `guest_email`, `admin_price_adjustment`, `booking_status` | `booking_code` (unique), `contact_lead_name`/`contact_email`/`contact_phone`, 3 separate admin columns (`admin_discount_amount`, `admin_surcharge_amount`, `price_adjustment_reason`, `price_adjusted_at` → 4), `status` enum with 7 values (`pending,approved,paid,completed,rejected,cancelled,expired`); missing payment columns (`payment_status`,`payment_method`,`gateway`,`payment_reference`,`payment_url`), `payment_deadline`, `reviewed_by_admin_id`, tax/discount fields |
| BOOKING_ITEMS | `itemable_*`, `total_price` | `item_type`/`item_id`; `subtotal`; missing `item_title`, `item_subtitle`, `hotel_name`, `selected_pax`, `nights`, `check_in/check_out`, `item_snapshot` |
| BOOKING_STATUS_HISTORIES (plural) | `status`, `notes`, `updated_by_*` | table is **`booking_status_history`** (singular); columns `from_status`, `to_status`, `note`, `actor_type`/`actor_id` |
| PASSENGER_CATEGORY_RULES | `min_age`, `max_age`, `price_multiplier` | all invented — real: `category_name` (unique), `display_label`, `adjustment_type` enum (discount/surcharge/none), `amount`, `is_active` |

Relationships drawn are correct (users→cart_items, users→bookings, bookings→booking_items, bookings→history).

## 6. `erd_c_chat_support.xml` — Sub-ERD C (POOR)

| Table | Diagram shows | Real schema |
|---|---|---|
| USERS | `role`, `ban_level : integer` | same as B — invented `role`; `ban_level` string |
| ADMINS | `role` | no `role` column — `admins` is `id, name, email, email_verified_at, password, remember_token` |
| CHAT_SESSIONS | `status`, `assigned_admin_id` | both invented — real columns `session_token` (unique), `user_id`, `metadata` jsonb; assignment lives on `support_inquiries.assigned_admin_id` |
| CHAT_MESSAGES | `sender_type`, `metadata` | `sender` (string enum user/assistant/support), `context_data` jsonb; also `chat_messages` has no `updated_at` |
| SUPPORT_INQUIRIES | `issue_summary` | `ticket_number` (unique), `chat_session_id`, `user_id`, `assigned_admin_id`, `status` (uppercase values `PENDING_ASSIGNMENT`, etc.), `requested_at`/`assigned_at`/`returned_to_ai_at`/`resolved_at` — no `issue_summary` |
| CHATBOT_ABUSE_REPORTS | `ip_address`, `reason`, `severity`, `blocked_until` | all invented — real: `user_id`, `message`, `category`, `reasons` (text), `status` (`pending`/`reviewed_dismissed`/`banned`), `reviewed_by`; ban state lives on `users.ban_*` |
| FAQS | ✓ mostly correct | missing `keywords`, `sort_order`, `embedding` |

**Invalid relationship:** `ADMINS → CHAT_SESSIONS` (error) — there is no FK from admin to `chat_sessions` (the correct edge is `admins → support_inquiries.assigned_admin_id` and `admins → chatbot_abuse_reports.reviewed_by`). Missing: sessions→support inquiries, abuse report→user ok, etc.

## 7. `erd_full.xml` — Full ERD (POOR)

- Title says **"22 Entities"** but only **14 table boxes** are drawn; missing: `package_hotel`, `package_activity`, `booking_status_history`, `passenger_category_rules`, `review_summaries`, `chatbot_abuse_reports`, `faqs`, `weather_cache`, `legal_documents`, `notifications`, `admins`, `reviews` relationships (users→reviews, bookings→reviews), `chat` relationships, plus the whole chat row has **zero relationship edges** (only 4 edges in the file).
- Inherits every invented/wrong column from the sub-ERDs (`vibe`, `island_group`, `role`, `ban_level int`, `price_per_night`, `booking_reference`, `itemable_*` refs, cart `price`, price-adjustment singular, etc.).
- The 4 drawn relationships (dest→hotels, hotels→rooms, users→bookings, bookings→booking_items) are correct.

---

## Cross-file consistency

1. **DFD0 vs DFD1:** weather/map/Leaflet appear at Level 0 but have no owning process at Level 1 (no Explorer/Weather process). Stripe/SMTP absent at both levels.
2. **DFD1 vs DFD2:** store numbering D3/D6/D8 is consistent between Level 1 and Level 2.1 (only consistency win).
3. **ERD A/B/C vs ERD-Full:** same tables drawn multiple times with contradicting columns (e.g., `users` in B and C; `role` invented in both).
4. **Two diagram sets exist:** `diagrams/` (this audit — hand-made, deviates from schema) vs `docs/diagrams/` (generated from the real schema). The two families contradict each other.

## Fix priority (proposed, not executed)

1. Rebuild `erd_full.xml` to match the real schema (21–25 domain tables, correct FKs/morphs, relationships for cart/booking/chat/support rows).
2. Correct/regenerate `dfd_level_1` with the missing entities (Stripe, OpenWeatherMap, SMTP) and flows (D3 reads, P2 inputs+Gemini, P5 reads/Gemini/reply/cart/handoff, D7 reads, P8→D2/D1, notifications).
3. Fix `dfd_level_2` (connect D8/D6/D3 stores, add Gemini entity + reply edge) — or replace with the Level-2a/2b/2c split already present in `docs/diagrams/`.
4. DFD Level 0: add Stripe + mail entities.
5. Unify: keep only one diagram set (`diagrams/` vs `docs/diagrams/`) to avoid contradictory documentation.