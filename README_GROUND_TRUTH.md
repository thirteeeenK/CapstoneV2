# Ground Truth Labeling — Sentiment Evaluation (Option B)

Human gold standard vs AI prediction. **Never mix the two columns.**

| DB column `reviews` | Writer | Values | Role |
|---|---|---|---|
| `sentiment` (exists) `app/Models/Review.php:30` | **AI only** `ProcessReviewSentimentJob` → `GeminiService::analyzeReviewSentiment()` | `pending` → `positive`/`neutral`/`negative` | `predicted` |
| `ground_truth_sentiment` (added `2026_08_25_141654`) | **You only** via CSV import | `positive`/`neutral`/`negative` or `NULL` | `actual` |

`pending` (gray `hourglass_top` badge) = blind, not neutral. `sentiment:run-ai --sync` converts `pending → pos/neu/neg`. Until then the donut `positive+neutral+negative` sums to `0` while header `totalReviews =100`.

## 1. Export blind template (no AI leak)

```bash
php artisan sentiment:export-template --path=sentiment_eval_100_template.csv
# 101 lines: header +100 rows id,comment,human_actual,notes (human_actual empty)
```

## 2. Fill ground truth (blind)

Open `sentiment_eval_100_template.csv` in Excel/Sheets. Read `comment` **only** (ignore `rating`/`sentiment`). Type exactly `positive` / `neutral` / `negative` (lowercase) in `human_actual`:

```csv
id,comment,human_actual
1,"The most relaxing stay we've had. Quiet, clean, and the bed was heaven after a long island day.",positive
2,"Room smelled a bit musty and the air conditioner was noisy. Location is great though.",negative
3,"Room was as shown in photos. AC worked, Wi-Fi was average in the room.",neutral
```

Aim `70 pos /20 neu /10 neg` (intents) but your real count is the gold truth. Save as `sentiment_eval_100_labeled.csv` (any name; also accepts headers `ground_truth_sentiment`/`actual`).

## 3. Import (writes ONLY ground_truth)

```bash
php artisan sentiment:import-ground-truth sentiment_eval_100_labeled.csv
# → Updated 100 review(s) ground_truth_sentiment. (never touches sentiment)
# verify:
# SELECT id, LEFT(comment,40), sentiment, ground_truth_sentiment FROM reviews LIMIT 3;
```

## 4. Run AI (writes ONLY sentiment)

```bash
php artisan sentiment:run-ai --sync
# --sync = no queue worker; --all = ignore ground_truth filter; --limit= N
# pending 100 → 69 pos /20 neu /11 neg (example after sync)
```

## 5. Evaluate (reads both)

```bash
php artisan sentiment:evaluate
php artisan sentiment:evaluate --export=matrix_100.csv
# matrix actual(ground_truth) × predicted(sentiment) + per-class Precision/Recall/F1
# Accuracy = trace/N, Macro-F1 = mean(F1), Weighted-F1 = Σ F1*support/N
# pending rows excluded, N = labeled & AI-done
```

## Commands

- `sentiment:export-template {--path=} {--all}` `SentimentExportTemplateCommand.php` — never includes `sentiment` values.
- `sentiment:import-ground-truth {file}` `SentimentImportGroundTruthCommand.php` — validates `positive|neutral|negative`, transactional.
- `sentiment:run-ai {--sync --all --limit=}` `SentimentRunAiCommand.php` — `dispatch_sync(ProcessReviewSentimentJob)` if `--sync`.
- `sentiment:evaluate {--export=}` `SentimentEvaluateCommand.php` — `SentimentEvaluationService` matrix/metrics.

## Sample 3-row labeled demo

See `sentiment_eval_100_labeled_demo.csv` (same format you will submit).

## Notes

- IDs are `1..100` after `TRUNCATE ... RESTART IDENTITY`. Don't hand-edit `id`.
- If you re-run `sentiment:export-template` it overwrites; keep your labeled copy separate.
- For a quick demo without hand-labeling: set `human_actual` from `rating → 5/4 pos, 3 neu, 2/1 neg` (weak proxy, disclose in paper).
