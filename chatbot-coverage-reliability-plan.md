# Conversational RAG coverage and reliability implementation plan

Status: proposed; planning only. No application changes have been made for this plan.
Prepared: October 22, 2026.

## 1. Objective and boundaries

Increase the range of supported travel questions and the reliability of follow-up answers, while keeping every factual recommendation tied to verified SunnyTrips data. The goal is measurable coverage of realistic user and panel conversations, rather than a promise to answer every question.

Preserve multi-intent results, consolidated forms, typed answers, date-derived duration, undated drafts, saved accommodation summaries, guest saves, planner exports, human support and existing checkout behavior. Continue acknowledging unavailable information and unsupported actions. Booking, payment, refunds, outbound messages and background monitoring remain outside chat-command capabilities.

Work against the existing `chatbotImprovement` working tree. Preserve unrelated uncommitted changes. No branch merge, dependency installation, automatic deployment or production data population is included.

## 2. Findings verified in the current code

| Existing component | Implication |
| --- | --- |
| `Faq`, `AdminFaqController`, `SaveFaqRequest` and FAQ admin views already exist. `FaqService` combines lexical matching with semantic fallback and intent gates. | Extend business FAQ management and provenance; preserve gates that prevent FAQs from replacing catalog searches. |
| `ConversationPlanner::UNSUPPORTED` tasks receive a fixed missing-source reply in `ChatbotService` before any knowledge lookup. | Adding content alone will not enable dining or broader travel guidance. These clauses need an explicit knowledge-retrieval path. |
| `DialogueManager` maintains scoped task state; `ConversationManager::history()` defaults to six recent turns. | Strengthen reference resolution and durable structured context. Do not claim that older structured state is entirely absent or send unlimited history. |
| Catalog search, activity comparison, exclusions, constraint handling and provider fallbacks already exist. | Add targeted improvements and regression cases rather than replacing the RAG pipeline. |
| `ChatResponseComposer` consumes verified cards and validates generated text. | Narrative knowledge needs its own bounded evidence contract; preserve existing price and action-claim safeguards. |
| `ChatbotEvalCommand` explicitly fakes embeddings and model responses. | Its results measure deterministic behavior; add a separate live-provider evaluation mode before claiming live answer accuracy. |
| `ChatEvalMetrics` already measures intent sets, task coverage, ranking and dialogue accuracy. | Extend these metrics with grounded-answer, constraint and correction checks; report each separately. |

## 3. Phase 1 — establish coverage and a reproducible baseline

Create a versioned benchmark of approximately 100 cases. Include single-turn questions and multi-turn conversations; count cases and checked turns separately. Each case identifies the supported destination, expected facts/records, forbidden claims, constraints, expected clarification or missing-data outcome, and relevant evidence references.

Initial allocation:

| Category | Cases |
| --- | ---: |
| Hotels, rooms, activities, packages and availability | 15 |
| Multiple intents, including partial knowledge gaps | 15 |
| Follow-ups, references, replacements and corrections | 20 |
| Comparisons and recommendation explanations | 10 |
| Budgets, travelers, exclusions and conflicting requirements | 15 |
| Destination guidance, policies and capability questions | 15 |
| Missing data, provider failures, injection and account isolation | 10 |

Use English, Filipino and Taglish, including common abbreviations and typos. Reserve at least 20 cases as a held-out set that is not used to tune rules or prompts. Report its score independently. Use synthetic, isolated catalog fixtures with unambiguous ground truth; never depend on production IDs or assume that the most expensive hotel is the best match.

Build a coverage matrix: category, verified source available, retrieval supported, result rendering supported, test coverage, missing data. Derive initial priorities from this matrix and benchmark failures. Existing tests remain in place.

Deliverable: a baseline report that separates data gaps, retrieval failures, dialogue failures and answer-generation failures. A correctly explained knowledge gap counts as correct abstention, not as successful substantive answer coverage.

## 4. Phase 2 — expand reviewed knowledge

### Business policies and product information

Extend existing FAQs with optional provenance and review metadata. Cover booking steps, payments, cancellation/refund conditions, support, account help, and a public explanation of the chatbot's capabilities and limits. Reuse canonical application settings or policy services where they exist; FAQ prose must not override executable prices, fees or eligibility rules.

Existing active FAQs remain backward compatible. Publishing new or changed policy claims requires recording a responsible source and review date. Legacy records without review metadata must not suddenly disappear; expose their review gap to admins and avoid presenting them as independently verified current policy.

### Destination knowledge

Introduce short, curated `KnowledgeArticle` records for non-bookable guidance, with:

- Title, concise body, category and optional destination association.
- Categories: destination access, local transport, dining, attractions, practical guidance, accessibility guidance and activity requirements.
- Source label, optional source URL, reviewed date, optional expiry, and draft/published/archived status.
- A pgvector embedding consistent with existing dimensions, plus a content hash or equivalent marker tying the embedding to the current text.

Use existing destinations and admin authentication. Provide an admin list, create/edit form, filtering, and visible review/embedding status using the existing AJAX list pattern. Generate new framework files through Artisan during implementation.

No public uploads, arbitrary document ingestion, autonomous web crawling or new provider are required. Source URLs are references; the server must not fetch arbitrary administrator-entered URLs as part of chat retrieval. Article text remains untrusted data for the model.

Only destination-compatible published articles with valid review/expiry state can substantiate a current answer. A narrative transport or dining article must not turn into an inventory card, availability claim, reservation or purchasable listing.

### Data preparation

Audit existing content before writing additional facts. Prepare a list of missing fields and source requirements. Populate production knowledge only from reviewed information supplied or approved by the project owner; use fictional content in automated fixtures. Infrastructure can be implemented and verified while real travel-content collection remains pending, but report the remaining data gaps honestly.

## 5. Phase 3 — route and retrieve knowledge alongside catalog results

Add an explicit `KNOWLEDGE_QUERY` task with a topic and destination scope. Route eligible travel-information clauses through it, including clauses currently classified as unsupported solely because the catalog lacks a matching entity type.

- Keep unsupported actions, out-of-scope questions and medical/emergency safeguards on their existing paths.
- Retrieve relevant articles using lexical and semantic evidence, with deterministic destination, publication and expiry filters. Use lexical fallback when embeddings fail or are stale.
- Calibrate relevance and ambiguity thresholds using development cases; do not copy a threshold across unrelated retrieval types without validation.
- For mixed queries, return catalog cards and knowledge answers in separate sections. A transport-data gap must not suppress available hotel results.
- Handle requests exceeding the task limit explicitly through the existing deferred-task mechanism.
- If sources conflict or evidence is insufficient, name the uncertainty and ask one useful clarification or suggest a supported next step.

Persist the retrieved source IDs, source revision/content hash, retrieval time and per-task outcome in chat context so restored answers can show their original evidence. Mark subsequently expired or withdrawn sources appropriately rather than silently presenting old messages as newly verified.

## 6. Phase 4 — strengthen conversational editing

Extend existing scoped state and reference resolution for examples such as:

- “Keep that hotel, replace the second activity, and make it cheaper.”
- “Actually four kami, same dates, no water activities.”
- “Compare that room with the first one you showed earlier.”
- “What about El Nido instead? Keep the same budget.”

Use stable catalog IDs and result-group references. Ambiguous ordinals, multiple prior destinations and expired/deleted items require clarification rather than an arbitrary selection. Destination changes must invalidate incompatible room/activity selections while preserving only appropriate shared preferences.

Maintain a compact structured summary of accepted trip facts, corrections and selected record IDs beyond the recent prose window. Treat this summary as user context, never as proof of current prices or availability. Exclude unsupported model claims, sensitive account details and raw prompt text. Keep per-scope reset/cancel controls effective and prevent corrections to one trip from mutating another.

Normalize common Filipino/English counts, aliases and typos in contextual fields. Use fuzzy entity matching only within appropriate catalog/destination candidates; low-confidence matches should become suggestions, not silently committed choices.

## 7. Phase 5 — improve comparisons, constraints and explanations

### Comparisons and explanations

Extend the existing activity comparison pattern to rooms and hotels, including selections from previous result groups. Compare known price basis, capacity, requested amenities, location and restrictions in a compact table or structured block. Show “Not documented” for missing attributes.

Provide a short “Why this matches” explanation tied to explicit matched constraints and catalog facts. Identify unmet or unverified preferences. Avoid uncalibrated confidence percentages, invented rankings or claims that a semantic score proves overall hotel quality.

### Constraint handling

Use a shared normalized constraint contract across typed input, forms, retrieval, itinerary generation and saved plans. Cover:

- Adults, children and total travelers, with child ages requested only when relevant documented rules require them.
- Room quantity, per-room capacity and stay dates, distinct from overall trip dates.
- Group/per-person budget basis, period and coverage; exact totals versus incomplete estimates.
- Hard requirements, soft preferences and explicit exclusions.

Reuse existing inventory capacity and pricing fields. Add structured supplier requirements only when existing fields cannot express the needed rule; use a targeted migration with source/review metadata where appropriate. Unknown child pricing, accessibility or age suitability must remain unknown. Do not assume a discount, a free child place or accessibility from a marketing tag.

Calculate prices with deterministic application logic. Identify included costs and unpriced expenses; an accommodation/activity subtotal is not a complete trip cost. If a combination is impossible, explain the specific conflict and offer adjustments without silently relaxing hard requirements.

## 8. Phase 6 — visible evidence and useful recovery

Extend response evidence to distinguish:

1. Current catalog facts and their update dates.
2. Availability checked for specified dates and quantity.
3. Reviewed destination/policy guidance and source references.
4. Planning assumptions and missing information.

Show source labels and safe links near relevant claims. Unknown source dates must remain unknown. Keep prose and cards consistent, and preserve evidence in history, saved plans and exports wherever the claim is carried over.

On an empty result, distinguish unavailable inventory, impossible constraints, absent data, ambiguous wording and a temporary provider failure. Offer a specific next step, such as another date range or an optional preference change. Preserve all independent successful sections and ask only the server-selected clarification once.

An optional feedback action can capture “wrong information,” “missing part,” or “not helpful,” linked to the response/task ID. Keep it local to SunnyTrips, rate-limited and isolated by account/session. Store only the diagnostic information needed; redact provider credentials and sensitive conversation content.

## 9. Phase 7 — evaluate live behavior and readiness

Keep the current offline `chatbot:eval` behavior backward compatible. Add a separate explicit live evaluation command/mode that uses real configured embeddings and answer generation against an isolated evaluation dataset. Require development/test configuration, separate report storage, bounded calls and clear provider-cost disclosure in the command help. Never seed or clean up live customer records.

Record model/configuration, fixture revision, source versions, timestamp and latency. Avoid putting credentials or unnecessary personal data into reports. Evaluate important cases repeatedly to expose nondeterminism; report run-to-run variation. Do not silently update baselines to conceal regressions.

Use deterministic checks for IDs, price arithmetic, scope, constraints, evidence references and side effects. Use human review for factual support, language quality and whether an answer actually addresses the question. A model judge may assist but must not be the sole authority for groundedness.

Proposed release gates, to be measured rather than assumed:

- All deterministic security, pricing, ownership and unsupported-action cases pass.
- Every requested intent is accounted for, including explicit missing/deferred outcomes.
- At least 95% of checked benchmark constraints and context corrections are handled correctly.
- At least 90% of answerable held-out cases receive a correct substantive answer; report abstention separately.
- Zero unsupported price, confirmed-booking or completed-action claims in reviewed benchmark runs.
- No regression against the current supported-feature baseline.
- No unnecessary model call for deterministic policy/catalog responses, and turns remain within configured deadlines. Report p50/p95 latency with timeouts included.

These targets describe this benchmark, not a guarantee of the same success rate for arbitrary panel questions.

## 10. Planned file changes

Paths are repository-relative. Final names should follow sibling conventions verified at implementation time.

| Change | Files / area | Responsibility |
| --- | --- | --- |
| MODIFY | `app/Models/Faq.php`, `app/Http/Requests/Admin/SaveFaqRequest.php`, `app/Http/Controllers/Admin/AdminFaqController.php`, `resources/views/admin/faqs/*`, `app/Services/Chat/FaqService.php` | Business FAQ provenance and review state; preserve existing matching gates |
| NEW | Targeted migration(s), `app/Models/KnowledgeArticle.php`, its factory, admin request/controller/views, `routes/adminKnowledgeArticleRoute.php` | Destination-scoped reviewed knowledge and admin management |
| MODIFY | `routes/web.php`, existing embedding command/job integration | Register the feature and reuse embedding configuration/update patterns |
| NEW | `app/Services/Chat/KnowledgeRetrievalService.php` | Bounded scoped retrieval and evidence records |
| MODIFY | `app/Services/Chat/IntentRouter.php`, `ConversationPlanner.php`, `DialogueManager.php`, `ConversationManager.php` | Knowledge tasks, contextual corrections and durable scoped references |
| MODIFY | `app/Services/Chat/ChatbotService.php`, `ChatRetrievalService.php`, `ChatResponseComposer.php`, `RetrievalOutcome.php`, `app/Services/GeminiService.php` | Mixed retrieval, grounded explanations/comparisons and recovery |
| MODIFY | `app/Services/Chat/TripDetailsForm.php`, `ChatbotTripPlanService.php`, relevant planner/pricing services and inventory admin forms | Consistent constraints and carried-over evidence; targeted additions only |
| MODIFY | `resources/js/chat-sections.js`, chat widget/partials, shared comparison/source UI, relevant saved-plan/export views | Accessible comparisons, evidence and feedback without blocking manual input |
| MODIFY | `app/Services/SystemPrompts/chatbot-system-prompt.md` | Align generation with evidence contracts and clarification rules |
| NEW / MODIFY | Benchmark fixtures, dedicated live evaluation command, `ChatbotEvalCommand.php`, `tests/Support/ChatEvalMetrics.php` and affected tests | Baseline, held-out evaluation and honest reporting |
| NEW, optional | Feedback request/controller/storage and admin diagnostic view | Response-linked improvement evidence |

Any schema work must first inspect the actual schema, existing migrations and applicable Postgres rules. Use existing pgvector conventions and incremental migrations; do not alter the existing vector dimension or replace the database. No new dependency or `.env` variable is required by default; reuse current model/time-limit configuration and explicit evaluation options.

## 11. Verification and delivery order

Deliver in reviewable stages:

1. Baseline, coverage audit and benchmark fixtures.
2. Reviewed knowledge storage/admin management and scoped retrieval.
3. Mixed-query integration and visible evidence.
4. Follow-up editing, comparisons and constraint fixes selected from benchmark failures.
5. Recovery, optional feedback, live evaluation and final readiness report.

For each stage, add focused Pest/JavaScript regression coverage and run the affected tests. Include publication/expiry filters, cross-destination leakage, stale embeddings, source conflicts, injected article instructions, deleted listings, account isolation, date/quantity calculations, reference ambiguity, provider failure and existing multi-intent behavior. Preserve old payload/history compatibility through optional additive fields.

Run required Pint formatting, Blade compilation and the production asset build for relevant changes. After affected tests pass, ask the user to run `php artisan test --compact` for the complete suite.

Browser acceptance must use the required local Playwright MCP with Brave: desktop/mobile, keyboard-only forms and dialogs, source links, mixed results, history restore, corrections, and save/reload/export. Report browser checks as unverified if that tool is unavailable.

The final implementation report must distinguish implemented functionality, populated verified knowledge, automated test results, live-model results and browser results. Production content collection and provider-dependent evaluation remain explicit outstanding work if unavailable; neither infrastructure completion nor a stubbed test score establishes panel readiness by itself.
