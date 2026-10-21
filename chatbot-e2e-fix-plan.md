# SunnyBot E2E fixes and interactive conversation plan

Status: planning only, October 21, 2026. No application changes authorized by this document.

## 1. Scope and verification limits

Source report: `C:/Users/John Kenly Pamor/.opencode/plan/chatbot-e2e-final-report.md`. Its reproduction steps are evidence to evaluate, not instructions to execute automatically. This plan also includes the user's request for buttons, selection lists, calendars, and uninterrupted access to manual answers.

Reviewed baseline: HEAD `705db4a`, plus the existing 15 modified and four untracked guided-conversation files. The source report matches that working-tree state. Existing changes are preserved. The unrelated root `implementation_plan.md` concerns payments and is not overwritten.

Verification performed in this review: source tracing, existing test coverage inspection, installed dotenv implementation inspection, and secret-safe local log checks. The current configured Gemini key is present in the local log; its value was not printed or copied into this plan. Missing application-key errors also exist in the log.

No new live E2E run, provider request, database write, cache clear, environment change, server restart, or test run was performed. The required local Playwright MCP with Brave is unavailable in this session. Browser-specific results below remain supported by the supplied report rather than independently reproduced. Existing passing tests do not invalidate these reported gaps.

## 2. Findings audit

| Report item | Assessment against current code | Corrected action |
| --- | --- | --- |
| BUG 1: internal slot names in copy | Confirmed. `DialogueManager::finish()` constructs text from raw field names; another placeholder exists in the service. Not every example is ungrammatical, but `pax`/`budget basis` are implementation language. | Shared user-facing status copy; keep the one actionable question separate. |
| BUG 2: budget clarification loop | Confirmed logical path. `answers()` accepts a basis; `merge()` records it; `requirement()` immediately rejects per-person/per-activity stay budgets and all non-total itinerary budgets. Valid replies reset progress without advancing the question. | Preserve the supplied basis and ask a different missing dimension when necessary. Do not simply declare every basis usable by every pricing calculation. |
| BUG 3: ceiling amount missing | Confirmed. `detectBudgetMode()` knows phrases that the amount parser does not, including `within`, `up to`, and `hanggang`. | One consistent amount/operator parser, including currency, commas, decimal thousands and non-price counts. |
| BUG 4: volunteered context discarded | Confirmed. The no-answer/no-new-task branch never merges recognized values. Correction: the resulting pause is resumable with “continue planning”; it is not permanent. | Merge valid scoped details before deciding what remains unanswered. Useful context must not count as an invalid reply. |
| BUG 5: exclusions ignored | Confirmed closed vocabulary and clause anchoring. A negated catalog name can escape exclusion detection and become a positive entity/detail task. | Parse negation before positive intent/entity extraction; resolve multiword names and scoped attributes, with clarification for unclear exclusions. |
| BUG 6: filtering after cap | Partially correct. `enforceTaskPreferences()` executes inside the dispatch callback, BEFORE `ChatRetrievalService` applies its final cap. However, handlers already truncate candidates: activities to three; ordinary hotels to a default five after reranking. Backfill can therefore be lost. The report's estimate of qualifying hotels is not an established count. | Move exclusion eligibility ahead of handler truncation or use bounded candidate expansion and backfill. Never invent “1 of N” without measuring N. |
| BUG 7: long-message error | Confirmed controller max 1000, no textarea maximum, and generic reply fallback rather than field-error rendering. | Visible limit and counter, preflight validation, meaningful 422 errors, and preserved input. Do not silently truncate a pasted request. |
| BUG 8: medical request retrieves an activity | The routing vulnerability is supported: health boundaries exist in the prompt, not the deterministic router; weak fuzzy/name-fragment matches can select an activity before generation. Exact live record/output is report evidence. Correction: deterministic out-of-scope handling DOES exist for some other subjects, especially coding. | Add a non-punitive health/scope response before catalog matching and tighten entity confidence. Query length alone is insufficient: the reported sentence is already long. |
| BUG 9: dev wrapper module mismatch | Confirmed source mismatch: package is ESM, wrapper uses `require`, and composer runs its `.js` path. Pre-existing and separate from guided changes. | Convert only the wrapper to ESM, preserving shutdown handling and all four processes. Do not remove global `type: module`. |
| BUG 10: stale button remains enabled | Reported DOM reproduction is credible; source confirms in-place message mutation, derived section/action objects and nested keyed Alpine loops. Neither this review nor the existing pure renderer tests establishes the exact Alpine cause. | Reproduce in Brave and test immutable updates or explicit live-state staleness. Assert actual DOM `disabled` and handler behavior; retain server 409 protection. |
| BUG 11: add-on text/outcome disagree | Confirmed mechanism. `handleAddOnSearch()` can produce alternative copy but returns no explicit outcome; retrieval defaults to `verified_result_found`. Exact catalog output remains report evidence. | Make evidence, match/alternative classification, cards and prose agree. A card's existence does not prove it matches the requested item. |
| BUG 12: concurrent Apache 500 | Missing-key log entries confirmed; concurrency association is reported. The assertion that two reads of `.env` caused a Windows sharing failure is NOT proven. Dotenv suppresses unreadable-path failures, but production defaults/null key alone do not establish that cause. | Diagnose SAPI, base/environment path, server variables, file readability and config cache per worker without revealing secrets; then rerun concurrency with overlapping requests. Preserve the existing key. |
| O1: provider key in log | Confirmed against the configured local key without revealing it. Raw exception logging with credential-bearing request URLs creates a real credential exposure, not a low-priority observation. | Highest-priority logging correction plus a coordinated credential replacement and restricted-log cleanup procedure. |
| O2: graceful model timeout | Reported working behavior. | Preserve fallback and bounded processing. Test both timeout and provider failure. |
| O3: new question UUID every turn | Confirmed; this deliberately invalidates prior replies. ID plus revision is somewhat redundant but not itself a correctness defect. | Retain server validation. Define current-question identity explicitly; never revive old buttons because the question text happens to match. |
| O4: options invisible | Incorrect as a blanket statement. `DialogueManager::finish()` converts `clarification.options` to `suggested_actions`; Blade renders those actions. BUG 10's visible “No budget preference” button also contradicts the claim. | Verify any particular missing-option case. Use one canonical question-control representation and avoid rendering duplicate buttons during the enhancement. |
| O5: repetition did not fire after three | Expected: threshold is FIVE matches in a trailing TEN-user-message window. `ALLOW_LOCAL=true` permits enforcement on localhost; it does not exempt it. The enable flag and human-support state also matter. | Test the actual threshold in isolated sessions. Review direct runtime `env()` reads before relying on config caching. Do not lower the threshold merely to match this test. |
| O6: trailing total-budget task | Supported parser gap. `total budget 20000` can reach unresolved-clause/model handling while `10000 budget` is already covered by a regression test. Exact four-section output can vary with the planner. | Recognize constraint-only clauses before task creation; assert no extra clarification task for the same budget fact. |
| O7: same category headings | Same category plus different destination is intentional and distinguishable. | Retain destination labels; include dates/scope labels where multiple trips share a destination. No mandatory rename needed. |
| O8: absent destination/reference choices | Supported enhancement gap: question options currently cover only dates/budget skips, while some ambiguity messages use plain status tasks. | Supply eligible destinations, trip scopes or referenced entities as server-backed choices using the same clarification contract. |
| O9: repeated tasks consume capacity | Confirmed incoming tasks get fresh persistent UUIDs without cross-turn semantic deduplication. | Reuse an equivalent task within its trip scope, refresh results, preserve provenance, and keep intentional separate trips distinct. |
| O10: notice ordering | Confirmed display order; a polish issue. | Place prices next to their results and planning notices/question together at the end. Render each once, including history. |

The report's summary severity counts do not match its individual labels. Use the prioritized work below rather than repeating the “12 confirmed defects” headline as an independently verified total.

## 3. Fix sequence and behavior decisions

### Phase A — Credential protection and unsafe routing

1. Audit all Gemini request paths: embeddings, generation, cached content, chat and fallback exception handling. Replace raw exception/body logging with bounded structured fields such as operation, exception class, status and trace ID. Scrub sensitive URL parameters and headers wherever a URL must be recorded; do not merely fix one timeout branch.
2. Add a regression using a synthetic sentinel credential and simulated transport/provider errors. No credential may reach logs, public responses or persisted chat metadata. Reuse the current HTTP client and provider configuration.
3. Credential replacement is an operational follow-up: create/configure a replacement in the provider and deployed environments, verify it, revoke the exposed credential, and handle existing local/remote log copies under a controlled cleanup procedure. Do not paste keys into chat, delete logs indiscriminately, or change remote credentials during planning.
4. Add deterministic health-request handling before fuzzy catalog matching, respecting active human-support routing. Asking for medical help must not create an abuse report, increment flag counts or ban a user. For urgent symptom language, provide concise emergency-help direction without diagnosis, itinerary promotion, or invented contact numbers. Non-urgent medical questions get a clear capability boundary. Travel logistics such as nearby healthcare facilities and accessibility requests must not be blanket-blocked.
5. Tighten entity matching using distinctive tokens, token boundaries, meaningful coverage, ambiguity handling and travel/catalog context. Preserve intentional short exact names such as ATV and established typo-tolerant destination/activity tests. Cover the report's `pains`, `ain`, `aircon` and duration-token collisions without hardcoding those examples as the entire fix.

### Phase B — Budget correctness and conversational progress

1. Unify budget amount, operator and basis extraction. Keep target amounts distinct from hard ceilings and counts of travelers/days/rooms distinct from money. Include all ceiling synonyms recognized by the current mode detector.
2. Separate budget basis from category scope and time scope. “Per person” for a hotel is a valid user statement but does not establish per person per night versus per person for the stay. Preserve it and ask that narrower question. An itinerary's per-night budget may cover lodging only; never multiply it into an all-inclusive trip ceiling without clarification.
3. Convert amounts only once their required dimensions are known. Keep the original amount/basis for display and provenance; calculate normalized comparison totals separately. Do not allocate the full joint budget independently to every category.
4. Merge every valid detail into compatible pending tasks before deciding whether the active question has been answered. If the user answers a duration question with “2 people,” retain pax=2, acknowledge it, then ask duration once without an invalid strike. Do not spread details across unrelated destinations/dates.
5. Repeated invalid values retain bounded, resumable recovery. Repeated valid but insufficient details prompt a more specific question; they must not loop with identical wording and unchanged state.
6. Parse trailing budget-only clauses as constraints. Reuse equivalent tasks by normalized intent, entities and trip scope; an explicit new trip or changed dates remains separate. Preserve the eight-task bound and deliberate overflow notice.

### Phase C — Exclusions and truthful retrieval outcomes

1. Extract negated category/name/attribute spans before positive entity detection. Support multiword exclusions, English/Taglish forms, multiple exclusions and inline negation. Avoid interpreting “not more than 3000” as an excluded catalog name.
2. Apply exclusions only to the intended task/scope. Prefer catalog IDs and verified descriptive fields over matching the entire serialized card, which includes URLs and unrelated metadata. Distinguish “no pool available” from an affirmative pool amenity. Unknown amenities are not proof of absence.
3. Retrieve a bounded eligible pool, filter and rank it, then apply display caps and construct references. Backfill when known qualifying candidates exist. Do not silently turn an exclusion into a positive feature requirement.
4. Derive result outcome, copy, card counts and alternative markers from the same eligible result set. Generic add-on browsing should not be treated as an unmatched named offering; genuine alternatives must keep their caveat. No made-up count of all matching database rows.

### Phase D — Interactive question controls with manual input

The controls supplement the guided flow. The composer remains available for free text at every step, with the existing temporary sending state to prevent overlapping submissions. The user may answer multiple fields, change intent or correct context instead of completing a form.

| Requested information | Initial UI | Submission and limits |
| --- | --- | --- |
| Destination | Native select for a short catalog; searchable list only when the number of options warrants it | Use current supported destinations; provide a manual-answer hint. Do not imply an unsupported destination is available. |
| Stay dates | Labelled check-in/check-out date inputs, using native calendar support first | Explicit “Use these dates”; display the year, valid minimum/range, timezone-consistent dates and “Dates undecided” when applicable. No implied availability confirmation. |
| Travelers | Numeric count/stepper; adults/children breakdown only with supported backend semantics | Explicit submit, integer bounds and a total. Do not imply child discounts or suitability without required age/policy data. |
| Duration | Small day/night number controls | Keep days and paid hotel nights distinct; no silent subtraction or date invention. |
| Budget | Currency amount plus basis and scope choices | Ask only missing dimensions. Preserve hard-ceiling versus approximate intent. Allow “No budget preference.” |
| Preferences | Checkbox-style multiple-choice chips from supported vocabulary | Explicit Apply; manual descriptions remain accepted. Unknown features stay unverified. |
| Ambiguous result/trip | Labelled choices identifying category, destination and dates/name as needed | Resolve server-side references scoped to the user's session. |

Contract and state plan:

- Extend the existing clarification payload additively with an allowlisted control type, labelled fields, permitted options, current values, skip availability and validation constraints. Preserve question ID, revision, task IDs and plain-text prompt; legacy clients can continue using text and existing suggested actions.
- Produce controls deterministically from question state and verified catalog data, not arbitrary model-generated HTML or option identifiers. Bound option counts; validate catalog visibility and session ownership again on submission.
- Accept an optional structured answer with question ID/revision and validated field values. Normalize it and typed answers through the same dialogue merge/validation path. Do not trust a client label or insert unvalidated text into a query. Preserve the existing message-only request format.
- Store a readable user answer in history along with necessary structured context. Validate stale submissions within the existing lock before adding messages or mutating dialogue. Unsupported control types degrade to the text prompt.
- Render question controls only on the current actionable question. Answered controls show submitted values as read-only history. Keep non-question actions, preview cards, room booking date inputs and Trip Basket behavior working.
- Correct the BUG 10 behavior with a verified Alpine update strategy. Pure `displayChatMessages()` tests are insufficient; test both pointer and keyboard interaction with the actual widget DOM. Server staleness checks remain authoritative.
- Preserve unsent draft text and control values on network/validation failure. A click must not silently overwrite a typed draft. Use explicit submission for dates, counts, budgets and multiple choices; simple one-choice chips may submit once.
- Keep a single source for options to prevent both `clarification.options` and projected `suggested_actions` appearing twice. Ensure rendering retains necessary question metadata after section splitting and refresh.
- Add an editable compact trip summary after core controls work. Its “Change” actions target specific fields and scopes, without discarding independent intents. “Start new trip,” “Resume planning,” and skip controls must follow existing state rules.

Accessibility and presentation:

- Reuse SunnyTrips colors/fonts and existing widget components. Prefer native inputs/selects before adding calendar or combobox dependencies. No dependency change is assumed.
- Visible labels, fieldsets/legends for grouped choices, associated error text, keyboard focus indicators, Enter/Space activation and clear read-only/stale states. Do not communicate state by color alone.
- Aim for 44-pixel primary touch targets, wrapping chips and a transcript that reflows at narrow widths and increased zoom. Long option labels must remain understandable.
- Announce loading, errors and a new question without rereading the entire transcript or stealing focus while the user is typing. If a popup is needed, support Escape and return focus to its trigger.
- Show a 1000-character limit and counter; prevent submission of an over-limit draft while explaining the correction. If a native maxlength is used, handle oversized paste visibly rather than silently discarding text. Test emoji/Unicode counting against backend validation.
- Handle 422 field errors, stale/busy 409, rate limits and transport failures distinctly. Keep draft inputs available for correction or retry.

### Phase E — Environment and development workflow

1. Repair `scripts/dev.js` with ESM imports; verify startup and shutdown of Laravel, queue listener, scheduler and Vite. No dependency change or removal of package module mode.
2. Investigate Apache before assuming a dotenv race: confirm the actual request entrypoint, application/environment paths, SAPI/worker identity, effective non-secret configuration indicators, permissions and cache lifecycle. Correlate requests and logs without exposing key contents.
3. Use the existing APP_KEY consistently across workers. Proper process variables or a correctly generated config cache may be the remedy once the deployment path is established. Do not run key generation or bypass encryption middleware to mask the issue.
4. Move repetition settings from runtime `env()` calls to `config/services.php` when implementing configuration-cache compatibility, preserving current defaults and localhost behavior. Reflect the settings in `.env.example` if absent; do not change real local/Railway values without the corresponding request.
5. Reproduce overlapping same-session POSTs on a multi-worker server: one request proceeds; the other returns 409 busy without duplicate processing. Different sessions should work independently. Verify lock release on failures. A single-worker development server cannot establish this concurrency property.

## 4. Proposed file scope

| Action | Files | Purpose |
| --- | --- | --- |
| MODIFY | `app/Services/Chat/DialogueManager.php` | Preserve partial details, resolve budget dimensions, task reuse, user-facing copy and question/control schema. |
| MODIFY | `app/Services/Chat/IntentRouter.php`, `ConversationPlanner.php` | Consistent budget parsing, negation precedence, safe entity resolution and constraint-only clauses. |
| MODIFY | `app/Services/Chat/ChatbotService.php`, `ChatRetrievalService.php` | Scope boundary response, eligible candidate flow, pricing/outcome consistency and configuration access. Keep sensitive-health routing separate from punitive abuse matching. |
| MODIFY | `app/Services/GeminiService.php` | Safe provider logging; candidate-window changes only where required. |
| MODIFY | `app/Http/Controllers/ChatbotController.php`, `app/Services/Chat/ConversationManager.php` | Validated structured replies, stale/busy behavior and history compatibility. |
| MODIFY | `resources/js/chat-sections.js`, `resources/views/components/frontend/chat-widget.blade.php` | Controls, current-question reactivity, drafts, accessible errors and notice ordering. Extract a small existing-directory helper only if needed. |
| MODIFY | `app/Services/SystemPrompts/chatbot-system-prompt.md` | Align generation with deterministic boundaries and budget semantics; remove travel promotion from urgent-health responses. |
| MODIFY | `scripts/dev.js` | ESM-compatible development wrapper. |
| MODIFY, if needed | `config/services.php`, `.env.example` | Config-cache-safe repetition settings; an interactive-control rollout flag only if independently useful. |
| MODIFY / targeted NEW tests | `tests/Feature/ChatbotGuidedConversationTest.php`, existing intent/planner/grounding/moderation/repetition feature tests, `tests/Unit/ChatSections.test.js`, `tests/Fixtures/chatbot_guided_eval_v1.json` | Regression coverage by behavior. Generate any new PHP test with the project's Artisan/Pest convention. |
| CONDITIONAL | Deployment configuration or `bootstrap/app.php` | Only after the Apache cause is isolated; no speculative middleware/key workaround. |

No schema migration, new framework, payment change, branch merge or remote deployment is expected.

## 5. Verification and acceptance gates

Add focused failing regressions before fixes, using isolated catalog fixtures and fake providers. Do not depend on live catalog IDs, changing prices or a real API key.

1. Budget phrases: compare under/within/up to/at most/hanggang/no more than against the same ceiling; include commas, PHP/₱/P, k notation and non-monetary counts. All confirmed cards obey the same normalized unit. Ambiguous targets retain labelled previews; ambiguous ceilings never claim compliance.
2. Multi-turn basis: hotel -> per person; itinerary -> per night; shared budget -> trip total. The recorded basis remains intact and the next prompt asks a genuinely missing dimension. No identical endless loop.
3. Partial answers: ask days -> “2 people” -> days supplied later. Pax remains saved, no invalid strike for meaningful progress, no cross-trip leakage. Invalid date/count answers still recover and resume.
4. Exclusions: no scuba diving, without party boat, no diving, no pool, inline/Taglish negations and budget negation. Excluded items never become detail tasks; eligible candidates beyond the initial window backfill; unknown attributes are not certified absent.
5. Routing: reported symptom sentence, short ambiguous fragments and ordinary unrelated text produce no accidental catalog cards. Preserve exact ATV, genuine activity typos, healthcare logistics boundaries, mixed intents, support priority and abuse-report behavior.
6. Outcomes: add-on browsing and genuine alternatives have consistent structured outcomes, references, counts and text. Trailing total-budget clauses produce no stray task. Equivalent requests do not consume repeated task slots, but separate trips remain distinct.
7. Controls: click and type equivalent answers; type several details instead of using a control; edit past values; refresh; resume; cancel; skip; reject stale IDs/revisions; preserve drafts after 422/409/network failures; prevent duplicate submits; validate server-side tampered option/entity IDs.
8. DOM/browser: reproduce stale buttons with the installed Alpine version, including immutable-update candidate, delayed response, restored history, keyboard activation and active human support. Assert disabled controls actually cannot submit. Test native date controls, mobile layout, zoom, focus and screen-reader announcements using local Playwright MCP with Brave and manual accessibility checks as appropriate.
9. Provider errors: sentinel secret never appears in logs/responses/state; deterministic fallback still works. Price-update notices and pending-question prompts appear once in live and restored messages.
10. Infrastructure: verify dev process startup/cleanup separately; verify real overlapping requests on the corrected multi-worker environment. Do not claim a global concurrency pass from unit lock checks alone.

After focused fixes pass: run affected chatbot/handoff/guest/renderer tests, guided offline evaluation, required Pint for changed PHP, Blade compilation, production build and `git diff --check`. Ask for the complete application suite separately. Record which checks use fakes versus live services. Rerun every supplied E2E reproduction and preserve results as a comparison against this audit.

## 6. Delivery boundary

Implement backend correctness/security first, then interactive controls on the corrected dialogue contract, then browser and environment validation. Preserve the existing guided feature switch and message-only fallback. Include a reversible rollout plan before deployment. Credential rotation and infrastructure changes remain explicitly tracked operational work, not silently completed by application edits.

This review changes documentation only. The supplied E2E report remains unchanged. The next implementation requires the user's go-ahead.
