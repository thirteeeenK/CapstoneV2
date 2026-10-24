# Chatbot itinerary review and implementation plan

Reviewed: October 24, 2026. Baseline: current working tree, including the uncommitted RSC improvements, on commit `7aefdc2`.

This is a review and proposal only. No application code, settings, migrations, or production records were changed for this review. The earlier `RSC_IMPLEMENTATION_PLAN.md` and historical `implementation_plan.md` remain intact.

## 1. Assessment and verification

**The recent changes have addressed several earlier RSC gaps, but the preference-based chatbot flow and duration/calendar requirement are not fully fixed.** The supplied Boracay transcript confirms excessive repetition and confusing incomplete-draft messages. The code explains those symptoms; it does not establish a server exception or a duplicate HTTP submission.

Existing automated checks run against the dedicated test database:

| Checks | Result |
| --- | --- |
| `ChatbotTripDetailsTest`, `ChatbotGuidedConversationTest`, `ChatbotItineraryBudgetTest`, `ChatbotTripPlanTest`, `TripPlannerTest` | 257 passed; 1,557 assertions |
| `BookingJourneyContextTest`, `TripJourneyTest`, `CancellationRequestTest`, `LuckyPromotionTest`, `CheckoutPromotionTest`, `AdminPromotionTest`, `ChatbotLuckyTest`, `BookingInstallmentTest` | 138 passed; 606 assertions |
| `LuckyItineraryTest`, `ChatbotItinerarySelectionTest`, `WeatherSchedulingTest`, `RoutingServiceTest` | 44 passed; 184 assertions |
| Node: `ChatTripDetails.test.js`, `TripPlannerMap.test.js`, `ChatSections.test.js` | 54 passed |

Total: **439 PHP tests and 54 JavaScript tests passed**. These are targeted suites, not the complete application suite. PHP initially could not read the bootstrap in the restricted shell; the existing tests ran successfully outside that restriction. That tooling issue is not evidence of a chatbot application failure.

Some passing tests encode behavior that must now change: exact dates currently override a previously selected duration, and old itinerary suggestions retain separate save references. Tests must be updated to the new requirements, not treated as proof those requirements already work.

No browser reproduction was performed: the required local Brave Playwright MCP was not available among the tools exposed to this session. The user-provided transcript supplies UI evidence. Live Gemini responses, PayMongo settlement/webhooks, actual inbox receipt, deployed configuration, and migrations in the application's non-test database were not verified.

### Earlier RSC findings: current closure status

| Recommendation | Current assessment | Remaining work or qualification |
| --- | --- | --- |
| Opening statement and structured chatbot booking process | Implemented, with remaining UX problems | Guided dialogue and consolidated details form exist. Repeated tasks, repeated output, and conflicting fields prevent treating the whole process as complete. |
| Budget suggestions | Partially addressed | Budget handling and dedicated tests exist. Undated draft and conversational skip paths still differ; missing traveler counts prevent meaningful totals. |
| Lucky in chatbot | Implemented and covered by passing tests | `IntentRouter::hasLuckyIntent()` and `ChatbotService::handleLucky()` use the existing Lucky generator and saved offers. |
| Lucky promotions and admin configuration | Implemented and covered by passing tests | Campaign administration, fixed/percentage discounts, terms/expiry, owner-bound codes, exact itinerary eligibility, checkout validation, allocation, and redemption lifecycle exist. An eligible active campaign is required; a discount is not guaranteed on every generation. |
| Partial payment to secure reservation | Implemented capability; operation not fully verified | Installment and discounted-deposit tests pass. Default `config/system_settings.php` still disables installments; the current database override was not inspected. Do not claim every new booking requires a deposit. Full payment remains an alternative. |
| Pre-trip and balance notices for **all trips, including ordinary catalog bookings** | Source coverage addressed in code/tests; operationally incomplete | `BookingJourneyContext` derives service windows from booking items without requiring a planner snapshot. Local `.env` still declares `TRIP_JOURNEY_ENABLED=false`; effective cached/deployed configuration and delivery remain unverified. Balance notices remain in the existing payment flow. |
| Cancellation, rebooking, refund | Partially addressed | Paid cancellation requests and restoration of prior state are implemented and tested. Rebooking is a new request with current prices/availability. Refund recording explicitly says SunnyTrips did not initiate a transfer; automatic provider refunds are not implemented by these changes. |
| Working PayMongo | Integration exists; live operation unverified | These tests do not demonstrate an actual provider payment or callback. A separate controlled provider verification is still needed. |
| Reliable email sending | Application paths improved; actual delivery unverified | Passing mail/queue tests are not inbox evidence. Check mail configuration, sender, worker, scheduler, and controlled delivery separately. |
| Printable itinerary and prominent reference | Addressed in templates and planner coverage | Documents now prominently distinguish plan/booking references and show recorded estimates or booking prices. Browser/print pagination still needs visual verification. Older snapshots correctly disclose missing historical estimates. |
| Traveler reviews | Existing feature remains | Journey tests cover review eligibility/invitations. This review does not replace a complete review UI audit. |
| Actual map routes | Existing routing integration retained; focused tests pass | Documents identify route basis and approximate/missing coordinates. Provider outages and missing location data can still require clearly labeled fallbacks. |
| Highlight forecast use and weather-aware, priced suggestions | Improved, but draft distinction remains essential | Checked schedules/documents show forecast provenance, provisional weather, route basis, and prices. Undated chatbot drafts cannot honestly claim dated weather or route verification. |

Additional static finding outside the chatbot: `TripJourneyService::process()` filters candidate bookings using item dates within a 40-day horizon or recent payment/deposit timestamps, but not `trip_end_override`. A booking whose original service dates and payment are old can be omitted despite a future extension. The query omission is verified; add a focused regression before changing the selection query. Keep this as a small follow-up to the all-bookings reminder work.

## 2. How the current preference flow works

1. `ChatbotController` validates structured details and checks session/form revision and scope.
2. `TripDetailsForm` validates/normalizes values. `DialogueManager` merges them into conversation tasks and decides what is still missing.
3. `ChatbotService::handlePlannedTasks()` executes the selected tasks. Undated itinerary tasks commonly use `handleDraftItinerary()`; this searches rooms and activities separately. The quoted combined-itinerary path is different.
4. `ChatRetrievalService` limits visible catalog cards and also trims draft choices to those remaining cards. `ChatResponseComposer` preserves the deterministic draft narrative because the draft sets `preference_notice`.
5. `chat-sections.js` turns response sections into visible messages. The widget renders narrative, room/activity cards, and `itinerary_suggestion` with its own day list and save action.
6. Saving uses the suggestion UUID to create/reuse a `TripPlan`. The planner later accepts dates and creates a checked schedule through `ItineraryService`; checkout remains the reservation boundary.

Lucky uses its own generator, saved offer, and promotion workflow. That working path should not be replaced with the preference-based draft builder.

## 3. Verified issues and root causes

### A. The itinerary appears three times within an answer

**Verified by code and the supplied transcript.** `ChatbotService::handleDraftItinerary()` returns a prose day-by-day schedule, `retrieved_rooms`/`retrieved_activities`, and `itinerary_suggestion`. `chat-sections.js::sectionExtras()` forwards all of them. The widget renders them all independently.

Consequences: duplicate stay names, duplicate activity names/day lists, repeated date notices, competing “Trip Basket” and “Add to Trip Plan” actions, and a room calendar that looks as if it controls the entire suggested itinerary but does not.

### B. Editing duration reruns unrelated tasks and repeats complete results

**Verified mechanism; exact repeated-request sequence remains unverified.** `DialogueManager::prepareDetails()` loops over every non-cancelled task in the submitted scope, including answered activity searches, and executes them again. `TripDetailsForm::build()` also combines nonempty constraints from multiple tasks in that scope. This explains how a duration edit can return “Things to do” alongside another itinerary.

`ChatbotTripPlanService::suggestion()` creates a fresh UUID each time it is called. Saving is idempotent for one UUID, but two identical regenerated drafts have different identities and save states. This is consistent with “Saved — View Trip Plan” followed by an equivalent “Add to Trip Plan” in the transcript.

The transcript alone cannot prove whether every repeated full message came from separate submissions, distinct tasks, or history restoration. Do not fix this by deleting equal-looking message text: different trips can legitimately contain the same catalog items.

### C. Six-day suggestions are constrained by a preview-card limit

**Verified.** The default `services.chatbot.cards_per_task` is three. `ChatRetrievalService::retrieve()` slices catalog cards, then filters the itinerary's choices to the surviving cards. `handleDraftItinerary()` also limits its narrative to three activities. Display limits and trip content are incorrectly coupled.

The claim “I do not have enough verified activities to fill every day” is therefore too strong without checking the eligible itinerary candidate pool. Do not promise one activity daily regardless of budget, preference, inventory, or schedule; report the actual reason for open days.

### D. Traveler count is optional, but missing prices are described as stale

**Verified.** The details form permits blank travelers. The draft calculates a room rate only when `pax` is present. `chat-itinerary-summary.blade.php` renders every null rate as “Price estimate needs updating,” including a rate that was never calculated. This produces the exact combination of “Unspecified travelers” and the price message supplied by the user.

The catalog card's displayed rate is not necessarily a verified whole-group stay total. It should not be presented as if it resolves the missing traveler input.

### E. Budget questions and dates-undecided behavior are inconsistent

**Verified.** “Whole group total” and a separate “Per night” period can be selected together; validation silently converts the basis. “Group per night” and “Whole stay / trip” are also overlapping dimensions. Budget coverage is not the same thing as whether accommodation is included, yet there is no explicit accommodation toggle in the form.

Typing “Dates undecided” in response to the dates question clears `max_price` in `DialogueManager::prepare()`. The structured form's `dates_undecided` handling preserves the amount. A user can get different budget behavior from equivalent inputs.

The undated draft explicitly prices accommodation and activities separately rather than proving a combined budget fit. Retaining that limitation honestly is better than asserting a total the code has not calculated. The new flow should calculate a consistent estimate where enough inputs exist.

### F. Dates override duration instead of respecting it

**Verified at several boundaries:**

- `chat-trip-details.blade.php` hides/disables duration when dates are decided and recalculates days from dates.
- `chat-widget.blade.php::submitTripDetails()` drops `days` from dated submissions.
- `TripDetailsForm::validate()` unsets submitted days and derives duration from the date range.
- `DialogueManager::merge()`, `ChatbotService::handleItineraryQuery()`, and `ChatbotTripPlanService::suggestion()` also derive duration from dates.
- `TripPlannerController::update()` overwrites `duration_days` from a freely selected range. The planner form has no duration field.
- `TripPlannerController::store()` does not set `duration_days`; the column is nullable. Newly created ordinary planner records can therefore have dates but no persisted duration.

For a saved six-day/five-night draft, selecting a two-night date range currently changes the trip to three days rather than rejecting or preventing the mismatch.

### G. Date boundaries differ between forms

**Verified.** Chat details allow arrivals from today; booking/planner policy requires two calendar days ahead in Asia/Manila. Departure controls allow selecting the arrival day while server validation requires a later day. Undated one-day drafts are allowed, but same-day dated trips are rejected.

The shared room picker in `resources/js/app.js` independently selects a one-night stay after a single date click and accepts unrestricted ranges. It has no itinerary-duration context. It also uses elapsed browser milliseconds for nights; date-only arithmetic is more appropriate for travelers in other timezones.

### H. “Preferences” combines different kinds of inputs

**Verified.** Hardcoded raw slugs mix activity interests, accommodation attributes, traveler suitability, and price positioning. The section is called preferences even though a reply such as “6 days, 5 nights” changes trip basics. The generic “Use these details” action does not explain whether it generates, updates, or searches.

**Not yet proven:** a particular preference was lost in the user's session, a live Gemini error occurred, or history restoration sent the same HTTP request twice. Preserve these as investigation cases, not confirmed bugs.

## 4. Proposed user flow and presentation

Keep the existing Blade/Alpine chat, guided dialogue, session revision checks, services, and planner. No new framework or replacement chatbot pipeline is needed.

### Trip details form

| Field | Proposed behavior and wording |
| --- | --- |
| Destination | Required catalog destination, retaining the existing selection. |
| Travelers | “Number of travelers”; require 1–20 for a priced personalized itinerary. General browsing may remain possible without it, with “Add travelers to calculate your estimate.” Do not silently assume one or two guests for a personalized quote. |
| Duration | Always show “Trip duration” as one editable number of days and a derived number of nights: “6 days · 5 nights.” Never ask for independently editable contradictory values. |
| Dates | “Choose dates” / “Decide later.” Choosing dates reveals a start date and calculated end date. Deciding later clears dates but retains duration, preferences, travelers, and budget. |
| Accommodation | Explicit “Include accommodation” toggle. For a day trip, explain that overnight accommodation is excluded. “I already have a place to stay” must not be confused with a budget that covers activities only. |
| Budget | Optional “Budget in PHP.” Default a newly entered amount visibly to “Whole group · Whole trip,” while preserving known prior meaning. An explicit “No budget limit” clears amount and related constraints throughout the active trip. |
| Budget unit | “Whole group” or “Per traveler.” Show “Whole trip” / “Per night” only where meaningful. Keep legacy per-activity support in activity search; do not silently interpret it as a trip ceiling. |
| Budget coverage | “Accommodation and selected activities,” “Accommodation only,” or “Activities only.” Avoid suggesting flights, meals, or transfers are included unless present in the actual quote. |
| Preferences | Friendly labels, grouped into “Activities you enjoy” and “Stay preferences.” Keep existing supported machine values initially; do not wire every onboarding option into matching without verifying its semantics. Hide stay preferences when accommodation is excluded. |
| Notes | “Other preferences or things to avoid,” with the existing 300-character limit and helpful examples. |
| Submit | “Create my itinerary” initially; “Update itinerary” after one exists. A clarification action should say exactly what it supplies. |

Show a short cost-meaning preview when entering a budget, e.g. “PHP 10,000 per traveler for the whole trip = PHP 20,000 for 2 travelers.” Calculate only after all required multipliers are known. The form must either reject contradictory units or remove the contradictory option; it must not silently reinterpret them.

Preserve user input on validation errors. Add field-specific error associations, focus the first invalid control, and announce derived duration/end-date changes without moving focus. Use keyboard-accessible date input and do not rely only on color or placeholder text.

### One primary itinerary result

Render a brief introduction followed by **one itinerary card** containing:

- Destination, duration, dates or “Dates to be decided,” traveler count, and draft/checked status.
- One accommodation summary and one day list, with expandable catalog details/previews.
- One cost breakdown, with clearly distinguished unavailable, not-yet-calculated, estimate, and checked states.
- A concise weather/availability notice and one primary save/view-plan action.
- An “Edit trip details” action and, if needed, a separate explicit “Show alternatives.”

Do not render full standalone room/activity cards again for the same itinerary choices. Preserve catalog cards for genuine separate room/activity searches. Within an itinerary, “Choose travel dates” updates the trip; individual booking actions should not silently detach services from it.

For the supplied example, changing 3D/2N to 6D/5N should acknowledge “Updated to 6 days and 5 nights,” preserve other inputs, and produce one revised itinerary. It must not rerun an earlier general activities search or ask an already answered budget question.

Keep conversation history, but label/collapse superseded itinerary revisions and leave one current primary action. Do not alter a previously saved plan silently. A materially changed suggestion can offer an explicit update of the owned saved plan or save as a new plan; unchanged acknowledgements should retain the existing suggestion/save identity.

## 5. Duration and calendar contract

Use calendar dates in **Asia/Manila**, represented as `YYYY-MM-DD`, throughout planning. Compute dates using date-only calendar arithmetic, not elapsed local browser hours.

Let `D` = trip days, `N` = trip nights, `S` = start date, `E` = end date:

```text
N = D - 1
E = S + N calendar days
D = calendarDifference(S, E) + 1
N = calendarDifference(S, E)
day[k].date = S + (k - 1) calendar days, for k = 1..D
```

Example: **6 days / 5 nights**, starting **2026-10-26**, ends **2026-10-31**. A range ending October 28 is not valid for that selected duration.

### Validation and state transitions

1. Use integer days 1–30 and derived nights 0–29 for preference/planner trips. Preserve Lucky's separate existing 1–7 nights and 1–4 travelers limits.
2. With dates undecided, both dates must be null; keep a valid duration. Availability/forecast must remain unverified. Never remove a budget merely because dates are unknown.
3. With dates decided, require a start date meeting `TripBookingDatePolicy` and derive the end. Display the end as read-only, or allow only the single valid endpoint if retaining a range calendar. Disabled calendar choices must be backed by server validation.
4. If an API client submits an end date, verify it exactly equals `start + days - 1`. Reject a mismatch with a field-specific 422 response; do not overwrite the selected duration. Continue returning 409 for stale session/plan revisions.
5. A start-date change moves the entire trip while preserving days/nights. An explicit duration change keeps the start and recalculates the end. A notes/title edit must not revalidate unchanged historical dates or trigger rescheduling.
6. Require both dates together for date edits; reject malformed dates, impossible dates, reversed ranges, fractional durations, and missing duration where the active workflow requires it.
7. Use the existing Manila two-day lead policy consistently in chat, planner, calendar, scheduling, and transfer. Refresh minimum arrival when a long-open tab regains focus or crosses Manila midnight.
8. Recommended one-day behavior: **1D/0N is an activity-only day trip with `E = S`**. Exclude rooms and reject nightly-budget units for this case. Verify the scheduling path supports an activity-only day before enabling this dated case; never charge `max(1, nights)` for a zero-night room stay. If overnight-only planning is retained instead, the form must visibly require at least 2D/1N everywhere—do not retain the current hidden contradiction.
9. Trip nights and individual hotel nights are related but distinct. Default a full-trip room stay to `S..E`; preserve valid shorter stays for multi-hotel trips. Every room requires checkout after check-in and must stay within the trip; every activity date must fall inclusively within it.
10. On duration/date changes, rebuild affected choices through the existing revision/transaction flow, invalidate checked route/weather/price evidence, and identify out-of-range activities or hotel stays before scheduling. Do not silently discard a user's choices when shortening the trip.
11. Persist `duration_days` for newly created ordinary planner trips as well as chatbot plans. For legacy dated plans with null duration, derive it safely on read/edit; do not rewrite historical snapshots or booked financial records. If existing values conflict, require explicit resolution before regeneration/transfer.

Add a small shared `TripDurationPolicy` beside `TripBookingDatePolicy` for PHP normalization and consistency checks. Add a small date-only JavaScript helper for the two UIs. Keep the existing lead-date policy responsible for earliest booking dates. This removes repeated contradictory arithmetic without introducing a new planning architecture.

## 6. Implementation scope and sequence

Paths below are relative to `C:/xampp/htdocs/SunnyTripsCapstoneV2`. `[MODIFY]` and `[NEW]` describe future work, not changes made in this review.

### Phase 1 — Lock down the reported conversation and canonical state

- **[MODIFY] `tests/Feature/ChatbotTripDetailsTest.php`, `ChatbotGuidedConversationTest.php`, `ChatbotTripPlanTest.php`**: reproduce the supplied 3-day → 6-day undated edit, with a previous activities task and budget clarification; capture normalized response/state assertions with mocked Gemini.
- **[MODIFY] `app/Services/Chat/DialogueManager.php`**: narrow `prepareDetails()` to the active itinerary and genuinely dependent work; preserve independent search tasks without automatically rerunning them. Align typed and structured undecided/clear-budget handling. Preserve an explicit “no budget” decision across the active scope and do not restore it from an older answered task.
- **[MODIFY] `app/Services/Chat/TripDetailsForm.php`**: build values from the active trip's canonical constraints, not whichever nonempty task field wins. Add accommodation intent and coherent budget validation; retain revision/scope protection.
- **[MODIFY] `app/Http/Controllers/ChatbotController.php`**: coordinate allowed structured fields and its current maximum field count when adding accommodation intent. Keep existing validation and session locking.
- **[MODIFY] `app/Services/Chat/IntentRouter.php` only where needed**: normalize explicitly stated days/nights and typed budget/date updates consistently. Preserve Lucky intent routing and ordinary searches.

Acceptance: one duration edit changes the active itinerary only; repeated clarifications do not generate another full identical draft; explicit clearing remains cleared.

### Phase 2 — Fix draft content, price meaning, and presentation

- **[MODIFY] `app/Services/Chat/ChatbotService.php`**: revise `handleDraftItinerary()` and relevant `handlePlannedTasks()` composition. Produce a concise narrative plus one structured itinerary; keep the selected stay/activities on a duration-only edit when still eligible. Recompute affected estimates rather than randomly selecting a new hotel unless an alternative is explicitly requested or the choice is invalid.
- **[MODIFY] `app/Services/Chat/ChatRetrievalService.php`**: separate preview-card limits from itinerary selection limits. Do not prune valid itinerary choices just because they are not visible as standalone cards. Continue enforcing visibility, exclusions, availability status, and budget constraints on every selected item.
- **[MODIFY] `app/Services/GeminiService.php` only for the necessary candidate/quote boundary**: reuse existing inventory and price functions for a bounded itinerary candidate pool. Determine open-day reasons from eligible candidates, not the three-card UI cap. Keep checked scheduling in `ItineraryService`.
- **[MODIFY] `app/Services/Chat/ChatbotTripPlanService.php`**: validate normalized duration before building/saving a suggestion; preserve identity for unchanged content/clarification and assign a new version for material changes. Use existing session/context metadata and `source_suggestion_uuid`; no new table is assumed. Do not reuse expired availability/price evidence indefinitely.
- **[MODIFY] `resources/js/chat-sections.js` and `resources/views/components/frontend/chat-widget.blade.php`**: render structured itinerary results through one primary path; retain feedback trace/task IDs, distinct genuine multi-intent sections, old history support, and Lucky rendering.
- **[MODIFY] `resources/views/components/frontend/chat-itinerary-summary.blade.php`**: distinguish missing travelers from stale estimates, use explicit price units, and consolidate stay/date notices.
- **[MODIFY] `resources/views/components/frontend/chat-trip-details.blade.php`**: implement the field labels, conditional budget controls, accommodation toggle, retained values, and contextual submit action above.
- **Review `app/Services/Chat/ChatResponseComposer.php`**; modify only if needed to preserve the concise itinerary contract. Do not add another Gemini call merely to format facts already available.

Acceptance: one itinerary block, one stay, one day list, one estimate explanation, one current save/view action. A six-day plan is not limited to three choices merely by presentation configuration.

### Phase 3 — Enforce duration at every planning boundary

- **[NEW] `app/Services/TripDurationPolicy.php`**: shared calendar-day normalization/validation; no database schema change expected.
- **[NEW] `resources/js/trip-duration.js`**: strict date-only arithmetic and duration presentation shared by chat/planner.
- **[MODIFY] `TripDetailsForm.php`, `DialogueManager.php`, `ChatbotService.php`, `ChatbotTripPlanService.php`**: replace conflicting derivations at their existing boundaries with the agreed contract. Explicit chat duration is authoritative; a conflicting natural-language date range should prompt clarification instead of silently rewriting it.
- **[MODIFY] `app/Http/Controllers/User/TripPlannerController.php`**: `store()`, `update()`, `rebuiltChoice()`, and `recordDraft()` validate/persist duration and preserve item intent on rescheduling. Include duration changes in revision/invalidation decisions.
- **[MODIFY] `resources/js/trip-planner.js`, `resources/views/trip-planner/_trip-details.blade.php`**: add duration state/control, compute departure from arrival, preserve it on open/save/clear-dates, show resulting 6D/5N summary, and send coherent values.
- **[MODIFY] `app/Services/TripPlannerService.php`, `app/Services/ItineraryService.php` only at schedule/transfer consistency gates**: reject inconsistent trip state before generating or transferring; keep existing item-specific stay bounds, inventory checks, routes, weather, and transactions.
- **[MODIFY, only if an itinerary-bound room picker remains] `resources/js/app.js` and `resources/views/components/frontend/date-range-picker.blade.php`**: accept optional duration context and synchronize with the itinerary. Keep the default unrestricted catalog-room behavior. Prefer removing the duplicate picker from the itinerary result over making two independent calendars appear authoritative.

Acceptance: a six-day saved draft can only acquire matching five-night travel dates. Chat, planner, checked schedule, print summary, and transferred service dates agree.

### Phase 4 — Regression and outstanding RSC verification

Run the targeted suites below, then the complete project suite before claiming implementation complete. Visually inspect the actual Blade/Alpine form through the configured Brave Playwright MCP when available. Check chat refresh and guest-to-login restoration, mobile form behavior, date picking, and printable documents.

Separately address the journey extension query regression and perform controlled configuration/provider/email verification. Do not enable scheduled customer mail or change payment settings merely as a side effect of fixing the chatbot.

## 7. Impact on working random/Lucky generation

Expected direct impact is low if the changes remain in preference dialogue, presentation, and date validation. **No replacement of `LuckyItineraryService::generate()`, its random selection strategy, defaults, capacity limits, or promotion lifecycle is proposed.**

Shared boundaries still need regression coverage:

- Lucky messages also pass through the widget; retain their dedicated offer/code/copy/accept actions.
- `LuckyOfferService::planFor()` creates planner records; ensure their existing days/nights/dates satisfy the new shared contract and populate duration where necessary.
- A saved Lucky offer's code belongs to its original eligible itinerary. Editing a planner copy must not silently change the original offer, basket group, quoted selection signature, or eligibility. Explain when a changed trip no longer qualifies; do not automatically transfer the code.
- Preserve exact-group, owner, date, traveler, item/quantity, expiry, price revalidation, and redemption checks in `PromotionService` and checkout.
- Do not apply preference-trip 30-day limits/defaults to Lucky or its seven-night limit to ordinary planner trips.

## 8. Testing plan

| Area | Normal and edge cases | Required result |
| --- | --- | --- |
| Supplied conversation | Boracay 3D/2N draft → 6D/5N, dates undecided; prior activities task; no budget, known budget, unknown basis | One current revised itinerary; no unrelated activities rerun; no already-answered preference/budget question |
| Rendering/history | New response, restored sections, legacy messages, genuine two-destination request, superseded and saved revisions | No triple display; genuine distinct requests retained; correct trace IDs and save/view state after refresh |
| Identity/submission | Unchanged resubmit, retry, stale form, two tabs, save twice, material edit after save | Existing locks/revision protections work; same suggestion saves once; edits do not silently mutate a saved trip |
| Travelers/pricing | Missing travelers, 1, room capacity boundary, 20, 0/21/fractional input | Clear missing-input state or validation; correct per-room/per-person totals; no fake stale-price warning |
| Budget | Whole group/person × trip/night; coverage variants; clear amount; no budget; exact cap; insufficient budget; change duration/pax | Consistent conversion and totals; no double multiplication; typed/form parity; no resurrection of cleared budget |
| Preferences | Select/clear all, notes/exclusions cleared, stay disabled, destination change, unsupported values | Intended constraints retained/removed; no hidden accommodation choice or stale selection from another destination |
| Candidate limits | More than three eligible activities, genuinely fewer candidates, exclusions, hidden/unavailable items, budget-limited itinerary | Preview limit does not erase valid itinerary content; open days explain the actual limitation |
| Duration | 1D/0N activity-only, 2D/1N, 6D/5N, 30D/29N; 0/31 days, fractional input, inconsistent stated D/N | Same contract everywhere; no room nights charged for a day trip; invalid combinations rejected |
| Date arithmetic | Month/year boundaries, leap day, invalid Feb 29, reversed dates, same-day overnight trip, mismatched submitted end | Correct calendar arithmetic and field-specific errors; no silent duration replacement |
| Lead policy | Manila today/tomorrow/+2 days, traveler browser in another timezone, tab open past midnight | Same earliest date across form/server/planner and transfer |
| State transitions | Undated → dated → undecided, duration-only edit, start-only edit, title-only edit, legacy null duration | Retained intent, correct derived end, valid persisted duration, no needless historical-date rejection |
| Selected services | Full-trip stay, two shorter hotel stays, activity on first/last day, shorten past an activity, unavailable room after move | Valid subranges retained; invalid choices surfaced; checked prices/routes/weather invalidated before reuse |
| Lucky | Existing random generation, 1/7 nights, pax 1/4, affordable/fallback plan, no campaign, active code, reshuffle, saved planner copy | Existing behavior retained; one offer acceptance; correct code eligibility and expiry |
| Checkout/print | Plan → checked schedule → cart → booking; estimates vs purchased services; changed Lucky group | Dates and duration agree; server prices authoritative; no improper discount transfer; correct reference/status |
| RSC follow-up | Ordinary catalog, activity/package, Lucky, planner, admin guest, mixed windows; extension older than query horizon | All eligible booking sources assessed; extension not omitted; no duplicate or stale reminder |

Extend the existing tests instead of building a parallel harness. Add focused policy/helper tests for calendar math. Update the tests that currently assert stale duration is discarded. Include cases with **both guided conversation and planner enabled**, matching local configuration; several form tests intentionally disable the planner and do not cover that complete path.

Likely test files: the feature suites listed in section 1; `tests/Unit/ChatSections.test.js`, `ChatTripDetails.test.js`, `TripPlannerMap.test.js`; new focused `TripDurationPolicyTest.php` and `TripDuration.test.js` if the shared helpers are introduced. Use existing factories and HTTP/mail fakes; avoid live provider calls in regression tests.

## 9. Completion criteria and remaining uncertainty

Implementation is complete only when the supplied conversation can be replayed without duplicate itinerary representations or unrelated reruns; equivalent typed/form inputs preserve the same intent; missing information is described accurately; and selected duration constrains dates on both client and server through save, schedule, and transfer.

The exact initial budget/preferences and request IDs for the supplied conversation were not provided. Reproduce those surrounding states with targeted fixtures; inspect session/trace data only if needed to distinguish repeated submissions from repeated task execution. No evidence currently justifies a framework rewrite, replacing Gemini, or changing the working Lucky algorithm.

This plan stops before implementation because the user explicitly requested review and planning only.
