# Chatbot dates and accommodation implementation plan

Status: implemented (October 21, 2026). Sections 1-8 are in the working tree with targeted Pest and node:test coverage.
Outstanding: the section 10 browser acceptance pass is UNVERIFIED - the required local Playwright MCP server with Brave was not available.
Prepared: October 21, 2026.

## 1. Intended behavior

Simplify trip details around arrival and departure dates. Remove the editable days count when exact dates are selected, calculate duration on the server, and display it as read-only text such as **4 days, 3 nights**. Include a hotel and room in complete itineraries and accommodation-inclusive requests, and preserve those details through **Add to Trip Plan**.

Keep the existing **Dates not decided yet** option. In that mode, a day-by-day itinerary requires trip length, while general hotel/activity discovery can proceed without it. Manual chat remains available. This extends the existing trip-plan implementation; it does not replace it or require a branch merge.

## 2. Verified starting points

- `TripDetailsForm` already derives days and nights from exact dates, but also accepts a separate days value. Its exact-date validation currently requires departure after arrival and caps the trip at 30 days.
- `chat-trip-details.blade.php` currently displays Days whenever `needs_duration` is true, even when dates are selected. The date inputs do not currently enforce the proposed conditional required state.
- `handleDraftItinerary()` currently builds an activities-only bundle. This leaves accommodation out of undated complete-trip suggestions.
- `ChatbotTripPlanService::suggestion()` copies a room ID/name when a dated bundle contains a room, but does not preserve a complete accommodation display object. Its daily entries currently start with null dates.
- Saving uses the authoritative suggestion stored in chat history, ownership checks and an idempotent suggestion UUID. Preserve these protections.
- Undated saved drafts, weather advisories, planner editing, PDF/print/email and manual chat are already implemented locally. Preserve their compatibility.

## 3. Date and duration rules

| Situation | Required information | Result |
| --- | --- | --- |
| Exact-date overnight itinerary or room availability request | Arrival and departure | Departure after arrival; derive days and nights |
| Undecided-date itinerary | Trip length, 1–30 days | Draft with numbered days and dates pending |
| General hotel/activity recommendations | No mandatory dates | Discovery results, with availability unconfirmed |
| Booking or dated room availability | Exact stay dates | Revalidate through existing availability/booking services |

- Calculate `nights = departure − arrival` and `days = nights + 1` using date-only values in the application timezone. Example: October 25–28 is 4 days, 3 nights.
- For exact dates, ignore stale client-derived duration and recompute authoritatively. If a typed request explicitly contradicts itself, ask one clarification rather than silently treating conflicting durations as consistent.
- Require both dates on submission of a relevant exact-date form. Continue accepting incomplete typed messages so the chatbot can gather the missing date conversationally.
- Support partial edits against stored scope values: changing one date revalidates the resulting complete pair and recalculates duration.
- Prevent past dates for new planning, reversed ranges and trips beyond the existing 30-day limit. Historical saved plans remain readable.
- Switching to undecided clears exact dates and availability assertions; retain the previous derived trip length as an editable default. Switching back requires a valid pair before dated generation.
- Preserve the existing one-day undated activity draft (1 day, 0 nights). Do not invent an overnight stay. Same-day dated Trip Planner support is outside this change because existing storage requires end date after start date; show a clear message rather than silently advancing departure.
- Arrival/departure are travel dates, not flight times or hotel check-in hours. Do not invent times.

## 4. Trip-details UI

1. Show Arrival and Departure for relevant itinerary/stay requests; mark both required only in exact-date mode.
2. Hide and disable editable Days in exact-date mode. Display the calculated days/nights once both dates are valid.
3. Under Dates not decided yet, show **Trip length (days)** only when needed to generate an itinerary. Derive tentative nights rather than adding another mandatory input.
4. Keep destination, travelers, budget and optional preferences in the existing consolidated card. Keep all manually typed answers supported.
5. Provide visible labels, inline errors, keyboard access and a polite duration announcement. Hidden inputs must not interfere with browser validation or submit stale values.
6. Preserve form revision/scope checks, readable user-answer echoes, and one clarification per turn.

## 5. Accommodation in generated itineraries

### Request scope

- A complete-trip itinerary includes accommodation by default when an overnight stay is relevant.
- Explicit hotel/room plus itinerary requests include both stay and activities.
- Activities-only itineraries, day trips, and requests saying the user already has accommodation must not add a hotel automatically.
- Track this scope explicitly through parsing, dialogue state and retrieval; do not infer it solely from whether a room happened to be retrieved.

### Retrieval and presentation

- Use existing verified room/hotel retrieval and capacity, destination, exclusion and budget filters for both dated and undated suggestions.
- For dated stays, use the established availability path. Distinguish checked availability from an unchecked recommendation; do not imply a reservation.
- For undated stays, suggest a verified room where possible, mark **Dates pending — availability not checked**, and use clearly labeled estimates only.
- Render a dedicated **Your stay** block: hotel, room, guest capacity, travelers/room quantity where supported, check-in, check-out, nights, nightly rate and accommodation estimate.
- Default stay dates to trip arrival/departure. If the user requests a shorter hotel stay, request the actual stay dates or mark the stay unresolved. Do not price two nights while carrying a three-night booking range. A new separate stay-date editor is not required in this iteration.
- Respect the existing room quantity and pricing model. Do not multiply a room rate by travelers unless the inventory actually uses per-person pricing.
- Keep accommodation, activities and combined estimates distinct and preserve budget basis/period/coverage. Unknown rates remain unknown, never zero.
- If no matching room exists, retain activity results and explain the missing accommodation with one useful next step. Never substitute a fabricated room or silently drop the requested stay.
- Assign dated daily entries their actual calendar dates. Mark arrival/departure days and prefer a lighter activity load, without promising feasible timings when transport times are unknown.

## 6. Structured data and saved plans

Extend the existing authoritative suggestion with optional accommodation metadata: hotel/room identifiers and display names, capacity, quantity, stay dates, derived nights, price basis, nightly rate, stay estimate, estimate timestamp and availability-check status. Keep the room choice ID as the actionable inventory reference.

- Retain trip-level arrival/departure, duration and dated day entries alongside the stay object.
- Add optional fields compatibly to the current snapshot contract; old version-1 suggestions and saved plans must still render and save. If implementation requires a new version, support both versions during transition rather than rejecting old history.
- Save only the server-stored suggestion reference; never trust browser-supplied prices, room IDs or dates as the authoritative itinerary.
- Carry the same information into **Itinerary (From Chatbot)**, reloads, PDF/print and explicitly requested email.
- Preserve existing idempotency, guest-to-login recovery, ownership protection and missing-listing handling.
- Editing dates or travelers invalidates old price/availability conclusions. Recalculate dependent duration/day labels and use existing validation before scheduling or booking. Keep historical snapshots unchanged.
- Missing/hidden rooms remain visible as unavailable draft entries with a replacement prompt; they cannot become bookable merely because a snapshot contains them.
- No schema migration is expected: existing dates, duration and JSON context/snapshot fields should suffice. Confirm this during implementation before considering schema changes.

## 7. Weather and action boundaries

Saving a draft must remain independent of weather, routing and availability network calls. Display the existing weather advisory for outdoor/water activities. Exact dates do not imply that a useful forecast exists for those dates.

Preserve existing dated scheduling safety checks and booking-time availability checks. **Add to Trip Plan** saves a plan only; it does not reserve a room, charge money, add to the basket or send email automatically.

## 8. Files to change

All paths below are repository-relative. These are targeted modifications to the current working tree; preserve unrelated changes.

| Action | Files | Purpose |
| --- | --- | --- |
| MODIFY | `app/Services/Chat/TripDetailsForm.php`, `app/Services/Chat/DialogueManager.php` | Conditional date requirements, canonical duration, scope and stale-value handling |
| MODIFY as needed | `app/Services/Chat/IntentRouter.php`, `app/Services/Chat/ConversationPlanner.php`, `app/Http/Controllers/ChatbotController.php` | Typed date/scope parity and request validation without breaking partial conversation turns |
| MODIFY | `resources/views/components/frontend/chat-trip-details.blade.php`, `resources/views/components/frontend/chat-widget.blade.php` | Date-first form, derived duration, stay display and readable errors |
| MODIFY | `app/Services/Chat/ChatbotService.php`, `app/Services/Chat/ChatRetrievalService.php`, `app/Services/GeminiService.php` | Accommodation-inclusive draft retrieval, scope-aware dated retrieval, card/data consistency and honest estimates |
| MODIFY | `app/Services/Chat/ChatbotTripPlanService.php` | Complete structured stay data, actual day dates, compatible persistence |
| MODIFY | `app/Services/Chat/ConversationManager.php`, `resources/js/chat-sections.js`, `app/Services/SystemPrompts/chatbot-system-prompt.md` | Preserve new data in history/sections and align prose with authoritative results |
| MODIFY | `app/Http/Controllers/User/TripPlannerController.php`, `resources/js/trip-planner.js` | Preserve/reconcile accommodation after draft edits and reloads |
| MODIFY | `resources/views/trip-planner/_chatbot-draft.blade.php`, `resources/views/trip-planner/index.blade.php`, `resources/views/trip-planner/draft-document.blade.php` | Consistent saved stay display and exports |
| MODIFY if needed | `app/Notifications/TripItineraryReady.php` | Keep emailed draft content aligned with the shared snapshot/template |
| MODIFY | Existing chatbot details, saved-plan, guided dialogue, intent, itinerary and JavaScript tests | Focused regression coverage described below |

No new dependency, environment variable, automatic deployment or unrelated refactor is planned.

## 9. Implementation sequence

1. Add focused failing cases for date-mode validation and accommodation retention.
2. Normalize date/duration and request scope in backend dialogue handling; retain manual-input behavior.
3. Update the consolidated form with conditional inputs and read-only duration.
4. Extend dated and undated retrieval plus structured stay metadata and daily dates.
5. Render and save the stay consistently, including planner edits, history and exports.
6. Run targeted regression tests, formatting and build checks; then perform browser acceptance tests using the required local Playwright MCP with Brave when available.

## 10. Verification and acceptance criteria

- Exact dates October 25–28 produce 4 days/3 nights in the form, chatbot, saved plan and export. No editable Days field is shown in exact-date mode.
- Missing one date, reversed dates, past dates and excessive duration give clear errors; partial typed replies continue the conversation instead of failing the entire chat request.
- Changing dates across month/year/leap-day boundaries derives correct date-only durations; stale submitted days cannot override dates.
- Switching date modes clears incompatible values and old availability claims; dates-undecided itineraries require duration and remain saveable.
- Full itinerary includes a verified hotel/room plus activities; activities-only, already-booked accommodation and day-trip requests do not acquire an unsolicited stay.
- No-room, insufficient-capacity, unavailable-room and budget-mismatch cases preserve other requested results and accurately describe the gap.
- Price totals use the correct night count, quantity and budget basis. Separate activity-only/lodging budgets remain correctly scoped.
- Saving/reloading retains accommodation and dates, repeated saves remain idempotent, and another user cannot access the suggestion or plan.
- Old snapshots without accommodation metadata remain usable. Deleted listings and date/traveler edits cannot preserve false availability or stale cost guarantees.
- PDF/print/email match the saved snapshot. Saving alone sends no email and makes no reservation, basket mutation or weather request.
- Form and typed-input tests preserve multi-intent coverage, one-question behavior, exclusions, support handoff and manual correction flows.
- Run the affected Pest files, existing JavaScript tests, Pint (`--dirty --format agent`), Blade compilation and production asset build. Ask the user to run the full suite after targeted checks pass.
- Browser acceptance covers keyboard-only interaction, mobile layout, hidden-field validation, history restore, manual typing, complete/undated itineraries, save/reload and date edits. If the required browser tool is unavailable, report these checks as unverified rather than substituting another browser.

## 11. Scope boundary

This plan is ready for review. No application implementation, migration, build or tests have been run for this planning-only request. Multi-hotel stays, flight-time scheduling, same-day dated planner storage and a separate shorter-stay date editor are future extensions; incomplete information must be disclosed rather than guessed.
