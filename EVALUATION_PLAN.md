# EVALUATION_PLAN.md — Capstone Module Evaluation Guide

This document defines **how** to measure the effectiveness of each DSS module
in SunnyTrips, **what metrics** to compute, and **how to compute them** with
worked examples. All sample computations use realistic placeholder numbers —
swap in your own logged data during testing.

---

## 1. Purpose & Scope

| Module | Files (reference) | What we measure |
|---|---|---|
| Recommendation System | `RecommendationController`, pgvector `<=>` / `cosineSimilarity` | Ranking quality (offline), user-perceived relevance |
| RAG Chatbot | `ChatbotService`, `IntentRouter`, `ConversationManager` | Intent accuracy, retrieval quality, answer quality, handoff behavior |
| Weather | `WeatherController` / service | Forecast accuracy, API reliability |
| Maps | Map service / views | Geocoding accuracy, task completion |
| System-wide | All | Usability (SUS), task success rate |

Goal: produce a **quantitative results table** per module for the capstone
paper, with a baseline comparison where possible.

---

## 2. Recommendation System

### 2.1 Test set

Build a labeled evaluation set from real user behavior:

- For **N users** (target: 20+), collect each user's
  `preferences_embedding` plus the items they actually **booked or
  wishlisted** after onboarding.
- Each user `u` gets:
  - `recommended(u, k)` — the top-k items the system returns.
  - `relevant(u)` — the set of items they actually engaged with.

Example (User A, k=5):

```
recommended(A, 5) = [H1, H2, A3, H4, P5]     (top 5 from the model)
relevant(A)      = {H2, P5}                   (actually booked/wishlisted)
```

### 2.2 Metrics

#### Precision@k

Fraction of the top-k recommendations that are relevant.

```
Precision@k = |recommended(u,k) ∩ relevant(u)| / k
```

Sample — User A above, k=5:

```
|{H2, P5} ∩ {H1,H2,A3,H4,P5}| = 2
Precision@5 = 2/5 = 0.40
```

Averaged over all N users:

```
Mean Precision@5 = (0.40 + 0.25 + 0.60 + ...) / N
```

#### Recall@k

Fraction of all relevant items that appear in the top-k.

```
Recall@k = |recommended(u,k) ∩ relevant(u)| / |relevant(u)|
```

Sample — User A:

```
|relevant(A)| = 2
Recall@5 = 2/2 = 1.00
```

#### MRR (Mean Reciprocal Rank)

For each user, take the rank position of the **first** relevant item, invert
it, then average.

```
RR(u) = 1 / rank(first relevant item)
```

Sample — User A recommended list `[H1, H2, A3, H4, P5]`:
first relevant item H2 is at rank 2 → `RR(A) = 1/2 = 0.50`

```
MRR@5 = (0.50 + 1.00 + 0.33 + ...) / N
```

#### NDCG@k (Normalized Discounted Cumulative Gain)

Rewards relevant items appearing **higher** in the list.

```
DCG@k  = Σ [ rel_i / log2(i + 1) ]        i = 1..k
IDCG@k = ideal DCG (all relevant items at top)
NDCG@k = DCG@k / IDCG@k
```

Sample — User A, `rel` = 1 if relevant, 0 if not:

```
recommended:  H1  H2  A3  H4  P5
rel:          0   1   0   0   1

DCG@5  = 0/1 + 1/log2(3) + 0/log2(4) + 0/log2(5) + 1/log2(6)
       = 0 + 0.631 + 0 + 0 + 0.387
       = 1.018

IDCG@5 = 1/1 + 1/log2(3) = 1 + 0.631 = 1.631

NDCG@5 = 1.018 / 1.631 = 0.624
```

### 2.3 Baseline comparison

To prove the DSS adds value, compare against a **non-vector baseline**:

- **Baseline**: top-k items by popularity (most-booked) or keyword match.
- **Proposed**: current vector-similarity ranking.

Report side-by-side:

| Model | Precision@5 | Recall@5 | MRR@5 | NDCG@5 |
|---|---|---|---|---|
| Popularity baseline | 0.18 | 0.31 | 0.22 | 0.40 |
| **Vector DSS (proposed)** | **0.40** | **0.55** | **0.45** | **0.62** |

### 2.4 Implementation notes

- Store test-set items per user via a small `artisan tinker` script or a
  Pest test that seeds users + bookings and calls
  `RecommendationController` / the similarity helpers directly.
- Log `user_id → item_ids → rank` to a CSV for the offline script.
- Compute metrics with a throwaway PHP/Python script or in a Pest test
  (`tests/Feature/RecommendationEvalTest.php`).

---

## 3. RAG Chatbot

### 3.1 Intent routing accuracy

Create a labeled query set (target: 50–100 queries across the 9 intents):

```
# sample_queries.csv
intent,query
room,"show me ocean view rooms in Boracay"
hotel,"list budget hotels in Palawan"
weather,"what's the weather in El Nido?"
...
```

Run each through `IntentRouter` (already covered by `IntentRouterTest`),
build a confusion matrix:

```
          predicted
           room hotel activity package itinerary availability map weather general
actual
room       18    1      0       0         0            2        0    0        1
hotel       0   15      1       0         1            1        0    0        0
...
```

**Accuracy** = correctly classified / total

```
Accuracy = (18 + 15 + ... ) / 80 = 72 / 80 = 0.90
```

Also report **precision/recall per intent** (useful when intents are unbalanced):

```
Precision(room) = true positives / (true + false positives)
Recall(room)    = true positives / (true + false negatives)
```

### 3.2 Retrieval hit-rate

For each query, the pipeline retrieves a set of docs (rooms/hotels/etc.).

```
Hit@1 = fraction of queries where the correct item is the top-1 retrieved
Hit@3 = fraction of queries where the correct item is in the top-3
```

Sample (30 queries):

```
Hit@1 = 21/30 = 0.70
Hit@3 = 26/30 = 0.87
```

### 3.3 Answer quality (RAGAS-style rubric)

Rate each bot answer on a 1–5 scale. Two raters minimum; report the average.

| Criterion | What it checks | Sample rating |
|---|---|---|
| **Faithfulness** | Answer does not contradict retrieved context | 4.2 / 5 |
| **Answer relevance** | Answer addresses the user's question | 4.4 / 5 |
| **Hallucination** | No invented facts (hotels, prices, policies) | 4.1 / 5 |

Normalize to a 0–100 scale for the paper if needed.

### 3.4 Human-eval procedure

1. Collect 20–30 real/realistic conversations (or curated transcripts).
2. Two independent raters score each bot turn 1–5 on the rubric above.
3. Compute mean per rater, then inter-rater agreement (simple % agreement).
4. Report overall means in the results table.

### 3.5 Operational signals

- **Handoff escalation rate** = support-tickets started / total conversations.
- **Latency** = average seconds from `POST /chat` to response (p50/p95).
- **Abuse-guard triggers** = blocked messages per day.

---

## 4. Weather Module

### 4.1 Forecast accuracy (MAE)

Log each forecast and compare to observed values. Requires ~2 weeks of data.

| Date | Forecasted temp (°C) | Observed temp (°C) | Abs. error |
|---|---|---|---|
| 2026-08-01 | 28.5 | 29.1 | 0.6 |
| 2026-08-02 | 29.0 | 28.2 | 0.8 |
| 2026-08-03 | 27.5 | 27.9 | 0.4 |
| ... | ... | ... | ... |

```
MAE = (0.6 + 0.8 + 0.4 + ... ) / N
MAE = 9.8 / 14 = 0.70 °C
```

Also compute **rain-prediction hit rate** (did it rain when forecast said so):

```
Hit rate = correct predictions / total days
```

### 4.2 Reliability

- **API success rate** = successful weather API calls / total calls (target ≥ 99%).
- **Cache hit rate** = responses served from cache / total requests (shows the caching layer works).

### 4.3 Implementation notes

- Add a small log (DB table `weather_eval_log` or a CSV) written by the
  weather service each fetch: `date, location, forecast_temp, forecast_rain`.
- Compare manually against observed weather (or Open-Meteo historical API).

---

## 5. Maps Module

### 5.1 Geocoding accuracy

For 20 destinations, compare the resolved map coordinate to the correct one.

```
Correct pins = 18 / 20
Geocoding accuracy = 90%
```

Tolerance: within a small radius (e.g. ≤ 2 km) counts as correct.

### 5.2 Task-completion usability test

Give 5–8 participants a task, e.g. "use the map to locate hotels near the
beach in Palawan and add one to your trip basket."

```
Task success rate = successful completions / participants
Time on task      = average time to complete (seconds)
```

Sample:

```
Success rate = 7/8 = 87.5%
Avg time     = 42 seconds
```

---

## 6. System-Wide (Usability)

### 6.1 SUS (System Usability Scale)

10 questions, each answered 1 (strongly disagree) to 5 (strongly agree).
Odd items are positive, even items are negative.

```
Odd items  (1,3,5,7,9):  score = (answer − 1)
Even items (2,4,6,8,10): score = (5 − answer)
SUS = (sum of all 10 scores) × 2.5        → 0–100 scale
```

Sample from one participant:

```
answers = [5,2,4,3,5,1,4,2,5,3]
odd:  (5-1)+(4-1)+(5-1)+(4-1)+(5-1) = 4+3+4+3+4 = 18
even: (5-2)+(5-3)+(5-1)+(5-2)+(5-3) = 3+2+4+3+2 = 14
SUS   = (18 + 14) × 2.5 = 80
```

Average over N participants. Interpretation: **≥ 68 = acceptable**;
80+ = good.

### 6.2 Task success rate

Core journeys (onboarding → recommendations → chatbot → basket → checkout):

```
Success rate = completed journeys / total attempts
```

---

## 7. Results Table Template (for the paper)

| Module | Metric | Baseline | Proposed / Result | Target |
|---|---|---|---|---|
| Recommendation | NDCG@5 | 0.40 | 0.62 | > 0.50 |
| Recommendation | Precision@5 | 0.18 | 0.40 | > 0.30 |
| Chatbot | Intent accuracy | — | 0.90 | ≥ 0.85 |
| Chatbot | Hit@3 | — | 0.87 | ≥ 0.80 |
| Chatbot | Faithfulness (1–5) | — | 4.2 | ≥ 4.0 |
| Weather | MAE temp | — | 0.70 °C | ≤ 1.0 °C |
| Maps | Geocoding accuracy | — | 90% | ≥ 90% |
| Usability | SUS | — | 80 | ≥ 68 |

---

## 8. Discussion Prompts

Use these in the capstone write-up:

- Where did the system fail, and why? (e.g. cold-start users, intents with few
  training samples, bad weather forecast on mountain areas.)
- Which metric improved most vs. the baseline? Why?
- What would you change next? (e.g. hybrid retrieval, reranking, more training
  queries, longer forecast log.)

---

## 9. Appendix — Blank Data Templates

### Recommendation log

```
user_id, recommended_items (comma-sep), relevant_items (comma-sep), k, hit_count
```

### Chatbot query set

```
intent, query
```

### Weather log

```
date, location, forecast_temp, forecast_rain, observed_temp, observed_rain, abs_error
```

### SUS responses

```
participant_id, q1..q10, sus_score
```
