# CHATBOT NOTES — SunnyBot

Study guide for the SunnyTrips AI Decision Support System chatbot. This is my own reference so I can explain the system confidently in the capstone defense. Everything below was verified against the code.

---

## 1. What it is

**SunnyBot** is a Philippine travel assistant built on a **Hybrid RAG** pipeline. It takes a natural-language message (English or Taglish), decides *what kind of travel question it is* (intent), retrieves real data from the Postgres database (semantic vectors + structured filters), and hands that ground truth to Google Gemini to write a friendly, grounded reply. It never answers from the model's general knowledge — everything it says must come from the retrieved DB results.

The bot also has **conversation memory**, **live weather/map/availability data**, a **FAQ shortcut**, an **abuse guard**, **guest rate limits**, and a **human-agent handoff** (support queue).

---

## 2. The request flow

```
User types message in chat widget (chat-widget.blade.php)
        │
        ▼
POST /chat  (routes/chatRoute.php)
   middleware: throttle:ai  +  EnforceGuestChatLimits (guests only)
        │
        ▼
ChatbotController::chat (app/Http/Controllers/ChatbotController.php:20)
   • validate message (max 1000 chars)
   • resolve or create ChatSession (session_token from localStorage)
        │
        ▼
ChatbotService::handle (app/Services/Chat/ChatbotService.php:33)
   │
   ├─ 1. checkAbuse()          → blocked? 403 (keyword guard / ban / abuse report)
   ├─ 2. persist user message
   ├─ 3. Active support ticket? → bypass Gemini, stream admin replies
   ├─ 4. isFollowUpQuery()?    → answer from PREVIOUS RECOMMENDATIONS only (no new search)
   ├─ 5. FaqService::findBestMatch()? → short-circuit with canned FAQ answer
   ├─ 6. IntentRouter::classify()      → pick 1 of 9 intents
   ├─ 7. IntentRouter::extractConstraints() → regex-parse pax, budget, destination, dates
   ├─ 8. dispatch to the matching handler  (match statement, ChatbotService.php:87)
   │      • handler retrieves DB rows (RAG) and builds context text
   └─ 9. Gemini generateChatResponse(system prompt + 6-turn history + prompt)
        │
        ▼
JSON response back to widget:
   { status, reply, control (ai|admin|pending), session_token,
     retrieved_rooms | retrieved_hotels | retrieved_activities |
     retrieved_packages | itinerary | availability | map | weather | faq }
```

Key idea: **every search intent follows retrieve-then-generate.** The DB retrieval happens in PHP/SQL (step 8); Gemini only *writes* the reply using the context we give it (step 9). This is the whole "RAG" story — and the "Hybrid" part is explained next.

---

## 3. Why is it "Hybrid RAG" and not just RAG?

This is the part I kept getting lost on, so here it is slowly.

**Plain RAG** (used for hotels, activities, packages, FAQs):

1. Embed the user's query into a vector (`generateEmbedding(query, 'RETRIEVAL_QUERY')`).
2. Grab every embedded row from the table.
3. Rank them by **cosine similarity** to the query vector.
4. Inject the top N into the prompt → Gemini writes the reply.

That's it. No filtering. "Show me a hotel in Boracay under ₱4,000" would be ranked *purely by semantic similarity* — the price and destination constraints are ignored during retrieval and left for the LLM to (maybe) handle. Bad.

**Hybrid RAG** (used for **rooms** and **availability** — `searchRoomsHybrid`, `GeminiService.php:1603`):

The retrieval is now *two mechanisms combined*:

1. **Structured/lexical filter** — `IntentRouter::extractConstraints()` regex-parses the user's message into hard facts: `destination_id`, `hotel_id`, `pax`, `max_price`, `check_in_date`, `check_out_date`. Those become **SQL `WHERE` clauses** that shrink the candidate pool first (GeminiService.php:1615-1626):

   ```sql
   WHERE destination_id = ?      -- "in Boracay"
     AND max_occupancy >= ?      -- "for 2"
     AND base_price   <= ?       -- "under ₱3,000"
   ```

2. **Semantic ranking** — the survivors are then ranked by **cosine similarity** against the query embedding (`rankRecommendations`, GeminiService.php:1641).

**"Hybrid" = vector search + structured constraints combined.** Semantic similarity decides *relevance*; the extracted constraints decide *eligibility*. Pure RAG does step 2 only.

### Worked example

> "budget room for 2 in Boracay under ₱3,000"

1. `extractConstraints` finds: `pax = 2`, `max_price = 3000`, `destination_name = "Boracay"` → resolved to `destination_id`.
2. `searchRoomsHybrid` runs SQL: only rooms whose hotel is in Boracay, `max_occupancy >= 2`, `base_price <= 3000`. (Rooms with no embedding are skipped.)
3. The remaining rooms are cosine-ranked against the embedding of the full sentence.
4. Top 5 → formatted into `=== DATABASE RESULTS ===` context → Gemini narrates.

Without the hybrid step, step 2 would return rooms from *every* destination and the LLM might hallucinate or ignore the budget.

### Bonus hybrid: FAQs

`FaqService` is also "hybrid" but in the **lexical-first** direction (FaqService.php:15): keyword/Jaccard token-overlap match first, and only if that scores below 0.5 does it fall back to the pgvector embedding match (with an overlap guardrail so an unrelated query can't latch onto the closest FAQ).

---

## 4. The 9 intents

`IntentRouter::classify()` (IntentRouter.php:51) checks intents in a **fixed priority order** (first match wins), because phrases overlap ("best resort near a beach with rooms"). Detection is keyword/regex-based — no LLM call for classification.

| # | Intent | Trigger examples | Retrieval | Handler |
|---|--------|------------------|-----------|---------|
| 1 | `WEATHER_QUERY` | "weather in Boracay", "ulan ba sa El Nido?", "typhoon" | Live `WeatherService` (OpenWeather) by destination | `handleWeatherQuery` (:655) |
| 2 | `MAP_QUERY` | "where is X", "how far is A from B", "saan ang..." | `DistanceService::haversine` for 2 places; coords for 1 | `handleMapQuery` (:608) |
| 3 | `AVAILABILITY_QUERY` | "available May 3-5", "check availability", "may bakante?" (must include a date) | **Hybrid** `searchRoomsHybrid` + `RoomAvailabilityService::check` per room | `handleAvailabilityQuery` (:550) |
| 4 | `ITINERARY_QUERY` | "plan a trip", "3-day itinerary", "sample itinerary" | Deterministic builder: picks 1 hotel + room (≤50% budget), up to 4 activities (≤80% of remaining), pre-computes totals | `handleItineraryQuery` (:523) → `buildItineraryContext` (GeminiService.php:1841) |
| 5 | `PACKAGE_SEARCH` | "packages", "promo", "deal", "tipid" | **Plain RAG** `searchPackages` (SQL `<=>`), then date-validity filter | `handlePackageSearch` (:491) |
| 6 | `ROOM_SEARCH` | "room", "suite", "villa", "ocean view", "kwarto" | **Hybrid** `searchRoomsHybrid` (+ price-intent re-sort if "cheapest"/"luxury") | `handleRoomSearch` (:343) |
| 7 | `HOTEL_SEARCH` | "hotel", "resort", "stay at", "best hotel" | **Plain RAG** `searchHotels` (only constraint honored: `hotel_id` if a hotel name is named) | `handleHotelSearch` (:374) |
| 8 | `ACTIVITY_SEARCH` | "activities", "island hopping", "things to do", "pasyalan" | **Plain RAG** `searchActivities` | `handleActivitySearch` (:473) |
| 9 | `GENERAL_TALK` | anything else (also any message containing a travel keyword but no specific intent → falls through to `ROOM_SEARCH`) | No retrieval — system prompt only | `handleGeneralChat` (:697) |

Fallback logic at IntentRouter.php:87-89: if no specific intent matched but the message contains a travel keyword (`isTravelQuery`), it defaults to `ROOM_SEARCH`.

### Price-intent sorting (rooms/hotels only)

If the query says "cheapest/cheap/budget/mura" → results sorted by price ascending; "most expensive/luxury/mahal" → descending. Ties fall back to similarity score (`sortRoomsByPrice` :447, `sortHotelsByPrice` :422).

---

## 5. Constraint extraction

`extractConstraints` (IntentRouter.php:94) returns a fixed shape:

```php
[
  'pax'              => int|null,   // "2 pax", "couple"→2, "solo"→1
  'max_price'        => int|null,   // "under 3000", "₱3,500 budget"
  'destination_id'   => int|null,   // from name lookup (ILIKE)
  'destination_name' => string|null,
  'hotel_id'         => int|null,   // from hotel_name lookup (ILIKE)
  'hotel_name'       => string|null,
  'check_in_date'    => 'Y-m-d'|null,
  'check_out_date'   => 'Y-m-d'|null,
  'nights'           => int|null,   // "3 nights", or derived from dates
  'days'             => int|null,
  'place_names'      => [],         // destinations found in query (for map)
]
```

How each is parsed:
- **pax** — regex on `pax|persons|people|tao|katao|guests`; plus `couple`→2, `solo/alone/myself`→1.
- **max_price** — regexes like `under|below|less than|max|budget of ₱N` and `₱N budget`.
- **nights/days** — `N nights|gabi` or `N days|araw` (days → nights = days − 1).
- **dates** — explicit "March 3 to March 5", or fuzzy: `this weekend` → next Sat–Mon, `next week`, `tonight/today`.
- **destination / hotel** — longest-name-contains matching against the `destinations` / `hotels` tables (with stop-word stripping for hotel names so "The Beach Resort" doesn't swallow a whole query).

These constraints are consumed by `searchRoomsHybrid` (rooms, availability) and by the itinerary builder. Note: hotel/activity/package searches mostly **ignore** constraints (hotel search only uses `hotel_id`) — this is a known asymmetry, and the hybrid path is rooms-only.

---

## 6. Cosine similarity vs cosine distance

Both are used — and both express the *same* cosine metric, so the capstone's "we use cosine similarity" claim holds.

| | PHP cosine similarity | SQL cosine distance (`<=>`) |
|---|---|---|
| Where | `GeminiService::cosineSimilarity` (:327) via `rankRecommendations` (:342) | `GeminiService::searchPackages` (:771), `searchFaqs` (:802) |
| Used for | **Hotels, Rooms, Activities** (and homepage "Recommended for you" in `RecommendationController.php:68,80`) | **Packages, FAQs** |
| How | Vectors are L2-normalized first (`normalizeVector` :298), so dot product **is** cosine similarity | `1.0 - (embedding <=> ?) AS similarity` — pgvector's cosine distance converted to a similarity score |
| Mechanics | PHP loops over rows, `usort` by score | Pure SQL: `orderByRaw('embedding <=> ? ASC')`, LIMIT in DB |

`cosineSimilarity` is just the dot product because all embeddings are normalized (dot product of unit vectors = cosine of the angle). `<=>` returns *distance* (0 = identical, 2 = opposite), so similarity = `1 − distance`. Higher is better in both cases.

---

## 7. Conversation memory & follow-ups

- **Session persistence:** each conversation is a `ChatSession` with a `session_token` stored in the widget's `localStorage`. When a guest logs in, the session is claimed for that user (`ConversationManager::resolveSession` → `claimFor`), so history survives the login.
- **History restore:** `GET /chat/history?session_token=...` returns the last 30 messages + handoff status, so the widget replays the conversation on refresh.
- **6-turn window:** `ConversationManager::history($session, 6)` feeds the last 6 user/bot turns to Gemini. Old turns are dropped (a `summarizeHistory` helper exists but only kicks in past 20 messages).
- **Follow-up detection** (`ChatbotService::isFollowUpQuery` :113): a short message that refers back to the previous turn — refinements ("no, just Palawan", "actually cheaper"), referential pronouns ("which one", "it", "the first"), or continuation openers ("how much", "what about", "tell me more") — is answered from `PREVIOUS RECOMMENDATIONS` stored in the previous bot message's `context_data`, **without running a new database search**. The rule guard: if it looks like a fresh search (new destination + "hotels in X", "book/reserve"), it's treated as a new search.
- This is why the widget keeps recommendation cards clickable across follow-ups — the cards are carried over in `context_data`.

---

## 8. FAQ shortcut (before the LLM runs)

`ChatbotService::handle` checks `FaqService::findBestMatch` *before* intent classification (ChatbotService.php:74). The FAQ path:

1. **Lexical:** tokenize query and FAQ question+keywords, score by Jaccard + query coverage; if score ≥ 0.5 → return that FAQ answer (cheap, fast, deterministic).
2. **Semantic fallback:** if lexical is weak, `GeminiService::searchFaqs` (pgvector `<=>`); require score ≥ 0.7 **and** at least one shared token, else null.
3. If null, the request continues to the full intent pipeline.

So common questions ("how do I book?", "cancellation policy?") never burn a Gemini call.

---

## 9. Human-agent handoff

Guests and users can request a real support agent; the bot is **bypassed** while a ticket is active.

- **Endpoints** (`routes/chatRoute.php`): `POST /chat/handoff`, `POST /chat/handoff/cancel`, `POST /chat/handoff/return`, `GET /chat/poll` (widget polls every 5s while a handoff is active — no WebSockets).
- **Ticket states** (`SupportInquiry`): `pending` → `human_active` → (`returned_ai` | `resolved`). Cancel sets an unclaimed ticket back to `ai_active`.
- **Queue logic** (`SupportQueueService`): tickets are created with a `TKT-YYYYMMDD-XXXX` number; admins **claim** atomically (`lockForUpdate` inside a DB transaction — two admins can't take the same ticket). Admin replies are written with `sender = 'admin'`.
- **During a ticket:** `ChatbotService::handle` checks for a pending/human-active inquiry first (ChatbotService.php:42-63):
  - `pending` → "An administrator will be with you shortly."
  - `human_active` → returns `control: 'admin'`; the widget switches to admin-broadcast mode and the AI never runs.
- **Return to bot:** `resumeAi` sets `returned_ai`; the bot resumes, and admin messages still appear in the Gemini history as authoritative `Support Agent (human):` lines (per the system prompt).

---

## 10. Abuse guard & rate limits

**Guest limits** — `EnforceGuestChatLimits` middleware:
- 15 messages/day/IP, and 5 messages/60s burst. Over-limit → 429 with signup prompt. Logged-in users are unlimited (they get `throttle:ai` only).

**Abuse guard** — `ChatbotService::checkAbuse` (:312):
- **Guests:** keyword blocklist (NSFW, threats, injection patterns like "ignore previous instructions", "you are now DAN"). Blocked → 403, no DB record.
- **Authenticated users:** `GeminiService::detectAbuseAndGuard` (:1519) — same categories, but **logged to `ChatbotAbuseReport`** and `chatbot_flag_count` incremented; repeated flags can lead to a ban (`isBanned()` short-circuits first).

**Prompt-injection defense** — layered:
1. Keyword guard in the service layer.
2. System prompt (`app/Services/SystemPrompts/chatbot-system-prompt.md`) instructs Gemini to treat all DB context as **untrusted**, discard embedded directives, never leak its own system prompt, and refuse role overrides. Trust hierarchy: system prompt > app controls > live data (untrusted) > user.
3. DB content itself is never used as instructions — only factual fields (name/price/address).

---

## 11. Key file map

| File | Role |
|---|---|
| `routes/chatRoute.php` | All chat routes + throttle middleware |
| `app/Http/Controllers/ChatbotController.php` | HTTP layer: chat, history, handoff, poll |
| `app/Services/Chat/ChatbotService.php` | Orchestrator: `handle()` pipeline, all 9 intent handlers, follow-up logic, prompt builder |
| `app/Services/Chat/IntentRouter.php` | Intent classification + constraint extraction |
| `app/Services/Chat/ConversationManager.php` | Session resolve/claim, 6-turn history, message persistence |
| `app/Services/Chat/FaqService.php` | Lexical→semantic FAQ matching |
| `app/Services/GeminiService.php` | Embeddings, `searchRoomsHybrid`, `searchHotels/Activities/Packages/Faqs`, `cosineSimilarity`, `rankRecommendations`, `buildItineraryContext`, `detectAbuseAndGuard`, chat generation, prompt cache |
| `app/Services/SystemPrompts/chatbot-system-prompt.md` | SunnyBot identity, grounding rules, injection defense |
| `app/Services/Support/SupportQueueService.php` | Handoff tickets, atomic claiming, admin messages |
| `app/Http/Middleware/EnforceGuestChatLimits.php` | Guest 15/day + 5/min limits |
| `app/Services/RoomAvailabilityService.php` | Real availability check per room/date |
| `app/Services/WeatherService.php` | Live weather + advice |
| `app/Services/DistanceService.php` | Haversine distance for map queries |
| `resources/views/components/frontend/chat-widget.blade.php` | Alpine.js widget (cards, modals, date pickers, polling, Add to Trip Basket) |

**Tests:** `tests/Feature/IntentRouterTest.php`, `tests/Feature/ChatbotTest.php` (includes handoff/guest-limit coverage), `tests/Feature/ChatbotCacheTest.php`, `tests/Feature/FaqServiceTest.php`.

---

## 12. Quick memory hooks

- **Hybrid RAG = vector ranking + SQL constraint filtering.** Rooms/availability are hybrid; hotels/activities/packages are pure vector RAG. That's the one sentence that explains the whole design.
- **9 intents, checked in order:** weather → map → availability → itinerary → package → room → hotel → activity → general.
- **Everything Gemini says must come from `DATABASE RESULTS`** in that turn. Follow-ups use `PREVIOUS RECOMMENDATIONS` instead.
- **Cosine similarity = dot product on normalized vectors (PHP)** for hotels/rooms/activities; **`1 − cosine distance (SQL `<=>`)** for packages/FAQs. Same metric, two implementations.
- **FAQ answer < Gemini call** — common questions short-circuit before the LLM.
- **Support ticket active → AI never runs.** `control: 'admin'` tells the widget to switch modes.