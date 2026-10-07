Here are your eval/report commands (all read-only — safe to run anytime):

1. Sentiment (Gemini vs your labels)
   php artisan sentiment:evaluate
   php artisan sentiment:evaluate --export=Evaluations/sentiment_eval_matrix.csv
   3×3 confusion matrix + per-class P/R/F1 + Accuracy / Macro-F1 / Weighted-F1. Current: N=295, Acc 0.9322, Macro-F1 0.8416.

2. ROUGE (AI hotel summaries vs blind human references)
   php artisan summary:evaluate --references=Evaluations/human_labeled_reviews.md
   php artisan summary:evaluate --references=Evaluations/human_labeled_reviews.md --export=storage/eval/rouge_metrics.csv
   Per-hotel ROUGE-1/2/L + macro-F1. Re-run this now — summaries were just rebuilt, so old ROUGE CSVs are stale.

3. LLM-as-Judge (reference-free scoring of stored summaries)
   php artisan summary:judge --references=Evaluations/human_labeled_reviews.md
   php artisan summary:judge --references=Evaluations/human_labeled_reviews.md --export=storage/eval/llm_judge.csv
   Faithfulness / coverage / conciseness 1–5 per hotel vs source reviews. Needs GEMINI_API_KEY.

4. Hit Rate@5 (onboarding recommendations)
   php artisan recommendation:evaluate
   php artisan recommendation:evaluate --export=storage/eval/hitrate_metrics.csv
   Needs ≥30 tracked users before the numbers mean anything; run recommendation:reset --force once before collecting real panel data.
   Record copy: --export CSV holds every clicked verdict (oldest-first, click-less excluded from list but counted in summary). Terminal prints all rows; scroll up or open the CSV for the appendix. Admin /evaluation page mirrors the same full list.

    Pipeline commands (only when re-labeling / re-running AI):
    php artisan sentiment:run-ai --sync # inline, no worker needed
    php artisan sentiment:export-template --path=sentiment_eval_100_template.csv
    php artisan sentiment:import-ground-truth <file>.csv
    Suggested order right now: summary:evaluate → summary:judge (fresh summaries), then sentiment:evaluate --export=... to archive the 295 matrix. Say the word if you want me to run them.

php artisan chatbot:judge --export=Evaluations\chatbot_judge_4point.csv --no-interaction

php artisan chatbot:eval

php artisan summary:generate

php artisan summary:evaluate
