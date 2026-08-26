# Chapter 4 — Metrics Computation Guide (ROUGE-1, Hit Rate, Faithfulness & Relevance)

How to compute the three Chapter 4 metrics for SunnyTrips using the existing codebase. Formulas and worked examples from your prompt are preserved verbatim; mapping to files is verified against the current repo.

---

## 1. ROUGE-1 Computation (Summarization)

> **Maps to:** `GeminiService::summarizeReviews()` `app/Services/GeminiService.php:1674` → `normalizeSummaryOutput:1713` → `fallbackSummarizeReviews:1752` writing `review_summaries.ai_summary_text` `app/Models/ReviewSummary.php:21` via prompt `app/Services/SystemPrompts/review-summary-prompt.md:1`. Pattern to copy: `SentimentEvaluationService.php:23` / `SentimentEvaluateCommand.php:15` (`sentiment:export-template` → `import-ground-truth` → `run-ai` → `evaluate`).

### What ROUGE-1 measures

ROUGE-1 compares an AI summary against a human-written *reference* summary by counting overlapping unigrams (words).

### Ground truth collection (blind, like `README_GROUND_TRUTH.md:18`)

1. Pick **N = 20–30 entities** (RoomType / HotelModel / ActivityModel) each with ≥5 verified reviews.
2. For each entity, a human reads **only the reviews** and writes a 4-bullet reference summary (same constraint the AI has — 4 bullets via `review-summary-prompt.md`).
3. Store pairs as `storage/app/summary_eval/{entity_type}_{id}.json`:
   ```json
   {"entity_type": "App\\Models\\RoomType", "entity_id": 12, "reference_summary": "Human 4 bullets...", "ai_summary_text": "AI 4 bullets..."}
   ```
   The `ai_summary_text` is already in `review_summaries` — just export it; do not regenerate.

### Worked example (from your prompt)

```
Reference Summary (Human): "The resort has great amenities but expensive food." (8 words)
AI Summary:               "Great amenities but food is expensive."           (6 words)
Overlapping Words: "great", "amenities", "but", "food", "expensive" (5 words)

Recall:    R = 5 / 8 = 0.625  (AI captured 62.5% of human points)
Precision: P = 5 / 6 = 0.833  (83.3% of AI words were relevant)
F1-Score:  F1 = 2*P*R / (P+R) = 2*0.833*0.625 / (0.833+0.625) = 0.714
```

### Formal computation

```
tokenize(s):
  lower(s) → split on /[^\p{L}\p{N}]+/u → filter empty → array

Let ref = tokenize(reference_summary)
Let ai  = tokenize(ai_summary_text)
Let overlap = | ai ∩ ref |   // set intersection; use multiset count if you want strict ROUGE-1

R  = overlap / |ref|
P  = overlap / |ai|
F1 = (P+R) > 0 ? 2*P*R/(P+R) : 0
```

Implementation is 10 lines of PHP — no new dependency. If you prefer the Python `rouge-score` package, `rouge1.fmeasure` gives the same F1.

```
R  = 0.625
P  = 0.833
F1 = 0.714
```

### Aggregation for Chapter 4

Compute R/P/F1 per entity, then macro-average over N:

```
Mean R  = Σ R_i  / N
Mean P  = Σ P_i  / N
Mean F1 = Σ F1_i / N   (report also std dev)
```

### Suggested command (copy Sentinel pattern)

```
app/Services/SummaryEvaluationService.php  // tokenize + R/P/F1 + matrix()
app/Console/Commands/SummaryEvaluateCommand.php  // summary:evaluate --export=rouge.csv
```

Usage:

```bash
php artisan summary:evaluate
php artisan summary:evaluate --export=storage/app/rouge_eval.csv
```

### Chapter 4 table template

| Entity | Ref len | AI len | Overlap | R | P | F1 |
|---|---|---|---|---|---|---|
| Room 12 — Deluxe Ocean View | 48 | 52 | 29 | 0.604 | 0.558 | 0.580 |
| Hotel 3 — Villa Maria | 51 | 44 | 27 | 0.529 | 0.614 | 0.568 |
| ... | ... | ... | ... | ... | ... | ... |
| **Mean (N=20)** | — | — | — | **0.61** | **0.58** | **0.59** |

Interpretation: F1 ≥ 0.45 acceptable for abstractive 4-bullet summary; ≥ 0.60 good.

---

## 2. Hit Rate Computation (Recommendations)

> **Maps to:** `RecommendationController.php:62` (`rankRecommendations` per destination) + `GeminiService::rankRecommendations:481` (PHP cosine) + `GeminiService::searchPackages:990` (pgvector `<=>`) + `searchRoomsHybrid:1952`. Existing spec: `EVALUATION_PLAN.md:26` (Precision@k / Recall@k / MRR / NDCG@k). HR@K is Recall@k with |relevant|=1.

### Definition

Hit Rate (HR@K) checks if the *correct* travel package appears in the top K results when executing cosine similarity logic.

```
HR@K = Total Hits / Total Test Cases
Hit = 1 if perfect package ∈ top-K, else 0
```

### Test setup (from your prompt)

Create **10 mock traveler preference profiles** (e.g., "budget beach trip for 2 pax, loves snorkeling").

Minimum viable: 10 as you specified. Recommended: 20+ using real `users.preferences_embedding` + booked wishlisted items as `relevant(u)` per `EVALUATION_PLAN.md:31` for stronger statistics.

Each profile:

```
profile i:
  query_text OR preferences_embedding (vector 3072)
  perfect_package_id  // the single ground-truth "should be #1"
```

### Execution

Via Pest test or tinker script (follow `tests/Feature/SentimentEvalTest.php:69` pattern):

```php
use App\Services\GeminiService;

// Option A — query embedding (mirrors chatbot Packages)
$ranked = app(GeminiService::class)->searchPackages($profile->query_text, 3);
// $ranked = [['item'=> Package, 'score'=> float], ...] ordered by 1 - (embedding <=> query)

// Option B — preference vector (mirrors dashboard recommendations)
$ranked = app(GeminiService::class)->rankRecommendations(
    $user->preferences_embedding, // vector float[3072] from users table
    Package::whereNotNull('embedding')->get(),
    3
);

$ids = array_map(fn($e) => $e['item']->id, $ranked);
$hit = in_array($profile->perfect_package_id, $ids, true) ? 1 : 0;
```

Log per profile:

```
profile_id, query_text, perfect_package_id, retrieved_ids (comma-sep), hit
```

### Worked example (from your prompt)

```
Total Test Cases = 10
Total Hits (perfect package in Top 3) = 8

HR@3 = 8 / 10 = 0.80  (80%)
```

Also report HR@1 and HR@5 for the paper:

```
HR@1 = hits_in_top1 / 10
HR@3 = hits_in_top3 / 10
HR@5 = hits_in_top5 / 10
```

### Baseline comparison (required for DSS claim)

| Model | HR@1 | HR@3 | HR@5 |
|---|---|---|---|
| Popularity baseline (most-booked) | 0.20 | 0.35 | 0.50 |
| Keyword match baseline | 0.30 | 0.50 | 0.65 |
| **Vector DSS (proposed)** | **0.50** | **0.80** | **0.90** |

### Storage

```
storage/eval/recommendation_hits.csv
# profile_id, query_text, perfect_package_id, retrieved_ids, hit
```

Compute HR with a throwaway PHP/Python script or `tests/Feature/RecommendationEvalTest.php` per `EVALUATION_PLAN.md:140`.

---

## 3. Faithfulness & Relevance Computation (RAG)

> **Maps to:** `app/Services/Chat/ChatbotService.php:32` (orchestrator) + `IntentRouter:81` + `ConversationManager` + `GeminiService::getHotelContext / getRoomContext / getPackageContext / generateChatResponse:2109` + `EVALUATION_PLAN.md:150` (intent accuracy + Hit@K + 1-5 rubric). Your requested metric is the stricter claim-level RAGAS variant.

For capstone projects, these metrics are typically evaluated **manually by researchers** on a controlled dataset of test queries before user testing.

### Controlled dataset

Curate **20 test queries** covering the 9 intents in `IntentRouter` (room/hotel/activity/package/itinerary/availability/map/weather/general). Example:

```
1. "Find cheapest ocean view room in Boracay for 3 pax under 3000"
2. "Is Frendz Resort available Aug 30-31 for 2 pax?"
3. "Plan a 2-day itinerary in Palawan for 2 pax under 10000"
...
```

For each query, log:

```
query, retrieved_context (the DB blocks injected via get*Context), reply (bot text)
```

`retrieved_context` is the source of truth — the bot must not invent facts outside it (`ChatbotService::buildPrompt` rule at `app/Services/Chat/ChatbotService.php:1123`).

### 3a. Faithfulness (No Hallucinations)

Analyze the AI response and isolate factual claims. Use:

```
Faithfulness = Claims found in database / Total claims made by AI
```

Procedure per reply:

1. Split reply into atomic claims (e.g., "Villa Maria costs ₱2,500/night", "It has ocean view", "It is available Aug 30").
2. For each claim, check if it appears verbatim or is entailed by `retrieved_context` or the DB rows.
3. Score 1 if grounded, 0 if hallucinated.

Worked example (from your prompt):

```
AI makes 4 distinct claims
All 4 match travel agency data (price, inclusions, validity from Package/Hotel rows)

Faithfulness = 4 / 4 = 1.0  (100%)
```

Aggregate:

```
Mean Faithfulness = Σ faithfulness_i / 20
Report also: % replies with faithfulness == 1.0
```

Target: ≥ 0.90 (≤ 10% hallucinated claims).

### 3b. Answer Relevance

Does the answer directly address the traveler prompt without adding unrelated fluff?

Score binary per query:

```
1 = yes, directly answers
0 = no (off-topic, ignores constraint, generic fallback)

Answer Relevance = Σ relevance_i / 20   → percentage
```

Worked example:

```
20 queries scored
18 scored 1, 2 scored 0

Relevance = 18/20 = 0.90 (90%)
```

### Human-eval procedure (for Chapter 4 methodology section)

1. Two independent raters score each of the 20 replies for faithfulness (claim counts) and relevance (0/1).
2. Compute mean per rater, then inter-rater agreement (% agreement or Cohen's κ) — same protocol as `EVALUATION_PLAN.md:214`.
3. Report overall means + example failure cases (cold-start, ambiguous intent, Gemini outage fallback via `fallbackSummarizeReviews`/`fallbackSentimentAnalysis`).

### Chapter 4 table template

| Query # | Intent | Claims (total) | Grounded | Faithfulness | Relevance (0/1) |
|---|---|---|---|---|---|
| 1 | room | 4 | 4 | 1.00 | 1 |
| 2 | availability | 3 | 2 | 0.67 | 1 |
| ... | ... | ... | ... | ... | ... |
| **Mean (N=20)** | — | — | — | **0.93** | **0.90** |

Normalize to 0–100 if needed for the paper. Discuss: which intent hallucinates most, and why (e.g., itinerary without `destination_id` requires elicitation per `DSS_CAPSTONE_GUIDE.md:58`).

---

## Appendix — Quick Commands Reference

```bash
# 0. Ensure DB + pgvector ready (EVALUATION_PLAN.md notes .env.testing Postgres)
php artisan migrate

# 1. ROUGE-1 (after you add the service)
php artisan summary:evaluate
php artisan summary:evaluate --export=storage/app/rouge_eval.csv

# 2. Recommendation HR@K (offline Pest test)
php artisan test --filter=RecommendationEval

# 3. RAG manual eval — no command; fill this sheet then average
# storage/eval/rag_eval.csv: query, retrieved_context, reply, total_claims, grounded_claims, faithfulness, relevance
```

## Files to Create (when you move to build)

- `app/Services/SummaryEvaluationService.php` — tokenize + R/P/F1 (mirror `SentimentEvaluationService.php:91`)
- `app/Console/Commands/SummaryEvaluateCommand.php` — `summary:evaluate` (mirror `SentimentEvaluateCommand.php:15`)
- `tests/Feature/RecommendationEvalTest.php` — HR@K harness (mirror `SentimentEvalTest.php:69`)
- `storage/eval/*` templates — `summary_eval_template.csv`, `recommendation_hits.csv`, `rag_eval.csv`

All evaluations are offline and deterministic except the initial Gemini calls that already populate `embedding` (via `php artisan embed:all`) and `review_summaries`; no extra API cost during scoring.
