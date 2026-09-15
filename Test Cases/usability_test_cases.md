# Usability Test Cases

Sub-characteristics per ISO/IEC 25010 Usability (Table 14): Appropriateness Recognizability, Learnability, Operability, User Error Protection, User Engagement, Inclusivity, User Assistance, Self-Descriptiveness.

Rating scale: 1 = Fail, 2 = Poor, 3 = Good, 4 = Excellent.

| TC ID | Sub-characteristic | Module | Assigned Respondent | Test Task (Action to Perform) | Expected Result | Rating (1-4) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| TC-UB-01 | Appropriateness Recognizability | Landing Page | Traveler | Open the SunnyTrips homepage and identify, without prior briefing, what the website is for. | The user recognizes within seconds that SunnyTrips is a Philippine travel booking platform; the hero headline ("Your Legacy of Extraordinary Travel") and destination/package imagery clearly communicate the system's purpose. | |
| TC-UB-02 | Self-Descriptiveness | Navigation | Traveler | Locate the main navigation and describe what each menu item leads to (Dashboard, My Trip Basket, My Bookings, Island Destinations, Hotels & Sanctuaries, Rooms & Stays, Activities & Tours, Transfers & Add-ons, Tour Packages & Promos). | Navigation labels are plain-language and match their destinations; the user correctly predicts each link's target without trial and error. | |
| TC-UB-03 | Learnability | Onboarding | Traveler | Create a new account and complete the onboarding preference quiz (destination, traveler type, vibes, activities, amenities). | A first-time user completes onboarding without assistance; steps are self-explanatory, selections give visual feedback, and progress is visible; the user reaches the dashboard with tailored recommendations. | |
| TC-UB-04 | Learnability | Booking Flow | Traveler | Starting from the catalog, add a package to the Trip Basket and proceed through checkout to create a booking. | A first-time user completes the flow with minimal instruction; the steps (browse → Add to Basket → checkout → booking created) follow a recognizable e-commerce pattern; success is confirmed by a booking code page. | |
| TC-UB-05 | Operability | General UI | IT Expert | Interact with common controls: search box, filter tabs, quantity (pax) steppers, date pickers, chat widget toggle. | Controls respond to input as expected on first use; the active filter tab is highlighted, steppers disable at min/max limits, and the chat widget opens/closes instantly. | |
| TC-UB-06 | Operability | General UI | IT Expert | Navigate the site using only the keyboard: Tab through the packages page, open the details modal, close it with Escape, and submit a form with Enter. | All interactive elements are reachable via Tab in logical order, focus indicators are visible, the modal closes on Escape, and forms submit on Enter; no keyboard traps exist. | |
| TC-UB-07 | User Error Protection | Checkout | Traveler | Attempt to submit the checkout form with empty contact fields, and attempt checkout with a package below its minimum passenger count. | Submission is blocked before reaching the server; required fields are marked and focused inline, and a clear message ("The contact phone field is required." / minimum-pax alert) explains what to fix. | |
| TC-UB-08 | User Error Protection | Cart / Bookings | Traveler | Click Remove on a Trip Basket item, then Request Cancellation on a booking. | A confirmation dialog appears before each destructive/irreversible action ("Remove this item from your trip basket?" / "Submit this cancellation request for review?..."); cancelling the dialog aborts the action with no data change. | |
| TC-UB-09 | User Engagement | Visual Design | Tourism Student | Browse the landing page, a destination page, and a package card grid; give an overall impression of the interface. | The user reports the interface as visually appealing and consistent (Sora/DM Sans typography, ocean/sand palette, card layouts, imagery); no broken images or layout shifts. | |
| TC-UB-10 | User Engagement | AI Chatbot | Traveler | Open SunnyBot and ask two travel questions (e.g., "hotels in Boracay", "plan a 3-day itinerary"). | The chatbot engages the user with relevant, rich answers including cards with prices and Add-to-Basket actions; the user reports the interaction as helpful and enjoyable. | |
| TC-UB-11 | Inclusivity | Responsive UI | IT Expert | Access key pages (home, packages, checkout) on a mobile-sized viewport (~375px) and on desktop. | Layouts adapt without horizontal scrolling or overlapping elements; touch targets are adequately sized; functionality (search, basket, checkout) works on both form factors. | |
| TC-UB-12 | Self-Descriptiveness | General UI | Tourism Student | Read body text, labels, prices, and badges across pages; check contrast on key screens. | Text is readable at default sizes (≥12px body), prices use ₱ formatting consistently, and text/background contrast is sufficient on cards, badges, and buttons. | |
| TC-UB-13 | User Assistance | AI Chatbot | Traveler | Open the chatbot and ask a question unrelated to the catalog (e.g., "how do I pay?" or an out-of-scope query). | The chatbot greets with guidance ("I'm SunnyBot, your travel assistant..."), answers contextual questions, gracefully declines off-topic queries, and offers a human handoff ("Talk to Admin"). | |
| TC-UB-14 | User Assistance | Search / Discovery | Traveler | Search for a keyword that matches no packages (e.g., "zzzz") on the packages page. | Instead of a blank grid, a friendly empty state appears: "No packages match your search or filters. Try different keywords or clear your filters." with a Clear Filters & View All Packages action. | |
| TC-UB-15 | Self-Descriptiveness | Page Titles | IT Expert | Open the packages page, Trip Basket, and a booking page and read each browser tab title. | Each page has a distinct, descriptive title (e.g., "Tour Packages & Vacation Deals — SunnyTrips", "Trip Basket | SunnyTrips"), so tabs/history entries are distinguishable. | |
| TC-UB-16 | Self-Descriptiveness | Forms | Traveler | Review the checkout contact form and the cancellation reason form; check every field has a label. | All inputs have visible labels above them (Full Name, Email Address, Contact Number, Reason for cancellation) with required markers; placeholders give format examples, never replace labels. | |

---

## Automated Verification Evidence (pre-study)

Mechanical checks were verified before the participant study so ratings reflect UX, not broken wiring.

| TC ID | Method | Result |
| :--- | :--- | :--- |
| TC-UB-01 | Playwright pass (screenshot `tc-ub-01-landing.png`) | PASS |
| TC-UB-02 | Playwright pass — sidebar labels enumerated | PASS |
| TC-UB-04 | Playwright end-to-end booking created (ST-2026-EDBUT, pending, ₱21,398.00) | PASS |
| TC-UB-06 | Playwright/JS audit — 0 keyboard traps, visible focus outlines, Escape closes modal, aria-labels present | PASS |
| TC-UB-07 | Playwright pass — HTML5 required, client-side pax alert, server-side message | PASS |
| TC-UB-08 | Playwright pass (screenshots `tc-ub-08-cart-remove-confirm.png`, `tc-ub-08-booking-cancel-confirm.png`) | PASS |
| TC-UB-13 | Playwright pass (screenshot `tc-ub-13-chatbot-greeting.png`) | PASS |
| TC-UB-14 | Playwright pass (screenshots `tc-ub-14-empty-search.png`, `tc-ub-14-live-filter-fallback.png`) + Pest | PASS |
| TC-UB-15 / 16 | Pest `tests/Feature/UsabilityGuardTest.php` (6 tests, 15 assertions) | PASS |

Defects found and fixed during verification: package empty-search state lacked recovery actions; Alpine live-filter blank grid; missing confirm dialogs on cart remove + booking cancellation; checkout crash (`formattedSubtotal` undefined) that broke checkout submit.

## Participant Sheet (50 respondents)

| # | Date | Respondent Type | Device | TC IDs Attempted | Successes | Failures | Time-on-Task (min) | Comments |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | | | | | | | | |

Suggested distribution: 30 Travelers, 10 Tourism Students, 10 IT Experts; ~40% of sessions on mobile. Each participant runs the scripted tasks for their assigned cases and rates each 1-4; ratings are consolidated into the main table per case (mean or mode, with notes).
