# Lucky_Itinerary_Plan.md — "I'm Feeling Lucky" Random Itinerary Generator

Status: Foundation plan (spec). This doc is the source-of-truth for the feature being planned. Verify against the code before trusting (it may drift during implementation).

## Concept

A Google-Earth-style "I'm Feeling Lucky" button. One click generates a surprise travel itinerary: one hotel (room) + a set of activities, all within a **single destination**, bounded by user filters (max budget, activity count, nights, hotel category, pax, date). The user can shuffle as many times as they like; accepting the itinerary adds every item to the cart grouped as one "I'm Feeling Lucky" package with a single master checkbox. Checkout is untouched: grouped items are plain cart line items with identical pricing.

## Non-goals

- No AI/embeddings — pure constrained random selection. (Gemini may be considered later for "smart" shuffles.)
- No add-ons/transfers in the itinerary (user can add them manually from the cart).
- No changes to checkout, booking, payments, or admin flows.
- No persistence of past itineraries.

## Filters (all optional except implied defaults)

| Filter | Default | Range / mapping |
|---|---|---|
| destination_id | random | from `destinations` table |
| max_budget | 20000 | total trip cost: room (rate × nights) + all activities, for all pax |
| activity_count | 2 | 1–5 |
| nights | 2 | 1–7 (room booked for nights+1 days) |
| hotel_category | any | room-price bands, matching room/index.blade.php: budget ≤ 2500, mid 2500–6000, luxury ≥ 6000 (nightly rate, selected pax) |
| pax | 2 | 1–4 |
| start_date | today + 7d | drives check_in_date/check_out_date |

Activity `rate` strings parsed via `ActivityModel::calculateRateForPax(pax)`; room rate via `RoomType::calculateNightlyRate(pax)` — the exact same functions `CartItem::subtotal` uses, so the generated total can never drift from the cart/checkout total.

## Generator algorithm (`app/Services/LuckyItineraryService::generate`)

1. Resolve destination pool (filtered or all shown destinations).
2. Pool A: hotels with `is_shown`, rooms with `is_shown` matching category band.
3. Pool B: activities with `is_shown` (+ optional level/category filter).
4. Repeat up to 40 attempts:
   a. random hotel → random room;
   b. sample `activity_count` distinct random activities from pool B;
   c. total = roomRate(pax) × nights + Σ activityRate(pax);
   d. if total ≤ max_budget → accept.
5. If nothing fits: return the cheapest observed combo with `budget_exceeded = true` (UI shows a warning, never silently).
6. If pools are empty: 422 with a friendly message.

Returns an itinerary DTO: destination, hotel (name/type/image), room (name/rate/night), activities (name/category/level/rate), nights, check_in/out, pax, line items, total.

## Routes (`routes/luckyRoute.php`, required from web.php)

- `GET /lucky` (auth) — page: filter form + shuffle button + result card.
- `POST /lucky/generate` (auth) — JSON itinerary.
- `POST /lucky/accept` (auth) — validates + **recomputes server-side** (client totals are never trusted), creates 1 room + N activity CartItems sharing a single `lucky_group_id` (UUID). Returns redirect to `/cart`.

## Cart grouping (schema + UI)

- Migration: `cart_items.lucky_group_id` nullable string(36), indexed. `CartItem::$fillable` extended. Checkout code never reads this column.
- `CartController::data()` / `index()` group items by `lucky_group_id`; payload gains a `groups` map: `{ id, title: "<Destination> — <nights>N Surprise Itinerary", destination_name, item_count, selected_count, subtotal, is_selected }`.
- New endpoint `POST /cart/toggle-group/{group_id}` — sets `is_selected` for every item in the group (master checkbox). Per-item toggles remain available.
- Cart page + drawer: items with a `lucky_group_id` render under a dedicated "I'm Feeling Lucky" section header with a group master checkbox; ungrouped items render exactly as today. Group total = sum of member subtotals.

## Checkout (unchanged by design)

Selected grouped items flow through the existing pipeline (room date holds, activity/addon pax normalization, min-pax gates, guest manifest, BookingItem snapshots). Booking produces one booking with N+1 booking items, same totals as shown in cart.

## Shared cart logic

`CartController::store`'s add routine (session-token claim, dedupe, room-hold check, pax normalization) is extracted to `app/Services/CartService::add(...)` and reused by `POST /cart/add` and `POST /lucky/accept`, so lucky items get identical inventory checks.

## UI notes

- Navbar: "I'm Feeling Lucky" button (shuffle/dice icon) → `/lucky`.
- Palette ocean/sand/ink/coral, Sora headlines + DM Sans body (typography-system skill).
- Result card: destination header, hotel + room block, activity list with rates, total with budget badge (green within budget / amber "over budget" warning), Shuffle and "Add to Cart" actions.

## Tests (`tests/Feature/LuckyItineraryTest.php`, Pest, BookingFlowTest patterns)

1. Generate: hotel + every activity share one destination_id.
2. Total ≤ budget when a fitting combo exists (exact budget boundary).
3. `budget_exceeded` returned when budget below cheapest combo (e.g. ₱100).
4. Filters respected: activity_count, nights (check_in/out + totals), pax, category band.
5. Accept: cart gains 1 room + N activities sharing the same `lucky_group_id`, correct dates/pax, is_selected true; unauthenticated → redirect.
6. `cart.data` exposes the group; `toggle-group` flips all members.
7. Full `checkout.process` on grouped items → 1 booking, N+1 booking items, correct total, cart emptied (proves checkout parity).
8. Validation: bad filter values → 422.

## Edge cases

- Activity rate ranges: `calculateRateForPax` yields min for 1 pax / max otherwise — matches cart subtotal behavior by construction.
- Destination with hotels but no shown activities (or vice versa) → skip/next attempt, then friendly 422.
- Room availability holds (pending/approved/paid bookings) are enforced at accept time via CartService (same as manual adds).
- Tampered accept payloads: server recomputes everything; budget is re-checked.
