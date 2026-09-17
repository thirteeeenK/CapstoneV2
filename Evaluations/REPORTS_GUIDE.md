# AI Performance Reports Guide (Production)

How to pull the three Chapter 4 metric reports **after the system is deployed**.
All commands run on the **server** via SSH from the project root
(the same folder that contains `artisan`). Nothing here touches code.

Prerequisites on the server:

- `GEMINI_API_KEY` set in production `.env` (needed for F1 AI pass and summaries).
- Queue worker running (`php artisan queue:listen` or Supervisor) — review
  summaries and sentiment jobs are queued; metrics read their output.
- The `Evaluations/human_labeled_reviews.md` file deployed with the release
  (it lives in the repo, so a normal deploy includes it).
- Writable `storage/` so `--export=storage/...` CSVs can be created, then
  downloaded via SFTP (or `php artisan storage:link` + a download route).

General pattern for every metric below:

```bash
php artisan <metric-command> --export=storage/eval/<name>_<date>.csv
```

then download `storage/app/eval/<name>_<date>.csv` and paste the console
table as a screenshot into the manuscript.

---

## 1. F1-Score — sentiment zero-shot (Gemini vs human labels)

Pipeline: template → you label → import → AI pass → evaluate.

```bash
# 1. Export labeling template (100 production reviews, sentiment='pending')
php artisan sentiment:export-template --path=storage/eval/sentiment_template.csv

# 2. Download it, fill the human_actual column (70 pos / 20 neu / 10 neg intent),
#    upload back to the server, then:
php artisan sentiment:import-ground-truth storage/eval/sentiment_labeled.csv

# 3. Run the AI over labeled rows only (inline, no worker needed)
php artisan sentiment:run-ai --sync

# 4. Report: 3x3 matrix + per-class P/R/F1 + accuracy/macroF1/weightedF1
php artisan sentiment:evaluate --export=storage/eval/sentiment_metrics.csv
```

Report the **macro-F1** as the headline number; the CSV + console matrix go
to the appendix. If step 3 reports pending rows, the worker is down or the
API key is missing — fix and re-run step 3, then 4 (idempotent).

## 2. ROUGE — AI summaries vs blind human references

```bash
php artisan summary:evaluate --references=Evaluations/human_labeled_reviews.md --export=storage/eval/rouge_metrics.csv
```

- Headline numbers: **macro-F1 ROUGE-1 / ROUGE-2 / ROUGE-L**.
- Hotels listed under "Skipped" have no stored AI summary → the queue worker
  hasn't processed their reviews yet; wait and re-run (read-only, safe).
- Method note for the manuscript: references were written blind (without
  seeing AI output), same 4-bullet shape as the AI prompt constrains.

## 3. Hit Rate@5 — onboarding cosine recommendations (implicit feedback)

**One record per user.** A user is tracked exactly once, and the record is
locked on their **first click** — not on their first page view:

1. First onboarded dashboard render logs one `impression` row and hands the
   page a `session_token`.
2. Reloading the dashboard **reuses that same token** — no new row, and the
   reload itself is never recorded as a hit or a miss.
3. The user's **first click** decides their verdict. If it is a Top-5 AI card
   → HIT. Anything else (default-tab card, sidebar / burger / packages / nav
   exit, logged as `nav`) → MISS.
4. After that first click, **detection stops for that user**: later renders
   emit no token and later clicks are discarded. So the user's genuine first
   interaction is the only thing on record.

No action is needed to collect data. To report:

```bash
php artisan recommendation:evaluate --export=storage/eval/hitrate_metrics.csv
```

- Table 1: HR@5 overall + split by ai/default tab and hotel/activity.
- Table 2 (the clean record): one row per tracked user who clicked —
  `session | user | viewed_at | HIT/MISS | first click`.
  Because tracking is once-per-user, every row is a distinct user's first
  click. Users who never clicked (and users already locked out after their
  first click) appear nowhere in Table 2 and are counted on the
  `Click-conditional HR@5` line, which is the headline rate. Table 1
  (all sessions) stays as the conservative companion number.
  The CSV's second section is appendix-ready.
- Collect **at least 30 tracked users** before reporting; note the count
  next to every rate. More users → re-run the same command, numbers update.

**Before collecting real panel data**, wipe any rows left over from testing
(seeded or demo accounts) — a user with an old click row is permanently
locked out and can never be measured again:

```bash
php artisan recommendation:reset --force
```

This deletes every `recommendation_hits` row (impressions + clicks) and
starts tracking fresh for all users. Run it once, then let real users
generate the data. It does not touch reviews, summaries, or any other table.

## Chapter 4 mapping

| Metric | Command | Headline | Appendix |
|---|---|---|---|
| F1 sentiment | `sentiment:evaluate --export=...` | macro-F1 | matrix CSV + labeled-template note |
| ROUGE summary | `summary:evaluate --export=...` | macro-F1 R1/R2/RL | per-hotel CSV |
| Hit Rate@5 | `recommendation:evaluate --export=...` | HR@5 + session N | session verdict CSV |

Regenerate all three exports on defense week so numbers match the deployed data.
