# RSC implementation plan and Lucky discount feasibility

Prepared: 24 October 2026, Asia/Manila.

Status: proposed plan only. No application changes, migrations, environment changes, emails, payments, or deployments are authorized by this document. The existing `implementation_plan.md` is a historical PayMongo plan and is preserved.

## Confirmed scope

- Address the gaps identified in the RSC comparison, preserving the features already implemented.
- **Every booking source must be considered for trip reminders:** ordinary catalog checkout, Lucky, chatbot, Trip Planner, mixed baskets, and admin-created bookings. A saved itinerary snapshot is not an eligibility requirement.
- Include a proposed admin-configurable Lucky discount system: an issued code belongs to a specific generated itinerary and traveler; checkout applies it only to eligible items.
- Current work is feasibility assessment and planning. Proposed commercial policies below are recommendations, not configured business rules.

## Current foundations and verification limits

Lucky acceptance creates grouped cart lines through `lucky_group_id`. It does not persist a trusted generated-offer identity before acceptance. Booking snapshots currently preserve planner provenance but do not preserve Lucky group provenance. Checkout has no coupon input or validation, and existing `discount_amount` represents passenger-category discounts. Payments and deposit calculations use the final booking `net_amount`.

Trip journey eligibility, date resolution, payload generation, completion checks, and delivery assume saved itineraries and registered users. The user-level 20-hour reminder cap can also suppress a second trip's notice. Simply removing `whereNotNull('trip_itineraries')` is insufficient.

The earlier review found guided chat enabled, trip journey processing disabled locally, and a missing sandbox PayMongo webhook secret. Database-backed installment settings could not be read. Recheck these values in the intended environment; they are not production findings. Forty-six focused JavaScript tests passed during that review, but PHP feature tests could not start because of a bootstrap-path error. No live email/payment or Brave browser verification has been completed.

## Proposed delivery order

1. Establish a runnable verification baseline and resolve effective settings without exposing credentials.
2. Extend trip reminders to every booking source and verify operational delivery.
3. Complete deposit/cancellation/rebooking behavior and PayMongo verification.
4. Add durable Lucky offer identity, admin promotions, and checkout redemption.
5. Expose Lucky in chat using the same offer service.
6. Complete itinerary price/reference presentation and clarify routing/weather evidence across flows.

## 1. Trip reminders for all bookings

### Behavior

Build a common booking journey context from included booking items. Enrich it with saved itinerary details when available; do not require a planner record or generate a fictional itinerary for catalog bookings.

Resolve dates from confirmed room stays, dated activities/packages/add-ons, stored service dates, and explicit trip extensions. Handle several distinct trips in one booking as identifiable service groups/date windows rather than assuming one continuous trip. Existing snapshots may help identify groups; record provenance for new Lucky and planner bookings. Ambiguous historical grouping must be reported rather than guessed.

An undated activity must not suppress reminders for a dated hotel in the same booking. Show undated services as “date to be arranged.” An entirely undated booking participates in payment/status communication and receives a date-confirmation follow-up; date-triggered reminders begin only once dates are confirmed. Do not invent a departure date.

Use account email for registered owners, retaining verified/unverified email eligibility and preferences. Support admin-created guest bookings through their saved contact email for transactional service messages. Never expose a booking through a code-only public link: use the existing authenticated destination for owners; guest mail can contain the necessary summary and support instructions without adding public account access. Guest marketing/review-account access is outside the transactional fallback and must not be silently enabled.

Retain pre-trip and departure timing initially. Keep review invitations after completion, with fulfillment evidence and an unreviewed reviewable service. Use activity-trip completion wording for activity-only trips instead of hotel checkout wording. Marketing follow-ups remain opt-in; “all trips” does not override preferences or authorize marketing.

Separate booking/payment eligibility from the presence of itinerary JSON. Include deposit-secured reservations in the journey assessment, but make outstanding-balance messages accurate and avoid calling overdue trips fully confirmed. Continue using the payment notice processor for balance demands rather than sending competing versions of the same deadline notice.

Deduplicate by booking/service group, message kind, and relevant date revision. Replace blanket cross-trip suppression with per-trip limits or an aggregate message that includes every eligible trip. Recheck recipient, status, dates, preferences, included items, and fulfillment at delivery. Cancellation, rescheduling, and stale queued payloads must not send outdated information. Preserve idempotent delivery and track queued, provider-accepted, suppressed, failed, and uncertain outcomes honestly.

### File scope

- [NEW] `app/Services/BookingJourneyContext.php`: normalized recipients, service groups, dates, included services, and revision signature.
- [MODIFY] `app/Services/TripJourneyService.php`, `app/Jobs/SendTripJourneyMail.php`: source-independent selection, eligibility, composition, completion and deduplication.
- [MODIFY] `app/Mail/TripJourneyMail.php`, `resources/views/emails/trip-journey.blade.php`, `resources/views/emails/trip-journey-text.blade.php`: catalog/Lucky/guest summaries and conditional links.
- [MODIFY] `app/Services/BookingRequestService.php`, `app/Http/Controllers/Admin/AdminBookingController.php`: preserve service provenance and confirmed date context.
- [NEW] additive migration generated as `extend_trip_journey_messages_for_booking_recipients`: permit a booking contact recipient without a user and store service-group/revision data. Inspect the live schema before designing constraints; do not rewrite the existing migration.
- [MODIFY] `app/Console/Commands/ProcessTripJourney.php`, `config/services.php`, `.env.example`, `routes/console.php`: activation guidance and useful processing diagnostics. Actual environment activation is a rollout action.
- [MODIFY] `tests/Feature/TripJourneyTest.php`, `tests/Feature/ReviewTest.php`; [NEW] `tests/Feature/BookingJourneyContextTest.php`.

### Acceptance

Test ordinary room bookings, activity-only bookings, packages, Lucky, chatbot/planner, mixed baskets, admin guests, several bookings due on one day, and multiple trip windows in one booking. Test mixed dated/undated services, cancellations, partial payments, unpaid/overdue cases, rescheduling, extensions, exclusions, duplicate scheduler runs, failed delivery retries, preferences, and fulfillment gating. A dated catalog booking with null `trip_itineraries` must receive the appropriate reminder. Actual receipt at a designated test inbox is separate operational evidence.

## 2. Reservation payments, cancellation, rebooking and refunds

Recommended reservation interpretation: a pending request may have a bounded temporary inventory hold; a secured reservation requires a verified eligible deposit or full payment. Preserve full payment as an alternative and distinguish these states clearly. Verify and enable the existing installment policy for new approvals in the intended environment; do not retroactively rewrite existing payment agreements. Resolve the inventory-hold interpretation before any broad stock-policy change.

Allow an eligible paid or partially paid traveler to request cancellation for admin review. Define the cutoff, refundable amounts, and applicable fees explicitly rather than inventing them in code. Denying or withdrawing a request restores its previous valid state without blocking remaining payments. Payment reconciliation during cancellation must retain receipts and route late/conflicting receipts for review.

Make rebooking clearly start a new request with new dates and current inventory/pricing. Link it to the old booking for audit. Payment credit or promo entitlement must not transfer automatically. Keep the current externally processed refund workflow accurate, with a request/outcome trail and receipt reference. Automated provider refunds would be a separate expansion after refund policy and provider capabilities are verified; the RSC does not itself require automatic transfers.

File scope:

- [MODIFY] `app/Services/Payment/BookingPaymentPolicy.php`, `app/Services/BookingExpiryService.php`, `config/system_settings.php`, `resources/views/booking/_payment-summary.blade.php`: only where verification identifies a policy or state gap.
- [MODIFY] `app/Http/Controllers/BookingPaymentController.php`, `app/Http/Controllers/Admin/AdminBookingController.php`, `app/Services/BookingRequestService.php`, `app/Services/Payment/BookingPaymentAccountingService.php`.
- [MODIFY] `resources/views/booking/show.blade.php`, `resources/views/admin/bookings/show.blade.php`, applicable existing cancellation/payment notifications.
- [NEW] additive booking provenance/request-outcome migration only for fields not already represented by booking history; exact columns follow schema inspection.
- [MODIFY] `tests/Feature/CancellationRequestTest.php`, `tests/Feature/BookingInstallmentTest.php`, `tests/Feature/FlexibleBookingPaymentTest.php`, `tests/Feature/BookingFlowTest.php`.

Acceptance: verified deposits secure the intended reservation state; unverified claims do not. Cancellation requests do not themselves issue refunds. Paid travelers can request review; denial/withdrawal preserves valid payments. Rebooking uses current dates/prices without duplicating receipts or carrying credits implicitly.

## 3. PayMongo and email operational verification

Reuse the existing integrations. Diagnose the PHP bootstrap/database issue before relying on automated results. Run payment configuration checks, verify the matching test/live credentials and signing secrets, scheduler/worker operation, public callback routes, sender configuration, and failed jobs. Inspect provider documentation through the approved tools before any provider-dependent implementation.

Use mocked HTTP/mail tests first, then a controlled PayMongo sandbox transaction and a designated test inbox when authorized. Reconciliation must recover a missed webhook without double-crediting; signed duplicate events must remain harmless. Verify booking amount, payment mode, reference, deposit/balance settlement, and final status. Do not equate a configured key or queued email with a successful payment or inbox delivery.

- [REVIEW; MODIFY ONLY FOR CONFIRMED DEFECTS] `app/Console/Commands/CheckPaymentConfiguration.php`, `app/Console/Commands/ReconcileQrphPayments.php`, `app/Services/Payment/Drivers/QrphDriver.php`, `app/Services/Payment/PaymentEnvironmentResolver.php`, `app/Http/Controllers/PaymentWebhookController.php`, `config/mail.php`, `config/services.php`, `scripts/dev.js`, `scripts/railway-worker.sh` if present in the deployment.
- [VERIFY] `tests/Feature/QrphPaymentTest.php`, `tests/Feature/PaymongoSecurityTest.php`, `tests/Feature/PaymentGatewayIsolationTest.php`, `tests/Feature/TripJourneyTest.php`.

Deployment, production configuration changes, real payments, and customer mail remain distinct execution actions, not actions performed by this plan.

## 4. Admin-configurable Lucky discount codes — feasible proposed design

### Data and ownership

Use three focused records: a promotion campaign defining admin rules; a Lucky offer storing the generated itinerary, owner, issued code, rule revision and expiry; and a redemption linking the offer to the booking and eligible booking items. Existing `lucky_group_id` remains useful basket presentation/provenance, but is not sufficient proof of entitlement.

Persist the server-generated itinerary before displaying a code. Bind the code to its owner and immutable selection signature: destination, room, activities, quantities, traveler counts, applicable dates, and pricing-rule revision. Bind the accepted offer to its exact basket lines. Repeated acceptance must be idempotent. Preserve the offer identity on booking items because the cart disappears after checkout. Do not accept browser-supplied discount values, eligibility, or arbitrary group IDs as authority.

Admins configure active/inactive state, campaign name, code prefix or managed unique-code pool, fixed peso or percentage discount, percentage cap, issuance/redemption windows, optional travel windows, minimum eligible spend, destination/listing restrictions, traveler/night conditions, total and per-traveler limits, and combination rules. Optional random selection uses explicit admin-defined eligible offers/weights and bounded values. No active eligible promotion means no promised discount.

Recommended starting policy: one issued code per exact Lucky offer, owner-only, one redemption, complete eligible group required, and no stacking of multiple Lucky codes on the same group. Random awards are persisted, not rerolled on refresh. Define an issuance/reshuffle allowance to prevent unlimited searches for the largest reward. Actual amounts, dates, award probabilities and limits require business selection; this plan does not invent them.

### Traveler flow

1. Generate a Lucky itinerary. Display its offer code, terms, expiry, eligible subtotal and estimated saving, with a Copy action.
2. Accept it into the basket. Keep offer identity/code discoverable and clearly associate them with that group.
3. Paste the code into checkout and press Apply. The server validates ownership, issuance, itinerary identity, selected items, date windows, limits and current prices.
4. Display the eligible group and a separate Lucky discount line, then the revised total. Unrelated basket items retain their ordinary price.
5. Revalidate under a database transaction when submitting the booking; reserve redemption capacity atomically. A successful preview does not consume the code.
6. Preserve the accepted offer terms and applied item allocations on the booking. Payments charge the discounted net amount; installment amounts are based on that same net amount.

Example only: an eligible Lucky group costs PHP 12,000 and another ordinary item costs PHP 3,000. A 10% Lucky offer saves PHP 1,200; checkout total becomes PHP 13,800, before other separately applicable adjustments. The ordinary item receives no Lucky discount.

### Pricing and lifecycle rules

Calculate in integer centavos with explicit percentage rounding and caps. Separate promotional discounts from existing passenger-category and admin discounts. Define the eligible basis and adjustment order once in a shared server pricing path used by preview, submission and admin approval. Never remove required passenger adjustments merely because a promotion is present. Block unsupported zero/below-gateway-minimum amounts or define an explicit zero-payable booking path; do not send invalid collections to providers.

Define changed-itinerary behavior: changing a bound room/activity/date/quantity/pax invalidates the offer until a new eligible offer is issued; changes to unrelated items do not. Price changes require a refreshed quote and visible totals, not silent acceptance of a stale browser price.

Reserve redemption at booking submission; finalize at the agreed first verified qualifying payment, with reconciliation for late receipts. Release or retire it under a documented policy if an unpaid request expires/rejects/cancels. Failed payment retries on the same booking must not consume a second use. Never automatically make a paid/refunded code reusable. Use unique constraints and row locks for code ownership, usage limits and concurrent checkout.

If an admin excludes an eligible item before approval, re-evaluate the complete-group condition and discount allocation before creating payment terms, and explain changes to the traveler. After payment begins, use the existing controlled quote-change/refund process. Campaign edits apply prospectively; stored booking terms and receipts remain auditable. Suspension should stop new awards/redemptions according to explicit terms, not silently rewrite accepted bookings.

### Proposed file scope

- [NEW] `app/Models/Promotion.php`, `app/Models/LuckyOffer.php`, `app/Models/PromotionRedemption.php`, their factories, and additive migrations generated as `create_promotions_table`, `create_lucky_offers_table`, `create_promotion_redemptions_table`, `add_lucky_offer_pricing_to_bookings`. Exact columns/indexes follow PostgreSQL schema inspection; include per-item allocation/provenance and uniqueness enforcement.
- [NEW] `app/Services/LuckyOfferService.php`, `app/Services/PromotionService.php`, `app/Services/BookingQuoteService.php`. Extract the relevant existing pricing calculations rather than maintain independent preview/submission formulas.
- [MODIFY] `app/Services/LuckyItineraryService.php`, `app/Http/Controllers/LuckyItineraryController.php`, `app/Services/CartService.php`, `app/Models/CartItem.php`: durable offer issuance and idempotent acceptance.
- [NEW] `app/Http/Controllers/Admin/AdminPromotionController.php`, `app/Http/Requests/Admin/StorePromotionRequest.php`, `app/Http/Requests/Admin/UpdatePromotionRequest.php`, `routes/adminPromotionRoute.php`, `resources/views/admin/promotions/index.blade.php`, `resources/views/admin/promotions/_results.blade.php`, `resources/views/admin/promotions/_form.blade.php`. Use existing admin auth/2FA, audit and live-list conventions.
- [NEW] `app/Http/Controllers/CheckoutPromotionController.php`, `app/Http/Requests/ApplyPromotionRequest.php`, `routes/promotionRoute.php`; [MODIFY] `routes/web.php` to require feature routes.
- [MODIFY] `app/Http/Controllers/CheckoutController.php`, `app/Services/BookingRequestService.php`, `app/Models/Booking.php`, `app/Models/BookingItem.php`, `app/Http/Controllers/Admin/AdminBookingController.php`: validation, atomic reservation, allocation and approval repricing.
- [MODIFY] payment accounting/expiry services where redemption state follows verified money or closure; keep PayMongo itself charging the authoritative booking net amount.
- [MODIFY] `resources/views/lucky/index.blade.php`, `resources/views/checkout/index.blade.php`, `resources/views/cart/index.blade.php`, `resources/views/components/frontend/cart-drawer.blade.php`, booking/payment/admin summaries and applicable booking emails.
- [NEW] `tests/Feature/AdminPromotionTest.php`, `tests/Feature/LuckyPromotionTest.php`, `tests/Feature/CheckoutPromotionTest.php`; [MODIFY] existing Lucky, booking, installment, cancellation and payment tests for interactions.

Acceptance includes wrong user/group, forged client totals, expired/not-yet-valid codes, same code races, last campaign slot races, mixed baskets, partial selections, changed itinerary, admin exclusions, fixed/percentage caps, rounding, passenger adjustments, payment retries, cancellation/rebooking, and deposit/balance calculations from the discounted amount.

## 5. Lucky access from the chatbot

Add a visible Lucky action and an explicit conversational action that uses the same generation/offer service as the dashboard. Reuse existing trip detail collection. Render the same persisted itinerary/code/terms and support the same acceptance path. Do not let the language model invent promo codes, dates, percentages or eligibility. Maintain current authentication rules; a guest can discover the feature and be guided to sign in before a personal offer is issued.

- [MODIFY] `app/Services/Chat/IntentRouter.php`, `app/Services/Chat/ChatbotService.php`, `app/Services/Chat/DialogueManager.php`, `resources/views/components/frontend/chat-widget.blade.php`.
- [NEW] `tests/Feature/ChatbotLuckyTest.php`; [MODIFY] `tests/Feature/IntentRouterTest.php`, `tests/Feature/LuckyItineraryTest.php` where shared contracts change.

Acceptance: dashboard/chat use identical prices and offer eligibility; chat supports budget/date/pax input without bypassing offer limits; opening text and existing ordinary itinerary intents continue to work.

## 6. Itinerary documents, prices, routes and weather

Highlight the booking reference in a prominent printable block. Drafts retain a clearly labeled plan/version reference. Include itemized room/activity estimates, quantities/pax, dates, discount allocation where actually applied, totals, currency, estimate timestamp and exclusions. Booked documents use stored booking prices and purchased subsets; draft documents use labeled estimates. Old snapshots without pricing render “estimate unavailable” rather than silently using today's prices as historical prices.

Connect Lucky and chatbot offers to an explicit checked-schedule action using existing planner logic. Keep draft versus checked status visible. Preserve forecast-horizon/staleness warnings in documents as well as screens. Include route-quality labels and location precision in exported documents; dashed lines and destination-center pins must not become claims of verified road/boat travel. Review missing coordinates as a catalog-quality task without inventing locations. Actual ferry timetables/routes require a separate supported data source; do not add a provider/dependency implicitly.

- [MODIFY] `app/Services/ItineraryService.php`, `app/Services/TripPlannerService.php`, `app/Services/Chat/ChatbotTripPlanService.php`, `app/Http/Controllers/BookingConfirmationController.php`, `app/Notifications/TripItineraryReady.php`.
- [MODIFY] `resources/views/trip-planner/document.blade.php`, `resources/views/trip-planner/draft-document.blade.php`, `resources/views/trip-planner/index.blade.php`, `resources/js/trip-planner.js`, and Lucky/chat handoff views.
- [MODIFY] `tests/Feature/TripPlannerTest.php`, `tests/Feature/ChatbotTripPlanTest.php`, `tests/Feature/WeatherSchedulingTest.php`, `tests/Unit/TripPlannerMap.test.js`; retain routing regression coverage.

Acceptance: paper/PDF references are legible; priced eligible items and exclusions reconcile; no pending/unpurchased item appears as paid; no missing weather is fabricated; no straight-line connector is described as an actual route. Inspect generated PDFs and use the required Brave Playwright MCP for browser verification when available.

## Verification and rollout gates

Run affected Pest suites against the dedicated PostgreSQL test database with external mail/HTTP faked, plus relevant Node suites. Resolve the prior runner problem first. After implementation, format modified PHP with `vendor/bin/pint --dirty --format agent`, build assets for UI changes, and run the complete project test command before rollout. No tests were added or rerun solely for this planning document.

Separate code readiness from environment readiness. Record effective installment/journey flags, queue/scheduler status, provider mode, sandbox transaction outcome and test inbox receipt. Introduce promotions disabled until actual terms are configured. Introduce source-independent journey processing with reviewed date/recipient coverage before activation to avoid sending a backlog of stale messages. Existing campaigns, accepted offers and historical bookings require migration compatibility tests.

## Business decisions to resolve before dependent implementation

1. Actual Lucky discount values/caps, award frequency, campaign dates, eligible inventory, minimum spend and usage limits.
2. Combination order with passenger/admin adjustments; whether several separate Lucky groups may each redeem one code in a booking.
3. Offer expiry at submission versus payment, abandoned-request release policy, and whether qualifying itinerary edits require reissuance (recommended).
4. Cancellation cutoff/refund/rebooking-credit rules and whether manual external refunds meet the intended process.
5. Temporary unpaid stock hold versus deposit-only inventory reservation.

The all-booking-source reminder requirement is confirmed and is not an open decision. Safe independent implementation can be prepared without inventing the commercial decisions above, but current authorization remains planning/assessment only.
