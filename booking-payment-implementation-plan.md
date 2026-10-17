# Seven-day deposit eligibility and approval repair

Status: approved and implemented locally, October 17, 2026. This document supersedes the earlier suggestion that hotel deposit eligibility could be determined only by the two-day balance deadline. The original investigation below describes the pre-fix state; verification results are recorded at the end.

## 1. Confirmed findings from booking 616

- Booking: ST-2026-KYEHS, ID 616, currently pending/unpaid, total PHP3,705.
- Submitted October 17, 2026 at 12:33 PM Philippine time.
- Included room: El Nido Resorts - Pangulasian Island / Canopy Villa, October 18–19. An undated activity is also included.
- Both signed-in browser pages were inspected. The admin preview displays: “Set a future payment deadline before the first booked service.” The customer still sees Pending.
- The Approve button is enabled, has the correct POST form action, and has no invalid browser form fields. No browser console errors were observed. No approval POST was submitted during planning, to avoid changing this booking before plan approval.
- The current payment policy uses a date-only service start of October 18 at 9 AM, subtracts the configured 24-hour full-payment cutoff, and obtains October 17 at 9 AM. That is earlier than the reservation's creation time and current time. `approvalData()` throws a validation exception, so the approval transaction cannot complete under the default deadline.
- The admin page renders session success/error messages but lacks a general validation error summary and an inline error for the approval deadline. Laravel's validation error bag is therefore easy to miss after a failed POST; only the preview explains the date problem.
- The current policy has no independent “more than seven days ahead” deposit eligibility gate. A two-day balance lead and a seven-day deposit gate must be separate rules.
- `TripBookingDatePolicy` already enforces arrival at least two Philippine calendar days ahead for trip-planner bookings. Direct room cart endpoints currently accept tomorrow (`after:today`), and admin proxy creation only rejects past room dates. These entry points are inconsistent.
- Hotels already have `check_in_time`; the payment policy currently ignores it and uses 9 AM for every date-only anchor.

## 2. Proposed business rules

### Three independent rules

1. Minimum arrival for new hotel/confirmed-trip requests: today in Asia/Manila plus two calendar days. On October 17, the earliest new check-in is October 19. Undated activities remain undated.
2. Deposit eligibility: a dated booking submitted more than seven Philippine calendar days before its earliest confirmed booking date may offer a fixed 50% deposit. Exactly seven days ahead or fewer requires full payment. Paying in full remains available for eligible bookings.
3. Payment deadlines: initial payment within 48 hours of reservation submission; when a deposit is eligible, the remaining 50% is due two days before the confirmed start/check-in.

The seven-day eligibility threshold must not be confused with the two-day balance lead. It must be impossible to obtain a deposit for a booking within seven days merely by choosing an earlier admin deadline or a different gateway.

### Reference dates and clocks

- Interpret “making the reservation” as submission (`booking.created_at`), following the latest request. This intentionally changes the previous initial-window calculation, which started at admin approval.
- Use server-side Asia/Manila calendar dates for the seven-day and minimum-arrival comparisons. Client clocks and submitted eligibility flags have no authority.
- For example, submission on October 17: October 24 is exactly seven calendar days ahead, so full payment only; October 25 is eight days ahead, so a deposit may be offered.
- Snapshot the calculation reference, request date, service anchor, calendar lead, threshold, deadline basis, and exact deadline instants. Later time passage or itinerary suggestion edits do not silently rewrite agreed terms.
- Approval delay does not silently restart the 48-hour clock. If no future collection window remains, show a clear validation result and require explicit booking review instead of approving an already-expired payment window.

### Full payment for near-term stays

- Use the earlier of reservation submission plus 48 hours and the payment cutoff before the first confirmed service/check-in.
- Do not subtract an unconditional 24 hours from tomorrow's start and then fail with an invisible error. A shortened, future payment window is valid when it ends before service starts.
- Two calendar days of notice do not always equal 48 elapsed hours. Late-night submissions still need this cap.
- Prefer the hotel's configured check-in time and store it in the booking snapshot. If it is unavailable, retain a clearly displayed 9 AM Philippine payment cutoff as an explicit fallback, without presenting it as the hotel's real check-in hour.
- Full payment must be verified before the cutoff. A deadline override cannot allow payment after check-in or bypass the seven-day deposit gate.

### Booking 616 and existing requests

Recommended compatibility decision: apply the new two-day arrival restriction prospectively to newly submitted requests. Preserve the accepted arrival dates of existing pending requests such as 616, while applying the seven-day payment gate when they are approved.

- Booking 616 is one day ahead and must be full-payment-only for PHP3,705.
- Its default initial deadline should be the earlier of October 19 at 12:33 PM (submission +48 hours) and the October 18 check-in/payment cutoff. The latter is earlier and should be displayed clearly to admin and customer.
- This existing request may therefore be approved using a shortened, future window, provided the room remains available and the cutoff has not passed. Record why the prospective minimum-arrival rule was not retroactively applied.
- Do not silently move its dates to October 19, change prices, reject/cancel it, or fabricate payment.
- Mark new requests with a request-policy revision in the existing JSON snapshot so prospective validation is unambiguous. Existing requests without that revision retain their previously accepted arrival rule; all still require a future service date and valid collection deadline.
- Existing approved, partially paid, and paid bookings retain their saved terms and immutable payment history. No bulk recalculation is included.

### Flexible activities and mixed bookings

- Activity-only requests without confirmed travel dates retain the existing flexible-date behavior: optional activity dates do not block a deposit; the balance uses the existing seven-day-after-approval fallback.
- A hotel or confirmed trip makes a mixed booking dated. Use the earliest included confirmed trip/fixed service for the seven-day gate and payment deadlines. Undated activity items do not override that date.
- Preserve the existing minimum installment amount and 50/50 centavo rounding protections.

## 3. Implementation steps

### A. Centralize dated-booking calculations

- Update `BookingPaymentPolicy` to separate arrival eligibility, seven-day deposit eligibility, initial collection deadline, and remaining-balance deadline.
- Add an explicit seven-day deposit threshold to payment configuration/settings, distinct from `balance_lead_days=2`.
- Use one calculation for approval previews, saved terms, customer choices, and backend collection validation.
- Store a new policy revision and calculation basis in existing JSON fields. Preserve old policy snapshots.
- Remove the ability for an earlier initial override to defeat the new seven-day gate. Overrides still undergo future-date, balance-order, and service-cutoff validation.
- Handle disabled-installment approvals consistently: full-payment deadlines must also respect the service cutoff.

### B. Make minimum-arrival checks consistent

- Reuse `TripBookingDatePolicy` rather than writing another date rule.
- Enforce today +2 days on new direct room cart additions, cart date updates, lucky-itinerary room additions, checkout submission, and new admin-created dated bookings. Verify the relevant chat/planner routes funnel through the same guarded services.
- Recheck at checkout, including baskets left open across Philippine midnight. Do not rely solely on date picker minimums.
- Do not reject a previously accepted booking merely because time passed while it was pending. Preserve its request-policy snapshot and separately validate that the service and payment deadline remain in the future.
- Show the same earliest eligible date in room modals, basket editing, checkout, and admin creation. Preserve availability and price checks.

### C. Repair approval and its feedback

- Keep approval atomic: room availability locking, repricing, saved payment terms, status transition, audit entry, and notification dispatch must remain coordinated. A validation failure leaves the booking pending with no partial writes or notification.
- Add a prominent accessible validation summary near the approval form, inline deadline errors, preserved input, and focus/scroll to errors after a failed POST.
- Change the approval button copy so it does not promise a full 48-hour window for a short-notice booking.
- Show the initial amount due, exact deadline, deposit eligibility reason, balance deadline where applicable, and shortened-window explanation before submitting approval.
- Prevent duplicate submission while processing; show a clear success or failure result. Do not remove server-side protections to make the button appear functional.

### D. Carry the saved terms through collection

- Ensure full/deposit/balance choices and collection amounts are calculated by the server for PayMongo, Stripe, QR Ph Sandbox, and manual GoTyme.
- Direct/forged deposit requests for an ineligible booking must fail even if the customer bypasses the UI.
- Update approval emails, customer pending/approved pages, due reminders, and expiry behavior to explain the submission-based initial clock and shortened deadlines.
- Pending requests whose submission-based payment window has elapsed need explicit review messaging; they must not receive a fresh window merely because an admin clicks Approve late.
- Preserve live/test environment resolution, credentials, signatures, provider reference verification, idempotency, locks, and refund accounting.

## 4. File map

No dependency changes or schema migration are expected. If tracing exposes a need for a new column or unrelated feature, bring it back to the plan first.

- [MODIFY] `app/Services/Payment/BookingPaymentPolicy.php` — separate seven-day eligibility, submission clock, check-in-aware cutoff, legacy snapshots.
- [MODIFY] `config/system_settings.php`; `app/Http/Requests/Admin/UpdateSystemSettingsRequest.php`; `resources/views/admin/settings/index.blade.php` — independently labelled eligibility threshold, initial window, and balance lead.
- [MODIFY] `app/Services/TripBookingDatePolicy.php` — reusable two-day arrival check with field-appropriate errors.
- [MODIFY] `app/Services/BookingRequestService.php` — checkout enforcement, request-policy/check-in snapshots, entry-point consistency.
- [MODIFY] `app/Http/Controllers/CartController.php`; `app/Http/Controllers/LuckyItineraryController.php`; `app/Http/Controllers/CheckoutController.php` — dated request validation through shared policy.
- [MODIFY] `app/Http/Requests/Admin/StoreAdminBookingRequest.php`; `app/Http/Controllers/Admin/AdminBookingController.php` — prospective date validation and atomic approval with explicit failure feedback.
- [MODIFY] `resources/views/admin/bookings/show.blade.php`; `_payment-approval.blade.php`; `create.blade.php` — validation summary, accurate preview and button copy.
- [MODIFY AS NEEDED] `resources/views/components/frontend/room-preview-modal.blade.php`; `components/frontend/cart-drawer.blade.php`; `room/index.blade.php`; `cart/index.blade.php`; cart item partials; `checkout/index.blade.php`; `components/frontend/chat-widget.blade.php` after locating the exact existing date controls — consistent minimum dates and pending-payment explanation.
- [MODIFY] `resources/views/booking/show.blade.php`; `_payment-summary.blade.php`; `installment-pay.blade.php` — explicit full-only reason and current outstanding deadline.
- [MODIFY AS NEEDED] `app/Services/Payment/InstallmentPaymentService.php`; `app/Services/BookingExpiryService.php`; `app/Console/Commands/ProcessBookingPayments.php`; `app/Notifications/BookingApproved.php`; `BookingPaymentDue.php` — use saved policy consistently.
- [MODIFY] Existing `HotelDepositPolicyTest`, `BookingInstallmentTest`, `FlexibleBookingPaymentTest`, `BookingFlowTest`, relevant cart/date and admin proxy/settings tests. Retain old-policy regression coverage rather than deleting it.
- [NEW IF NEEDED] A focused Pest approval/date-boundary test file created through Artisan when the cases do not fit an existing suite.

## 5. Verification and acceptance criteria

1. Eight calendar days ahead offers full or 50%; exactly seven and six days ahead offer full only.
2. Differences around Philippine midnight are deterministic even with a UTC application/database timezone. Leap/month boundaries are covered.
3. New check-in on today or tomorrow is rejected; today +2 is accepted when inventory is available. Direct room/cart/checkout/admin and planner paths agree, including stale baskets and forged requests.
4. A 616-shaped existing pending request can be approved full-only with a future shortened deadline; submitting an invalid deadline produces a visible error and preserves pending status/data.
5. A cutoff already passed produces an explicit review/reschedule error, never approval with a past deadline.
6. The initial clock uses the requested submission basis; delayed approval does not reset it. Customers see that basis and cannot be charged before approval.
7. Eligible deposits have a separate remaining-balance deadline two days before confirmed travel, with exact amount rounding. An earlier override cannot turn a within-seven-day booking into a deposit booking.
8. Undated activities retain their existing fallback; mixed bookings use the earliest fixed commitment.
9. Existing approved/part-paid policies and receipts do not change when settings are updated.
10. All gateway/backend routes reject ineligible deposits; local tests stay sandbox and production PayMongo stays live.
11. Failed approval causes no partial availability, totals, audit, or notification changes. Successful approval produces the expected history and notification once; repeat/double submission cannot duplicate it.
12. In the browser, inspect 616 before implementation, then after approval of this plan verify the failure feedback and successful approved/customer full-payment screens. Do not create a real charge.

Run the narrow affected Pest suites, required Pint, Blade compilation, and asset build if frontend date-control code changes. Browser checks must not run concurrently with Blade compilation on Windows. Re-run the full suite only when the separate known registration/demo-booking issues are being resolved or a new regression concern justifies it.

## 6. Scope and release notes

- The user approved implementation, the two-day minimum for new requests, compatibility handling for 616, and subsequent browser verification. Implementation and that local approval are complete.
- No production push, deploy, Railway variable changes, payments, refunds, or automatic rescheduling are included.
- Previously identified release findings remain: two registration/demo-booking issues in the last full test suite; Railway worker must run queue plus scheduler; both Railway services track `fixMapIssueV2` while local work is on `paymentlive`; live PayMongo environment/key-variable configuration was confirmed present.
- Existing `implementation_plan.md` documents the earlier PayMongo integration and is preserved. This focused plan is the current proposal for the new seven-day booking policy and approval fix.

## 7. Local verification completed

- Final isolated regression run: 188 passed, 1,166 assertions (`storage/logs/booking-policy-final-tests.log`). After adding the final cart-update, disabled-deposit, and passed-service cases and preserving field validation on cart updates: 35 passed, 156 assertions (`storage/logs/seven-day-boundary-final.log`). These runs overlap; their test counts should not be added together.
- The broader affected run covered 342 tests. Its three failures exposed an outdated tomorrow fixture and validation-message priority differences, which were corrected and verified in the isolated runs above. Earlier concurrent test database resets caused missing-table/deadlock errors; the final isolated runs are clean after other runs were paused.
- Required Pint passed, Blade templates compiled successfully, Vite production assets built successfully, and `git diff --check` passed. The existing asset-size warning remains.
- Browser: submitting October 18 at 10 AM as booking 616's deadline visibly failed because it exceeded the 9 AM date-only anchor. Both the summary and inline error appeared; the booking remained pending with its inputs and items intact.
- Browser: blank/default deadline then successfully approved booking 616 at approximately October 17, 1:04 PM Philippine time. It remains unpaid, totals PHP3,705, retains its October 18–19 room dates and undated activity, and requires full payment by October 18 at 8:59 AM Philippine time. Its page explicitly labels the 9 AM payment anchor as a fallback because no confirmed service time was stored.
- Customer browser: approval message appears above the payment card. The payment page offers full payment only with the specific one-day/seven-day explanation. PayMongo, Stripe, manual GoTyme, and QR Ph Sandbox remain visible; QR Ph Sandbox was successfully selected without starting a charge. Local test mode is preserved.
- No production push, deployment, Railway variable update, real charge, refund, or booking date/price change was performed. Release findings in section 6 remain outstanding.
