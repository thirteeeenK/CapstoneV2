# Chatbot trip details and saved itinerary implementation plan

Status: implemented locally; browser verification remains pending.
Prepared: October 21, 2026.

## 1. Outcome and scope

Replace repeated single-field clarification cards with one compact trip-details card and an optional preferences card. Keep manual chat available throughout. Let users save a generated itinerary with **Add to Trip Plan**, then find it under **Itinerary (From Chatbot)** in the existing Trip Planner.

Users may save an undated draft. Weather availability must not block generating or saving that draft. Saving is a planning action: it does not create a reservation, add items to the basket, send an email, or confirm availability.

This plan builds on the current working tree, including the recent multi-intent fixes. It does not require merging `fixMergeConflicts`. Existing uncommitted work must be preserved. The separate existing `implementation_plan.md` concerns another feature and is not replaced.

## 2. Findings from the current code

| Current behavior | Consequence for this work |
| --- | --- |
| `DialogueManager` already tracks tasks, scopes, slots, revisions and one active question. | Extend this state; do not create a competing frontend conversation state. |
| `ChatbotController` accepts a typed message or a single-question structured answer, checks revisions inside a session lock, and returns a readable answer echo. | Add a distinct validated multi-field submission contract while retaining these protections and the existing answer path. |
| `handleDraftItinerary()` currently returns activity cards and day-by-day prose, not a structured itinerary. | Introduce structured draft data before adding a save button. Do not parse prose to reconstruct a saved plan. |
| The dated chatbot itinerary is a room/activity cost bundle; Trip Planner uses a different route/schedule snapshot. | Add an explicit adapter. Do not pass the chatbot object directly to existing planner views. |
| `trip_plans.start_date` and `end_date` are non-nullable; planner creation and item validation require dates. | Support draft dates at the storage and service layers, not just in the form. |
| Planner JS and Blade call `start_date.slice(...)`; generation, item options, and journey checks dereference date objects. | Audit and update every consumer affected by nullable draft dates. |
| `ItineraryService` evaluates forecast windows and can exclude unsafe weather slots from a dated schedule. | Preserve dated scheduling checks. Saving a draft must bypass scheduling, routing and weather calls. |
| The chatbot has **Add to Trip Basket**, but no planner-save action. | Add a separate planning action and preserve existing basket behavior. |

## 3. User experience

### Card 1: Trip details

Show a form only when relevant information is missing or the user chooses **Edit trip details**. Prefill known values from the selected conversation scope.

Fields, shown only when relevant:

- Destination: existing supported destinations, with manual input still available through chat.
- Travelers: numeric entry and increment/decrement controls.
- Travel dates: exact dates, or **Dates not decided yet** with an optional duration. A day-by-day itinerary needs a duration; general recommendations do not.
- Budget: optional amount plus understandable controls for who it covers, time period, and coverage (whole trip, accommodation, activities). Keep these dimensions separate internally.
- Current request: a short editable summary such as “Hotels and activities”; preserve all detected intents.

Use one primary **Use these details** button. Submit all completed fields together. Do not force a user seeking activity suggestions to choose a hotel or provide hotel dates. A simple text question remains appropriate when only one easy detail is missing; users should not have to open a full form for every correction.

An undecided value is distinct from an omitted field or an explicit clear. Unchanged fields must not overwrite newer context. The existing maximum-duration and traveler limits remain authoritative; show their validation errors inline.

### Card 2: Preferences, optional

Use a collapsed **Preferences (optional)** area or second card for interests, pace, accommodation preferences, exclusions and additional notes. Offer supported choices and free text. Include **Skip preferences**. Avoid introducing preference controls whose values the retrieval layer cannot honor; unsupported wishes need an honest explanation.

The cards are one interaction with one shared submit action, not two mandatory conversational turns. Keep a compact editable summary after submission and retire older active forms.

### Conversation and accessibility

- Typing remains enabled, including while a card is displayed. A typed correction updates the same context and invalidates any stale form revision.
- Preserve the readable user bubble for form submissions, e.g. “Boracay · 2 travelers · 3 days · dates undecided · ₱20,000 for the whole trip.”
- Keep all requested result sections. Available results must not disappear because another intent is missing a detail.
- Retain one server-selected follow-up at most per turn; form labels do not become repeated questions in each result section.
- Use native inputs, labels, fieldsets/legends, keyboard navigation, visible focus and inline errors linked to their fields. Preserve the existing screen-reader-only legend.
- Retain user entries on validation/network failure, focus the first invalid field, and announce save/status messages. Support narrow mobile layouts with adequate tap targets.

## 4. Form and context contract

Extend the chat request with a versioned `trip_details` object, mutually exclusive with `message` and the existing `answer` object. Include a server-issued form ID, dialogue revision and scope identifier. The server supplies an allowed field schema and current values for that form.

Validate fields explicitly; do not simply relax the current `answer|max:8` rule or accept arbitrary nested arrays. Specify limits for preference arrays and notes. Check scope ownership, revision, form ID and allowed keys inside the existing turn lock before persisting anything. Invalid input returns field errors; stale input returns the latest form/context without changing history.

Apply a valid submission atomically to the shared scope and the relevant tasks. Reuse the existing normalization and budget calculations. Do not convert the form to prose and then depend on the language model to recover its values; the readable echo is presentation only. Free-text notes still follow existing moderation and bounded-input handling.

Preserve omitted values; clear only explicitly requested fields. Scope a correction to the selected trip. When multiple destinations are active, require scope selection before applying shared details. Destination changes invalidate dependent selected inventory; date, party-size and budget changes invalidate any outdated calculations.

Continue supporting historical conversations, old single-field controls, typed corrections, guest-to-login restoration, support handoff and the existing guided-conversation flag.

## 5. Structured suggestions and saving

### Structured suggestion

Create a versioned `itinerary_suggestion` payload for both dated and undated chatbot suggestions. Include:

- A stable server-generated suggestion UUID, source message/task reference and scope.
- Destination, title, travelers, duration, nullable dates and relevant normalized preferences/budget dimensions.
- Ordered days with stable day indexes, optional calendar dates, verified inventory references and unscheduled choices.
- Explicit assumptions, price timestamps, estimated costs where actually calculable, and availability/route/weather assessment status.

Persist the authoritative payload in the bot message's `context_data` and return the same identifier through fresh responses and history restoration. Keep the existing `itinerary` response during compatibility rollout so current basket rendering continues working.

Only verified catalog IDs become selectable planner items. Unknown stops remain clearly described suggestions, not fabricated products. Do not invent a room, route time, price, day assignment or forecast to satisfy the snapshot shape. Preserve free days and unscheduled activities explicitly. If a dated chatbot bundle has no day allocation, save its selections as unassigned and offer scheduling later.

### Save interaction

1. Render **Add to Trip Plan** on each eligible itinerary suggestion, including undated drafts.
2. For an authenticated user, send only its source/suggestion reference and an optional edited title to a dedicated save endpoint.
3. The server resolves the persisted suggestion, checks conversation ownership and rechecks inventory identity/destination/visibility. It never trusts client-supplied prices, ownership, or arbitrary item IDs.
4. Create the plan, selection records and draft snapshot in one transaction. A per-user unique source-suggestion key makes retries and concurrent double clicks return the same plan.
5. Return the saved plan ID and a named-route URL. Change the control to **Saved — View Trip Plan** and restore that status after refresh.

Guests may generate suggestions but must sign in to save to an account. Retain the suggestion reference through the existing conversation migration and any onboarding redirect; after returning, offer the save action again. Do not use possession of a source ID alone as authorization. If the conversation can no longer be resolved, explain that and offer regeneration.

Save each suggestion as a new plan initially. Merging into an existing plan is deferred because conflicting dates, destinations and duplicate items require a separate interaction. Regenerated suggestions have new identifiers and can be saved independently. A deleted saved plan can be saved again deliberately; repeated requests against an existing plan do not duplicate it.

If a referenced listing has been removed or hidden, retain its descriptive entry as an unavailable, non-bookable draft choice with a warning; do not silently replace it or import it as an active inventory item. If the destination itself no longer exists, return an actionable validation error with no partial write.

## 6. Persistence and Trip Planner integration

Reuse `TripPlan`, `TripPlanItem` and `TripItinerary`; do not create a second trip-plan system.

Proposed additive migration:

- Allow both plan dates to be null for drafts. Enforce “both present or both absent”; existing manual plan creation continues requiring dates.
- Add plan `source` (existing rows default to `manual`), `status` (existing rows treated as dated plans), optional `duration_days`, a bounded JSON context, and nullable source-suggestion UUID.
- Add a unique `(user_id, source_suggestion_uuid)` constraint for idempotency. Do not attach saved-plan lifetime to deletion of chat history.
- Use explicit draft snapshot version/kind inside `TripItinerary.snapshot`; retain the existing scheduled snapshot shape for existing records. Imported suggestions remain drafts even if dates were supplied until scheduling/validation succeeds.

Inspect the actual PostgreSQL schema and installed framework migration APIs before implementation. Backfill existing rows without changing their itinerary snapshots. A rollback must not fill missing dates with fabricated values; refuse a destructive rollback while undated drafts exist or export/migrate them through a separately reviewed process.

In the existing planner, group saved plans under **Itinerary (From Chatbot)** and the existing/manual category. Show **Draft — dates not selected** or **Draft — schedule not checked**. Avoid losing older saved plans behind the current latest-20 limit: add bounded pagination or a load-more mechanism with ownership filters.

Add a draft-aware detail view supporting review, rename, removal of a choice and setting dates. Preserve the saved suggestion as a snapshot; subsequent edits increment the plan revision. Adding dates does not overwrite the original schedule silently. Offer **Check dates and build schedule**, which revalidates inventory and creates a new scheduled snapshot through the existing engine. If a hotel base is required for routing, ask at that stage; it is not a prerequisite for saving an activities-only draft.

All planner consumers must recognize snapshot kind: lists, map/day keys, details, export/print, email, reminder eligibility, generation and basket transfer. Use day indexes for undated keys and “Dates to be decided” labels. Draft PDF/print and explicitly requested email may use a dedicated simple draft view with advisory text; saving itself sends nothing. Do not calculate automatic travel reminders from null dates.

Drafts cannot transfer directly to the basket through planner endpoints. Require dates, capacity/availability validation and a current scheduled revision first. Preserve the chatbot's existing dated **Add to Trip Basket** flow and all booking/payment rules.

## 7. Weather policy

| Situation | Behavior |
| --- | --- |
| No exact dates | Generate/save the draft without calling forecast or route services. |
| Dates outside available forecast coverage | Save; show that forecast coverage is unavailable for those dates. |
| Forecast provider fails or returns stale/partial data | Saving still succeeds; do not imply favorable conditions. |
| Outdoor/water activity | Show a weather-dependent label based on verified activity metadata; if classification is unknown, use a general advisory rather than inventing it. |
| Reliable forecast available during later scheduling | Keep the current weather checks; offer review and alternatives. Preserve the saved draft choices even when a choice cannot be safely scheduled. |

Suggested advisory: “Some outdoor and water activities are weather-dependent. Check the forecast and operator updates closer to your travel date.”

Saving does not certify an activity as safe or operating. Do not apply today's weather to an undated trip. Do not automatically remove saved choices or promise background monitoring. Automated weather reminders are outside this implementation.

## 8. File-level work and delivery order

No deletion of existing files is planned. Proposed new PHP classes/tests should be created with the project's Artisan commands during implementation.

| Phase | Files | Work |
| --- | --- | --- |
| 1: Context contract | [MODIFY] `app/Services/Chat/DialogueManager.php`, `IntentRouter.php`, `ChatbotService.php`, `ConversationManager.php`; `app/Http/Controllers/ChatbotController.php` | Multi-field form schema, typed/form normalization parity, scope/revision checks, readable echo and history. |
| 2: Cards | [MODIFY] `resources/views/components/frontend/chat-widget.blade.php`, `resources/js/chat-sections.js`; [NEW] `resources/views/components/frontend/chat-trip-details.blade.php` | Consolidated details/preferences, inline validation, manual chat synchronization and accessibility. Reuse existing visual tokens/components. |
| 3: Suggestion contract | [MODIFY] `app/Services/Chat/ChatbotService.php`, `ConversationManager.php`, `app/Services/GeminiService.php`, `app/Services/SystemPrompts/chatbot-system-prompt.md` | Structured drafts and adapters for existing dated output, grounded inventory, stable persisted IDs, weather advisory. |
| 4: Storage/save | [NEW] migration under `database/migrations/`, `app/Services/Chat/ChatbotTripPlanService.php`, `app/Http/Controllers/User/ChatbotTripPlanController.php`; [MODIFY] `app/Models/TripPlan.php`, `routes/tripPlannerRoute.php`, `app/Services/TripPlannerService.php` | Draft persistence, server-owned import, transaction, idempotency, authorization and validation. Model/factory updates follow actual new fields. |
| 5: Planner | [MODIFY] `app/Http/Controllers/User/TripPlannerController.php`, `resources/js/trip-planner.js`, `resources/views/trip-planner/index.blade.php`; [NEW] `resources/views/trip-planner/_chatbot-draft.blade.php`, `resources/views/trip-planner/draft-document.blade.php` | Saved-plan grouping, pagination, draft view/edit/date transition, save/view controls and draft export. |
| 6: Consumer guards | [MODIFY] `app/Services/ItineraryService.php`, `TripJourneyService.php`, `app/Notifications/TripItineraryReady.php`, `resources/views/trip-planner/document.blade.php` and verified consumers found during implementation | Draft/scheduled dispatch, null-date guards, revision checks, email/print compatibility and existing weather behavior. |
| 7: Coverage | [MODIFY] existing chatbot guided/multi-intent/itinerary, planner, journey, cart, history and JS tests plus guided eval fixtures; [NEW] `tests/Feature/ChatbotTripDetailsTest.php`, `tests/Feature/ChatbotTripPlanTest.php` | End-to-end contracts and focused regression coverage below. |

Complete and test each phase before moving on. Do not expose the save button until persistence, draft rendering and authorization work together. No new package, provider, weather subscription or environment variable is expected for the initial implementation.

## 9. Acceptance and regression tests

### Consolidated input

- A multi-intent query prefills the card and retains both hotel/activity results after one submission.
- A complete typed request generates results directly; controls remain optional.
- Typing a correction while a form is open makes the old submission stale without mutating history.
- Multi-field validation reports errors without partial state changes; manual and structured answers normalize identically.
- Per-person budget and per-night period remain independent; explicit corrections, shared budgets and exclusions retain the recent fixes.
- Multiple trip scopes do not leak preferences or budget values into one another.
- Unknown dates and optional preferences do not cause repeated questions or block a valid draft.
- Browser keyboard/mobile checks cover labels, focus, errors, restored forms and readable user-message echo.

### Save and planner

- Dated and undated suggestions save, survive refresh and appear in **Itinerary (From Chatbot)** with matching days, selections and caveats.
- Activities-only drafts save without inventing a hotel; missing inventory is visibly unavailable and non-bookable.
- Double click, concurrent retry and history-restored save return one plan for the same suggestion.
- Guests, another account, forged source IDs, tampered payloads and unsupported snapshot versions cannot import unauthorized data.
- Guest-to-login/onboarding return retains a usable authorized suggestion reference.
- Save is atomic; failures create no partial plan, items, basket entries or notifications.
- Loading more saved plans retains account isolation and makes imported plans beyond the newest 20 accessible.
- Editing dates invalidates old schedule calculations; generation/transfer respects revisions, date policy and availability.
- Existing scheduled plans, map rendering, PDF/email, reminders, basket/checkout and booking snapshots remain compatible.

### Weather and execution

- Draft generation/save succeeds with the weather provider disabled and asserts no forecast/routing request is made.
- Missing, expired, partial or out-of-range forecasts produce honest advisory states.
- Known unsafe dated windows still follow existing scheduling rules; draft choices remain visible for revision.
- Run affected Pest tests, existing JS tests, production asset build, Blade compilation, Pint and `git diff --check` after implementation.
- Run the guided evaluation harness against controlled fixtures; preserve the one-question and intent/card coverage gates.
- Perform browser E2E through the required local Playwright MCP with Brave when available. Report browser coverage as unverified if that tool is unavailable; do not substitute another browser tool.
- Ask for the full application test run after focused checks pass. Automated fixtures do not establish live provider quality.

## 10. Completion criteria and deferred features

Complete when users can provide context in one consolidated submission or through typing, receive all requested result categories, save an undated or dated suggestion reliably, reopen it in the correct planner group, and later supply dates without bypassing booking checks. Weather must not be a save prerequisite.

Deferred: merging suggestions into an existing plan, automatic weather alerts, collaborative/public sharing, calendar synchronization, automatic reservations and a broad Trip Planner redesign. Implementation approval covers the proposed application work, not deployment or production migration execution.

## 11. Implementation and verification record

- Implemented a combined details form with optional preferences and manual chat retained. Existing single-question API submissions remain supported. Form IDs, revisions and destination scopes are checked under the existing conversation lock; typed changes retire stale forms.
- Added structured dated/undated suggestion payloads, persisted suggestion references, account-owned save/status endpoints, transactional imports and duplicate-save protection. Imported suggestions appear in the existing saved-trip selector under **Itinerary (From Chatbot)**.
- Added nullable draft dates and explicit source/status/context fields through `2026_10_21_173532_add_chatbot_drafts_to_trip_plans.php`. Applied only this migration to the local development database. No production migration or deployment was performed.
- Added draft review, rename, choice removal, date/traveler edits, PDF/print and explicit email support. Date validation and room availability failures roll back edits. Users can later build a checked schedule through the existing engine; a draft cannot transfer directly to the basket.
- Forecast and routing calls are unnecessary for saving. Explicit activity environment metadata supplies weather-dependent labels; unknown classifications use the general advisory. Existing dated scheduling checks remain active.
- Added activity-only budget coverage and kept budget basis separate from period. Draft exclusions remain consistent with displayed cards, including after users clear preference notes.
- An unspecified traveler count uses the planner's existing default of two, with an explicit assumption notice. Confirming the count clears that notice. Imported day allocations remain suggestions; unassigned choices and open days are retained.
- Existing saved chatbot responses generated before structured suggestion IDs were introduced need to be regenerated before they offer the new save action.

Verification:

- Broad affected PHP run: **615 tests passed, 2,988 assertions**, including chatbot, handoff, planner, journey, itinerary-basket and guided evaluation coverage.
- After the final input-normalization and traveler-assumption follow-ups: **34 focused feature tests passed, 164 assertions**. These overlap the broader run and should not be added to its total.
- JavaScript: **44 tests passed**, covering existing maps/rendering plus form submission, restored save references, guest sign-in retention, concurrent save clicks and retry behavior.
- Production asset build, Blade compilation, route inspection, Pint and whitespace checks passed. The build retains the existing large-chunk advisory for the ApexCharts bundle.
- Live browser E2E remains unverified because the required local Playwright MCP/Brave tool is unavailable in this session. Full application test execution remains a follow-up: `php artisan test --compact`.
- No new dependencies, environment variables, commits, pushes or deployment were required.
