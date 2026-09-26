# Chatbot Single-Result Response Plan

## Objective

Return one concise factual answer for exact field questions, keep the verified result card, and mention alternatives only when more than one result actually exists.

## Root behavior to preserve

- The deterministic field lead is valuable: it quotes stored database values and prevents Gemini from omitting or inventing inclusions, exclusions, prices, durations, requirements, and similar fields.
- Verified cards remain the authoritative structured results shown below the prose.
- Multi-result recommendation searches may still use a short AI verdict, provided its wording reflects the actual result count.

## Implementation

### 1. Make response mode explicit from retrieval outcome

- `[MODIFY] app/Services/Chat/ChatbotService.php`
  - Track whether a package result came from the exact-name shortcut and pass `ordering: exact` into `promptOptions()` instead of always passing `null`.
  - Make `rankRule()` use `result_count` as well as ordering:
    - exact result: answer directly; no ranking or alternatives language;
    - one non-exact result: describe the single match; never mention other cards or alternatives;
    - multiple results: allow best-match and alternative-card language.
  - Keep price-order rules unchanged, but only point to other cards when the result count is greater than one.

### 2. Give deterministic field answers sole ownership of factual field queries

- `[MODIFY] app/Services/Chat/ChatbotService.php`
  - Compute `fieldLead()` / `fieldMissingLead()` before asking Gemini to compose recommendation prose.
  - When a deterministic field lead exists, use it as the complete prose answer and append only the verified card list. Do not ask Gemini to repeat the same field.
  - Apply this shared rule consistently to room, hotel, activity, package, and add-on handlers that already use `fieldLead()`.
  - When the requested field is absent for one confidently resolved entity, return the deterministic “not listed” statement plus its card without speculative filler.
  - Continue using Gemini for ordinary recommendation requests and genuinely ambiguous questions where no deterministic field answer can be produced.

Expected package response shape:

```text
Boracay Best Deal includes: Roundtrip Airfare, Roundtrip Transfer, ...

Here are the options I found:

- Boracay Best Deal — ₱9,999.00 per pax — ₱9,999.00 total for 1 pax
```

The package card still renders below this text; there is no repeated AI paragraph and no alternatives claim.

### 3. Keep cleanup as defense in depth, not primary behavior

- `[MODIFY] app/Services/Chat/ChatbotService.php`
  - Retain `stripRankFootnote()` for unexpected model output.
  - Extend final recommendation cleanup or validation so single-result replies cannot retain generic “other cards” / “close alternatives” language if Gemini disregards its prompt.
  - Base this cleanup on structured result count rather than blindly removing the phrase from legitimate multi-result replies.

### 4. Normalize inclusion-like activity data and enforce field authority

The live database currently has four activities with `inclusions = null` while their free-text notes contain `include/includes`: Parasailing, Banana Boat, Jet Ski (30 mins), and Scuba Diving. This is why the deterministic layer reports that inclusions are absent while Gemini extracts an apparent inclusion from `Additional Notes`.

- `[MODIFY] database/seeders/ActivitySeeder.php`
  - Move actual included items into the structured `inclusions` array for all affected seeded activities, not only the two reported examples.
  - Keep operational notes in `notes`, but remove inclusion claims from that field so newly seeded databases have one authoritative source.
  - Planned normalization:
    - Parasailing: `Free Insta360 camera use` becomes an inclusion; keep the flyer-count note.
    - Banana Boat: `Free Insta360 camera use` becomes an inclusion; keep the minimum-group note.
    - Jet Ski (30 mins): `Free Insta360 camera use` becomes an inclusion; keep the unit/capacity note.
    - Scuba Diving: `Underwater diving experience` becomes an inclusion; remove the redundant notes sentence.
- `[NEW] database/migrations/<timestamp>_normalize_activity_inclusions.php`
  - Apply the same targeted correction to existing databases using stable activity names and destination scope where available.
  - Update only records whose structured inclusions are still empty, so administrator edits made before deployment are not overwritten.
  - Make the data migration reversible by restoring only the known original seeded values.
- `[MODIFY] app/Services/GeminiService.php`
  - Keep `Inclusions:` as the only prompt field that may answer an inclusions question.
  - Label notes as non-inclusion operational notes and explicitly instruct the model not to reinterpret `Additional Notes`, descriptions, vibes, itineraries, or requirements as inclusions.
- `[MODIFY] app/Services/Chat/ChatbotService.php`
  - Treat `fieldValue('inclusions')` as authoritative.
  - If it is empty, the response must remain “not listed”; Gemini must not contradict it using another text field.
  - If it is populated, return the deterministic inclusion list once and skip repetitive AI prose as described in section 2.

### 5. Restore independent FAQ visibility controls and improve the manager UI

The two stored booleans already represent separate concerns, but the current landing-page query and the combined three-state admin control incorrectly make `is_active = false` hide an FAQ everywhere. The corrected contract will support all four combinations:

| SunnyBot (`is_active`) | FAQ page (`show_on_landing`) | Meaning |
| --- | --- | --- |
| On | On | Visible to both |
| On | Off | SunnyBot only |
| Off | On | FAQ page only |
| Off | Off | Hidden from both |

- `[MODIFY] app/Http/Controllers/LandingController.php`
  - Select public FAQs by `show_on_landing = true` without also requiring `is_active = true`.
  - Preserve category grouping and sort order.
- `[MODIFY] app/Services/Chat/FaqService.php`
- `[VERIFY] app/Services/GeminiService.php`
  - Continue restricting chatbot FAQ retrieval and semantic FAQ search exclusively by `is_active = true`; public-page visibility must never affect SunnyBot retrieval.
- `[MODIFY] app/Http/Controllers/Admin/AdminFaqController.php`
  - Replace the combined audience mapping with independent validated booleans.
  - Add separate actions for toggling SunnyBot visibility and FAQ-page visibility; each action updates only its own column and records the precise audit change.
  - Return explicit success messages such as `Hidden from SunnyBot` and `Shown on FAQ page`.
  - Update filters and stats to reflect the four combinations rather than collapsing them into three states.
- `[MODIFY] routes/adminFaqRoute.php`
  - Keep the existing SunnyBot toggle route or rename it clearly, and add a separate FAQ-page visibility toggle route.
- `[MODIFY] resources/views/admin/faqs/_table.blade.php`
  - Replace the oversized ambiguous action with a compact two-channel visibility control in each row.
  - Show two restrained status chips—`SunnyBot` and `FAQ page`—each with visible `On`/`Off` text and an icon, so status is not encoded by color alone.
  - Use two clearly labelled native buttons or compact toggle forms: `Show/Hide in SunnyBot` and `Show/Hide on FAQ page`.
  - Keep actions keyboard accessible, give icon-only affordances an accessible name, and provide visible focus styles and practical touch targets.
  - Use the existing ocean/sand/ink palette, Sora headings, and DM Sans UI typography; avoid gradients or decorative treatment inside the dense table.
- `[MODIFY] resources/views/admin/faqs/index.blade.php`
  - Present four accurate filter choices: visible to both, SunnyBot only, FAQ page only, and hidden from both.
  - Replace the current status summary with compact channel totals (`Available to SunnyBot`, `Shown on FAQ page`) plus the overall count, avoiding four visually heavy stat cards.
  - Preserve the existing AJAX live-search behavior and focus retention.
- `[MODIFY] resources/views/admin/faqs/create.blade.php`
- `[MODIFY] resources/views/admin/faqs/edit.blade.php`
  - Replace the combined Audience select with two independent, programmatically labelled checkbox/switch controls.
  - Explain each channel directly: one controls chatbot retrieval; the other controls the public FAQ section.
  - Preserve all four combinations on validation failure and edit.

No schema migration is required for visibility: both boolean columns already exist and can operate independently.

## Regression coverage

- `[MODIFY] tests/Feature/ChatbotTest.php`
  - Exact package inclusion question returns the stored inclusion list once.
  - Response retains exactly one verified package card.
  - Exact field answer does not contain a second recommendation paragraph.
  - Missing-field response remains explicit and grounded.
- `[MODIFY] tests/Feature/ChatbotSemanticRoutingTest.php`
  - One semantic package result contains no `other cards`, `alternatives`, or ranked-list language, including natural Gemini phrasing that does not contain `Ranked by system`.
  - Multiple package results retain valid multi-result behavior.
- `[MODIFY] tests/Feature/ChatbotObjectiveFirstTest.php`
  - Add equivalent single-result checks for another catalog type, such as activity inclusions, proving the shared field-answer path does not regress outside packages.
  - Assert the deterministic database value leads the response and appears only once.
  - Add a conflict regression where `inclusions` is null but notes contain the word `includes`; the answer must not promote the note into structured inclusions or produce contradictory paragraphs.
  - Add a populated-inclusions regression proving the structured value is returned once.
- `[MODIFY] tests/Feature/ActivitySeederTest.php` or the closest existing seeder/data-integrity test
  - Verify the four affected activities store the corrected structured inclusions and retain only their non-inclusion operational notes.
- `[NEW or MODIFY] tests/Feature/ActivityInclusionsMigrationTest.php`
  - Verify the targeted data migration repairs untouched seeded rows, preserves administrator-populated inclusions, and reverses only its own known changes.
- `[MODIFY] tests/Feature/AdminFaqTest.php`
  - Verify each visibility toggle changes only its intended column.
  - Cover and render all four visibility combinations, filters, status labels, and explicit action labels.
  - Verify create/edit requests preserve independently selected values.
- `[MODIFY] tests/Feature/LandingFaqTest.php`
  - Prove an FAQ with `is_active = false` and `show_on_landing = true` remains visible on the public FAQ page.
  - Preserve chatbot-only and hidden-from-both behavior.
- `[MODIFY] tests/Feature/FaqServiceTest.php`
  - Prove an FAQ with `is_active = false` never reaches SunnyBot even when `show_on_landing = true`.
  - Prove a SunnyBot-only FAQ remains retrievable.

## Verification

1. Run the new regression tests individually while implementing.
2. Run the affected chatbot files with `php artisan test --compact`.
3. Run `vendor/bin/pint --dirty --format agent`.
4. Run the broader chatbot suite to confirm recommendation, exact-match, semantic-routing, price-ordering, and field-intent behavior remain intact.
5. Inspect the migrated activity rows to confirm inclusions and notes are stored in the correct fields.
6. Verify the reported package, Jet Ski, and Scuba Diving queries in fresh and existing chat sessions through the required Brave/Playwright setup: one factual answer, one matching card, no contradiction, and no false alternatives wording.
7. In the FAQ Manager, exercise all four visibility combinations and verify both status controls visually, by keyboard, and against the public FAQ page and SunnyBot.

## Safety boundary

This changes response composition, corrects known seeded activity data, and restores the intended independence of the two existing FAQ visibility fields. It does not alter retrieval ranking, embeddings, prices, result-card structure, checkout behavior, or database schema. No free-text inference will silently rewrite administrator-authored data; only the four confirmed seeded activity records are targeted.
