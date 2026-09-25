# Catalog Detail Responses — Implementation Plan

## Goal

Make a question about a specific catalog record answer the requested stored field before showing the verified card. Examples:

- `Boracay Tipid Deal full inclusions` → the package's saved `generic_inclusions`.
- `My Station Hotel amenities` → the hotel's saved `featured_amenities`.
- `Social Mixed Dormitory Bed amenities` → the room's saved `room_amenities`.

The response must not depend on Gemini to restate catalog attributes. Gemini may still retrieve and rank records, but database values remain the only source for a direct detail answer.

## Confirmed Root Cause

- `IntentRouter::detectFieldIntent()` recognizes `inclusions`, but `ChatbotService::handlePackageSearch()` does not use that field intent.
- `fieldLead()` / `fieldValue()` provide database-backed values for activities, rooms, and a limited hotel subset, but not packages. They also do not cover amenities.
- The fallback in `validateGroundedReply()` renders only verified names and prices. When Gemini's optional explanation is unavailable or rejected, the requested field is lost.
- Consequently, a package detail request falls back to `Boracay Tipid Deal — ₱6,999.00`, while the hotel-amenities example succeeds only when Gemini happens to produce prose that passes the current filter.

## Response Contract

For a confidently resolved single record plus a recognized field request:

1. Return a deterministic answer from the exact saved database attribute.
2. Preserve the original record name and attribute values exactly as stored (apart from safe list formatting).
3. Then show the existing verified result card/list.
4. If the requested attribute is empty, say that the catalog does not list that detail; do not infer it from the description or generate a replacement.
5. If the name is ambiguous or no exact record is resolved, retain the existing semantic-search behavior and clearly present the results as alternatives.

## Proposed Changes

### `[MODIFY] app/Services/Chat/IntentRouter.php`

- Extend `detectFieldIntent()` with an `amenities` intent, including singular/plural and common facility wording.
- Keep `inclusions`, `exclusions`, price, duration, capacity, requirements, location, and itinerary intents unchanged.
- Confirm exact package-name extraction remains case-insensitive and does not require a separate `package` keyword when the full package name appears in the message.

### `[MODIFY] app/Services/Chat/ChatbotService.php`

- Pass `field_intent` into the package response path, as room, hotel, and activity paths already do.
- Extend `fieldValue()` to support `Package` records:
  - `inclusions` → `generic_inclusions`
  - `price` → stored package price with its existing per-pax wording
  - `duration` → saved days/nights
  - `capacity` → saved minimum-pax requirement
  - `location` → package destination
- Add safe `amenities` support using only:
  - `HotelModel::featured_amenities`
  - `RoomType::room_amenities`
- Use the existing `joinList()` helper so JSON-array and string-backed values are rendered consistently.
- Invoke the deterministic field lead before the optional Gemini explanation/list fallback for package searches as well.
- Ensure a missing field returns a clear no-data statement only for a confidently resolved single entity; it must not claim that a broad search result has no amenities or inclusions.
- Do not change catalog cards, checkout pricing, embeddings, semantic ranking, or database schema.

### `[MODIFY] app/Services/GeminiService.php` — only if verification identifies a missing context field

- Verify the relevant package, hotel, and room context already includes the stored field.
- Add context only when a field is absent from the relevant context builder. This is not required for the deterministic answer itself, but keeps optional conversational wording aligned with the displayed result.

## Tests

### `[MODIFY] tests/Feature/ChatbotTest.php` (or the closest existing chatbot field-query test file)

- `Boracay Tipid Deal full inclusions` returns the known `generic_inclusions` values and the verified package card.
- A known package with no `generic_inclusions` returns the explicit not-listed message and does not invent inclusions.
- `My Station Hotel amenities` returns only its known `featured_amenities` values and its verified hotel card.
- A named room amenities query returns only that room's known `room_amenities` values.
- Verify a fabricated Gemini amenities/inclusions explanation cannot replace or alter the deterministic field answer.
- Preserve existing tests covering ungrounded names/prices and card rendering.

### `[MODIFY] tests/Feature/IntentRouterTest.php`

- Cover `amenities` / `facility` phrasing mapping to the new `amenities` field intent.
- Preserve existing inclusion and exclusion detection, including `not included` taking precedence over `included`.

## Acceptance Examples

| User query | Required first answer |
| --- | --- |
| `Boracay Tipid Deal full inclusions` | `Boracay Tipid Deal includes: ...` using `generic_inclusions` only |
| `My Station Hotel amenities` | `My Station Hotel amenities: ...` using `featured_amenities` only |
| `Social Mixed Dormitory Bed amenities` | `Social Mixed Dormitory Bed amenities: ...` using `room_amenities` only |
| `Boracay Tipid Deal exclusions` | A clear unavailable/not-listed response if packages do not store exclusions |
| `cheap Boracay packages` | Existing semantic result list; no claim about one package's inclusions |

## Verification

1. Run the newly added/updated field-intent test after each edit.
2. Run the affected chatbot and intent-router feature test files.
3. Run `vendor/bin/pint --dirty --format agent`.
4. Run the affected tests again and inspect the final diff to ensure no unrelated chatbot behavior changes.

## Scope Boundaries

- No migrations or dependencies are needed.
- This plan addresses database-backed detail questions, not the quality of broad semantic recommendations.
- No implementation begins until this plan is approved.
