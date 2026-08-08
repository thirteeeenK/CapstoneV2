# AGENTS.md

SunnyTrips — Philippine travel booking capstone with an AI Decision Support System. Laravel 13 / PHP 8.3, Blade + Tailwind 3 + Alpine.js + Vite, PostgreSQL + pgvector, Gemini embeddings (`text-embedding-001`). Windows/XAMPP dev machine.

## Commands

- `composer dev` — run the whole stack at once: `artisan serve` (localhost) + `queue:listen` + Vite via `scripts/dev.js` (wrapper suppresses the Windows EPIPE crash on Ctrl+C). Prefer this over separate terminals.
- `composer test` — `config:clear` + `php artisan test` (Pest). Single test: `php artisan test --filter=Name`.
- `php artisan embed:all` — (re)generate Gemini embeddings for hotels/rooms/activities/packages (`--force` overwrites existing). Requires `GEMINI_API_KEY`; without it only a warning is logged and `embedding` columns stay null.
- `composer setup` — fresh-install bootstrap (composer + .env + key + migrate + npm build).

## Database / env gotchas

- Local dev DB is **PostgreSQL** (`DB_CONNECTION = pgsql` in `.env`), overriding `.env.example`'s sqlite default. Tests also run on Postgres via `.env.testing` (`SunnyTripsCapstoneV2_test`, localhost:5432, creds hardcoded there) — Postgres must be running or `composer test` fails. The pgvector `vector` extension migration is pgsql-only.
- `GEMINI_API_KEY` goes in `.env` (read via `config('services.gemini')`); not present in `.env.example`.
- `database/seeders/AdminSeeder.php` uses `updateOrCreate(['email' => env('EMAIL')], ...)` — it keys the admin off the `EMAIL` env var, which is unset on most machines (admin won't be created reliably). `DatabaseSeeder` also creates user `test@example.com` / `12345678`.

## Architecture

- Routes are split per feature and `require`d from `routes/web.php` (`packageRoute.php`, `cartRoute.php`, `checkoutRoute.php`, `adminPackageRoute.php`, ...). New feature routes go in a new file, then add the `require` in web.php.
- Admin panel uses a separate auth system (`routes/adminAuth.php`, `AdminModel`, `auth:admin` middleware). Controllers under `app/Http/Controllers/Admin/` are admin-only; `app/Http/Controllers/User/` (e.g. `OnboardingController`) is customer-facing; root controllers are public landing/show pages.
- Model naming is inconsistent by design: `*Model` suffix (`HotelModel`, `ActivityModel`, `DestinationModel`, `AddOnModel`, `AdminModel`) vs plain (`Booking`, `Package`, `RoomType`, `CartItem`). Match the convention of the files you touch.
- Embeddings are stored in real pgvector `vector(3072)` columns for rooms, hotels, activities, add_ons, users (preferences), and packages (migrated from text). Parse/serialize via `GeminiService` helpers (`parseVector`-style handling, `formatVectorForDb`, `cosineSimilarity` in `RecommendationController`/`OnboardingController`). Onboarding averages selected hotel/activity vectors into `users.preferences_embedding` — note this column is not in `User::$fillable` (attributes are set directly).
- JSON image columns hold storage paths; frontend renders with `asset('storage/' . $img)`. Unsplash URLs are used as image fallbacks (onboarding).
- Chatbot is **implemented** — 9 intent hybrid RAG pipeline (room/hotel/activity/package/itinerary/availability/map/weather/general) via `POST /chat`. Guest rate limit: 15 msg/day/IP + 5/min burst. Abuse guard: keyword detection (guests get block; authed users get block + DB report + possible ban). Conversation sessions persist across guest→login via `chat_sessions.session_token` stored in localStorage. Conversation history is restored on page refresh via `GET /chat/history`. Widget: `chat-widget.blade.php` (Alpine.js, activity preview modal, inline room date pickers, hotel/activity/package cards all with Add to Trip Basket). Human agent handoff: users request a support agent (guests allowed), admin inbox with atomic ticket claiming (polling-based, no WebSockets), state machine gatekeeper in `ChatbotService::handle()` that bypasses Gemini during active support. Package search uses pgvector `<=>` cosine distance in SQL; other searches (room/hotel/activity) use PHP-level `cosineSimilarity` via `rankRecommendations`. Key files: `ChatbotController`, `ChatbotService` orchestrator, `IntentRouter` (9 intents + constraint extraction), `ConversationManager` (6-turn windowing, admin message grounding), `GeminiService::searchRoomsHybrid`, `GeminiService::searchPackages`, `GeminiService::generateChatResponse`, `GeminiService::buildItineraryContext`, `EnforceGuestChatLimits` middleware, `SupportQueueService`, `Admin\SupportQueueController`. Chatbot system prompt: `SystemPrompts/chatbot-system-prompt.md`. Tests: `IntentRouterTest` + `ChatbotTest`. Handoff tests: `HandoffTest`.
- `CheckUserOnboarding` middleware (aliased on auth routes) silently redirects any logged-in user with null `preferences_embedding` to `/onboarding` — unless the route is `onboarding.*`, `logout`, or `admin*`. Expect this when hitting authenticated pages in tests/feature work.

## Conventions

- UI: Tailwind custom palette `ocean`/`sand`/`ink`/`coral` (defined in `tailwind.config.js`). Fonts: Sora for headlines (`font-headline`/`font-display`), DM Sans for body/labels (`font-body`/`font-label`); loaded via Google Fonts CDN in layouts. Never use arbitrary `font-[...]` classes — follow `.agents/skills/typography/SKILL.md` (repo skill: `typography-system`).
- Models use Laravel 13 attribute `#[Fillable([...])]` / `#[Hidden([...])]`, not `$fillable`/`$hidden` properties.
- Root-level `*.md` files are specs/plans/scratch, not instructions: `code.md` and `bugFix.md` are outdated scratch; `Package_System_Plan.md`, `Travel_Cart_Plan.md`, `DASHBOARD.md`, `DSS_*_GUIDE.md`, `HUMAN_AGENT_HANDOFF_SPECIFICATION.md`, `plan.md` are design docs for planned features — verify against the code before trusting.
- `.npmrc` sets `ignore-scripts=true` (npm installs never run lifecycle scripts).
