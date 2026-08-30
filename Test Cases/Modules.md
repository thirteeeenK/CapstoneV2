# SunnyTrips Modules — Paper vs Added (Functional Suitability)

> Reference for `functional_suitability_added_test_cases.md` (TC-FS-01 → 54). Paper count: 7 modules × 3 sub-characteristics × 2 = 42 minimum. Added file extends to 9 modules × 3 × 2 = 54.

## 1. Modules As Stated On Paper (7)

| # | Module (paper name) | What it covers in code | Routes / Key Files |
|---|---|---|---|
| 1 | **User Authentication & Management Module** | Register, login, logout, password reset, profile, role guard (`auth` vs `auth:admin`), ban/IP checks | `routes/auth.php`, `routes/adminAuth.php`, `app/Http/Controllers/Auth/*`, `app/Http/Controllers/ProfileController.php`, `app/Models/User.php` |
| 2 | **Visual Onboarding Module** | Tag-based quiz (vibes, amenities, traveler_type, destination), image+icon options, persist to `user_preferences` + `users.preferences_embedding` | `routes/web.php` `onboarding.*`, `app/Http/Controllers/OnboardingController.php`, `app/Models/OnboardingOption.php`, `AdminOnboardingOptionController.php` |
| 3 | **Travel Package Discovery** | Browse/search/filter packages, package detail (itinerary, inclusions, pricing), destination filter | `routes/packageRoute.php`, `routes/destinationRoute.php`, `app/Http/Controllers/PackageShowController.php`, `resources/views/package/index.blade.php` |
| 4 | **RAG-Based AI Chatbot Module** | 9-intent hybrid RAG (room/hotel/activity/package/itinerary/availability/map/weather/general), Gemini grounding, conversation history, human handoff queue | `routes/chatRoute.php`, `app/Services/Chat/ChatbotService.php`, `IntentRouter.php`, `GeminiService.php:searchRoomsHybrid/searchPackages/generateChatResponse`, `chat-widget.blade.php` |
| 5 | **Review and Sentiment Analysis Module** | Verified-booking reviews, sentiment (positive/neutral/negative), keywords, ReviewSummary AI highlights, admin moderation | `routes/reviewRoute.php`, `routes/adminReviewRoute.php`, `app/Models/Review.php`, `app/Services/SentimentEvaluationService.php` |
| 6 | **Real Time Data Integrations** | Weather (`weather_cache`, OpenWeather), interactive map (markers, preview card, coordinates), graceful fallback when external API fails | `app/Services/MapService.php`, `WeatherService` (weather_cache), `resources/views/components/frontend/*map*`, `GeminiService.php:buildItineraryContext` |
| 7 | **Administration Module** | Admin login, dashboard, CRUD: hotels/rooms/inventory, activities, add-ons, packages, destinations, faqs, legal, passenger rules, users, bookings lifecycle, audit log | `routes/admin*.php`, `app/Http/Controllers/Admin/*`, `resources/views/admin/*`, `app/Models/AdminModel.php`, `app/Services/AdminAuditService.php` |

All 7 are covered by `TC-FS-01` → `42` in both `functional_suitability_test_cases.md` and the new `functional_suitability_added_test_cases.md`.

## 2. Modules Added Aside From Paper (2 new → 9 total)

These were **implicit inside** the 7 above in the paper but never named as separate modules. They are now explicit in `functional_suitability_added_test_cases.md` `TC-FS-43` → `54` to close the gap between paper and live system (`php artisan route:list`).

| # | Added Module | Why it was split out | Test Cases | Key Files / Tables |
|---|---|---|---|---|
| 8 | **Booking & Checkout Module** (includes Trip Basket / Cart, Lucky Itinerary, Checkout, Payment) | Paper folded "booking" into Discovery/Admin. Live code has 4 dedicated route files, `CartItem` with `lucky_group_id`, `guest_manifest` form, QRPh `payment_url` + webhook idempotency, and `Booking::transitionTo` state machine with `booking_status_history` audit — too large to test via Discovery alone. | `TC-FS-43` → `48` (2×Completeness, 2×Correctness, 2×Appropriateness) | `routes/cartRoute.php`, `routes/luckyRoute.php`, `routes/checkoutRoute.php`, `routes/bookingRoute.php`, `routes/paymentWebhookRoute.php`, `app/Http/Controllers/CartController.php`, `CheckoutController.php`, `BookingPaymentController.php`, `AdminBookingController.php:176`, `app/Models/Booking.php:146`, `CartItem.php`, `BookingItem.php`, `booking_status_history` |
| 9 | **DSS & Personalization Module** (Recommendation Engine + Notifications) | Paper folded DSS into "Visual Onboarding → Discovery". Live DSS is a standalone engine: onboarding quiz → `users.preferences_embedding` (pgvector 3072) → `RecommendationController` ranking via `GeminiService::rankRecommendations` (pgvector `<=>` for packages, PHP cosine for rooms/hotels/activities) → 2-3 sentence `RecommendationExplainer` per destination persisted in `user_preferences.recommendation_explanations` + deterministic tag-match fallback + `BookingApproved/Paid/Cancelled` notifications. Needs its own correctness/fallback tests. | `TC-FS-49` → `54` (2×Completeness, 2×Correctness, 2×Appropriateness) | `app/Http/Controllers/RecommendationController.php`, `OnboardingController.php:store()->invalidateForUser()`, `app/Services/GeminiService.php`, `app/Services/RecommendationExplainer.php`, `user_preferences`, `notifications` |

> Naming: `functional_suitability_added_test_cases.md` labels them `Booking & Checkout` and `DSS & Personalization` for brevity. They map 1:1 to the two rows above.

## 3. Which Are Core Modules?

Classification by **must the system complete a booking without it?** + **is it a capstone differentiator?**

| Module | Core? | Rationale |
|---|---|---|
| User Authentication & Management | **Core** | No auth = no booking ownership, no profile, no admin guard. Gate for every other module (`CheckUserOnboarding`, `auth:admin`). |
| Travel Package Discovery | **Core** | Core transaction entry point; no discovery = no product to book. |
| **Booking & Checkout** *(added)* | **Core** | The actual money path: basket → manifest → booking → payment → status history. System is a booking platform — without this, other modules are demo only. |
| Administration Module | **Core** | Required to create inventory (hotels/rooms/activities/packages), approve/reject bookings, manage users. Live routes prove it: 40+ admin routes. |
| Visual Onboarding Module | **Core (capstone)** | Not required for a generic booking site, but **is** core for this capstone's thesis: solves the cold-start problem and feeds the DSS. Paper explicitly lists it. |
| **DSS & Personalization** *(added)* | **Core (capstone)** | The AI Decision Support claim. Without it, recommendations are just `orderBy(created_at)`. Panel expects this as differentiator; `RecommendationController` + embeddings are load-bearing. |
| RAG-Based AI Chatbot Module | **Supporting (capstone differentiator)** | High value for evaluation but bookings can complete without it. Treat as **near-core** for defense; supports discovery/booking via natural language. |
| Review and Sentiment Analysis Module | **Supporting** | Enhances trust/decisions, not blocking for checkout. Required for `completed`→`review` flow but not for revenue. |
| Real Time Data Integrations | **Supporting** | Weather/map enrich detail pages (`package/index.blade.php`, `activity/index.blade.php`) but have fallbacks by design (`weather/map section displays polite fallback` — TC-FS-34). |

**Summary:** **6 core** (Auth, Discovery, Booking & Checkout, Admin, Visual Onboarding, DSS & Personalization) + **3 supporting** (Chatbot, Reviews, Real-Time Data). If panel counts strictly by "can a guest pay?" then 4 are *strictly* core (Auth, Discovery, Booking & Checkout, Admin); the other 2 core are *thesis-core* — include them as core in your defense narrative.

## 4. How to Cite in Paper / Defense

* Keep the paper's 7-module list unchanged to avoid re-approval churn.
* In Chapter 3/4 and in this `Test Cases/` folder, add footnote: *"Implementation splits the 7 stated modules into 9 testable modules: Booking & Checkout and DSS & Personalization were evaluated separately (TC-FS-43→54) because they each span multiple route groups and have independent correctness criteria (state machine `Booking::transitionTo`, pgvector ranking with fallback)."*
* Point evaluators to this file and to `functional_suitability_added_test_cases.md:43` for the added cases; `TC-FS-46` (state machine) and `TC-FS-52` (Gemini fallback) are your strongest Correctness proofs.

## 5. Traceability

* Paper 42 cases → `Test Cases/functional_suitability_test_cases.md:1` (`TC-FS-01`→`42`)
* Added 12 cases → `Test Cases/functional_suitability_added_test_cases.md:43` (`TC-FS-43`→`54`)
* Total 54 = 9 × 3 × 2. No duplication; `TC-FS-37`→`42` already cover Admin so added cases do not re-test Admin CRUD.
