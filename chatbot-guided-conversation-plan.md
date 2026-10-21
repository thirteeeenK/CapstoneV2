# SunnyTrips guided conversation implementation plan

Prepared: October 21, 2026  
Status: implemented locally after approval. The result-retrieval correction is active; guided conversation remains behind an opt-in flag pending live browser validation. See section 14.

## 1. Goal and scope

Extend the existing conversational RAG chatbot so it gathers missing travel details naturally, one focused question at a time. Users may answer briefly, supply several details at once, correct earlier answers, skip optional questions, or ask something else.

This is commonly called slot filling or dialogue management. The application tracks known details and chooses the next useful question; retrieval still supplies travel facts. A prompt alone cannot reliably maintain this behavior.

Build on the current `chatbotImprovement` implementation and the existing `chatbot-conversational-rag-plan.md`. No additional merge from `fixMergeConflicts` is required for this feature. Preserve that earlier plan and the unrelated root implementation plans.

The first release covers:

- Guided trip planning, hotel/room recommendations, activities, and availability.
- English and Taglish replies, including short contextual answers.
- Shared questions across compatible tasks, with separate details for different trips or destinations.
- Corrections, optional skips, cancellation of a planning request, temporary topic changes, refresh, and guest-to-login continuity.
- Existing capability limits, grounding, pricing, support handoff, and session protection.

No new provider, framework, database table, autonomous booking, payment, email sending, or background monitoring is proposed.

## 2. Verified starting point

Static inspection of the current checkout found:

| Existing component | Current behavior | Needed extension |
|---|---|---|
| `ConversationPlanner::plan()` | Splits tasks, parses constraints, resolves references, and resumes selected clarification reasons. | Combine answers to a pending question with newly requested tasks before deciding what to execute. |
| Pending continuation | Dates resume the first matching pending task; destination and budget-basis continuation have paths gated by an empty new-task list. | Resume all compatible tasks even when the user adds another request in the same message. |
| Task identity | IDs such as `task-1` are assigned again each turn. | Persist stable task identity independently of display position. |
| `ConversationManager::rememberTaskState()` | Stores `conversation_v2` groups and pending tasks; pending keys use intent plus destination. | Keep distinct same-category tasks, one active question, and explicit slot values/provenance. |
| `ChatbotService::handlePlannedTasks()` | Each task can independently return a missing-destination, dates, budget-basis, or allocation question. | Select one shared question and retain every task's unresolved status. |
| `IntentRouter::extractConstraints()` | Handles numeric pax, dates, duration, and budgets. It can derive nights from days. | Interpret short answers using question context; distinguish explicit values from assumptions and derived values. |
| `ChatResponseComposer` | Composes grounded task explanations and forbids a separate generated question per block. | Keep the next question server-owned and append it once after useful results. |
| `chat-sections.js` and widget | Render task sections and restore context from history. | Render one clarification consistently for both fresh responses and restored history. |
| `ChatbotController` | Holds a per-session turn lock and returns stored history. | Preserve that boundary while persisting dialogue state and rejecting stale structured answers. |

The earlier multi-intent foundation is already present. This plan extends it rather than replacing its retrieval, cards, and handlers. `.ai/rules` was absent during this inspection.

## 3. Conversation rules

### Ask only when the answer matters

Do not turn every query into a questionnaire. “Recommend extreme activities in Boracay” can receive recommendations immediately. “Is this room available?” needs dates before a dated availability claim.

Ask one focused question per bot turn. A natural pair such as check-in and check-out counts as one question. If the user supplies destination, dates, travelers, and budget together, accept all valid fields and skip those questions.

Prioritize:

1. Resolve ambiguity about what task or trip the user means.
2. Ask a shared missing detail that unlocks the most pending tasks.
3. Ask the remaining detail required for the requested operation.
4. Offer an optional refinement only if it improves the result; do not delay an otherwise useful answer.

Destination usually comes first when absent, but there is no universal destination → dates → travelers → budget script.

### Required versus optional details

| Request | Minimum for a useful response | Ask only when relevant |
|---|---|---|
| Hotel discovery | Destination or an unambiguous named property | Budget, amenities, dates, and pax refine recommendations. |
| Room fit or priced stay | Property/destination plus occupancy for capacity claims | Dates and nights for dated totals; distinguish catalog base price from a stay quote. |
| Activities | Destination or a named activity | Pax for group totals, dates for supported availability checks, preferences for ranking. |
| Simple itinerary | Destination and duration for a day-by-day draft | Exact dates optional for an undated draft; travelers/budget required only for claims involving capacity or group cost. |
| Budget-constrained itinerary | Destination, duration, travelers, budget amount and basis | Confirm coverage, such as stays and activities versus transfers; never imply an all-inclusive total without evidence. |
| Room availability | Valid check-in/check-out and a room or search scope | Occupancy if required for the requested fit/search; never manufacture availability from a recommendation. |
| Weather, map, package details, FAQ | Existing handler requirements | Do not require unrelated trip-planning fields. |
| Unsupported action | Enough to recognize the requested operation | Explain the limit immediately; do not collect booking/payment details for an operation the bot cannot perform. |

Validate the exact handler requirements during implementation. A required field is tied to a specific operation or claim, not simply a convenient form field.

### Preserve every intent

“Hotel and extreme activities” creates two tasks. If both lack a destination, ask once. After the destination arrives, show available discovery results; if the user requested room availability too, ask for dates while retaining the activity answer.

Sections may state “Waiting for your stay dates,” but only the shared clarification should ask the question. Missing catalog data, an unsupported operation, and a provider outage are not missing user details.

## 4. Example interactions

**Guided itinerary**

> User: Help me plan a trip.  
> Bot: Saan mo gustong pumunta?  
> User: Boracay, dalawa kami.  
> Bot: Ilang araw ang trip ninyo?  
> User: Three days.  
> Bot: Here is a draft three-day plan based on the available activities. I have not checked dated room availability. [Grounded suggestions.]

The bot may offer budget refinement after delivering the draft. If the user originally requested a plan within a budget, gather the required budget details before presenting a cost-constrained plan.

**Short answer plus a new request**

> Bot: What are your check-in and check-out dates?  
> User: November 12–15, 2026. Also find airport transfers.

Resume the pending availability task and create a transfer task. Check which trip the dates belong to if more than one is unresolved.

**Uncertain dates**

> Bot: What are your stay dates?  
> User: Di pa sure.

Save dates as undecided, explain that availability cannot yet be verified, and offer general recommendations. Do not ask the same date question again immediately.

**Correction**

> User: Actually apat kami, same budget.

Update travelers to four, preserve the budget's established amount and basis, and recalculate affected capacity/cost results. If the budget was per person, preserve that meaning; do not silently reinterpret it as a group total.

**Unsupported action**

> User: Book it for us.

Explain that the chatbot cannot complete the booking and point to the supported booking flow. Do not start a reservation questionnaire.

## 5. Proposed architecture

### A. One dialogue coordinator

Add `app/Services/Chat/DialogueManager.php` as the focused coordinator for slot updates, task readiness, and next-question selection.

Keep responsibilities distinct:

- `IntentRouter`: reusable extraction and normalization of explicit language.
- `ConversationPlanner`: identify tasks, task scope, references, and new requests.
- `DialogueManager`: interpret answers in context, merge valid slot updates, determine readiness, choose one question.
- `ConversationManager`: read and persist versioned dialogue state.
- `ChatbotService`: enforce gates, orchestrate execution, and finalize the turn.
- Existing retrieval/composer/pricing services: retrieve evidence, calculate supported quotes, and produce grounded results.

Start with one new service and typed array contracts matching the existing project. Add another abstraction only if implementation reveals a separate responsibility that needs it.

### B. Turn processing order

1. Acquire the existing session lock and apply existing moderation, authentication, rate-limit, and human-support rules.
2. Load and validate the current dialogue state.
3. Inspect the whole message for explicit corrections, new requests, topic changes, cancellation, and answers to the active question.
4. Extract every explicit detail; use the active question to interpret genuinely ambiguous short replies.
5. Merge validated values into the correct trip/task scope and invalidate dependent selections or results as needed.
6. Evaluate required fields across all active tasks; retrieve only tasks ready for the requested operation.
7. Retain all task outcomes, choose one next question, and compose useful results plus that question.
8. Persist the response and resulting state once under the session lock, then release the lock.

Integrate with the existing planner before legacy follow-up early returns. The guided path must also handle a single incomplete task; it cannot depend on multi-intent interception.

Legacy handlers may initially return missing-field outcomes. Translate those into dialogue requirements before response assembly instead of letting each handler print another question. There must be one owner for selecting the next question.

### C. Stored state

Add a versioned `dialogue` block inside the existing `conversation_v2` metadata, preserving current groups and legacy readers. Use a separate schema version for this additive block.

Proposed fields:

| Field | Purpose |
|---|---|
| `version`, `revision` | Validate schema and order state transitions. |
| `trip_scopes` | Separate shared details for distinct trips or destination contexts. |
| `tasks` | Stable task IDs, trip scope, intent, original request, task-specific overrides, and status. |
| `slots` | Normalized value, status, source turn, provenance, and applicable scope. |
| `active_question` | Question ID, slot or date pair, task IDs, text, required/optional status, and attempts. |
| `suspended_tasks` | Bounded references for requests temporarily interrupted by a topic change. |
| `updated_at` | Support freshness checks without pretending stored travel facts remain current. |

Slot status is explicit: `unknown`, `provided`, `undecided`, `skipped`, or `invalid`. Absence of a budget is different from “no budget preference.” An invalid replacement must not become an active search constraint.

Task lifecycle: `collecting` → `ready` → `answered`, with `suspended` and `cancelled` transitions. Provider failures remain retrieval outcomes and may be retried; they must not reopen unrelated slot questions.

Start with the existing bound of eight retained pending tasks and twelve result groups. Keep at most one active question and two suspended trip scopes. If a limit is reached, acknowledge deferred work rather than silently dropping a request.

Stable IDs must distinguish two room searches at the same destination with different dates. Existing intent/destination keys are insufficient for that case. Per-turn display IDs may remain for compatibility, but must map to persistent task IDs.

Legacy pending state should be normalized lazily when read. New dialogue state becomes authoritative for guided tasks; legacy pending entries become a compatibility projection, not an independent queue that can revive completed questions. Unknown or malformed state falls back safely without exposing another session's context.

### D. Slot precedence and scope

Use this precedence:

1. Explicit corrections or values in the current turn.
2. Previously explicit values for the same task.
3. Explicit shared values for the same trip.
4. Applicable profile preferences as suggestions or disclosed defaults.

Inferred values must be identified as such and must not satisfy a requirement for verified dates, occupancy, or money. Profile destination is not proof of the destination for a new trip.

Store budget amount, currency, basis, scope, and ceiling/target separately. A budget shared by hotels and activities must not be applied in full to each category. Reuse the existing allocation clarification unless the user requests a supported combined itinerary.

Keep duration days, stay nights, and calendar dates distinct. “Three days” can support a draft itinerary; it does not establish exact stay dates or necessarily prove two paid hotel nights. Existing derived nights must be labelled as an assumption when used and confirmed before a precise stay quote.

On destination change, clear incompatible entity selections and location-specific evidence. On dates/pax/budget changes, revalidate affected availability, capacity, and price results. Preserve unrelated preferences only when their scope remains compatible.

### E. Language and validation

Reuse current extraction first, then add contextual normalization:

- “Dalawa,” “apat kami,” “two,” and “2” can answer a traveler question.
- “15k” can answer a budget-amount question; its basis still needs to be known.
- “Per night,” “bawat tao,” and “total naming lahat” resolve budget basis and scope.
- “Kahit ano,” “wala pa,” “skip,” and “not sure” are interpreted according to the active question.
- “2” without an active question or clear scope must not be guessed as travelers, days, or an ordinal.
- “Two adults and two children” must not silently become two travelers. Preserve breakdown only where supported and clarify total/occupancy requirements as needed.

Validate destination against supported records, traveler counts as positive integers within existing application constraints, and date order against the application's timezone and booking rules. Ambiguous numeric dates or a missing year near a year boundary require clarification when competing interpretations matter.

Do not add a model call for every slot. If semantic extraction is needed, reuse the existing provider path with a strict schema and source spans from the user's message. The server validates proposed values, allowed fields, scope, and IDs. A model cannot invent missing answers, set capabilities, or assert action completion.

## 6. Question lifecycle and interruptions

Use deterministic question templates with English/Taglish variants. Templates identify the missing field and why it is needed only when useful. Retrieval evidence and model output cannot override the active question.

Deduplicate questions by field and compatible scope. Two independent trips with unknown destinations should be labelled clearly rather than incorrectly sharing one destination.

After one invalid answer, clarify with an example. After a second invalid/undecided response, explain the limitation and offer a useful fallback or pause the task. Do not trap the user in repeated prompts. Optional skips remain skipped until the user supplies a value or explicitly asks to refine.

“Never mind” cancels the active planning request when unambiguous. “Cancel my booking” remains a booking action subject to capability checks. Cancellation of chat planning never cancels a reservation.

A clear new question is answered as a new task. Suspend the earlier question; do not append it to every unrelated answer. “Continue planning” can resume a single suspended task, or ask which trip when several exist.

Support-agent control retains priority. Suspend guided prompts during active handoff. After handoff ends, resume only when the user returns to the planning request, revalidating stale selections.

## 7. Response and widget contract

Add a nullable top-level `clarification` payload:

`{id, task_ids, field, text, required, options}`

Date ranges may use a `fields` pair instead of a single `field`. Options, when supplied, are server-owned `{label, value}` entries; they are never executable instructions.

Keep `sections` and existing card payloads. Ready tasks render results; blocked tasks retain a short status. Append the shared question once after sections, including when there are no result cards.

Store clarification in message `context_data` so history restores the same question. The plain `reply` must still contain the question once for clients that do not render structured sections. Section-aware rendering must avoid repeating it.

Reuse existing suggested-action controls for a small number of useful answers such as “Dates undecided” or “No budget preference.” Free text remains available. Disable obsolete question options on older messages, and validate question ID/revision server-side if structured answers are submitted.

For an outdated option click, do not mutate the new task with an old answer. Return the current question or explain that the conversation has moved on. Do not consume an extra chat message for a client-only disabled control.

Do not alter guest quotas in this feature. Clarification turns must follow the existing limits; reducing unnecessary questions is particularly important for guests.

## 8. Planned file map

| Change | File | Planned responsibility |
|---|---|---|
| NEW | `app/Services/Chat/DialogueManager.php` | Slot updates, required-field policy, readiness, question selection, interruptions. |
| MODIFY | `app/Services/Chat/ConversationPlanner.php` | Stable task mapping and whole-turn continuation plus new requests. |
| MODIFY | `app/Services/Chat/ConversationManager.php` | Additive dialogue persistence, legacy normalization, bounded state. |
| MODIFY | `app/Services/Chat/IntentRouter.php` | Contextual parsing helpers and explicit versus derived duration handling. |
| MODIFY | `app/Services/Chat/ChatbotService.php` | Guided single/multi-task flow, normalize missing-field outcomes, finalize once. |
| MODIFY | `app/Services/Chat/ChatResponseComposer.php` | Preserve one server-selected question without losing task coverage. |
| MODIFY | `app/Services/SystemPrompts/chatbot-system-prompt.md` | Missing-detail, correction, scope, and uncertainty rules. |
| MODIFY | `app/Http/Controllers/ChatbotController.php` | Additive clarification input/output validation and history compatibility where needed. |
| MODIFY | `resources/js/chat-sections.js` | Render shared question once for fresh/restored section messages. |
| MODIFY | `resources/views/components/frontend/chat-widget.blade.php` | Preserve clarification payload, reuse controls, disable stale options. |
| MODIFY | `config/services.php`, `.env.example` | `CHATBOT_GUIDED_CONVERSATION_ENABLED`, initially off during validation. |
| NEW | `tests/Feature/ChatbotGuidedConversationTest.php` | Public multi-turn behavior and state transitions. |
| MODIFY | `tests/Feature/ChatbotConversationPlannerTest.php`, `tests/Feature/ChatbotMultiIntentConversationTest.php` | Continuation, scope, multiple same-type tasks, regression coverage. |
| MODIFY | `tests/Feature/IntentRouterTest.php`, `tests/Unit/ChatSections.test.js` | Contextual parsing and question/history rendering. |
| MODIFY | `tests/Feature/HandoffTest.php` | Suspend/resume boundary if existing cases do not cover it. |
| MODIFY | `app/Console/Commands/ChatbotEvalCommand.php`, `tests/Support/ChatEvalMetrics.php`, `tests/Feature/ChatbotEvalHarnessTest.php` | Multi-turn execution and dialogue metrics. |
| NEW | `tests/Fixtures/chatbot_guided_eval_v1.json` | Curated conversation sequences alongside existing single-turn fixtures. |

`GeminiService.php` is a conditional target only if semantic slot extraction requires extending its existing structured planning contract. `RetrievalOutcome.php` should change only if existing statuses cannot represent the behavior; prefer specific reason codes over duplicate status systems.

No files are planned for deletion. Confirm package versions and applicable framework documentation before implementation; create new PHP classes/tests with the repository's Artisan conventions.

## 9. Implementation phases

### Phase 0 — Correct missing multi-intent results

Address the confirmed budget/retrieval issue described in section 13 before adding guided conversation behavior. Keep this fix independently testable so failures in the existing pipeline are not hidden by the new dialogue flow.

Gate: the reported combined request returns both categories when matching catalog records exist, with an explicit distinction between preliminary previews and verified budget matches. Hard spending limits and missing essential details retain their safeguards.

### Phase 1 — State and question policy

Introduce versioned state, persistent task IDs, readiness rules, and deterministic question selection behind the flag. Add lazy normalization for legacy pending tasks.

Gate: a single missing destination creates one question; multiple compatible tasks share it; different scopes remain separate.

### Phase 2 — Multi-turn understanding

Add contextual short replies, explicit corrections, several answers in one turn, skip/undecided handling, and question-loop prevention. Resume pending tasks together with new requests.

Gate: itinerary and availability journeys complete without repeating supplied fields or corrupting scope.

### Phase 3 — Retrieval and response integration

Execute ready tasks, translate handler clarifications into shared requirements, preserve partial answers, and invalidate only affected evidence after corrections.

Gate: no false availability, invented budgets, lost intents, or action-completion claims; existing simple questions still answer directly.

### Phase 4 — Widget and history

Render one question, wire validated options through the existing send flow, restore history, and handle stale controls and overlapping turns.

Gate: fresh and restored views agree; text input and existing cards remain usable on mobile and desktop.

### Phase 5 — Evaluation and rollout

Run focused regressions and a reviewed live-language conversation set. Compare turns-to-answer and unnecessary-question rates with the current behavior. Enable the flag only after required checks pass.

Rollback: disabling the guided flag restores the existing conversational path. Preserve stored state, but ignore incompatible guided state while disabled. Re-enabling must validate its schema and revision.

## 10. Acceptance scenarios

| Scenario | Required result |
|---|---|
| “Help me plan a trip” | Ask destination; retain the itinerary task. |
| Reply “Boracay, dalawa kami, three days” | Record all three details; no repeat questions for those fields. |
| Complete constrained request in one turn | Retrieve immediately when all required details are valid. |
| “Extreme activities in Boracay” | Answer without forcing dates, travelers, or budget. |
| Hotel + activities without destination | One destination question covering both tasks. |
| Destination answer plus “also transfers” | Resume both original tasks and account for transfers. |
| Activities ready, room availability missing dates | Show activities and ask one date-range question. |
| “Dalawa” after traveler question | Two travelers; no unrelated intent or ordinal. |
| “2” with no question or clear scope | Clarify meaning; no silent slot assignment. |
| Same intent/destination, separate stay periods | Preserve both tasks and bind answers to the intended period. |
| Two adults and two children | Correct total or explicit clarification; never assume two guests. |
| “Three days” | Draft duration recorded; exact dates remain unknown; hotel-night assumption labelled. |
| Reversed/invalid/past stay dates | Targeted validation response; no availability lookup using invalid dates. |
| “₱15k” then “for all of us, whole trip” | Keep one group-total budget; do not allocate ₱15k separately to each category. |
| “Actually four, same budget” | Update relevant occupancy/cost results while preserving budget basis. |
| “Actually El Nido” | Clear incompatible Boracay selections and refresh affected tasks. |
| “Not sure” for dates | Offer undated discovery; no repeated immediate date question or availability claim. |
| “Skip” for optional budget | Continue without a price constraint. |
| “Skip” for required availability dates | Explain limitation and offer discovery; no false availability. |
| Unrelated FAQ mid-planning | Answer FAQ, suspend planning question, resume only when appropriate. |
| “Never mind” versus “cancel my booking” | Cancel planning only for the former; capability handling for the latter. |
| Repeated invalid answer | Bounded recovery, helpful example/fallback, no endless loop. |
| Unsupported booking/email request | Immediate limitation; no unnecessary personal/payment collection. |
| Gemini/Groq unavailable | Deterministic slot handling/questions and existing retrieval fallbacks remain useful. |
| Injected text proposes slot values or action success | Server validates source and ignores unauthorized instructions. |
| Refresh or guest-to-login continuation | Correct state and question restored with existing ownership rules. |
| Stale option or simultaneous submissions | No overwritten scope or duplicate state transition. |
| Human support active | No competing guided question or AI retrieval. |
| Legacy session or guided flag disabled | Existing behavior remains usable. |

## 11. Verification and success criteria

Use Pest feature tests through the existing chat endpoint, factories, HTTP fakes, and blocked unexpected network calls. Assert responses, task coverage, persisted scope, and absence of unintended side effects. Freeze time for date tests. Prefer behavioral cases over tests that duplicate internal implementation.

Add focused JavaScript cases for one question after mixed sections, question-only messages, restored history, stale options, and legacy messages. Run PHP formatting for changed PHP files, Blade compilation, and the Vite build for widget changes.

Run the narrowest affected tests first, then relevant chatbot, grounding, pricing, guest/session, and handoff regressions. Confirm the PostgreSQL test environment before running database-backed tests. After affected tests pass, request the full application suite with `php artisan test --compact`.

Browser verification must use the configured local Playwright MCP with Brave. If that tool is unavailable, record the unverified browser work explicitly; do not substitute a different browser tool.

Extend offline evaluation to execute ordered turns in isolated sessions. Measure:

- Required-slot accuracy and correct task scope.
- Repeated questions for already supplied or skipped details.
- Clarification loops and turns until the first useful answer.
- Intent/task retention during slot answers and interruptions.
- Retention of ready-task results when other tasks need clarification.
- Model calls and latency per turn, reported separately for mocked and live runs.

Deterministic acceptance gates: every requested task accounted for, at most one clarification per response, no unnecessary required fields in the curated discovery cases, no repeated valid-slot questions, no cross-session leakage, and no unsupported completion/availability claims. A live reviewed English/Taglish sample must assess naturalness and ambiguity handling separately; offline passes do not establish real-world accuracy.

## 12. Planning outcome

Recommended first implementation: deterministic dialogue state and question policy, then contextual extraction, then UI/evaluation. This makes the conversation behavior inspectable and usable even during model outages.

During the planning review, this document was the only retained change. Temporary investigation code/tests were reverted at the user's request. The user subsequently approved implementation with “proceed”; the resulting changes and verification are recorded in section 14.

## 13. Confirmed defect: budget clarification suppresses hotel results

### Reported behavior and baseline

User message: **“activities and hotel for 2 pax 10000 budget”**.

The response displays activity cards for Boracay, then a hotel section asking whether the budget is per night, per person, or for the whole part of the trip. The hotel section has no cards.

Inspected baseline: commit `705db4a` (`fix: conversational chatbot with strict retrieval text to card ratio`), following `ccca2e3`. The committed code already contains the previous multi-intent implementation. No merge is needed to investigate or fix this defect.

The message does not explicitly name a destination. Reproduction therefore needs an established Boracay conversation context or an applicable user preference. A fresh guest session with no destination should still ask for one.

### Root cause

1. `ConversationPlanner::plan()` splits the message into activity and hotel tasks. The amount is extracted from the hotel clause; the activity clause has no budget. The text does not establish whether the amount applies to one category or to the combined trip.
2. The hotel task has `max_price = 10000` but `budget_basis = unknown`. `ChatbotService::handlePlannedTasks()` returns `missing_budget_basis` **before dispatching hotel retrieval**. Consequently the hotel section has no `retrieved_hotels` data for the widget to display.
3. The activity task continues to retrieval, producing the uneven result the user reported. The missing hotel cards originate in backend control flow, not a demonstrated widget-rendering failure.
4. Existing regression coverage accepts clarification-only sections. It did not require preliminary hotel results for an ambiguous target budget, so the existing tests passed despite this behavior.

A related reproducible context gap: after a hotel-only search establishes Boracay, a new activity task does not necessarily inherit that destination. Current group inheritance is category-specific. Default destination resolution also occurs inside execution while section constraints can retain the earlier unresolved value, making saved context and headings inconsistent with retrieved results.

### Proposed correction

- Separate **catalog discovery** from **verification that a budget is satisfied**. An unspecified target budget should not automatically prevent discovery of otherwise eligible hotels or activities.
- For a target amount with an unknown price basis, retrieve preliminary cards using valid destination, visibility, and other applicable constraints. Preserve the original amount in dialogue state, but do not apply it as an invented nightly, per-person, or total limit.
- Label these results clearly: “These are catalog previews. I still need to clarify your budget before checking the trip total.” Keep a structured indication such as `budget_unverified` and retain the clarification outcome. The composer must not replace this notice with a claim that the options fit the budget.
- Ask one shared question about **scope and basis**: whether ₱10,000 covers hotels and activities together or a particular category. If it covers the trip, gather duration and cost coverage before computing a supported total. If it is per night, apply it to the stay task, not automatically to activities.
- Preserve the difference between a target and an explicit ceiling. “Under ₱10,000” must not silently produce unfiltered recommendations claimed to satisfy that limit. Clarify an unknown basis before presenting verified budget matches; any separately offered alternatives must be clearly identified and must not weaken the ceiling.
- An explicit combined total remains a shared budget. Do not assign the full amount to every category. Integrate with the existing allocation/itinerary handling, rather than removing its checks just to force cards to appear.
- If discovery finds no records, preserve `no_match`; if retrieval fails, preserve the failure outcome. Do not disguise either as a budget question or invent placeholder cards.
- Reuse a conversation destination across categories only when the relevant context identifies one unambiguous destination. Multiple prior destinations require scoped resolution or clarification. Current explicit locations override history; unsupported explicit locations must not fall back silently to an older destination.
- Persist the resolved destination with the section/task so card data, headings, pending clarification, and history agree. Avoid inheriting unrelated stale hotel/activity IDs.

This approach lets the user see useful options while the guided flow gathers the missing information. It does not promise hotel availability or a complete trip within ₱10,000 before those claims have been checked.

### Files and implementation boundaries

| File | Proposed change |
|---|---|
| `app/Services/Chat/ChatbotService.php` | Replace target-budget early termination with labelled discovery where appropriate; preserve hard-ceiling, no-match, and provider-failure behavior; retain resolved scope. |
| `app/Services/Chat/ConversationPlanner.php` | Resolve unambiguous cross-category destination context and budget scope without assuming each category owns the full amount. |
| `app/Services/Chat/ConversationManager.php` | Preserve unresolved shared budget and correct task scope through clarification, if the current metadata contract is insufficient. |
| `app/Services/Chat/ChatResponseComposer.php` | Ensure preliminary-result notices and clarification cannot be overwritten by generated claims; change only if existing outcome gating is insufficient. |
| `tests/Feature/ChatbotMultiIntentConversationTest.php` | Reproduce the supplied message through `/chat`; verify both card sets, truthful budget status, history, and follow-up behavior. |
| `tests/Feature/ChatbotConversationPlannerTest.php` | Test destination ambiguity, scope isolation, category order, and budget scope. |
| `tests/Unit/ChatSections.test.js` | Verify preliminary sections retain their cards and notice when displayed/restored. |

The shared question policy belongs to the guided-conversation integration above. The immediate result-retrieval correction should remain small and independently reviewable; avoid unrelated retrieval/ranking rewrites.

### Required regression cases

1. The exact reported message after Boracay is established returns activity and hotel previews when both catalogs have eligible records.
2. Reversing category order does not suppress either category or change the budget's meaning.
3. A standalone trailing budget clause, such as “hotels and activities, 10000 budget,” is retained and clarified rather than lost.
4. A very low target can show explicitly unverified previews, but never a false “within your budget” claim.
5. An explicit hard ceiling with an unknown basis remains unresolved; a known per-night/per-person ceiling is enforced for the correct category.
6. “₱10,000 total for both” is handled as one shared amount, not two allowances.
7. “Per night” and “whole trip for both of us” resume the appropriate pending work, preserve amount/pax, and ask only for still-required details.
8. A hotel-only prior turn followed by activities plus hotels reuses a single known destination; multiple possible destinations do not select the first arbitrarily.
9. A fresh guest without a destination is asked for one; an explicit destination change or unsupported location cannot silently inherit old results.
10. Missing/hidden hotels and provider outages preserve independent activity results and truthful task outcomes.
11. Refresh and subsequent clarification retain the cards, budget notice, amount, and correct destination.
12. Existing single-intent, pricing, grounding, capability-limit, session-isolation, and human-handoff behavior remains intact.

### Investigation evidence and current status

- The existing two multi-intent test files passed: **32 tests, 104 assertions**.
- Six temporary regression cases were added during investigation; **five failed against the committed implementation**, reproducing missing previews and the cross-category destination gap. The hard-ceiling safeguard case already passed.
- A limited temporary correction passed **46 affected tests, 178 assertions**. This was a diagnostic result, not completion of the full proposed solution: shared budget scope, the full regression matrix, browser behavior, and live model quality still require verification.
- At the user's instruction, all application and test edits from that investigation were reverted. No implementation from this investigation is retained. No commit, merge, push, migration, or deployment was performed.

The user subsequently approved proceeding from this updated plan.

## 14. Authorized implementation and verification

Implemented locally on `chatbotImprovement`, based on committed baseline `705db4a`:

- Ambiguous target budgets allow explicitly labelled catalog previews for eligible records while retaining the unresolved budget question. Explicit ceilings remain guarded. A shared amount is never automatically assigned as a full allowance to each category.
- Task planning retains standalone trailing budgets, reuses one unambiguous conversation destination across categories, and preserves explicit unsupported locations. Hotel discovery with a traveler count checks eligible room capacity. Verified hard ceilings use quoted group costs, including extra traveler charges for stays; per-person activity budgets are converted consistently for group retrieval.
- `DialogueManager` coordinates stable task IDs, scoped slot updates, required-detail questions, invalid-answer recovery, corrections, skips, interruptions, cancellations, and support suspension. State lives in the existing `conversation_v2.dialogue` metadata; no migration is required. Legacy pending entries are normalized and then cleared when guided state takes ownership so they cannot reopen completed questions.
- Guided turns ask one shared question and retain ready-task cards. Simple undated itineraries use verified activity cards and explicitly disclose their limits. Priced stay itineraries require real dates and traveler counts rather than silently choosing them. Days and hotel nights remain distinct.
- History includes the same clarification, previews, and stable references. The renderer appends the question once. Existing suggestion buttons support server-validated question IDs/revisions; old buttons are disabled, and stale submissions return 409 without adding a message or mutating task state. Active human support retains priority.
- Parsing is deterministic and bounded, including common English/Taglish number words, numeric counts, date ranges with explicit years, and budget bases. No extra model call is needed for each answer. Unsupported or unclear language falls back to clarification; this does not promise unrestricted natural-language understanding.
- `chatbot:eval --guided` adds isolated multi-turn fixture evaluation alongside the existing single-turn suite. Reports separate guided metrics and do not replace the saved baseline unless that option is explicitly requested.

Verification:

- Final affected Pest run: **411 tests passed, 1,863 assertions**, including chatbot routing, retrieval, pricing, grounding, capabilities, guest/session behavior, handoff, and the new guided scenarios.
- The evaluation harness subsequently received one additional regression test for repeated runs. All **6 harness tests passed, 29 assertions**, including two sequential evaluations that leave no new fixture accounts or sessions. Fixture emails are unique and cleanup permanently removes only the account created for that run, avoiding collisions from soft-deleted test accounts.
- JavaScript renderer: **3 tests passed**, including preview cards, restored history, single-question rendering, stale controls, and legacy behavior.
- Required Pint formatting and Vite production build passed. The build retains the existing large ApexCharts chunk advisory.
- `git diff --check`, Blade compilation, and inspection of the eight existing chat routes passed.
- Final isolated offline evaluation passed: **30 existing fixtures and 11 guided turns** with stubbed models and embeddings; guided turn expectations scored 1.0 and wrong-destination rate was zero. The saved baseline was not overwritten. An initial isolated run without the dummy embedding key took the lexical path and failed comparison with the semantic baseline; rerunning with the same stubbed embedding configuration as the tests passed. These are fixture checks, not live-quality or latency guarantees.

Rollout setting: `CHATBOT_GUIDED_CONVERSATION_ENABLED=false` by default. The core missing-result correction applies independently. For guided-flow testing, set `CHATBOT_GUIDED_CONVERSATION_ENABLED=true` and clear cached configuration with `php artisan config:clear`. Setting it back to false restores the existing conversational path while preserving stored guided state.

Remaining validation: the required local Playwright MCP with Brave is unavailable in this session, so a live browser smoke test remains outstanding. Tests and evaluation use stubbed providers; live Gemini/Groq response quality and latency are not established by these results. The complete application suite remains a separate check with `php artisan test --compact`.

No dependency changes, database migrations, branch merges, commits, pushes, or deployment were performed. The earlier investigation reversal remains historical; the implementation above was performed only after the subsequent approval.

## 15. E2E follow-up and interactive controls — planning only

The October 21, 2026 E2E report has been reviewed against the current working tree. See [the E2E fix and interactive conversation plan](chatbot-e2e-fix-plan.md) for the finding-by-finding audit, corrected root-cause claims, prioritized fixes, interactive buttons/selectors/date inputs with manual-answer support, proposed file scope, and regression/browser acceptance gates.

The earlier automated verification record above is historical. In particular, its statement that stale controls are disabled describes the intended behavior and pure-renderer coverage; the reported live Alpine DOM defect remains to be reproduced and corrected. This follow-up changes documentation only, and does not implement the proposed fixes.
