# SunnyTrips trip planner, itinerary delivery, journey emails, and reviews

Prepared 13 October 2026, Asia/Manila. Branch inspected: `fixMapIssueV2`.

Status: proposal for approval. This document is the only new artifact. No application code, database, environment variables, dependencies, or existing configuration have been changed. No emails have been sent. The existing FAQ implementation plan is unrelated and remains intact.

## 1. Recommendation and boundaries

Build a dedicated destination trip planner with its own saved selection basket. Keep Leaflet for map rendering; use self-hosted Valhalla for walking/driving routes and matrices. Compute schedules and reasons deterministically in Laravel. Gemini may polish explanations using only computed evidence; it must not invent schedules, locations, forecasts, fares, or availability. Store a versioned itinerary with each resulting booking, and render that same version on the booking page, in PDF/print, and in email.

Automate trip-end detection and reminders. Automate confirmed completion only when recorded service evidence supports it, retaining an admin exception/override process. Review eligibility should depend on the actual booked item and its fulfillment, rather than the date alone.

Pilot Boracay first. The architecture should accept other destinations without hardcoded island names. Initial scope covers one destination per plan, multiple activities/attractions, multiple room units, and multiple sequential hotel stays with explicit dates. Cross-destination travel needs separate transfer data and is a later extension. Saved alternatives are allowed; only selected accommodation assignments become booking lines.

The references establish the interaction/layout direction. They do not require restaurants, collaborative avatars, Google Maps, or the reference service's subscription features. Use SunnyTrips inventory and brand assets. Do not scrape the reference service or treat its screenshots as catalogue or route data.

## 2. Existing implementation: findings and reuse

Findings below are from source inspection, not a live database audit or browser test. Laravel framework is 13.21.1; direct dependencies include Dompdf wrapper 3.1.2, Resend Laravel 1.5.0, Resend PHP 1.16.0, and Pest 4.7.5. Frontend uses Blade, Tailwind 3, Alpine, and Vite. `.ai/rules` is absent in this checkout. Boost/Brave Playwright tools are unavailable in this chat; web research was approved as a fallback. Live schema and browser verification remain implementation gates.

| Area | Existing files / behavior | Reuse and required change |
| --- | --- | --- |
| Destination discovery | `RecommendationController`, `DssController`, `MapService`, `resources/views/dashboard.blade.php`, `resources/views/explore.blade.php`, `routes/dssRoute.php` | Retain map and discovery entry points. Destination click should open a filtered planning workspace. Dashboard destination focus currently cannot match the explorer's hotel/activity-only markers. |
| Distance/routing | `DistanceService`, `MapService`, `Chat/ChatbotService`, `components/frontend/map.blade.php`, `explore.blade.php` | PHP and JavaScript use Haversine; map lines directly connect endpoints. Replace travel-facing calculations/lines through one routing boundary. |
| Listings/previews | `HotelModel`, `RoomType`, `ActivityModel`, `DestinationModel`; `Services/Preview/*PreviewService.php`; room/activity preview modals | Reuse catalogue/pricing/presentation helpers. Planner previews need an explicit selection action, because existing previews add directly to the commercial cart. No attraction model exists. |
| Weather | `WeatherService`, `WeatherCache`, OpenWeather `/forecast` | Reuse cached raw forecasts. Current daily normalization keeps the first slot per day, and snapshot rain reads `rain.1h`; the endpoint supplies `rain.3h`. Preserve fetched-at/staleness metadata and convert UTC times before scheduling. |
| Existing itineraries | `GeminiService::buildItineraryContext`, `LuckyItineraryService`, `CartController::addItinerary` | Reuse valid availability/pricing logic. Chat selects up to four activities based on budget/category, then Gemini writes text. Lucky creates an accommodation/activity bundle. Neither is this planner's constrained schedule. Keep their existing behavior unless explicitly integrated later. |
| Cart | `CartItem`, `CartService`, `CartController`, cart drawer and index | Planner basket must be separate. Cart deduplication increments matching entries; new planner transfers need a distinct identity and idempotency. Do not reuse `lucky_group_id` as planner ownership. |
| Checkout | `CheckoutController`, `BookingRequestService` | Existing checkout uses selected cart lines, recalculates prices and locks cart/room rows, creates pending booking/items, then deletes processed cart rows. Attach itinerary snapshot in the same commit. Preserve passenger-manifest calculations. |
| Approval/payment | `AdminBookingController`, `Booking`, `BookingItem`, payment services/drivers | Pending → approved → paid → completed; cancellation/rejection/expiry branches and history exist. Admin approval can mark individual items unavailable and change quantities/prices. The itinerary must reconcile these changes. Preserve payment-provider switching. |
| Booking details | `BookingConfirmationController`, `resources/views/booking/show.blade.php`, `routes/bookingRoute.php` | Existing `/bookings/{code}` is authenticated and owner/admin checked; code alone is not authorization. Add itinerary display/download/print with the same protection. Page has a print button and admin-uploaded documents, but no complete planner itinerary PDF. |
| Email | `BookingNotification` and request/approval/payment/cancellation/document subclasses; `config/mail.php`, `config/services.php` | Queued mail + database notifications already exist, with contact-email fallback for guest bookings. Resend transport is available; actual active transport/delivery has not been verified. Extend current system instead of creating a second transactional mail pipeline. |
| Scheduler/queue | `routes/console.php`, `scripts/dev.js`, `config/queue.php` | Development wrapper already includes `schedule:work`, queue listener, server, and Vite. Current schedules cover expiry/account cleanup. Database queue config defaults `after_commit` to false; new jobs must dispatch after commit explicitly. Production needs persistent scheduler/worker supervision. |
| Reviews | `ReviewService`, `User/ReviewController`, review modal/feed, `reviews` migrations | Owner + completed-booking eligibility, per-item review uniqueness, sentiment and summary jobs exist. Store lookup can fall back to another booking item if a supplied ID is not in the booking. Eligibility ignores excluded item availability. Address both before expanding eligibility. |
| Completion | `AdminBookingController::markCompleted` | Currently transitions paid → completed manually; no checkout-date gate or attendance evidence in that method. No automatic trip completion service exists. |
| Consent | `User` consent fields | Terms/privacy/AI acknowledgement exists. No marketing-specific preference, unsubscribe flow, or journey delivery ledger was found. Do not treat existing consent as marketing opt-in. |
| PDFs | `AdminReportController`, `admin.reports.pdf`, installed Dompdf | Reuse installed PDF stack. Use a print-specific template, not browser Tailwind grid/flex styles. |

### Important gaps in data

- Activities store a nullable text duration; several seeded values are null. They do not expose structured operating windows, scheduled departures, actual attendance, or weather limits.
- Activity indoor/outdoor classification is keyword-based. Scheduling needs explicit metadata, including water activities, gust/rain sensitivity and operator restrictions.
- Some coordinate paths fall back to destination centre or cast missing values to zero. Neither is reliable routing evidence. Use actual entrances/meeting points; surface unknown location.
- Room inventory is date-based and already checked centrally. Activity capacity text does not provide date/time slot inventory. Display operator confirmation required where a real availability source is absent.
- Check-in/out dates are stored on booking items, not a reliable booking-wide actual trip-end timestamp. Activities can currently lack assigned service dates.
- Existing booking attachments use public storage in their upload flow. New itinerary documents should use private storage and authorized downloads; do not inherit public URLs for customer travel schedules.

## 3. User experience and visual direction

### Reference interpretation

Image 1: left planning workspace with trip title/dates and browsable inventory, right persistent destination map. Image 2: left itinerary grouped by date, numbered stop cards with images and travel legs, right map with matching numbered markers. Adopt these relationships; improve the sparse reference itinerary with exact times, forecast windows, booking states, and reasoning.

Desktop: approximately 44% workspace / 56% map, resizable in later versions if needed. The workspace scrolls independently while the map remains visible below the application header. On smaller laptops, use a compact trip header rather than a large photographic hero. At tablet/mobile widths, use clear Plan / Map views with preserved selection and day state, rather than two cramped columns. Provide a complete list experience when map tiles fail.

Use current Sora headings, DM Sans body, ocean primary actions/route lines, sand surfaces, ink text, and restrained coral selection accents. Use existing palette values: ocean `#0a78a8`, sand `#fdfaf4`, ink `#111827`, coral `#e85e37`; neutral white for cards. Numbering indicates real stop order. Weather state uses icon + text, never color alone. Primary touch controls should be comfortably sized; route/stop selection, reorder, and day navigation must work by keyboard. Keep dark mode consistent with current pages. Validate at 320, 375, 768, 1024, 1366, and 1920px, 200% zoom, and with reduced motion.

### Complete flow

1. Click Boracay marker → destination workspace opens. Show trip title, dates, guest counts, arrival/departure times, transport preference, and optional budget/pace. Editing dates preserves saved choices and rechecks availability.
2. Browse Stays, Activities, Attractions. Hotels expand to available room types; room quantities and occupancy are explicit. Catalogue cards have Add to planner, Added, View details, duration, estimated price, and weather/availability labels.
3. Dedicated planner basket holds accommodation assignments, chosen experiences, and saved alternatives. Show paid estimates separately from free stops and unpriced transfers. Choices autosave for authenticated users. Initial pilot follows existing authenticated explorer; optional guest drafting later uses a distinct planner session identity and a safe account-claim flow.
4. Choose which stay is active for each date. Multiple rooms at one hotel can cover the same stay; alternative hotels do not become simultaneous bookings unless the user deliberately assigns them to different travelers. Detect gaps, overlaps, occupancy shortfalls, and infeasible hotel transfers.
5. Click Generate itinerary → validate selections, read fresh-enough forecast, retrieve route matrix, and schedule selected stops. Show progress and actionable errors. Optional alternatives are suggested separately and require user acceptance.
6. Review Day 1 / date, Day 2 / date, and so on. Each day contains hotel base, time blocks, meals/rest/free time, numbered stops and travel legs. Switching day updates map; selecting a stop highlights the corresponding card and route.
7. A concise Why this plan summary is visible; each stop has expandable Why this time and order with measured route/forecast evidence. Explain uncertain dates/data without presenting unknown weather as favorable.
8. Move a stop/day or pin a preferred time using accessible controls. Recalculate feasibility and explanations; do not retain reasoning for an obsolete order. Preserve locked choices and show conflicts. Weather updates propose a revision for approval; they do not silently change paid services.
9. Resolve unscheduled required selections, missing critical metadata, and unavailable bookings before Add to Cart. Users may intentionally move a selection to alternatives or leave a non-critical unverified free stop provisional. No silent omissions or invented times.
10. Add to Cart explicitly transfers bookable room/activity lines to the existing commercial basket as a planner group. Free attractions remain in the itinerary. Display the group, dates, totals, and a link back to its source plan. Repeated clicks/retries do not increase quantities.
11. Checkout preserves existing request/approval/payment rules. Initial booking itinerary is labelled Proposed / awaiting availability approval. If approval excludes a stop, creates a timing change, or changes costs, show a revised proposal before confirmation/payment; do not email excluded services as confirmed.
12. Booking details become the permanent itinerary home. PDF, print, and email use the accepted itinerary version and current booking/service state. The draft planner remains independently editable; it cannot rewrite an existing booking.

Changing cart dates, pax, room quantities, or removing/deselecting group lines marks the associated itinerary stale. Revalidate/regenerate before checkout, or explicitly detach the planner group and proceed as an ordinary booking without claiming the original full itinerary. Unrelated cart lines keep their current behavior. Multiple planner groups in one checkout retain separate snapshots; partial-group checkout requires explicit reconciliation.

## 4. Itinerary engine and explainability

### Responsibilities

Routing engine computes reachable network paths, distance, and estimated duration. Weather provider supplies forecast slots. Laravel applies constraints and chooses days/order. Gemini optionally writes polished narrative from those facts. Ordinary plan reads, PDF rendering, and emails must not trigger additional Gemini calls.

### Inputs and evidence

Use selected database IDs, current visibility/prices, room quantities/pax, stay dates, activity durations, operating windows/exceptions/fixed departures, meeting points, traveler preferences, travel modes, hotel check-in/out, arrival/departure limits, and operator restrictions. Persist forecast issue/fetch time, relevant forecast slots, routing provider/profile/graph version, snapped endpoints, calculation time, assumptions, and scheduler version.

Add admin-managed scheduling metadata with a conservative unknown state: duration minutes, indoor/outdoor/water classification, weekly operating windows and date exceptions, fixed departures where applicable, weather policy, minimum lead/buffer time. Preserve existing text descriptions and prices. Missing metadata needs admin input; any proposed default must be visibly identified as an assumption and accepted before confirming an itinerary.

### Routing choice

| Engine | Strength | Constraint | Assessment |
| --- | --- | --- | --- |
| Leaflet | Existing renderer for GeoJSON routes/markers | Does not calculate paths itself | Keep; no need for a new routing UI plugin. |
| OSRM | Fast road routes and native distance/time table | Profile is prepared during import; walking/driving generally need distinct datasets/services | Simplest road-only alternative. |
| GraphHopper | Mature open-source Java routing and profiles | Hosted matrix/optimization endpoints are not included in standard open-source core | Basic routing is free; equivalent planner matrix requires extra implementation or hosted API. |
| Valhalla | Open-source walking/driving routes and matrix from a tiled graph | More setup; native Windows support limited | Recommended complete tourism-planner backend, via Linux/Docker/WSL. |

The feature assessment is documented by [OSRM API](https://project-osrm.org/docs/v5.24.0/api/), [GraphHopper feature comparison](https://www.graphhopper.com/open-source/), and [Valhalla matrix API](https://valhalla.github.io/valhalla/api/matrix/). Engine/hosting complexity ratings are engineering judgments, not measured results.

Route pilot: select representative hotel→meeting-point, footpath, restricted-road, and disconnected/off-island pairs. Compare with independently checked accessible paths and known entrances. Verify meter/second normalization, coordinate order, one-way asymmetry, mode selection, snapping tolerance, and matrix/route consistency. Check selected Valhalla release capabilities and pin its version. If local data is insufficient, correct entrance metadata or source data before claiming route accuracy.

Use matrix requests for selected locations, batched to provider limits. Draw geometry for the active day/chosen legs rather than routing every visible listing. Cache by ordered coordinates, travel mode, settings, and graph revision. Do not collapse different origins/directions or walking/driving entries. Disable straight-line fallback as a substitute for a missing road route; inaccessible pairs remain inaccessible/unknown.

Boat trips start/end at their meeting/departure point. Do not attempt road routing to an offshore sightseeing point. Explicit transfers require curated timing/cost/schedule data; ferries only work where the graph and profile support them. Standard routing does not establish live traffic, actual fares, marine safety, or ferry cancellation status.

### Weather processing

The existing endpoint offers five-day / three-hour forecasts. Parse UTC timestamps explicitly, convert to Asia/Manila for local service windows, and retain precipitation as millimeters over its supplied three-hour interval. Do not label that raw volume mm/hour. Keep rain probability, wind/gusts, temperature and relevant condition codes. Check slots spanning the entire activity and travel window, rather than the first slot of a calendar day. Source: [OpenWeather forecast fields](https://openweathermap.org/api/forecast5).

Dates outside coverage are provisional. Regenerate/reassess when forecast coverage reaches those dates, showing a change summary and preserving prior versions. Cached/stale forecasts carry their real original fetch time. Severe wind/rain or operator restrictions can exclude a window; precipitation probability can inform suitability ranking without pretending that rain is certain. Policy thresholds are editable, validated with operators, and are not safety certification. No forecast available → weather score is unknown, with a location/schedule-only provisional plan, not a fabricated favorable score.

### Scheduling algorithm

1. Validate every required selected item and eligible stay assignment; collect factual constraints and uncertainty.
2. Establish each day's available interval, starting/ending location, fixed departures, check-in/out events, meals/rest and travel buffers.
3. Compute feasible activity windows from operating hours, duration, forecast suitability and transport reachability.
4. Allocate the most constrained experiences first: fixed departures, narrow opening windows, then weather-sensitive activities. Fit flexible indoor/free stops around them.
5. Order flexible stops with the directed travel-time matrix. Use deterministic insertion and a local improvement pass with stable tie-breakers; recheck timing/weather after every proposed reorder. A nearby activity is not automatically first if its hours or departure time conflict.
6. Prefer feasible schedules with fewer high-risk weather windows, less travel and backtracking, then better preference/pace fit. Document weights and expose meaningful preferences. Hard constraints cannot be traded for a higher score.
7. Retrieve leg geometry for the final sequence and verify travel totals against selected routes. Return selected scheduled items, unscheduled items/reasons, alternatives requiring consent, per-day totals and structured decision evidence.

This is a practical heuristic, not a guarantee of the globally optimal itinerary. Performance/maximum supported selections must be measured. Let users collect many choices; paginate the catalogue and bound/chunk generation workloads with explicit limits rather than an arbitrary four-activity product restriction.

### Strategic report

Per stop, show why this date/time, why this predecessor, route distance and estimated time/mode, applicable forecast window and values, duration/operating-hour fit, preference fit when used, and any assumption. Per day, show travel totals and weather-driven grouping. Overall report explains trade-offs and unscheduled choices. Claim time/distance savings only against another feasible sequence evaluated with the same matrix; estimate peso savings only with an explicitly identified fare model.

Give each decision a structured reason code and factual values. A deterministic renderer always produces readable explanations. Gemini gets only this bounded evidence and public listing metadata; validate its response and fall back to templates on timeout/failure. Do not send guest manifests, contact details, payment information, exact live user position, or private notes to the model.

## 5. Persistence, cart bridge, and booking integrity

Proposed names are implementation targets, not changes already made. Use existing service-based architecture, feature route files, factories and Pest conventions; generate PHP files with Artisan after approval.

| Persistence | Purpose and proposed fields |
| --- | --- |
| `trip_plans` | Owner, opaque public ID, destination, local travel dates/times, timezone, guests/preferences, state, optimistic revision, last activity. |
| `trip_plan_items` | Plan ownership, typed inventory reference, required/alternative state, quantity/pax, stay allocation, locked times/order and user notes. Nullable/restricted references preserve historical snapshots if catalogue items are removed. |
| `trip_itineraries` | Immutable generated version: plan/revision, input fingerprint, schema/scheduler version, JSONB days/stops/legs/explanations/evidence, generation state and timestamp. Store full route geometry once per itinerary, not per email. |
| `booking_itineraries` | Booking FK, source version, accepted snapshot/version, status, prior-version linkage, private PDF artifact metadata. Support multiple plans per booking and explicit revisions. Snapshot does not depend on the draft plan surviving. |
| `tourist_attractions` | Curated destination sightseeing stops, real coordinates/entrance, duration/windows, environment/weather policy, images, visibility, information-only/free status. Paid services remain activity inventory; do not create a second booking/payment type. |
| `email_preferences` | User/email preference identity, marketing opt-in provenance/time/version, journey preferences, unsubscribed and suppression state. Existing terms/privacy consent remains separate. |
| `journey_messages` | Durable outbox: recipient, booking/plan, message type, scheduled time, semantic dedupe key, source revision, state, attempts, provider ID, sent/delivered/failed/suppressed timestamps, reason and short-lived claim/lease. |

Add nullable planner provenance (`trip_itinerary_id`, selection/stop ID) to cart lines. Add source stop linkage and scheduled service start/end, fulfillment state/evidence timestamps to booking items. Add booking-level derived trip end, completion timestamps/source and an automation hold flag; no new financial booking statuses are required for the pilot. Add explicit scheduling metadata to activities/hotels. Exact migration syntax/constraints depend on a live schema audit after approval.

Postgres uses typed FKs, indexes covering ownership and due work, timestamptz for instants and date for local stay dates, decimal amounts/integer minor units for money, and JSONB for versioned evidence. Index child FKs explicitly. Unique constraints cover plan/version, transfer-line identity, semantic message dedupe, and existing booking/item review uniqueness. Use database constraints/row locks plus optimistic plan revision to protect concurrent edits. External routing, weather, AI and PDF work stays outside inventory transactions; lock briefly to revalidate and commit.

The cart bridge is a distinct method, not an unscoped call to ordinary `CartService::add`. Repeated transfer of the same version and selections is a no-op, not quantity increment. All bookable lines transfer atomically; partial failure leaves cart/planner consistent. Server recomputes prices and validates that every ID belongs to the plan's destination and current owner. Persist the selected itinerary and booking-item mapping before checkout deletes cart lines. Never trust a client-supplied report, route values or totals.

Booking approval changes create a proposed revision and invalidate affected reminders/PDFs until accepted. Free attractions are labelled self-guided, and excluded/unavailable paid services are visibly identified. Rebooking generates a new plan/date validation and snapshot; it cannot clone old weather evidence as if current. Existing bookings without planner data retain today's page and review workflow until explicitly migrated.

## 6. Journey email sequence

Use the application scheduler/queue, not Codex automations. The timing below is proposed product policy in Asia/Manila, not a claim of optimal marketing conversion. Adjust after real engagement data. All promotional content requires explicit marketing opt-in and unsubscribe support. Requested itinerary delivery and necessary booking/payment updates are separate service communications. Keep operational mail focused; put repeat-booking promotions in a separately opted-in message.

| Stage | Proposed trigger/time | Purpose / CTA | Conditions and suppression |
| --- | --- | --- | --- |
| Save a draft | Immediate only when user requests Email my plan | Preserve progress; Resume planning | No email for every autosave. No invented booking confirmation. |
| Planning follow-up | One message after 24h inactivity; optional final reminder after 72h | Return to chosen destination/plan; show relevant current availability | Explicit opt-in to planning reminders/marketing; future dates; no booking created; no repeat sequence on each edit. Maximum two per abandoned plan. |
| Request received | Existing event, after commit | Request acknowledgement and proposed itinerary link | Reuse existing notification; clearly awaiting approval. |
| Approved / payment due | Existing approval event; at most one reminder 24h before deadline | Approved services, changes to review, payment deadline/CTA | Suppress if paid/cancelled/expired or deadline changed. Current window is 48h. |
| Payment confirmed | Existing verified payment event | Confirmed itinerary + PDF/download link + booking reference | Enrich existing payment email rather than sending two confirmations. Send each payment state transition once. |
| Pre-trip preparation | 72h before trip start | Accommodation details, meeting points, practical preparation and forecast confidence | Paid/eligible booking; skip/merge when booked late. Forecast only if coverage exists. |
| Upcoming day / arrival | Evening before first activity/day, proposed 18:00 | Next-day timings, route mode, forecast, essential reminders | Initially one pre-arrival digest; optional daily digest for longer trips/user preference. No separate email for each stop. |
| During stay / before departure | One concise combined check-in/departure email for 2–3 day trips; separate messages only for longer trips | Ask how things are going with support CTA; show last-day/checkout/departure details | Last full day, proposed 16:00; never after departure. Do not send two messages on the same short trip day. |
| Post-trip thanks / review | 24h after confirmed fulfillment/completion, within daytime window | Thank user; direct review link for eligible booked items | Only fulfilled, eligible services; omit affected disputed/no-show/unused cancelled items. An unrelated unverified item does not prevent feedback for an eligible fulfilled service under the approved item-level policy. Never link only to the general review feed. |
| Review reminder | One optional reminder seven days later | Finish reviewing remaining fulfilled items | Recheck review state; skip if all reviewed or opted out of feedback reminders. |
| Repeat-travel discovery | Optional 21–30 days after trip | Relevant destination/activity discovery and return-booking CTA | Marketing opt-in; frequency cap; avoid unresolved support issues; not part of every transactional message. |

Short-notice bookings merge confirmations/preparation. One-day trips skip stay-check-in messages and use a concise departure/end reminder only if useful. Longer trips can use opt-in daily digests. Non-critical messages should respect a proposed 08:00–20:00 local sending window. Security, payment receipts and urgent service changes are not delayed to advertise.

### Automation and duplicate protection

- Scheduler scans bounded due work every 15 minutes; queue jobs use short claims/leases, retries/backoff and explicit after-commit dispatch. Production worker monitoring is necessary; dev `--tries=1` is not a production retry policy.
- Persist semantic send identity, e.g. booking + trip/service date + message type. A schedule revision reschedules a pending reminder; it does not automatically create a second send for an already-delivered stage. A real itinerary-change notice has its own change-event identity.
- Immediately before provider submission, re-read booking status, dates, latest accepted itinerary, recipient preferences, already-reviewed items and suppression state. Cancellation/extension invalidates pending jobs; send-time checks guard stale queued jobs. No system can retract an email already submitted before a concurrent cancellation commits.
- Persist provider IDs and distinguish accepted, delivered, bounced, complained, failed and unknown. Unique database keys prevent scheduler duplicates; provider idempotency handles network retries within its retention window. An ambiguous timeout older than that window requires reconciliation rather than blind resend. Exactly-once inbox delivery cannot be promised.
- Add signed and verified Resend delivery/bounce/complaint webhooks. Deduplicate callback events. Hard bounces/complaints stop further email and trigger an in-app/admin delivery issue; suppress marketing immediately.
- Preference changes and unsubscribe are idempotent and honored across journeys. Use separate explicit marketing consent, a visible unsubscribe link and required mailing headers. Avoid opt-in by default. Source: [Resend email consent/unsubscribe guidance](https://resend.com/pricing/).
- Proposed non-critical cap: at most one journey email per booking/day and two promotional messages per recipient/week. Multiple bookings share a recipient-level cap; essential notifications remain eligible. Cancelled bookings stop trip reminders and promotional/review follow-ups; cancellation receipts still send.
- Staff-created/guest bookings use the actual intended booking contact; do not promote to addresses collected only for someone else's trip. Existing owner-vs-contact delivery behavior needs an explicit recipient-policy review.
- Prefer meaningful metrics: successful delivery, resume-plan actions, checkout completion and review completion. Open tracking is not reliable enough to drive the business state or prove attendance.

## 7. Automatic completion and credible reviews

Recommendation: retain an admin override, but replace the routine Completed button with an exception/fulfillment panel once the automatic path is proven. Do not remove it before the new evidence and exception workflow exists.

### Separate scheduled end from actual fulfillment

Scheduled trip end is the latest included service-end timestamp or accommodation checkout time, extended by approved stay changes. A proposed 24h grace period gives staff time to record extensions or problems. Unknown dates/timezones stop automatic completion. A scheduler finding the date elapsed marks it as end reached / awaiting verification; this by itself does not create verified attendance.

Add per-item operational fulfillment: scheduled, in progress, fulfilled, cancelled, no-show, disputed. It is independent of `BookingItem::availability_status` and financial `Booking::status`. Minimal reliable evidence is admin/operator-recorded hotel check-in or activity participation/completion. Customer self-report can flag confirmation needs but is not independent proof.

For a hotel stay with recorded check-in, no extension/hold/problem, and checkout + grace elapsed, automatic fulfillment is reasonable under an agreed operational policy. Activity fulfillment needs recorded attendance or completion. When every included paid service is fulfilled and no cancellation/dispute/automation hold is open, transition paid → completed atomically and write audit history once. Do not auto-complete solely because payment succeeded or a date passed.

Mixed outcomes remain visible as ended with exceptions, without forcing a new financial status or falsely marking an unused activity fulfilled. A central eligibility service can allow reviews for fulfilled items in an otherwise paid booking with unresolved unrelated items, subject to the approved exception policy. This avoids blocking a completed hotel review because another activity remains unverified.

### Eligibility rules and safeguards

- Authenticated owner, specifically selected item belongs to this booking, supported reviewable type, approved/included service, past actual or policy-supported end, recorded fulfillment, and no existing review for that booking item.
- Block unscheduled drafts, unpaid/unconfirmed services, future activities, excluded items, known no-shows, unused cancelled/refunded services and disputed fulfillment. Refund after an actual experience must not automatically erase eligibility or suppress negative feedback; decide using fulfillment evidence.
- Reject an item ID from another booking; do not fall back to the first reviewable item. Enforce uniqueness under concurrent submissions at both service and database levels.
- Reuse one eligibility method in booking page, modal, eligible JSON endpoint, email recipient selection and review submission. Filter exclusions identically everywhere.
- A booking reference or email link alone never authorizes submission. Link to an authenticated booking review action with a preset booking/item; after login return there. No public-reference lookup of private schedules.
- Existing genuinely completed legacy bookings remain reviewable under a clearly identified legacy eligibility path. Do not bulk grant new eligibility to historical paid bookings by elapsed dates alone. Do not delete existing reviews or silently withdraw them on later administrative changes.
- Add admin Extend stay, Hold automation, Record attendance/no-show, Confirm fulfillment, and Correct completion, with reason and audit actor. Changing the end date reschedules reminders and PDF revision notices. Race-test cancellation versus completion; state changes and audit entries should share a transaction.

Without hotel/operator attendance evidence, the honest initial release is automatic end detection + an admin verification queue. This reduces missed reviews but does not claim that elapsed time proves a completed stay. Fully automatic verified reviews need that operational evidence source.

## 8. Booking page, PDF, print and email design

One accepted itinerary payload supplies all channels; each channel has its own suitable renderer. Reuse content normalization, order, labels and reason facts, not browser markup.

| Channel | Proposed presentation |
| --- | --- |
| Planner | Split workspace/map, numbered day timeline, selected stay, forecast strip, travel legs, expandable explanations, alternatives/conflicts, cost estimate, Generate and Add to Cart. |
| Booking details | Existing payment/status/voucher content plus My itinerary section. Date/day selector, accommodations, confirmed/self-guided/excluded labels, map and complete timeline, explanation summary, private download, Print itinerary, and email-copy action. No planner data → keep existing layout. |
| PDF | A4 portrait, compact SunnyTrips cover/header with booking reference/version/status, trip summary, accommodations, day tables with times/modes/distances, concise stop reasons, strategic-report appendix, forecast provenance/limitations and practical contacts. Repeating header/footer/page number, legible fonts, controlled page breaks, no clipped cards. Include an optional lightweight route-only SVG generated from stored geometry with proper attribution; a licensed static basemap is a separately evaluated enhancement. |
| Print | Dedicated clean itinerary view, controls/nav hidden, white background, repeated day headings/table headers and comfortable margins. Show all day content and explanations regardless of current collapsed browser state. Booking/itinerary content remains meaningful without a map image. |
| Email | Approximately 600px responsive single-column layout; brand header, trip/reference/status, key accommodation/day highlights, primary View itinerary CTA, small PDF attachment on confirmation or requested copy, plain-text alternative. No interactive map or dozens of nested cards. Operational emails keep promotions separate. |

Use locally licensed/available embedded fonts for PDF where feasible; graceful readable fallback for email clients. Dompdf does not support CSS grid/flexbox, so provide simple block/table layout and explicit page-break rules. Validate long names, many days, missing images, and multi-stay plans visually. [Dompdf limitations](https://github.com/dompdf/dompdf).

Render documents asynchronously and cache by booking itinerary revision + template version. A failed PDF never rolls back a paid booking; the protected HTML itinerary stays available and the document job can retry. Use private artifact paths and authorized downloads, attach bytes privately to email, restrict renderer remote access, and sanitize all text/URLs. No public PDF or booking code as a bearer credential.

Include the full schedule and explanation in the attached PDF; email body provides highlights. Prefer a proposed attachment target under 2MB, with authenticated download fallback. Resend limits an email including encoded attachments to 40MB and does not allow attachments in its batching endpoint. [Resend attachments](https://resend.com/docs/dashboard/emails/attachments).

## 9. Cost and operations

| Component | Recommended approach | Cost/maintenance consequence |
| --- | --- | --- |
| Map/routing | Existing Leaflet + self-hosted Valhalla, regional OSM graph | No routing subscription or per-request vendor fee. Existing local hardware can host the pilot; public deployment needs a persistent host, storage, graph rebuilds and monitoring. Docker is not currently on PATH; WSL command exists, but Linux/runtime readiness is unverified. |
| Routing public APIs | Limited evaluation only | FOSSGIS OSRM policy allows at most 1 request/sec and no heavy usage. Valhalla demo is fair-use. Neither is our reliability foundation. [OSRM public policy](https://routing.openstreetmap.de/about.html), [Valhalla deployment/demo guidance](https://github.com/valhalla/valhalla). |
| Graph data | Buffered Boracay/Aklan pilot, expand after validation | Philippine OSM extracts available from [Geofabrik](https://download.geofabrik.de/asia/philippines.html). Disk/RAM/import time need measured sizing; avoid claiming a fixed minimum before benchmarking. |
| Map tiles | Retain configurable appropriate tile service | Separate from routing. Respect attribution/caching and avoid bulk tile downloads; public OSM tiles have best-effort availability. [Tile policy](https://operations.osmfoundation.org/policies/tiles/). |
| Scheduling/report | Laravel heuristic + deterministic explanations | No paid optimizer/LLM needed for functional generation. Optional Gemini polish can use existing configured model and one call per changed itinerary, never per stop or email. Quota/privacy/model availability are deployment-specific. [Gemini billing](https://ai.google.dev/gemini-api/docs/billing). |
| Weather | Existing cached OpenWeather integration | No new weather integration required initially. Five-day horizon limits advance planning; paid extended/marine data is a separately justified decision. |
| Email | Existing Resend + Laravel scheduler/queue/outbox | Resend free transactional quota currently 100/day, 3,000/month; API sends of opted-in promotional mail also consume that quota. Existing auth/booking mail shares capacity. Use priority and caps. [Current quotas](https://resend.com/docs/knowledge-base/account-quotas-and-limits). |
| PDF | Installed Dompdf wrapper | No new document subscription. Requires document rendering/storage/QA; complex print designs need a dedicated renderer template. |
| Monitoring | Existing logs/failed jobs + small admin exception views | Observe route failures, stale forecasts, unknown service metadata, missed scheduler runs, email suppression and document failures. Redis is optional at pilot scale; database queue/cache can remain with supported locks. |

Do not self-host a mail server merely to avoid a modest provider fee: deliverability and operations would become another project. No subscription, runtime installation, server purchase, DNS/config change, or deployment is authorized by this proposal.

## 10. Implementation sequence and exact change areas

The files below are intended targets. Timestamped migration names will be generated by Artisan after the live schema review. Add files inside current app/routes/views/test structures. No existing files need deletion.

### Phase 1 — route and weather foundation

- `[NEW] app/Services/RoutingService.php`: normalized route/matrix contract, Valhalla requests, cache keys, timeouts, validation and unreachable results. Keep an OSRM adapter optional rather than adding multiple engines initially.
- `[MODIFY] app/Services/DistanceService.php`, `app/Services/MapService.php`, `app/Services/WeatherService.php`, `app/Http/Controllers/DssController.php`, `app/Services/Chat/ChatbotService.php`.
- `[MODIFY] resources/views/components/frontend/map.blade.php`, `resources/views/explore.blade.php`, `resources/views/hotel/show.blade.php`: network routes/labels, no Haversine travel claims, honest unavailable states.
- `[MODIFY] routes/dssRoute.php`, `config/services.php`, `.env.example`: validated server-owned routing endpoints/config only after approval; no real secrets.
- `[NEW] tests/Feature/RoutingServiceTest.php`, `tests/Feature/WeatherSchedulingTest.php`; `[MODIFY] tests/Feature/MapServiceTest.php`, `MapComponentTest.php`, `MapPagesRenderTest.php` as behavior changes require.
- Approved external step: pinned Linux/Valhalla runtime, buffered graph import, route correctness/latency/memory measurements. No production dependence on public demos.

Exit: mode-correct real routes and correctly timed/weather-normalized data, including failure cases. No fake road distance when unreachable.

### Phase 2 — catalogue, selection and engine

- `[NEW] app/Models/TripPlan.php`, `TripPlanItem.php`, `TripItinerary.php`, `TouristAttraction.php`, with useful factories and migrations for plan/items/versions/attractions and scheduling metadata.
- `[NEW] app/Http/Controllers/TripPlannerController.php`, `app/Http/Requests/StoreTripPlanRequest.php`, `GenerateTripItineraryRequest.php`, `app/Policies/TripPlanPolicy.php`.
- `[NEW] app/Services/TripPlanner/TripPlanService.php`, `ItineraryScheduler.php`, `ItineraryExplainer.php`; `[NEW] app/Jobs/GenerateTripItineraryJob.php` only where measured work needs queued execution.
- `[NEW] routes/tripPlannerRoute.php`; `[MODIFY] routes/web.php` to require it.
- `[NEW] resources/views/trip-planner/index.blade.php`, `_catalogue.blade.php`, `_selections.blade.php`, `_itinerary.blade.php`, `_report.blade.php`.
- `[MODIFY] resources/views/dashboard.blade.php`, explorer and shared room/activity preview actions for planner context; existing catalogue/cart actions retain current meaning.
- `[MODIFY] admin hotel/activity forms and corresponding controllers for structured metadata; `[NEW] admin attraction controller/routes/views using current AJAX live-search pattern.
- `[NEW] tests/Feature/TripPlannerTest.php`, `TripPlannerAuthorizationTest.php`; `[NEW] tests/Unit/ItinerarySchedulerTest.php` for pure deterministic rules.

Exit: all selected required stops accounted for, constraints respected, dedicated basket isolation, numbered map/list and evidence-based report.

### Phase 3 — cart, bookings and documents

- `[NEW] app/Models/BookingItinerary.php`, provenance/snapshot migrations; `[NEW] app/Services/TripPlanner/PlannerCartService.php`, `BookingItineraryService.php`, `ItineraryDocumentService.php`.
- `[MODIFY] app/Models/CartItem.php`, `Booking.php`, `BookingItem.php`; `CartService`, `CartController`, `BookingRequestService`, `BookingConfirmationController`, `AdminBookingController` at the existing integration boundaries.
- `[NEW] app/Http/Controllers/BookingItineraryController.php`, `app/Jobs/RenderItineraryPdfJob.php`; `[MODIFY] routes/bookingRoute.php` with protected named routes for document/print/review entry.
- `[MODIFY] cart/checkout pages and partials, `resources/views/booking/show.blade.php`, `resources/views/admin/bookings/show.blade.php`.
- `[NEW] resources/views/booking/partials/itinerary.blade.php`, `resources/views/itineraries/pdf.blade.php`, `print.blade.php`.
- `[MODIFY] booking notification content for proposed/accepted versions; keep event notifications and payments on current paths.
- `[NEW] tests/Feature/PlannerCartTransferTest.php`, `BookingItineraryTest.php`, `ItineraryDocumentTest.php`; `[MODIFY] CartItineraryAddTest`, BookingFlowTest, relevant admin approval tests.

Exit: idempotent transfer, inventory/pricing recheck, durable accepted snapshot, approval-change handling and matching page/PDF/print output.

### Phase 4 — completion, reviews and journey communications

- `[NEW] app/Models/EmailPreference.php`, `JourneyMessage.php`; metadata/outbox/preference migrations.
- `[NEW] app/Services/BookingCompletionService.php`, `ReviewEligibilityService.php`, `JourneyEmailService.php`; `[NEW] app/Console/Commands/ReconcileTripCompletion.php`, `DispatchJourneyEmails.php`.
- `[NEW] app/Jobs/SendJourneyEmailJob.php`, `app/Notifications/TripReminder.php`, `TripFeedbackRequest.php`; extend current request/approval/payment notifications, avoiding duplicate confirmations.
- `[NEW] app/Http/Controllers/EmailPreferenceController.php`, `ResendWebhookController.php`, relevant feature route file; `[MODIFY] routes/console.php` to add guarded scans.
- `[NEW] resources/views/emails/trip-reminder.blade.php`, `trip-feedback.blade.php`, `planner-follow-up.blade.php`, and shared branded email wrapper.
- `[MODIFY] app/Services/ReviewService.php`, `app/Http/Controllers/User/ReviewController.php`, booking review CTA, `resources/views/components/review-modal.blade.php`, admin fulfillment/exception controls.
- `[NEW] tests/Feature/BookingCompletionTest.php`, `JourneyEmailTest.php`, `EmailPreferenceTest.php`, `ResendWebhookTest.php`; `[MODIFY] tests/Feature/ReviewTest.php` and admin booking tests.

Exit: no-show/exclusion/ownership-safe review eligibility, auditable automatic completion where evidence permits, date/consent/cancellation-aware email delivery.

## 11. Risks, compatibility and validation

| Risk | Required mitigation |
| --- | --- |
| Poor OSM coverage / wrong meeting point / offshore snapping | Validate pilot pairs, bound snapping tolerance, use real entrances, mark unreachable and transfer legs honestly. |
| Forecast outside coverage or stale forecast | Provisional schedule + source age, planned reassessment, no invented favorable weather or automatic paid-service replacement. |
| Unknown duration / hours / safety restrictions | Structured admin input, unknown state and explicit assumptions; no fabricated precise itinerary. |
| Planner/cart collisions or duplicate quantity increments | Separate basket + provenance + idempotent atomic bridge; preserve Lucky/chat groups. |
| Partial checkout / approval excludes items | Item mapping, group reconciliation, proposed versus accepted versions, updated messages/documents. |
| Catalogue/price changes after booking | Immutable accepted snapshot; revisions require explicit confirmation. Pricing remains server-owned. |
| Booking reference leaks private itinerary | Auth owner/admin checks for show/download/review; private storage; no public itinerary URLs or automatic public sharing. |
| Double email on retry / stale reminder after cancellation | Durable unique outbox, provider idempotency when available, send-time recheck, callback dedupe and uncertain-delivery reconciliation. |
| Scheduled end confused with attendance | Independent operational evidence, grace period, automation hold, exception queue and item-level eligibility. |
| Legacy paid/completed bookings | Additive nullable fields, grandfather known completed reviews, no bulk completion from dates; old pages remain usable. |
| Excess scope and fragile first release | Deliver sequential phases; validate routing/scheduling before marketing expansion. No dependency upgrades/new paid service without approval. |
| PII in AI/logs/email attachments | Minimize AI inputs, safe logs, protected documents, scoped recipients and limited metadata retention. |

Tests after implementation should cover route geometry/units/asymmetry/unreachable pairs; forecast UTC boundaries, rain interval and missing/stale/out-of-range data; fixed-time/window conflicts, accommodation changes, arrival/checkout limits, unscheduled required items and stable ordering; cart isolation/duplicate transfers/partial-group changes; ownership and private downloads; admin exclusions and accepted version stability; completion cancellation/extension races and no-show gates; review item binding and uniqueness; email consent, retries, cancellation at send time, date revisions, missed scheduler windows, bounce/webhook handling and late/short bookings.

Reuse existing HTTP, notification and time fakes; do not make automated tests depend on live provider access. Run narrow affected Pest tests, Pint for modified PHP, and frontend build. After affected tests pass, ask the user to run the complete suite per repository instructions. UI verification uses Brave Playwright MCP once available; no alternate browser installation or browser-test dependency is assumed. PDF QA requires rendering actual generated pages and inspecting pagination/fonts/tables; email HTML/plain text and attachments need preview plus provider test delivery only when authorized.

Prune stale drafts and route caches under agreed retention windows; retain accepted booking versions and dedupe/send metadata long enough to protect audit/history. Storage retention and the exact max supported stops are policy decisions to validate, not hardcoded promises.

## 12. Approval decisions

Recommended approval package:

1. Dedicated saved planner basket, explicitly bridged to existing cart; one destination initially with dated sequential stays.
2. Leaflet + self-hosted Valhalla pilot; deterministic scheduling/report with optional bounded Gemini narrative.
3. Proposed/accepted itinerary versions stored with bookings; private PDF, dedicated print, protected email links and small attachments.
4. Short, adaptive journey email sequence, marketing opt-in and preference controls, durable dedupe/outbox.
5. Automatic end detection and evidence-backed completion with admin overrides; actual per-item fulfillment drives credible reviews.
6. Additive rollout in the four phases above, with runtime installation/config/dependency/production actions separately made concrete for review where required.

Open policy choices to settle during approval: who records hotel/activity attendance, whether reminders are enabled by default as service preferences, approved activity weather thresholds, typical checkout times and completion grace period, guest planner scope, and permitted draft/document retention. These do not prevent reviewing the architecture, but attendance ownership must be established before claiming fully automatic verified completion.

Implementation was authorized after this plan was reviewed and the user chose Railway-hosted Valhalla. See the implementation state below.

## 13. Implementation state (13 October 2026)

The branch now contains the saved planner at `/trip-planner`, its dedicated `trip_plans` / `trip_plan_items` basket, immutable `trip_itineraries`, hotel/room/activity/attraction catalog, explicit cart transfer, protected print/PDF exports, queued account email, booking snapshots, admin scheduling editor, journey outbox and fulfillment-backed completion. Hotel choices are route bases; rooms are reservations. Attractions remain self-guided choices. Existing bookings without itinerary snapshots keep their existing workflow.

Scheduling is deterministic: directed Valhalla matrix times, fixed starts, visit durations, operating windows, hotel check-in/out assumptions, sequential hotel transfers, a lunch interval, buffers and return travel. Severe outdoor weather is screened; walking exposure before indoor visits is screened too. Forecasts must cover the complete interval and have fresh timestamps. Unknown or stale forecasts are provisional, never described as favorable weather. Impossible choices remain visible and prevent cart transfer until resolved. The report discloses heuristic limits, local times, assumptions, no live traffic/fare estimates and no operator safety clearance.

The original Haversine helper and straight-line travel rendering have been removed. Routes use real GeoJSON shapes. The client also checks Valhalla’s native `has_ferry` summary so ferry transfers are not presented as ordinary walking/driving legs; see [Valhalla 3.7 route documentation](https://raw.githubusercontent.com/valhalla/valhalla/3.7.0/docs/docs/api/turn-by-turn/api-reference.md). Missing precise activity meeting points cannot use the destination center as travel evidence. Matrix calls are bounded, with up to 100 pair-isolation fallbacks for malformed coverage batches.

Valhalla is running in Railway project `gracious-dedication`, production environment, as a separate service with a persistent `/custom_files` volume. Its graph covers Western Visayas, including the Boracay pilot. Limits are 1 GB RAM / 1 vCPU with one server thread. Live verification returned a walking path of about 1.27 km / 15 minutes with 44 geometry points, and a different driving path. The graph took about 28 seconds to build; observed idle memory was about 0.36 GB, not a long-term cost estimate. At the user's later request, a public Railway domain was restored at `https://valhalla-production-4481.up.railway.app` so the local Laravel app can call it. The ignored local `.env` points there and `php artisan routing:check` passed with the 1.27 km walking route and 44 geometry points. The deployed app and worker remain configured for `http://valhalla.railway.internal:8080`. The public endpoint is unauthenticated and can receive outside traffic, so monitor usage while it is exposed. No paid routing subscription is required; Railway compute, storage and network usage remain billable.

Admin → Planner Scheduling allows entrance/meeting coordinates, visit duration, indoor/outdoor classification, opening/closing times, fixed starts and hotel times to be verified. The optional attraction seeder adds names from the reference images with **no invented coordinates or hours**. Administrators must pin their real entrances before they can be scheduled. Do not silently increase the 150 m snap cutoff to make bad pins appear valid. The existing Henann sample pin failed this check during live testing and needs entrance verification.

Booking snapshots record the booked subset alongside the full plan; cart edits to planner dates/guest counts are rejected at checkout so the stored itinerary cannot silently disagree with the purchased trip. Later planner edits never rewrite accepted snapshots. Included and excluded services remain distinguished on the booking page. Each day and protected PDF retain the selected room name, quantity, guest count and stay dates from that version. Overlapping room quantities across cart lines are checked together.

Journey messages: an opted-in planning follow-up after 24 hours of inactivity, pre-trip reminder in the final 24 hours, departure check-in in the final 24 hours of the trip, review request after the 24-hour post-trip grace period, and opted-in destination suggestions after 30 days. A user receives at most one queued automated journey message in a 20-hour window. Marketing is off by default. Reminders can be disabled separately from booking/payment notifications. Database uniqueness, send-time cancellation/date/preference checks, a six-hour delivery window and Resend idempotency headers protect delivery. Ambiguous non-Resend send failures are marked uncertain rather than retried blindly. Delivery/bounce webhooks and retention automation remain operational follow-ups; there is no claim of exactly-once inbox delivery.

Automatic completion applies only to planner bookings with a known end, a 24-hour grace period, and every included service explicitly recorded as fulfilled. No-shows, pending attendance, cancellation requests and trip extensions block it. Admin fulfillment records are audited; the manual completion override requires a reason and fulfilled services. Reviews reject foreign booking item IDs, excluded items and unfulfilled planner services. Legacy completion remains admin-driven.

Production application code has **not** been published from this branch. The two migrations were applied locally. Before enabling the production journey workflow: deploy the reviewed application branch; retain the configured private routing variables; run `php artisan routing:check` inside the Railway app; verify actual inventory coordinates/schedules; set worker start command to `bash scripts/railway-worker.sh` so the existing worker also runs Laravel’s scheduler; then set `TRIP_JOURNEY_ENABLED=true` on app and worker. The flag defaults to false so routine local development does not start real campaigns. The supervisor restarts if either child stops, and the queue timeout remains below the database queue retry interval.

Verification passed 117 affected Pest tests (550 assertions) for routing, weather, planner ownership/generation, idempotent cart transfers, immutable/partial booking snapshots, exports, email consent/deduplication, cancellation/date changes, completion, admin evidence and review item binding. Pint, Vite build, Blade compilation and `git diff --check` passed. A Windows file lock in the first broad test run was resolved by using an isolated compiled-view path; the complete affected set then passed. The explicitly approved in-app browser verified the planner at 320px, 768px and desktop widths without page overflow and showed the saved room details. The generated A4 PDF was rendered and visually checked across three pages after correcting its day break. After public routing was restored, the local app generated a Boracay plan with a mapped 1.17 km walk to the selected activity, a 2.35 km round trip and numbered map route. Live production end-to-end planner use remains pending application deployment and verified inventory coordinates.
