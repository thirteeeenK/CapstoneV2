# SunnyTrips Chatbot — AI Semantic Search & Database Grounding

> This document answers: *what threshold is used (0.7? 0.8?)*, *how top-K semantic search is computed*, and *which chatbot responses are database-grounded*.

---

## 1. Threshold — Answer: 0.7 only for FAQ, no 0.8 anywhere

There is **no global 0.7 or 0.8 cosine cutoff** for the chatbot's main searches. The **only semantic similarity threshold in the entire codebase is `0.7`** for the FAQ fallback.

| Location | Value | Type | Code |
|---|---|---|---|
| `app/Services/Chat/FaqService.php:36` | `>= 0.5` | Lexical (Jaccard+coverage) | `if ($best['score'] >= 0.5) return $best['faq'];` |
| `app/Services/Chat/FaqService.php:41, 98` | `>= 0.7` | Cosine similarity (pgvector) | `if ($semantic['score'] >= 0.7)` and `if ($top['score'] < 0.7) return null;` |
| Everything else | — | Top-K only | No `WHERE similarity >` — see §2 |

Greps for `threshold`, `0.8`, `0.75`, `similarity_threshold`, `WHERE (embedding <=> ?) <` return **0 hits** for hotels/rooms/activities/packages. Config `config/services.php:37` has only `review_summary_threshold` (count, not similarity) and `.env.example` has no threshold var.

When the FAQ threshold fails (`top < 0.7` or no token overlap at `FaqService.php:104`), the chatbot does **not** return a FAQ answer — it falls through to normal intent handling (top-K search + Gemini narration).

---

## 2. How Top-K Semantic Search Is Computed (chatbot inquiries)

Used for **ROOM_SEARCH, HOTEL_SEARCH, ACTIVITY_SEARCH, PACKAGE_SEARCH, AVAILABILITY_QUERY** and dashboard recommendations.

### Two vector strategies (same model: `models/text-embedding-001`, `vector(3072)`, L2-normalized)

**A. PHP-side cosine (rooms, hotels, activities)**

`app/Services/GeminiService.php:298` `normalizeVector()` → L2 norm so dot-product == cosine.
`app/Services/GeminiService.php:332` `cosineSimilarity(vecA, vecB)` → dot-product.
`app/Services/GeminiService.php:348` `rankRecommendations(vector, items, limit = 5)`:

```php
// parse embedding (array or "[0.1,...]" string)
// score = cosineSimilarity(queryVector, itemVector)
// usort($scored, fn($a,$b) => $b['score'] <=> $a['score']); // descending
// return array_slice($scored, 0, $limit); // top-K, no threshold
```

Callers:
- `searchHotels(query, limit=5, hotelId?)` at `GeminiService.php:452` → `HotelModel::where is_shown + embedding NOT NULL` → `rankRecommendations`
- `searchRooms(query, limit=5)` at `:569` → same
- `searchActivities(query, limit=5)` at `:682` → same
- `searchRoomsHybrid(query, constraints, limit=5)` at `:1620` → **Hybrid**: `RoomType::where is_shown + embedding NOT NULL` + SQL filters `whereHas hotel.destination_id`, `hotel_id`, `max_occupancy >= pax`, `base_price <= max_price` before the same PHP ranking (fallback to unfiltered if 0 results).

**B. SQL pgvector (packages, FAQs)**

`app/Services/GeminiService.php:780` `searchPackages(query, limit=5)` and `:815` `searchFaqs(query, limit=3)`:

```sql
SELECT *, 1.0 - (embedding <=> ?) AS similarity
FROM packages -- or faqs
WHERE is_active = true AND embedding IS NOT NULL
ORDER BY embedding <=> ? ASC -- cosine distance asc = similarity desc
LIMIT 5
```

`<=>` is pgvector cosine distance. `1 - distance` is returned as `score`. Again **pure ordering, no `WHERE similarity > 0.7`**.

**Not semantic at all:** `buildItineraryContext()` at `:1855` is deterministic (cheapest hotel/room where `roomTotal <= 0.5*budget`, cheapest activities where `cost <= 0.8*remainingBudget`).

---

## 3. Which Chatbot Responses Are Database-Grounded and Use Semantic Search?

| Chatbot Path | File:Line | Uses Semantic Search? | Database-Grounded? | How |
|---|---|---|---|---|
| **ROOM_SEARCH** | `ChatbotService.php:344` → `GeminiService.php:1620` | **Yes — Hybrid (PHP)** | **Yes — Fully** | Constraints → SQL pre-filter → PHP cosine top-5 → `buildPrompt('room-search')` strict rules → `generateChatResponse()` |
| **HOTEL_SEARCH** | `ChatbotService.php:377` → `GeminiService.php:452` | **Yes — PHP** | **Yes — Fully** | `where is_shown + embedding NOT NULL` → PHP cosine top-3 → grounded prompt |
| **ACTIVITY_SEARCH** | `ChatbotService.php:476` → `GeminiService.php:682` | **Yes — PHP** | **Yes — Fully** | Same PHP cosine top-3 → grounded prompt |
| **PACKAGE_SEARCH** | `ChatbotService.php:494` → `GeminiService.php:780` | **Yes — SQL pgvector** | **Yes — Fully** | SQL `<=>` top-5 → date-valid filter `valid_from/valid_to` → grounded prompt |
| **AVAILABILITY_QUERY** | `ChatbotService.php:556` → `GeminiService.php:1620` + `RoomAvailabilityService.php:19` | **Yes — Hybrid + Live** | **Yes — Fully + Live** | Same hybrid search (limit 8) → per-room `BookingItem` overlap query → remaining rooms + `calculateNightlyRate()` total → grounded |
| **ITINERARY_QUERY** | `ChatbotService.php:529` → `GeminiService.php:1855` | **No** | **Yes — Fully (deterministic)** | Cheapest hotel/room/activities by price/budget math, Gemini only narrates pre-built context |
| **MAP_QUERY** | `ChatbotService.php:616` | **No** | **Partial** | `DestinationModel::where ILIKE` + `DistanceService::haversine()` math; with `user_lat/lng` now computes `You are ~X from {dest}` |
| **WEATHER_QUERY** | `ChatbotService.php:666` | **No** | **Partial** | `DestinationModel::where ILIKE` + `WeatherService::forecastForDestination()` (OpenWeather 5-day) |
| **DESTINATIONS_OVERVIEW** | `ChatbotService.php:805` | **No** | **Yes** | `DestinationModel::orderBy(name)->pluck(name)` → deterministic list (no Gemini) |
| **FAQ** | `FaqService.php:18` → `GeminiService.php:815` | **Hybrid (lexical → pgvector 0.7)** | **Yes** | Lexical `>=0.5` → semantic `searchFaqs` `>=0.7` + token overlap → returns `faqs.answer` verbatim |
| **FOLLOW_UP** | `ChatbotService.php:208` | **No** | **Yes (prior context)** | Replays `context_data` from last bot turn, no new search |
| **GENERAL_TALK** | `ChatbotService.php:830` | **No** | **Partial** | Only `DestinationModel::pluck(name)` injected as `KNOWN DESTINATIONS: ...` grounding; otherwise open `gemini-2.5-flash-lite` generation |

### What Is **Not** Using Semantic Search (so you’re aware)

These never call an embedding or `rankRecommendations` / `<=>`:

- **General responses** (`GENERAL_TALK` at `ChatbotService.php:830`) — no `search*`, only the injected destination list + `chatbot-system-prompt.md` strict grounding rules. This is why an ungrounded general answer could previously hallucinate Palawan/Cebu/Siargao/Bohol before the `DESTINATIONS_OVERVIEW` fix — it had no DB results.
- **Itinerary builder** (`GeminiService.php:1855`) — deterministic cheapest-pick, zero embeddings.
- **Map / Weather / Destinations Overview** — direct DB + `DistanceService` / `WeatherService` / `pluck`.
- **Follow-ups** — explicitly reuses previous `context_data`, forbids new search.
- **Abuse guard** (`ChatbotService.php:34` + `GeminiService.php:1536`) — keyword blocklists only.

All grounded handlers share the same RAG contract: `get*Context()` formats `=== DATABASE RESULTS ===` + `buildPrompt(stage, context, query, user)` at `ChatbotService.php:811` with rules *“Answer ONLY using DATABASE RESULTS / Never invent prices / If unavailable say not in our database”* → `generateChatResponse()` with a 6-turn `ConversationManager::history()` window.

---

## 4. Verification References

- `app/Services/GeminiService.php:298` `normalizeVector`, `:332` `cosineSimilarity`, `:348` `rankRecommendations`, `:452` `searchHotels`, `:569` `searchRooms`, `:682` `searchActivities`, `:780` `searchPackages`, `:815` `searchFaqs`, `:1620` `searchRoomsHybrid`
- `app/Services/Chat/ChatbotService.php:344` `handleRoomSearch`, `:377` `handleHotelSearch`, `:476` `handleActivitySearch`, `:494` `handlePackageSearch`, `:529` `handleItineraryQuery`, `:556` `handleAvailabilityQuery`, `:616` `handleMapQuery`, `:666` `handleWeatherQuery`, `:805` `handleDestinationsOverview`, `:830` `handleGeneralChat`, `:208` `handleFollowUp`
- `app/Services/Chat/FaqService.php:36` lexical 0.5, `:41,98` semantic 0.7, `:104` token overlap
- `app/Services/Chat/IntentRouter.php:60` `classify()` (all regex/keyword, zero semantic)
- `config/services.php:37` Gemini config (no threshold), `database/migrations/*_create_*_table.php` `vector(3072)` columns

---

*No `0.8` threshold exists. The 0.7 gates only FAQ specific answers; chatbot inquiries are top-K semantic ordering over DB-filtered candidates, always grounded via `DATABASE RESULTS`.*
