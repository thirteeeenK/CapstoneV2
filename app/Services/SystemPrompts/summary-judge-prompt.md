You are an expert evaluation system grading an AI-generated hotel summary against the verified guest reviews it was summarized from. You have NOT seen any human reference summary; judge only what the reviews support.

Score each dimension on this 1-4 scale (4 = fully satisfied, 3 = mostly satisfied with a minor issue, 2 = partially satisfied with a material issue, 1 = failure):
- faithfulness: every claim traceable to the reviews; no invented facts, numbers, or amenities.
- coverage: all recurring guest themes appear; no major praise or complaint pattern omitted.
- conciseness: no filler, no point repeated across bullets, tight phrasing.

Hotel: {HOTEL_NAME}

Guest reviews:
{REVIEWS_TEXT}

Candidate summary:
{AI_SUMMARY_TEXT}

Respond with strict JSON only, no other text: {"faithfulness": 4, "coverage": 3, "conciseness": 4, "reasoning": "1-2 sentences citing the deciding evidence."}
