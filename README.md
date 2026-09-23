# SunnyTrips

**Philippine travel booking platform with an AI Decision Support System.**

SunnyTrips is a capstone project that lets travelers discover and book destinations, hotels, rooms, activities, and curated packages across the Philippines — powered by an AI Decision Support System that understands what you like and recommends what you'll love.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Frontend | Blade, Tailwind CSS 3, Alpine.js, Vite |
| Database | PostgreSQL + pgvector (vector search) |
| AI / Embeddings | Gemini AI (`text-embedding-001`, `gemini-2.5-flash-lite`) |
| Payments | Stripe (sandbox) with a local payment simulator fallback |
| Extras | DomPDF (admin reports), ApexCharts (dashboards), OpenWeather (weather), Maps |

## Features

### Travel Booking

- Browse destinations, hotels, rooms, activities, add-ons, and pre-built travel packages
- Shopping cart and checkout flow with passenger rules and booking management
- Stripe payments (sandbox) — falls back to a local payment simulator when Stripe keys are absent
- **"I'm Feeling Lucky"** — surprise trip generator
- Reviews with sentiment analysis

### AI Decision Support System (DSS)

- Gemini embeddings (`text-embedding-001`, 3072-dim vectors) stored in real pgvector columns for rooms, hotels, activities, add-ons, packages, and user preferences
- Onboarding flow that profiles your preferences from selected hotels/activities
- Personalized recommendations ranked by cosine similarity (`<=>` in SQL for packages, PHP-level `cosineSimilarity` elsewhere)
- Semantic search across the whole catalog via the hybrid RAG pipeline

### AI Chatbot

- 9-intent hybrid RAG pipeline: rooms, hotels, activities, packages, itinerary building, availability, maps, weather, and general questions (`POST /chat`)
- Rich responses with preview modals, inline room date pickers, and "Add to Trip Basket" cards
- Conversation persistence — sessions survive page refreshes and guest→login handoff
- Guest rate limits (15 msg/day/IP, 5/min burst) and an abuse guard with banning
- **Human agent handoff** — request a support agent, admin inbox with atomic ticket claiming (polling-based, no WebSockets). See [HUMAN_AGENT_HANDOFF_SPECIFICATION.md](HUMAN_AGENT_HANDOFF_SPECIFICATION.md)

### Platform

- Admin panel with separate auth system, live-search list pages, dashboards, and charts
- Report generation (PDF via DomPDF)
- Notifications, user management with banning, and legal content pages (terms, privacy, AI disclosure)
- Maps and weather integration for trip planning

## Design Docs

- [HUMAN_AGENT_HANDOFF_SPECIFICATION.md](HUMAN_AGENT_HANDOFF_SPECIFICATION.md) — chatbot → human agent handoff design
- [DSS_CAPSTONE_GUIDE.md](DSS_CAPSTONE_GUIDE.md) — decision support system overview
- [DSS_API_INTEGRATION_GUIDE.md](DSS_API_INTEGRATION_GUIDE.md) — AI/API integration details
- [DSS_SENTIMENT_ANALYSIS_GUIDE.md](DSS_SENTIMENT_ANALYSIS_GUIDE.md) — review sentiment analysis
- [Package_System_Plan.md](Package_System_Plan.md) — package feature design
- [Travel_Cart_Plan.md](Travel_Cart_Plan.md) — cart/checkout design

## Requirements

- PHP ^8.3
- Composer
- Node.js + npm
- PostgreSQL (with the `pgvector` extension available — the vector migration is pgsql-only)
- Optional API keys (app works without them, but AI features degrade):
  - `GEMINI_API_KEY` — embeddings + chatbot (core AI features need this)
  - `OPENWEATHER_API_KEY` — weather in chatbot and trip planning
  - `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` / `STRIPE_WEBHOOK_SECRET` — real payments; otherwise the local simulator is used

## Setup

1. Clone the repository and install dependencies:

   ```bash
   composer install
   npm install
   ```

2. Create your `.env`:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Point the app at PostgreSQL. **Local dev expects `DB_CONNECTION=pgsql`** (Postgres must be running):

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=sunnytrips
   DB_USERNAME=postgres
   DB_PASSWORD=yourpassword
   ```

3. Add API keys to `.env` (see the [Environment Variables](#environment-variables) section).

4. Migrate, seed, and build assets:

   ```bash
   php artisan migrate --seed
   npm run build
   ```

   > The `AdminSeeder` keys the admin account off the `EMAIL` env var — set one (e.g. `EMAIL=admin@example.com`) before seeding to create the admin. `DatabaseSeeder` also creates a regular user: `test@example.com` / `12345678`.

5. Generate AI embeddings (optional but required for AI search/recommendations to return anything):

   ```bash
   php artisan embed:all --force
   ```

   Alternatively run `composer setup` to do install → env → migrate → build in one shot.

## Running the App

```bash
composer dev
```

Starts everything at once — `artisan serve` (localhost), the queue worker, and Vite (the wrapper suppresses the Windows EPIPE crash on Ctrl+C).

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

Tests run against a dedicated Postgres database (`SunnyTripsCapstoneV2_test` configured in `.env.testing`) — Postgres must be running or tests fail.

## Architecture

- **Routes are split per feature** — each feature has its own route file (`packageRoute.php`, `cartRoute.php`, `checkoutRoute.php`, `chatRoute.php`, ...) `require`d from `routes/web.php`.
- **Two auth systems** — public user auth (Breeze-based) and a separate admin auth (`auth:admin` middleware). Controllers under `app/Http/Controllers/Admin/` are admin-only; `app/Http/Controllers/User/` is customer-facing; root controllers are public pages.
- **Model naming is inconsistent by design** — `*Model` suffix (`HotelModel`, `ActivityModel`, `AdminModel`) vs plain (`Booking`, `Package`, `CartItem`). Match the convention of the files you touch.
- **Admin list pages** use a live-search pattern: an Alpine state machine in the index view fetching a results partial (`#listings-results`) over AJAX, with the controller returning the partial standalone on `X-Requested-With` requests.
- **AI search** — package search uses pgvector `<=>` cosine distance in SQL; room/hotel/activity search uses PHP-level `cosineSimilarity` ranking. Vectors are parsed/serialized via `GeminiService` helpers and stored in real `vector(3072)` columns.

## Environment Variables

| Variable | Description | Required |
|---|---|---|
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_KEY` | Standard Laravel app config | Yes |
| `DB_CONNECTION` | `pgsql` for local dev (override of the sqlite default) | Yes |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection | Yes |
| `GEMINI_API_KEY` | Gemini embeddings + chatbot | AI features |
| `EMBEDDING_MODEL` | Defaults to `models/text-embedding-001` | Optional |
| `GEMINI_CHAT_MODEL` | Defaults to `models/gemini-2.5-flash-lite` | Optional |
| `OPENWEATHER_API_KEY` | Weather intents in chatbot | Optional |
| `PAYMENT_PROVIDER` | `auto` picks Stripe when `STRIPE_SECRET_KEY` is set, else local simulator | Optional |
| `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` | Stripe sandbox payments | Optional |
| `EMAIL` | Email for `AdminSeeder` admin account | Seeding |
| `APP_TIMEZONE` | `Asia/Manila` | Yes |

## License

[MIT](LICENSE)
