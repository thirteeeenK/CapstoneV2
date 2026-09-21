# SENTIMENT_EVALUATION_PLAN.md — Option B Human Ground Truth + Confusion Matrix

Evaluate the Gemini sentiment analyzer (`GeminiService::analyzeReviewSentiment`,
`app/Services/GeminiService.php`) against a **human-labeled gold standard** of
100 reviews (70 intended positive / 20 neutral / 10 negative). Output: 3x3
confusion matrix + per-class Precision/Recall/F1 + Accuracy/Macro-F1/Weighted-F1.

---

## Column roles (who writes what — never mix them)

| Column (`reviews`) | Writer | Values | Role in matrix |
|---|---|---|---|
| `sentiment` (exists) | AI only — `ProcessReviewSentimentJob` | positive/neutral/negative/**pending** | **Predicted** |
| `ground_truth_sentiment` (NEW, nullable) | HUMAN only — CSV import | positive/neutral/negative or NULL | **Actual** |

Human labels are made **blind** (comment only — no rating, no AI sentiment).
AI runs AFTER labeling so it never sees the human label.

## Files

- [MODIFY] migration `*_add_ground_truth_sentiment_to_reviews_table.php` [NEW]
- [NEW] `database/seeders/SentimentEvalSeeder.php` (+ root copy `SentimentEval100Seeder.php`)
  - 100 fixed comments (70 pos / 20 neu / 10 neg intent), deterministically
    shuffled (seed 20260825), attached to verified completed bookings like
    `ReviewSeeder`, but: NO Gemini call at seed time — `sentiment='pending'`.
  - Skipped automatically if eval reviews already exist.
- [NEW] `app/Services/SentimentEvaluationService.php`
  - `matrix()` → counts[actual][predicted], excludes `sentiment='pending'`
  - `metrics()` → per-class TP/FP/FN/P/R/F1/support, accuracy, macro & weighted F1
- [NEW] commands:
  - `sentiment:export-template {--path=}` → writes `id,comment` CSV for blind labeling
  - `sentiment:import-ground-truth {file}` → validates + writes ONLY `ground_truth_sentiment`
  - `sentiment:run-ai` → dispatches `ProcessReviewSentimentJob` for pending eval rows
  - `sentiment:evaluate {--export=}` → prints matrix/F1 table; optional CSV export
- [NEW] root `sentiment_eval_100_template.csv` (header only; filled by export command)
- [NEW] `tests/Feature/SentimentEvalTest.php`

## Workflow (run order)

```
php artisan migrate
php artisan db:seed --class=SentimentEvalSeeder        # no AI, blind-safe
php artisan sentiment:export-template --path=sentiment_eval_100_template.csv
#   -> human fills human_actual column (blind: read comment only)
php artisan sentiment:import-ground-truth sentiment_eval_100_labeled.csv
php artisan sentiment:run-ai                            # queue worker NOT required (runs sync)
php artisan sentiment:evaluate                          # matrix + F1, for the paper
```

## Metrics (appendix formulas)

Per class c: `TP = M[c][c]`; `FP = Σ rows r≠c M[r][c]`; `FN = Σ cols k≠c M[c][k]`
`Precision = TP/(TP+FP)` · `Recall = TP/(TP+FN)` · `F1 = 2PR/(P+R)`
`Accuracy = trace/N` · `Macro-F1 = mean(F1_c)` · `Weighted-F1 = Σ F1_c × support_c / N`
Division-by-zero → 0.0.

## Status

- [x] Plan approved (both seeder copies requested)
