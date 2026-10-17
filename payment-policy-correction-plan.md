# Payment policy correction plan

Status: implemented locally and verified on October 17, 2026. No push or deployment performed. Booking 613 was explicitly approved for correction to test mode; its total and initial payment deadline were preserved.

Verification: all 206 affected Pest tests passed (1,031 assertions), including mocked provider flows, deadline timezone round-trips, refunds, and reporting across midnight. Vite build and Blade compilation passed. Browser checks confirmed the customer message precedes payment, the actual deposit restriction is visible, all four sandbox methods are selectable, and the configurable flexible-date balance window appears in admin settings. The customer payment screen was checked at mobile and desktop widths.

Local configuration follow-up: the PayMongo test signing secret (`PAYMONGO_TEST_WEBHOOK_SECRET`) is missing. Test authentication and provider polling remain available; signed test webhook delivery requires the matching test webhook secret. Production credentials and webhook settings were not changed.

October 17 hotel-policy refinement: the default balance lead is now two days before the earliest confirmed trip/fixed service, with a 48-hour initial window. The local admin settings were explicitly saved with this two-day rule, while the flexible-date balance window remains seven days after approval. New approvals default to full payment when the normal initial window cannot fit before the balance deadline; an explicit earlier initial override may allow a deposit when strictly ordered. Existing approved terms remain unchanged, including bookings saved under the former seven-day rule. Existing production settings are not overwritten by the new configuration fallback; their admin values must be reviewed during deployment.

## Correct business rules

- Local bookings are testing bookings. An admin should not have to select Sandbox for every approval.
- Activities may legitimately have no execution date. Travelers choose when to do flexible activities around routes, weather, and their preferences. Payment must not manufacture or require an activity schedule.
- Payment deadlines govern money collection. Trip dates govern travel; optional itinerary dates are suggestions unless a service explicitly requires a fixed date.
- Preserve full payment, fixed 50% deposit, later balance collection, all four payment methods, and the existing verification and accounting protections.

## 1. Resolve booking payment environment centrally

Use one server-side environment resolver shared by approval, checkout, receipt verification, manual verification, reconciliation, and reporting.

- APP_ENV=local/testing: sandbox automatically; no per-booking live selector.
- APP_ENV=production: live bookings automatically. Existing sandbox-specific methods remain identifiable but cannot credit live bookings.
- Other environments: explicit deployment configuration; reject an unrecognized or missing payment mode instead of guessing live.
- Never infer safety from localhost URL, request Host, or browser-submitted payment_environment.
- Existing booking records retain their original mode for historical receipts. Local unpaid records marked live are handled through the controlled correction described below.

Local testing does not make a live credential a test credential. PayMongo must use its test key locally, Stripe its test key, and QR Ph Sandbox its existing test configuration. Preserve the production PayMongo live key/configuration separately. If a local sandbox credential is missing, explain which sandbox configuration is needed; never fall back to a live key.

Manual GoTyme remains available locally as an explicitly simulated receipt/submission workflow. Do not show a usable real bank QR/account as a test payment instruction. Admin verification creates test ledger entries. Production retains the actual GoTyme instructions and verification workflow. Cash/offline receipts follow the same mode separation.

For PayMongo, resolve the existing shared QR Ph driver's credentials, expected livemode, signing secret, attempt identity, and reconciliation by execution mode. Do not simply change the UI label or disable provider-mode checks. Persist mode on every request/attempt/receipt; idempotency and provider-reference uniqueness must remain mode-aware.

## 2. Separate payment anchor from activity execution dates

Determine one payment anchor at approval:

1. Use a confirmed booking-level trip start, snapshotted from its source trip plan. Use the earliest relevant start when a booking combines multiple trips.
2. Also inspect explicitly dated, fixed services such as room check-in; the earliest confirmed fixed commitment bounds payment collection.
3. Flexible activities with no date are ignored when resolving that anchor. Optional itinerary suggestions must not become mandatory payment commitments.
4. If no trip start or fixed service date exists, use an approval-relative balance deadline under the configurable fallback below. No activity date is required.

Record anchor source, date/time, and the chosen deadline rule in the approved payment snapshot. Do not depend on later edits to a mutable trip plan or silently change terms when a traveler rearranges suggested activities. Audit existing date storage and transfer snapshots before implementing this resolution; confirm which fields represent confirmed commitments versus generated suggestions.

Use Asia/Manila consistently. A date-only start needs an explicit documented payment cutoff time, not an assumed actual activity start. Reuse the existing 9 AM convention initially, display it clearly, and keep it distinct from a traveler activity appointment.

## 3. Deadline calculation

Keep these independent admin settings, with clearer labels:

| Setting | Proposed default | Meaning |
| --- | --- | --- |
| Initial payment window after approval | Existing 48 hours | Time to pay full or the 50% deposit |
| Balance due before trip/fixed service | Existing 7 days | Used when a confirmed payment anchor exists |
| Balance window for bookings without travel dates | 7 days after approval | Fallback only when no confirmed payment anchor exists |
| Full payment cutoff before trip/fixed service | Existing 24 hours | Used for near-term full-payment bookings |

An approval-relative fallback is recommended because it gives the reservation a finite payment obligation without assigning activity dates. Measure it from approval rather than from the eventual deposit receipt, so the final due date is known before the customer pays and delayed deposits do not move it.

Calculation:

- Compute initial deadline = approval time + initial window.
- With an anchor: balance deadline = anchor minus configured lead days.
- Without an anchor: balance deadline = approval time + configured undated-booking balance window.
- Offer deposit only when enabled, both installments meet the existing minimum, and there is a real positive interval between initial payment deadline and balance deadline.
- When an anchored balance deadline would require shortening the initial window, show the proposed dates to the admin before approval. An explicit earlier initial deadline can make a deposit eligible if both deadlines are still in the future and properly ordered.
- If there is no valid deposit interval, offer full payment with the precise reason and a future deadline before the confirmed service. Reject approval if no valid full-payment deadline exists; require explicit admin review.
- For the undated fallback, validate settings so the balance window exceeds the initial window. Do not accept contradictory settings or silently invent a deadline.
- Missing optional activity dates alone never disable deposits.

Initial deadline override is available during approval. After approval, extension targets the currently outstanding installment, is logged, and notifies the customer. An initial extension cannot cross the balance deadline. Changing the balance deadline is a separate explicit action with validation against confirmed travel commitments. Offline money recording remains a separate action.

## 4. Examples and booking 613

All examples use Philippine time and assume approval October 16, 2026 at 9:12 PM, total PHP8,500, and initial window 48 hours.

| Booking | Expected terms |
| --- | --- |
| Flexible activities only, no trip dates | Deposit PHP4,250 by October 18 at 9:12 PM; remaining PHP4,250 by October 23 at 9:12 PM using the proposed 7-day fallback |
| Confirmed trip starts November 1; activities undated | Deposit allowed; balance due October 25 at the displayed cutoff time; optional activity dates do not block it |
| Room starts October 23; activities undated; 7-day lead | Full payment only because balance cutoff October 16 at 9 AM has passed; activity dates are not the reason |
| Same October 23 booking, 3-day lead chosen before approval | Balance due October 20 at 9 AM; default initial deadline October 18 at 9:12 PM permits deposit |

Booking 613 therefore still has a genuine near-term cutoff issue under its saved 7-day terms, even after removing the incorrect missing-activity-date restriction. Its extended October 21 initial deadline also falls after the October 20 balance deadline in the 3-day example. It must receive an explicitly reviewed earlier initial deadline if revised to those deposit terms. Do not promise that removing the activity restriction alone makes this booking deposit-eligible.

## 5. Existing local records and compatibility

Provide a preview-first local-only correction operation with explicit booking IDs or a scoped selection. Show original mode, proposed mode, policy rule, deadlines, receipt/request counts, and blockers before applying anything.

- Eligible for automatic correction: unpaid local records without payment attempts/requests, receipts, refunds, submitted transfer claims, or other provider references. Preview refreshed deadlines; never silently reset expired deadlines.
- Active attempts or submitted manual claims: block conversion until the existing workflow resolves them and the history is reviewed. Do not relabel a pending live payment as test.
- Existing genuine payments/refunds, including deliberate local live tests, remain accurately recorded as live historical activity. Local operational records may be test data while their provider transaction was still real money.
- Existing paid/part-paid policies and production bookings are not retroactively recalculated. Historical ledger mode is immutable.
- Legacy booking payment routes must use the environment resolver too; securing only the new installment screen would leave a live-payment path locally.
- Recheck deposit eligibility under corrected rules only through the explicit reviewed correction operation for eligible unpaid records. Keep prior terms/audit history.

## 6. Customer/admin interface

- Keep message card above payment card.
- Display initial amount/date and remaining amount/date together before checkout.
- Explain deadline source: before trip, before fixed service, or days after approval for a flexible-date booking.
- Display flexible activities as “Date chosen by traveler”; do not ask for invented dates to enable payment.
- Approval preview shows deposit eligibility and its actual reason before admin submits.
- Local UI has a prominent testing indicator; PayMongo/Stripe/QR Ph use test providers, manual GoTyme is simulated. Remove the misleading per-booking Live default locally.
- Production keeps the existing real gateway choices and its actual manual GoTyme workflow.
- UI labels, notifications, expiry jobs, reports, and admin summaries use the same saved terms and payment mode.

## 7. Implementation map

- [MODIFY] config/services.php and .env.example: explicit environment and sandbox/live provider configuration without copying or exposing secrets.
- [NEW] app/Services/Payment/PaymentEnvironmentResolver.php: central server-side mode resolution and credential selection contract.
- [MODIFY] app/Services/Payment/BookingPaymentPolicy.php: ignore undated flexible activities, snapshot confirmed anchor/fallback, validate ordered deadlines.
- [MODIFY] config/system_settings.php; app/Http/Requests/Admin/UpdateSystemSettingsRequest.php; app/Services/SystemSettingsService.php: configurable no-travel-date balance window and cross-field validation.
- [MODIFY] app/Http/Controllers/Admin/AdminBookingController.php; app/Http/Requests/Admin/StoreAdminBookingRequest.php: resolver-driven approval and deadline preview; no client-controlled local live mode.
- [MODIFY] existing trip-to-booking transfer/checkout code after tracing its exact path: preserve booking-level confirmed trip dates without assigning activity dates. Reuse existing booking snapshots if adequate; introduce an additive column/migration only if existing storage cannot represent the anchor reliably.
- [MODIFY] app/Services/Payment/InstallmentPaymentService.php; PaymentService.php; QrphPaymentService.php; GoTymePaymentService.php; Drivers/QrphDriver.php; Drivers/PaymongoDriver.php; Drivers/StripeDriver.php: consistent mode and credential routing, manual simulation, preserved provider verification.
- [MODIFY] app/Http/Controllers/BookingPaymentController.php and PaymentWebhookController.php; existing reconciliation commands: enforce mode on legacy and installment paths, callbacks, polling, and scheduled reconciliation.
- [MODIFY] app/Services/Payment/BookingPaymentAccountingService.php and existing admin reports only where necessary: preserve immutable receipt mode and exclude simulated money from real collections.
- [MODIFY] resources/views/admin/bookings/_payment-approval.blade.php; _payment-admin.blade.php; create.blade.php; show.blade.php; admin/settings/index.blade.php: approval preview, deadline meanings, local test indicators.
- [MODIFY] resources/views/booking/installment-pay.blade.php; _payment-summary.blade.php; gotyme.blade.php; show.blade.php and existing legacy checkout views: accurate amounts, flexible dates, test/manual instructions.
- [NEW] local-only Artisan correction command, created through Artisan: preview first, transactional audited apply, production prohibition, blocked-record reporting, repeat-safe behavior.
- [MODIFY] tests/Feature/BookingInstallmentTest.php; PaymentGatewayIsolationTest.php; QrphPaymentTest.php; GoTymePaymentTest.php; AdminSettingsTest.php and affected approval/checkout tests.

No dependencies need to change. Existing unrelated work must be preserved.

## 8. Verification and release gates

Regression coverage must prove:

1. Undated activities with dated room/trip are eligible when the resulting deadlines allow it.
2. Activity-only bookings with no dates receive deterministic fallback terms and correct 50/50 amounts.
3. Optional itinerary rearrangement leaves approved terms unchanged; confirmed trip changes require explicit review.
4. Near-term cutoff and invalid/deadline-order cases explain the actual reason; booking 613-shaped cases do not blame flexible activities.
5. Local mode is test even with live keys present; all creation paths, legacy endpoints, workers, webhooks, and manual receipts enforce this.
6. PayMongo test, Stripe test, QR Ph Sandbox, and simulated manual GoTyme each support full/deposit/balance without any live provider call or real bank instruction.
7. Production PayMongo/manual workflows remain live; test events cannot settle live bookings, and live events cannot settle sandbox bookings.
8. Missing test keys produce an actionable configuration error, never a live fallback; wrong mode/amount/currency/signature are rejected.
9. Local correction previews, refuses provider-linked records, preserves history, cannot run in production, and is safe to repeat.
10. Existing idempotency, refund accounting, late-payment review, concurrency locks, quote validation, expiry and notification behavior still pass.

Run affected Pest suites, required Pint, Blade compilation, build, and browser checks on customer/admin pages including mobile. Never create an actual charge or send a real manual transfer to verify sandbox behavior. No push/deployment is included in this planning request.
