# SunnyTrips

**Philippine travel booking platform with an AI Decision Support System.**

SunnyTrips is a capstone project that lets travelers discover and book destinations, hotels, rooms, activities, and curated packages across the Philippines — powered by an AI Decision Support System that understands what you like and recommends what you'll love.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP `^8.4`, Laravel `^13.8` (Breeze `^2.4`) |
| Frontend | Blade, Tailwind CSS `^3.1`, Alpine.js `^3.4.2`, Vite `^8` (`laravel-vite-plugin ^3.1`) |
| Database | PostgreSQL + pgvector (vector search) |
| AI / Embeddings | Gemini (`models/text-embedding-001`, `models/gemini-2.5-flash-lite`, judge default `models/gemini-3.1-pro-preview`) |
| Payments | Stripe sandbox + QRPH via PayMongo, with local simulator / demo-QR fallbacks |
| Extras | DomPDF `^3.1` (admin reports), ApexCharts `^6.7` (dashboards), OpenWeather, Maps |

## Features

### Travel Booking

- Browse destinations, hotels, rooms, activities, add-ons, and pre-built travel packages
- Trip Basket (cart) and checkout with passenger rules and booking management
- Booking lifecycle: pending → admin approve/deny → approved opens a 48-hour payment window (`payment_deadline`, `bookings:expire` runs hourly) → paid
- Payment pages guard approved + unexpired bookings. Users can request cancellation, withdraw the request, and rebook; admins approve/deny cancellations and can mark approved bookings paid
- Stripe payments (`PAYMENT_PROVIDER=auto|stripe|simulator`; `auto` uses Stripe when `STRIPE_SECRET_KEY` is set, else the local simulator)
- QRPH via PayMongo (GCash/GoTyme QR). Without PayMongo keys a demo QR with the actual amount is shown and no money moves
- **"I'm Feeling Lucky"** — surprise trip generator
- Reviews with sentiment analysis and summaries (threshold-gated, see below)

### AI Decision Support System (DSS)

- Gemini embeddings (`models/text-embedding-001`, 3072-dim) stored in real pgvector `vector(3072)` columns for rooms, hotels, activities, add-ons, packages, FAQs, and `users.preferences_embedding`
- Onboarding quiz rendered from admin-managed `OnboardingOption` rows (`vibe`, `traveler_type`, `amenity`, `activity`), persisted as a structured `UserPreference` row (vibes, amenities, destination, traveler type, notes) plus the user embedding
- `CheckUserOnboarding` redirects logged-in users with null `preferences_embedding` to `/onboarding` (except onboarding/logout/admin routes); a `[0]` zero-vector is treated as not personalized
- Personalized ranking via `rankRecommendations` / `cosineSimilarity`; section overviews ("why these were picked") persist per destination in `user_preferences.recommendation_explanations` and regenerate only after onboarding updates (`invalidateForUser`), with a deterministic tag-match fallback when Gemini is unavailable
- Recommendation click tracking (`RecommendationHit`: impression/nav/hotel/activity with `session_token`)

### AI Chatbot

15-intent hybrid RAG pipeline via `POST /chat` (`routes/chatRoute.php`):

`GENERAL_TALK`, `PACKAGE_SEARCH`, `ROOM_SEARCH`, `HOTEL_SEARCH`, `ACTIVITY_SEARCH`, `ITINERARY_QUERY`, `AVAILABILITY_QUERY`, `MAP_QUERY`, `WEATHER_QUERY`, `DESTINATIONS_OVERVIEW`, `ADDON_SEARCH`, `DISCOUNT_QUERY`, `BOOKING_STATUS`, `SUPPORT_AGENT`, `LEGAL_QUERY`

- Deterministic keyword/constraint routing first, Gemini semantic fallback second; structured constraints (destination, dates, pax, nights, budget) with relevance floors and bounded over-budget tolerance
- Grounded factual answers use `GEMINI_CHAT_GROUNDED_TEMP` (default `0`); general chat uses `GEMINI_CHAT_CONVERSATIONAL_TEMP` (default `0.2`); prompt context caching via `GEMINI_CHAT_CONTEXT_CACHE` (default on)
- Rich responses with preview modals, inline room date pickers, and "Add to Trip Basket" cards
- Persistent sessions via `chat_sessions.session_token` (localStorage); history restored via `GET /chat/history`; guest→login sessions merge
- Guest rate limits (15 msg/day/IP, 5/min burst → `429 guest_limit_reached` with login/register URLs) plus keyword abuse guard (guests blocked; authed users reported + possible ban). Optional repetition ban is off by default (`CHATBOT_REPETITION_BAN_ENABLED`, `ALLOW_LOCAL`, `SECONDS` default `600`; localhost exempt)
- **Human agent handoff** — guests and users can request a support agent; polling-based admin inbox with atomic ticket claiming (no WebSockets); the handoff state machine bypasses Gemini while human-active; `POST /chat/handoff/cancel` exits handoff

### Platform

- Admin panel with separate auth (`auth:admin`), live-search list pages, dashboards/charts, PDF reports
- Admin audit trail (`AdminAuditLog` via `AdminAuditService`, `/admin/audit`), user management with banning, onboarding-option administration, FAQ administration
- FAQ semantic retrieval (`searchFaqs`), itinerary context builder (pax/nights/budget), availability answers, weather + map/distance answers for trip planning
- Notifications, legal pages (terms, privacy, AI disclosure), review images on `REVIEWS_DISK` (`public` default, R2-ready)

## Design Docs

Historical/reference material — verify against the implementation before trusting:

- [Package_System_Plan.md](Package_System_Plan.md) — package feature design
- [implementation_plan.md](implementation_plan.md) — build plan snapshot
- [README_FILES/HUMAN_AGENT_HANDOFF_SPECIFICATION.md](README_FILES/HUMAN_AGENT_HANDOFF_SPECIFICATION.md) — chatbot → human agent handoff design
- [README_FILES/DSS_CAPSTONE_GUIDE.md](README_FILES/DSS_CAPSTONE_GUIDE.md) — decision support overview
- [README_FILES/DSS_API_INTEGRATION_GUIDE.md](README_FILES/DSS_API_INTEGRATION_GUIDE.md) — AI/API integration details
- [README_FILES/DSS_SENTIMENT_ANALYSIS_GUIDE.md](README_FILES/DSS_SENTIMENT_ANALYSIS_GUIDE.md) — review sentiment analysis
- [README_FILES/Travel_Cart_Plan.md](README_FILES/Travel_Cart_Plan.md) — cart/checkout design

More background notes live under [README_FILES/](README_FILES/) (RAG pipeline, chatbot grounding/dev notes, DASHBOARD, ERD/DFD, evaluation plans).

## Requirements

- PHP `^8.4`
- Composer
- Node.js + npm
- PostgreSQL with the `pgvector` extension (the vector migration is pgsql-only; Postgres must be running for dev and tests)
- Optional API keys (app boots without them; AI/payments/weather degrade — see [Degraded behavior](#degraded-behavior)):
  - `GEMINI_API_KEY` — embeddings + chatbot
  - `OPENWEATHER_API_KEY` — weather intents
  - `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` / `STRIPE_WEBHOOK_SECRET` — real Stripe payments, else simulator
  - `PAYMONGO_SECRET_KEY` (+ `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET`) — real QRPH, else demo QR

## Setup

Windows and Unix-like shells differ only in the copy command (`copy` vs `cp`).

1. Install dependencies:

   ```bash
   composer install
   npm install
   ```

2. Create your `.env` (Unix-like):

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Windows (`cmd`):

   ```cmd
   copy .env.example .env
   php artisan key:generate
   ```

   Point the app at PostgreSQL. **Local dev expects `DB_CONNECTION=pgsql`:**

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=sunnytrips
   DB_USERNAME=postgres
   DB_PASSWORD=yourpassword
   ```

   Default `.env.example` drivers are DB-backed (`SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`) — migrations create the jobs/sessions/cache tables, and queue-backed work (payments, notifications, chat jobs) needs a worker (handled by `composer dev`, or run `php artisan queue:listen`).

3. Add API keys to `.env` (see [Environment Variables](#environment-variables)).

4. Migrate, link storage, seed, and build assets:

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   npm run build
   ```

   Image columns hold `storage/` paths rendered via `asset('storage/' . $img)` — without `storage:link` uploaded images 404.

   > `AdminSeeder` keys the admin off the `EMAIL` env var — set one (e.g. `EMAIL=admin@example.com`) before seeding. `DatabaseSeeder` also creates `test@example.com` / `12345678`.

   One-shot alternative: `composer setup` does install → env copy → key → `migrate --force` (no seed) → `npm install --ignore-scripts` → build. Add `--seed` + `storage:link` manually when you need demo data/uploads.

5. Generate AI embeddings (needs `GEMINI_API_KEY`; covers hotels, rooms, activities, packages, add-ons, FAQs):

   ```bash
   php artisan embed:all --force
   ```

   Without the key, items log failures and `embedding` columns stay null (AI search/recommendations degrade — see below).

## Running the App

```bash
composer dev
```

Starts `artisan serve` (localhost) + `queue:listen --tries=1 --timeout=0` + Vite via `scripts/dev.js` (suppresses the Windows EPIPE crash on Ctrl+C).

Common commands:

```bash
php artisan serve              # web server only
php artisan queue:listen       # queue worker (payments, notifications, chat)
npm run dev                    # Vite dev server
php artisan embed:all          # (re)generate embeddings; --force overwrites
```

## Testing

```bash
composer test                  # config:clear + php artisan test (Pest)
php artisan test --filter=ChatbotTest
```

Tests run against a dedicated Postgres database (`SunnyTripsCapstoneV2_test` in `.env.testing`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `GEMINI_CHAT_CONTEXT_CACHE=false`) — Postgres must be running or tests fail.

## Architecture

- **Routes are split per feature** — each feature has its own route file (`packageRoute.php`, `cartRoute.php`, `checkoutRoute.php`, `bookingRoute.php`, `chatRoute.php`, `adminAuditRoute.php`, `adminOnboardingOptionRoute.php`, ...) `require`d from `routes/web.php`.
- **Two auth systems** — public user auth (Breeze-based) and separate admin auth (`auth:admin`). Controllers under `app/Http/Controllers/Admin/` are admin-only; `app/Http/Controllers/User/` is customer-facing; root controllers are public pages.
- **Model naming is inconsistent by design** — `*Model` suffix (`HotelModel`, `ActivityModel`, `AdminModel`, `OnboardingOption`) vs plain (`Booking`, `Package`, `CartItem`). Match the convention of the files you touch.
- **Admin list pages** use a live-search pattern: an Alpine state machine in the index view fetching a results partial (`#listings-results`) over AJAX, with the controller returning the partial standalone on `X-Requested-With` requests.
- **AI search** — catalog retrieval uses pgvector `<=>` cosine distance in SQL (packages, hotels, activities, add-ons, FAQs); room hybrid search and personalized ranking rerank in PHP (`cosineSimilarity` / `rankRecommendations`). Vectors are parsed/serialized via `GeminiService` helpers.
- **Chatbot request path** — widget (`chat-widget.blade.php`, Alpine) → `POST /chat` (`EnforceGuestChatLimits` for guests) → `ChatbotController` → `ChatbotService::handle` (support-handoff state machine first) → `IntentRouter` (deterministic → semantic fallback + constraint extraction) → retrieval (pgvector SQL / PHP rerank / FAQ / itinerary / availability / weather / maps) → `GeminiService::generateChatResponse` (grounded vs conversational temperature, cached context) → JSON reply with cards.

### Degraded behavior

- No `GEMINI_API_KEY`: `embed:all` fails per item, embeddings stay null; recommendations fall back to deterministic tag-match; zero-vectors (`[0]`) render the non-personalized view.
- No Stripe keys: `PAYMENT_PROVIDER=auto` uses the local simulator.
- No PayMongo keys: QRPH pages show a demo QR (no money moves); sandbox uses the return-URL confirm flow, no webhook needed; live needs `https://<host>/webhook/payment/qrph` registered.
- No OpenWeather key: weather intents return an unavailable message instead of a forecast.

## Environment Variables

`.env.example` does not list every integration variable — commented entries and the table below are authoritative for the full set (`config/services.php`, `config/filesystems.php`, `config/app.php`).

| Variable | Description | Default / Required |
|---|---|---|
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_KEY`, `APP_DEBUG` | Standard Laravel app config | Yes |
| `APP_TIMEZONE` | Display timezone | `Asia/Manila` |
| `DB_CONNECTION` | `pgsql` for local dev | Yes |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection | Yes |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Session store | `database`, `120` |
| `QUEUE_CONNECTION` | Queue driver (dev needs a worker) | `database` |
| `CACHE_STORE` | Cache store | `database` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS` | Mail (dev default `log`) | Optional |
| `FILESYSTEM_DISK` | Default storage disk | `local` |
| `REVIEWS_DISK` | Review photo disk (`public` local-first, `r2` for Cloudflare R2) | `public` |
| `GEMINI_API_KEY` | Gemini embeddings + chatbot | AI features |
| `EMBEDDING_MODEL` | Embedding model | `models/text-embedding-001` |
| `GEMINI_CHAT_MODEL` | Chat generation model | `models/gemini-2.5-flash-lite` |
| `GEMINI_JUDGE_MODEL` | Summary-judge model | `models/gemini-3.1-pro-preview` |
| `REVIEW_SUMMARY_THRESHOLD` | Min reviews before a summary generates | `3` |
| `GEMINI_CHAT_CONTEXT_CACHE` | Prompt context caching | `true` (`false` in tests) |
| `GEMINI_CHAT_GROUNDED_TEMP` | Temperature for grounded factual answers | `0` |
| `GEMINI_CHAT_CONVERSATIONAL_TEMP` | Temperature for general chat | `0.2` |
| `OPENWEATHER_API_KEY` | Weather intents | Optional |
| `OPENWEATHER_BASE_URL` | Weather API base | `https://api.openweathermap.org/data/2.5` |
| `PAYMENT_PROVIDER` | `auto` uses Stripe when keys exist, else simulator | `auto` |
| `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` | Stripe sandbox | Optional |
| `QRPH_PROVIDER` | QRPH driver | `paymongo` |
| `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET` | Real QRPH | Optional (commented in `.env.example`) |
| `QRPH_MERCHANT_NAME`, `QRPH_MERCHANT_ACCOUNT`, `QRPH_CITY` | QRPH receipt fields | `SunnyTrips`, empty, `Manila` |
| `CHATBOT_REPETITION_BAN_ENABLED`, `CHATBOT_REPETITION_BAN_ALLOW_LOCAL`, `CHATBOT_REPETITION_BAN_SECONDS` | Optional chatbot repetition ban (localhost exempt unless allow-local) | `false`, `false`, `600` |
| `DEMO_MODE` | Testing-only auto-seeded demo bookings on signup | `false` |
| `EMAIL` | `AdminSeeder` admin email | Seeding |

## Troubleshooting

- **Postgres/pgvector**: ensure Postgres is running, `.env` uses `DB_CONNECTION=pgsql` with the right host/port/db/user/pass, and the `vector` extension migration ran (pgsql-only). Tests need the `SunnyTripsCapstoneV2_test` database.
- **Empty AI results**: embeddings are null — set `GEMINI_API_KEY` and run `php artisan embed:all --force`.
- **Vite manifest error** (`Unable to locate file in Vite manifest`): run `npm run build` (or `npm run dev` / `composer dev`).
- **Jobs not running** (payments, notifications, chat): the default queue is `database`-backed — keep `queue:listen` running (included in `composer dev`).
- **External APIs unavailable**: expect simulator/demo-QR/tag-match/weather-unavailable fallbacks above, not hard errors.

## License

MIT (see `composer.json`).
