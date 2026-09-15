## Table 15: Reliability Testing Test Cases

| TC ID    | Sub-characteristic | Module         | Assigned Respondent | Test Task (Action to Perform)                                                                                         | Expected Result                                                                                              | Rating (1-4) |
| :------- | :----------------- | :------------- | :------------------ | :-------------------------------------------------------------------------------------------------------------------- | :----------------------------------------------------------------------------------------------------------- | :----------- |
| TC-RE-01 | Faultlessness      | Global UI      | IT Expert           | Perform standard operations such as login, browsing, and booking under normal operating conditions.                  | All standard operations complete successfully with no system errors.                                         |              |
| TC-RE-02 | Faultlessness      | User Auth      | IT Expert           | Establish multiple simultaneous user sessions and perform operations concurrently.                                  | Multiple simultaneous sessions operate independently without interference or data corruption.                |              |
| TC-RE-03 | Availability       | System Backend | IT Expert           | Simulate peak usage conditions by generating a high number of concurrent user requests.                              | System remains responsive and accessible with no downtime during load testing.                                |              |
| TC-RE-04 | Availability       | Global UI      | IT Expert           | Access the booking, chatbot, and discovery modules during scheduled testing periods.                                | All modules load and respond within acceptable time thresholds.                                               |              |
| TC-RE-05 | Fault Tolerance    | Weather API    | IT Expert           | Simulate an unavailable or timed-out external weather API and observe the system response.                           | System shows a fallback message instead of crashing when the API times out.                                   |              |
| TC-RE-06 | Fault Tolerance    | AI Chatbot     | IT Expert           | Simulate an unreachable Gemini API and observe the chatbot's response.                                              | Chatbot informs the user of service unavailability without causing system failure.                             |              |
| TC-RE-07 | Recoverability     | System Backend | IT Expert           | Simulate a server restart and verify the system's state after the restart.                                          | System resumes normal operation with no data loss after a restart.                                           |              |
| TC-RE-08 | Recoverability     | Booking        | IT Expert           | Interrupt a booking submission and check whether the system creates partial or duplicate booking records.           | Incomplete submissions are either fully rolled back or properly completed upon recovery.                      |              |

---

## Automated Verification Evidence (pre-study)

All 8 cases were verified mechanically before the participant study so ratings reflect residual risk, not broken wiring.

| TC ID | Method | Result |
| :--- | :--- | :--- |
| TC-RE-01 | Playwright smoke — 7 public pages (/, /packages, /explore, /hotels, /activities, /cart, /login) all HTTP 200 | PASS |
| TC-RE-02 | Pest `ReliabilityGuardTest` — two users' carts stay isolated via `/cart/data` JSON | PASS |
| TC-RE-03 | Playwright — 40 parallel requests across 4 pages: 40×200, 0 errors, 8.3s total (dev server, single-threaded `artisan serve`) | PASS |
| TC-RE-04 | Playwright timing — pages respond in 141–561ms, well under the 3s threshold | PASS |
| TC-RE-05 | Pest — hotel page renders 200 with weather API returning 500; chat answers with "weather data ... currently unavailable" fallback | PASS |
| TC-RE-06 | Pest — chat returns 200 with fallback reply ("could not generate a response at this moment. Please try again.") when Gemini API is 503 | PASS |
| TC-RE-07 | Playwright (screenshot `tc-re-07-restart-recovery.png`) — server killed and restarted; session survived, user stayed authenticated, pages 200 | PASS |
| TC-RE-08 | Pest — failed checkout (service throws mid-transaction) returns 500 JSON, cart intact, zero booking rows; successful checkout creates exactly 1 booking + 1 item and clears the cart | PASS |

Pest suite: `tests/Feature/ReliabilityGuardTest.php` (6 tests, 23 assertions) — all passing.

Mechanisms verified in code: weather failures fail soft (try/catch → null, 30-min failure cache, stale-DB fallback, 8s timeout; widget hides via `isset($weatherSummary)`); Gemini outages fall back to price-only search and canned chat replies; `BookingRequestService::buildFromCart` wraps Booking + BookingItem + cart clearing in a single `DB::transaction` (all-or-nothing).

## Participant Sheet (IT Expert respondents)

| # | Date | Respondent | TC IDs Attempted | Findings (failures / observations) | Rating per TC | Comments |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | | | | | | |

Each IT Expert respondent runs the scripted tasks for their assigned cases and records findings and ratings (1-4); results are consolidated into the main table per case.
