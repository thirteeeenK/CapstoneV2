# Full payment or 50% reservation deposit — implementation plan

Prepared for SunnyTrips on October 16, 2026. The approved plan is implemented locally; see the verification and rollout record at the end. Railway deployment and live deposit acceptance remain outstanding.

## 1. Product decision and proposed payment deadlines

Confirmed choice: offer **Pay in full** and **Pay 50% to reserve**. Do not accept customer-entered custom online payment amounts in this first version.

Keep 48 hours as the default initial payment window after admin approval, with an admin-configurable duration. Within it, the customer must complete either full payment or the qualifying deposit. A deposit paid successfully must stop the original unpaid-booking expiry rule from releasing that reservation. Examples below use the default duration; implementation must use the policy snapshot rather than a literal 48.

Recommended remaining-balance policy, proposed for owner review:

- Balance due 7 calendar days before the earliest included room check-in, activity, or package start, using Philippine time.
- If approval occurs on or after that cutoff, require full payment; do not offer a deposit with a balance deadline already due.
- Set the initial due time to the earlier of approval + 48 hours and the applicable payment cutoff. For eligible deposit bookings the cutoff is the balance deadline; for near-term full-payment-only bookings the proposed cutoff is 24 hours before the earliest service.
- If approval is already inside that final 24-hour cutoff, require staff to set an explicit feasible deadline before the first service. Do not generate a past deadline or grant a default 48 hours extending beyond the trip.
- A missing or ambiguous service date requires an admin-confirmed date before deposits can be offered. Do not guess from booking creation or a later item in a multi-item trip.
- Snapshot the percentage, total, due dates, and policy version when the approved quote is issued. Global policy changes affect future approvals, not existing customer agreements.
- At the balance deadline, mark the reservation overdue, stop routine payment creation, notify staff/customer, and retain its financial history and inventory hold while staff resolve it. Version 1 does not silently forfeit deposits, cancel paid reservations, or initiate refunds. Staff can extend the deadline, approve a late collection, or cancel with an explicit refund disposition.

Seven days is a configurable business default, not a PayMongo requirement. An admin deadline extension must carry a reason and audit history. Post-deposit automatic cancellation, forfeiture, and non-refundable deposit terms are separate owner decisions; this plan does not assume consent to them.

Example: admin approves on November 1 at 10:00 AM, trip begins November 20 at 9:00 AM. Initial payment is due November 3 at 10:00 AM; the remaining balance is due November 13 at 9:00 AM. For a November 5 trip approved November 1, only full payment is available, with the initial deadline capped before the service.

### Admin Settings → Booking & payments

Include this settings section in the first release. Reuse the existing database-backed `SystemSettingsService`, section revision checks, admin two-factor protected routes, validation, and `AdminAuditService`; do not create a second settings store or require a Railway redeploy for routine policy changes.

| Admin control | Default | Proposed validation/behavior |
| --- | --- | --- |
| Initial payment window after approval | 48 hours | Positive whole hours, proposed range 1–720; optional hours/days display control converts to one canonical duration |
| Offer reservation deposits | Enabled when the feature is released | Enables full-or-50% choice for future eligible approvals; disabling it does not strand existing deposit customers |
| Deposit required | 50% | Display fixed policy in version 1; arbitrary percentages are outside the confirmed scope |
| Remaining balance due before first service | 7 days | Positive whole calendar days, proposed range 1–90; Philippine timezone |
| Full-payment cutoff before first service | 24 hours | Positive whole hours, proposed range 1–72; used to cap initial deadlines for near-term bookings |

The numeric ranges are proposed operational bounds, not provider requirements. Validate the combined policy and preview the effective deadlines: a configured 72-hour window may be shortened by the trip cutoff. The screen must explain why the selected duration is a maximum, not an unconditional extension past travel dates. For date-less or inside-cutoff approvals, require an audited per-booking deadline override that is still before the first service.

Resolve effective policy with this precedence: an explicitly authorized per-booking override, otherwise the approved booking's immutable snapshot, otherwise current saved admin settings for a new approval, otherwise documented configuration defaults. Display the settings revision and the exact resulting Philippine-time deadlines before approval.

Changing 48 to 72 hours affects new approvals only. It must not silently shorten, extend, expire, or re-open existing bookings. Extend an existing booking separately on its admin detail page, require a reason, preserve the old/new deadline audit, notify the customer, and invalidate stale reminder schedules. If a provider session is active, resolve it before shortening a deadline; do not claim that changing a database date invalidates an already issued payable QR/session. Increasing a deadline does not extend a provider's QR expiry; renewal still follows its safe provider lifecycle.

The current duration is hard-coded in `AdminBookingController.php` at the two `now()->addHours(48)` sites: approval of a customer request and creation of an already-approved admin/proxy booking. `Booking::isPaymentDeadlinePassed()` and `ExpireBookings` compare against the stored deadline; they do not independently calculate 48 hours. Update both producers through `BookingPaymentPolicy` and preserve stored-deadline checks.

Also replace literal 48-hour customer messaging in `BookingPaymentController.php`, `BookingConfirmationController.php`, `BookingExpiryService.php`, `ExpireBookings.php`, `BookingApproved.php`, `BookingExpired.php`, checkout/booking views, and the booking context assembled in `Chat/ChatbotService.php`. Use each booking's snapshot and actual deadline. The separate Gemini report observation about pending requests awaiting admin review for 48 hours is not the customer payment window and should not be changed by a global text replacement.

Do not conflate these clocks: awaiting admin approval, initial payment window, remaining balance deadline, and QR/session expiry. This release makes the payment windows configurable; an automatic timeout for never-reviewed pending requests would be a separate policy. A PayMongo QR may expire before the booking payment window and be safely renewed without extending the booking deadline.

## 2. What exists today and why this needs more than a checkout toggle

Verified against the current local code:

| Area | Existing behavior | Required change |
| --- | --- | --- |
| Booking money | `net_amount` is the amount due after approval adjustments; `payment_status` already supports unpaid/partial/paid/refunded | Keep the quote total and distinguish it from each collection amount |
| Manual GoTyme | `GoTymePaymentService` records claimed/verified transfers, totals verified money, and uses partial status until fully covered | Reuse verification evidence; add qualifying-deposit and common balance accounting |
| PayMongo/QR Ph | `QrphPaymentService::amount()` charges the full net amount; payable guard accepts only unpaid bookings; settlement calls `markPaid()` | Charge an immutable payment request; a paid deposit must leave a balance |
| QR reuse | `latest()` is scoped by booking and live/test mode; a previously paid intent is returned | Scope reuse by collection request; a balance needs a new intent and QR |
| Stripe | Checkout amount and idempotency key are derived from the whole booking; return path verifies a boolean | Bind Checkout to a payment request and verify its exact amount, reference, currency, mode, and completed payment |
| Webhooks | PayMongo has durable attempts and transactional receipt processing; Stripe uses a booking-level event receipt and `markPaid()` | Both must call the same transactional money-posting operation; event receipt alone cannot replace receipt uniqueness |
| Expiry | `BookingExpiryService`, `ExpireBookings`, and booking display can expire any approved booking after its deadline | Qualifying deposits stop initial expiry; balance expiry is a separate decision |
| Inventory | `Booking::HOLD_STATUSES` already includes pending/approved/paid and room availability counts those states | Initial holds already exist; deposit confirmation strengthens the reservation and prevents its timed release, without reserving the same units twice |
| User/admin summaries | Controllers currently use the GoTyme-only summary even when other gateways exist | Provide one provider-independent summary |
| Receipts | `BookingPaid` says the whole booking is paid and shows `net_amount` as amount paid | Separate per-payment receipt, deposit confirmation, and fully-paid notification |
| Reports | Dashboard/reports sum full net amounts of paid bookings | Report actual collections, outstanding balances, and refunds separately from booking value |
| Trip lifecycle | Reminders, fulfillment, completion, and cancellation paths often check paid status directly | A reserved deposit booking may receive balance/pre-trip messages; fulfillment and completion still require full payment under this policy |

This audit inspected source and migration definitions. No production database schema query or new payment was performed during planning. Before implementation migrations, inspect the actual database schema and installed package versions; deployed schema may differ from historical migration files.

Room inventory has an explicit overlap/quantity hold check. The inspected approval path handles non-room item inclusion through admin availability review; activity passenger capacity is not automatically proof of a date-specific slot inventory. A deposit secures only the approved included services under the existing availability model. Confirm operator capacity during approval, and do not promise automated activity/package seat locking unless its actual capacity mechanism is verified or separately implemented.

## 3. Customer flow

1. Customer submits a booking request. Existing admin availability review stays in place.
2. Admin approves the final included items and adjusted net total. The application snapshots payment eligibility and due dates.
3. Payment page first offers amount choice: full amount, or 50% deposit when eligible. It shows amount payable now, remaining amount, initial deadline, balance deadline, and cancellation terms.
4. Customer then chooses a configured method: live PayMongo QR Ph, Stripe, or manual GoTyme. QR Ph sandbox remains a separate test option, with its existing environment distinction.
5. Backend calculates the permitted amount. It ignores any browser-supplied total, percentage, paid flag, or balance.
6. Online payment opens a request-specific QR/Checkout. Manual GoTyme displays the target amount and accepts a reference/claimed amount for staff verification.
7. A provider-confirmed or admin-verified payment is posted once. A confirmed qualifying deposit shows **Reserved — 50% paid**, with the balance and due date. A manual submission alone shows **Awaiting verification** and does not secure the deposit.
8. Customer returns later and selects **Pay remaining balance**. This opens a new collection for the outstanding amount; it cannot reuse the deposit's paid intent or Stripe session.
9. When the remaining amount is confirmed, show **Fully paid** and issue the final confirmation/voucher. Balance payment failure or QR expiry leaves the already received deposit intact.

For a ₱10,000 booking: pay ₱5,000 now, reserve after confirmation, then collect exactly ₱5,000 later. Full payment collects ₱10,000 once. Provider fees and PayMongo payout timing do not reduce the customer's credited payment: a confirmed ₱5,000 payment credits ₱5,000 even if the merchant receives a smaller net payout.

Use the existing Sora/DM Sans, palette, Blade/Tailwind/Alpine patterns, mobile layouts, labels, keyboard controls, and error announcements. The QR screen must show **Amount payable now** separately from **Booking total**. Receipts identify their own reference, amount, purpose, cumulative paid amount, and remaining balance.

## 4. Booking states without breaking existing status constraints

Prefer keeping the current booking status values for this release rather than adding a new `reserved` enum and changing every existing query.

| Situation | Booking status | Payment status | Reservation behavior |
| --- | --- | --- | --- |
| Requested | pending | unpaid | Existing provisional hold rules |
| Approved, no verified money | approved | unpaid | Initial payment deadline applies |
| Some verified money below deposit threshold | approved | partial | Not yet secured; staff review if deadline passes |
| Qualifying 50% deposit received | approved | partial | `deposit_secured_at` set; reserved until balance deadline |
| Balance collection pending/failed | approved | partial | Keep secured deposit and its balance deadline |
| Entire net total received | paid | paid | `paid_at` set once; fully paid lifecycle applies |
| Balance overdue | approved | partial | `balance_overdue_at` set; staff resolution, no automatic forfeiture |
| Cancelled after payment | cancelled | retains financial history | Refund/credit disposition tracked independently |

Introduce explicit predicates such as `hasSecuredReservation()`, `requiresInitialPayment()`, `canCollectBalance()`, and `isFullyPaid()` backed by authoritative money records. Do not globally treat every partial payment as reserved: a verified ₱500 transfer toward a ₱5,000 required deposit does not qualify.

Cancellation withdrawal must preserve the original payment plan and ledger. A refunded/reversed deposit triggers reservation review; historical `deposit_secured_at` alone must not keep a reservation funded after the credited money is removed.

## 5. Data design: quote, collection request, actual receipt

Keep these three concepts separate. One booking can have a deposit and a balance collection; either collection can have retries. A verified receipt is not the same thing as a pending request.

### Booking fields — additive migration

- `payment_plan_version`: legacy versus new accounting path; nullable initially for deployment compatibility.
- `payment_plan`: full or deposit_50; selected server-side before creating the first active request.
- `payment_policy_version` and compact policy snapshot: agreed percentage, eligible service start, cancellation-term version, timezone, and due-date rule.
- `deposit_required_cents`: immutable threshold for the approved quote; calculated with integer arithmetic.
- `deposit_secured_at`, `balance_due_at`, `balance_overdue_at`: timezone-aware timestamps.
- Keep `payment_deadline` as the initial deadline and `paid_at` for full payment only.
- Keep existing `net_amount`; use a shared decimal-to-centavos conversion at the boundary. Do not reduce it to the deposit or repeatedly calculate money using floats.

Define a quote revision/snapshot identifier, so an old QR cannot silently become payment for a revised quote. A valid alternative to a revision integer is an immutable stored quote identifier; choose one existing project-compatible representation at implementation.

### New `booking_payment_requests` / `BookingPaymentRequest`

One durable collection request: UUID, booking FK, purpose (full/deposit/balance), expected amount in centavos, PHP currency, gateway, live/test mode, quote/policy revision, lifecycle state, provider-session reference when relevant, stable idempotency token, provider expiry, deadline snapshot, verification timestamps.

Request states cover creating, pending, awaiting_manual_verification, processing/unknown outcome, succeeded, failed, expired, cancelled, and review_required. Failure to receive an HTTP response does not prove the provider did nothing.

Enforce at most one unresolved collection per booking and financial environment across all gateways. Use a PostgreSQL partial unique index for the selected active states plus a shared booking lock; audit/review-required requests block fresh collections until resolved. Do not scope this invariant by gateway, which would allow simultaneous Stripe and PayMongo charges for the same balance.

### New `booking_payment_transactions` / `BookingPaymentTransaction`

Canonical, auditable record of confirmed customer money: booking/request FKs, gateway, financial environment, amount/currency, external payment identifier or manual verification source, occurrence/verification times, actor for manual posting, allocation state, and transaction kind (receipt/refund/reversal or explicit legacy credit).

- Uniqueness by gateway + environment + actual provider payment ID, independently of webhook event IDs. Two distinct events about the same payment must not produce two credits.
- Manual receipt source references the verified `booking_payments` row with a unique constraint. Keep claimed amounts and evidence in the existing manual table.
- A refund/reversal references its original receipt and has its own external/audit identity. Do not overwrite or delete original receipts.
- Model confirmed money requiring review as unallocated/suspense, so it is visible but cannot silently reserve an invalid booking.
- Refund allocations cannot exceed their original credited receipt. Overpayments are tracked as credit/review, not negative balances or phantom revenue.

### Existing payment tables

Add a nullable request FK to `qrph_payment_attempts` and `booking_payments`; retain all current provider references and manual verification evidence. New attempts/receipts require a request through application validation after rollout. Keep legacy records readable. Do not repurpose the manual-transfer table into a generic provider table or drop deployed audit data.

### Accounting rules

`credited_cents = allocated confirmed receipts - confirmed allocated refunds/reversals`.

`remaining_cents = max(quote_total_cents - credited_cents, 0)`; `overpayment_cents = max(credited_cents - quote_total_cents, 0)`.

`deposit_due_now = max(deposit_required_cents - credited_cents, 0)` before reservation; after reservation, version 1 collects the complete remaining balance.

For a 50% threshold, round up the odd centavo: a ₱1,000.01 quote requires a ₱500.01 deposit, leaving ₱500.00. Ensure both installments meet the selected provider's actual minimum charge. Small totals that cannot produce two valid charges offer full payment only. Manual underpayments remain credited but do not qualify until cumulative verified money reaches the threshold.

Indexes should cover booking/history ordering, outstanding request lookup, due-date scheduling, source uniqueness, and refund lookup. Use restrictive financial-record FKs and indexed referencing columns. Inspect actual schema before creating constraints; do not edit already deployed migrations. No partitioning or new infrastructure is justified at current capstone scale.

## 6. Service and gateway changes

### Shared application services

- New `BookingPaymentPolicy`: calculates eligibility, deposit threshold, initial and balance deadlines, minimum amounts, and immutable policy snapshots. Reuse canonical included-item dates; evaluate extracting the date resolver currently duplicated around `TripJourneyService::dates()` rather than creating a conflicting arrival calculation.
- New `BookingPaymentAccountingService`: computes summaries, posts receipts/refunds exactly once, updates partial/full/secured state, and writes financial audit events. All gateways use this one accounting boundary.
- Extend existing `PaymentService` to create/resume requests and coordinate method changes; keep existing gateway selection and backwards compatibility.
- Update `PaymentDriver` contract to accept the collection context and return a structured verification result, rather than a boolean that hides amount/reference/mode.
- Use a validated payment selection request (full/deposit/balance plus supported method), owner authorization, CSRF, rate limiting, and server-derived amounts.

Create/reserve local request state in a short DB transaction. Perform remote provider calls outside row-lock transactions. Then persist provider results under a fresh lock. Keep stable request idempotency keys across crashes/retries. Cache locks improve coordination but database constraints remain authoritative if a lock expires or a worker dies.

### Live PayMongo QR Ph

Create an intent for the request amount using the existing backend QR Ph flow. Include booking code, request UUID, purpose, and quote revision in metadata. A successful deposit intent means that collection is paid; it does not mean the whole booking is paid.

Scope QR lookup/reuse by request ID and mode. The balance gets its own intent; retry/QR renewal within the same unpaid request keeps the current safe idempotency/renewal behavior. Verify intent/payment/reference/amount/currency/mode/source and request association. Settle against the immutable request amount, not an assumption that payment equals the booking's entire current net amount.

### Stripe

Preserve Checkout Sessions. Each request gets its own session and idempotency key, exact payable-now amount, and request metadata. Store and verify the session/payment identity before posting its receipt. Reuse only an unresolved session for the same request. A browser return cannot select a different request or declare success.

Change Stripe webhook processing so dedupe receipt, transaction posting, and aggregate booking update commit together. Current booking-level receipt insertion occurs before settlement and should not be carried into the new accounting path. Support asynchronous success/failure handling only for methods actually offered; do not count a completed-but-unpaid session as collected.

### Manual GoTyme

Keep reference submission and admin verification. Submitted/claimed money is not credited. On verification, post a receipt from the verified amount once. Reuse cumulative verified amounts for deposit qualification and remaining balance, while retaining below-threshold and overpayment review. The customer still cannot type an arbitrary online installment; actual manual transfer discrepancies must be recorded honestly.

Replace the existing blanket prohibition on changing gateways after any historic verified GoTyme transfer with request-aware protection. A completed GoTyme deposit can be followed by live PayMongo/Stripe for the balance; an unresolved manual submission still blocks a conflicting collection.

### Sandbox and simulator

Keep the existing sandbox option and its credentials isolated. Exercise deposit/balance in test bookings with test-mode requests and receipts. Test/simulator receipts must never contribute to live collected totals, settle a live balance, or reserve real inventory through the new accounting path. Define/validate the booking's financial environment for new-plan bookings; mixed or ambiguous legacy history requires review before conversion. Preserve legacy full-payment behavior behind its versioned path until that booking is deliberately migrated. Never silently downgrade the live PayMongo gateway to test mode.

## 7. Concurrency, deadlines, and exceptional cases

1. **Two tabs or two methods:** the first active request wins; the other resumes it or receives a clear in-progress response. A deposit cannot coexist with an independently payable full-amount request.
2. **Changing full/deposit/method:** cancel/expire the old provider session and verify it cannot still succeed before opening the replacement. Local cancellation, closing a tab, or hiding a QR is insufficient. If provider terminal state cannot be established, block replacement and reconcile.
3. **Duplicate webhook, poll, or browser return:** unique payment source and a booking row lock ensure one receipt, one state transition, and no repeated paid notifications.
4. **Payment near deadline:** compare trusted provider payment time with the agreed deadline when available; distinguish a late delivery from a late payment. Before releasing an unpaid hold, reconcile outstanding sessions. If inventory has already been released/cancelled or reassigned, record received money for review and never automatically revive the reservation.
5. **Below-threshold manual payment:** credit the verified receipt, show the shortfall, and retain evidence. Passing the initial deadline with money received goes to staff review; it must not be treated like a completely unpaid expiry.
6. **Provider outage/unknown outcome:** retain the unresolved request and retry reconciliation. Do not generate a second charge or expire a potentially paid reservation solely because a request timed out.
7. **Late balance:** keep deposit history and alert staff. Staff-approved extension/reopening is explicit and audited; no self-service indefinite extension.
8. **Price/item changes:** lock the agreed quote once payment is in progress or money has been credited. A later amendment requires an explicit revised quote, customer acknowledgment where appropriate, and separate delta collection/credit. Never reinterpret a paid QR against a new amount.
9. **Cancellation/refund:** record the requested cancellation separately from the actual refund. Refund only after a verified external/manual refund record; keep unrefunded money visible. Existing whole-booking `mark-refunded` shortcuts must not erase partial-payment accounting.
10. **Staff mark-paid / proxy booking / cash:** migrate administrative paths to create an audited verified receipt for the actual received amount. They cannot simply set a paid flag without explaining the credited source. Existing legacy records remain preserved.
11. **Fee and payout differences:** credit gross customer payment; report provider fees and merchant payouts separately. A pending PayMongo payout is not an unpaid customer installment.
12. **Trip completion/reviews:** no fully-paid receipt, completion, or review eligibility just because a deposit exists. If the business later allows pay-on-arrival, that is a separate fulfillment policy with its own safeguards.

## 8. UI, admin operations, reminders, and reporting

- Customer booking/journal: show quote total, amount verified, pending verification, remaining balance, deposit status, and current deadline. Do not show remaining balance as payable while another collection is unresolved.
- Admin listing/detail: filters for awaiting deposit, deposit secured, awaiting manual verification, balance due soon, overdue, and review required. Preserve existing AJAX listing/partial patterns and avoid per-row ledger queries.
- Staff actions: verify manual receipt, reject claim, extend deadline with reason, resolve reconciliation/overpayment, and document cancellation/refund disposition. Authorize all actions through the existing admin auth boundary and audit them.
- Notifications: distinct payment receipt, reservation secured, fully paid, initial deadline approaching, balance approaching, and overdue messages. Snapshot due-date/policy revision in dedupe keys so extensions do not create stale reminders. Dispatch after committed state; use durable retry/delivery records through an existing compatible notification mechanism or a small delivery log if required.
- Existing `BookingPaid` remains a fully-paid notification. A deposit confirmation must not claim the full net total was paid or issue a misleading fully-paid voucher.
- Reports: expose gross confirmed collections, allocated credits, outstanding balances, refunds, overpayments, and booked value separately. Keep actual receipt dates for collection reporting. Do not infer collected money by summing `net_amount` for paid bookings.
- Scheduler: process initial deadlines, balance reminders/overdue checks, and reconciliation in bounded chunks with overlap protection. Include booking display/controller expiry paths, not just the scheduled command. Use the deployed shared cache and existing queue/scheduler worker; no new queue product is needed.

## 9. Ordered file/module work

| Phase | Planned files/modules | Result |
| --- | --- | --- |
| 1. Policy and schema | [NEW] additive migrations, `app/Models/BookingPaymentRequest.php`, `BookingPaymentTransaction.php`, factories; [MODIFY] `Booking.php`, `QrphPaymentAttempt.php`, `BookingPayment.php`; [NEW] payment-plan config | Versioned policy snapshot, collection requests, receipt ledger, indexed constraints |
| 2. Shared accounting | [NEW] `app/Services/Payment/BookingPaymentPolicy.php`, `BookingPaymentAccountingService.php`; [MODIFY] `PaymentService.php`, `Drivers/PaymentDriver.php`; [NEW] validated payment-selection Form Request | One server-owned amount and accounting workflow |
| 3. Provider adapters | [MODIFY] `QrphPaymentService.php`, `Drivers/QrphDriver.php`, `PaymongoDriver.php` where needed, `StripeDriver.php`, `GoTymePaymentService.php`, simulator only for isolated test flow | Deposit and balance collections through preserved methods |
| 4. Verification and routes | [MODIFY] `BookingPaymentController.php`, `PaymentWebhookController.php`, `routes/bookingRoute.php`, `ReconcileQrphPayments.php`; add Stripe request reconciliation if existing delivery/return cannot close pending requests safely | Request-aware verification, atomic settlement, historical reconciliation |
| 5. Approval/expiry/inventory | [MODIFY] `AdminBookingController.php`, `BookingRequestService.php`, `BookingExpiryService.php`, `ExpireBookings.php`, `BookingConfirmationController.php`, `RoomAvailabilityService.php` only where lifecycle changes require; `TripJourneyService.php` and shared date resolver as appropriate | Deadline snapshots, deposit preservation, balance review, no double reservation |
| 6. Customer/admin UI | [MODIFY] `resources/views/booking/{pay,show,qrph,gotyme,index}.blade.php`, admin booking show and `_table` partial; [NEW] reusable amount-selection/summary partials if repeated | Clear full/deposit/balance flow and staff visibility |
| 7. Communications/reporting | [NEW] deposit/receipt/deadline notifications and scheduled processor; [MODIFY] `BookingPaid.php`, `AdminNotificationService.php` and matching notification preferences as needed, `routes/console.php`, `AdminReportController.php`, `DashboardController.php`, affected report views | Honest receipts, due-date alerts, actual collection reports |
| 8. Settings and rollout | [MODIFY] `config/system_settings.php`, `SystemSettingsService.php` section list, `UpdateSystemSettingsRequest.php`, `AdminSettingsController.php` as needed, `resources/views/admin/settings/index.blade.php`; existing `adminSettingsRoute.php` section allowlist follows the service list; [NEW] rollout feature flag/config default; approval/proxy paths use snapshotted policy | First-release Booking & payments settings, audit/revision validation, effective deadline preview, no changes to existing agreements |
| 9. Regression coverage | [NEW] focused deposit/accounting/deadline tests; [MODIFY] `QrphPaymentTest.php`, `PaymongoSecurityTest.php`, `PaymentGatewayIsolationTest.php`, `GoTymePaymentTest.php`, `BookingFlowTest.php`, `AdminReportTest.php`, `TripJourneyTest.php`, notification/admin proxy tests where behavior changes | Existing full-payment paths remain covered alongside deposits |

Generate actual classes/migrations/tests through Artisan at implementation time. Match installed Laravel/Pest APIs and sibling conventions; do not add packages or upgrade gateway SDKs as part of this feature.

## 10. Verification and acceptance criteria

Use local/test PostgreSQL, faked HTTP, and prohibited stray requests for automated tests. No live charge or refund is needed for this planning or implementation pass.

- Exactly 50% threshold, odd-centavo rounding, zero/negative amounts, tampered totals, too-small installments, and full-payment-only near-term bookings.
- Approval + 48-hour deadline and its trip cutoff; seven-day balance default; exact boundary instants; Philippine timezone; missing dates; mixed multi-item dates; extensions and stale reminder suppression.
- Admin settings: save 24/48/72-hour initial windows and configurable balance lead days; both customer approvals and admin/proxy approvals honor them; reject zero/negative/fractional/out-of-range/unknown fields; require admin auth/2FA and valid section revision; audit changes; prevent stale concurrent edits; immutable older booking deadlines; accurate checkout/email/chatbot copy; provider QR expiry remains independent. Extend `AdminSettingsTest.php` and affected proxy/notification tests.
- Deposit paid → partial/reserved, inventory retained beyond initial deadline, one deposit confirmation, and accurate receipt amount.
- Balance paid → total matches exactly, paid status/paid_at once, one final confirmation; balance failure/expiry does not undo the deposit.
- Unpaid initial expiry; partial below threshold; pending manual verification; provider outcome unknown; late webhook before/after inventory release; overdue staff resolution.
- Full and deposit creation through live-format PayMongo fakes, Stripe fakes, and verified GoTyme; sandbox/test isolation; cross-provider balance payment only after prior request is terminal.
- QR/session reuse for the same request and new intent/session for the balance; stable idempotency across retries and process failures.
- Two concurrent collection requests and two concurrent confirmations against PostgreSQL constraints; webhook versus poll versus admin verification; real concurrency coverage rather than sequential duplicates alone.
- Different event IDs for one actual payment; webhook ledger insertion rollback with failed receipt posting; retry succeeds without double credit.
- Signature, provider reference, request UUID, purpose/revision, amount, currency, mode, and ownership mismatch; browser redirects and manual claims cannot create trusted money.
- Refund/reversal/overpayment allocation; no negative balance; paid flags cannot bypass actual accounting; legacy administrative credits have explicit provenance.
- Reports include deposits, exclude unsettled claims/test funds, count collections once, and do not double count manual source rows plus canonical transactions.
- Admin cash/proxy booking, mark-paid, cancellation withdrawal, documents, fulfillment, completion, and reviews retain intended behavior.
- Responsive, keyboard, screen-reader labels, error messages, dark mode, and two-tab browser behavior.
- Pint for modified PHP, affected Pest suites, Blade compilation, Vite build, and complete suite before rollout.

Success example: approve ₱10,000 for a trip more than seven days away; pay ₱5,000; reservation survives the original 48-hour deadline; pay ₱5,000 through another configured live method after the deposit request is closed; booking becomes fully paid; two receipts and ₱10,000 gross collections exist; retries add nothing.

## 11. Safe production rollout and legacy bookings

1. Inspect actual schema, versions, and open provider sessions; review additive migrations and normal backup procedure. Keep all four existing payment choices/configurations.
2. Deploy nullable additive tables/columns with the feature disabled. Old in-flight requests and legacy bookings continue through their existing full-payment path.
3. Enable the new plan only for new approvals after a configured release point. Existing paid, partial, pending, completed, and refunded records must not be silently rewritten.
4. For an older booking that must opt in, reconcile its real confirmed sources, mode, pending sessions, and manual receipts first. Import exactly once with source identities. Where only an old paid flag exists, use an explicitly reviewed legacy opening credit with provenance, not an invented provider payment. Do not import both a full opening credit and the underlying receipts.
5. Existing unfinished legacy online sessions keep their original amount and settlement path. Do not convert them to deposits, cancel them blindly, or create an alternative balance request while they may still succeed.
6. Test the complete deposit/balance flow in isolated sandbox bookings. Verify scheduler/queue operation and delivery dedupe. Read-only production checks must confirm request/ledger linkage without printing keys or personal data.
7. Enable the feature for new approvals gradually; monitor unresolved sessions, overdue deposits/balances, duplicate-key failures, review cases, and notification failures.
8. Turning off new deposit offers must still allow existing deposit customers to pay their balances and process incoming webhooks. Do not roll back populated financial tables or abandon active plans. Prefer forward fixes.
9. A later real deposit/balance acceptance exercise requires explicit authorization and human payment; this planning request authorizes no money movement.

## 12. Decisions and boundaries

Confirmed: fixed 50% deposit versus full payment; preserve existing payment methods.

Confirmed planning addition: admin-editable initial payment window and remaining-balance lead days through the existing settings system. Keep 48 hours and seven days as proposed defaults; the owner can choose different values before rollout.

Recommended, not yet an agreed business policy: balance seven days before service by default; require full payment for near-term approvals; cap the initial deadline before travel; staff review rather than automated deposit forfeiture/cancellation on overdue balances.

Initial release excludes arbitrary customer amounts, recurring/off-session automatic debits, saved payment methods, BNPL, automatic refunds, provider-fee surcharges, item-by-item deposits, and a broad payment-platform rewrite. Customers actively initiate the second payment; scanning the first QR does not authorize a later debit from GoTyme.

## Official references checked

- PayMongo QR Ph API: https://docs.paymongo.com/docs/payment-acceptance-qr-ph-api — dynamic QR has an exact amount and a per-payment intent lifecycle. Deposit and balance can be implemented as separate collections using that flow.
- PayMongo Payment Intent creation: https://docs.paymongo.com/reference/create-a-paymentintent — backend creation uses the secret API key.
- Stripe Checkout creation: https://docs.stripe.com/api/checkout/sessions/create — one-time Checkout sessions support request-specific amounts and references. No recurring mandate is required for customer-initiated installments.

The two-collection design and seven-day policy are application design recommendations, not claims of a built-in PayMongo installment product. The references informed the approved design. The following implementation record supersedes the original planning-only status.


## Implementation and verification record — October 16, 2026

Implemented locally: versioned approval policy snapshots; full/50%/balance collection requests; integer-centavo receipts and linked refunds; live/test separation; PayMongo, Stripe and verified manual GoTyme collection integration; PostgreSQL locks and uniqueness; late-payment review and explicit allocation; configurable approval and balance deadlines; staff extensions; deposit inventory retention; durable receipt/reminder notices; scheduler reconciliation; accurate collection reporting; customer and admin screens. Legacy bookings retain their existing flow. Stripe, QR Ph sandbox, manual GoTyme and live PayMongo remain separate methods. Sandbox funds cannot credit live reservations.

The additive migration has been applied to local development and test databases only. No remote variables, production schema, Git commits, pushes or deployments were performed. No real payment or refund was initiated. Existing live API keys were not changed. The temporary browser preview used unsaved fixture models and was removed after verification.

Verification:
- Ten affected regression files passed: 154 tests / 761 assertions, before two final regression additions.
- Final installment suite passed: 36 tests / 158 assertions, including independent PHP processes simultaneously preparing the same collection and posting the same receipt against PostgreSQL. Both processes resolve to one collection and one receipt.
- Full suite run: 1,021 tests, 1,019 passed, one failure and one error in DemoSeedBookingsTest. Those two signup tests reproduce in isolation and omit the Turnstile token required by existing registration validation. Registration and those tests were not modified. Existing Mockery import warning remains unrelated.
- Pint, Blade compilation, Vite production build and git diff whitespace checks passed. The existing large ApexCharts bundle warning remains.
- Built-in browser verification (explicitly authorized) checked amount selection, all four methods, remaining-balance screen, admin settings, dark mode and narrow mobile layout down to 320px. Provider APIs were faked in automated tests; live deposit/balance acceptance has not been exercised.

Rollout:
1. Review and deploy this branch with the additive migration; run php artisan migrate --force in the production release step.
2. Confirm the existing Railway worker runs queue:work and schedule:work. The new payments:process command runs every minute alongside existing QR reconciliation.
3. In Admin Settings → Booking & payments, set the initial window (default 48 hours), balance lead time (default seven days), and full-payment cutoff (default 24 hours). Enable the full-payment/50% offer only when ready. It defaults to off.
4. Newly approved bookings receive the new policy; existing bookings and active sessions are not converted. Turning the offer off later still allows active deposit customers to settle their balances.
5. Complete a human-initiated live deposit and balance acceptance payment after deployment before treating the new flow as live-verified. No automatic refund or forfeiture is implemented; external refunds must be performed separately and recorded by staff.
