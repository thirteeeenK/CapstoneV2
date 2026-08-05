# Security Hardening Plan — Authentication, Authorization & Rate Limiting

> **STATUS: COMPLETE (2026-08-06)** — All items implemented, tested (12/12 BookingFlowTest incl. 3 new IDOR tests), and browser-verified (unauth `/bookings/{code}` → `/login`). Full report: `SECURITY_REPORT.md`.

## Findings Summary (audit of all 45 controllers, 20 route files, middleware, limiter config)

### CRITICAL
| # | Finding | Location |
|---|---------|----------|
| C1 | Booking status pages unauthenticated — PII disclosure (contact, manifest, history) via `booking.show`, `booking.success`, `booking.pay` | `routes/bookingRoute.php`, `routes/checkoutRoute.php`, `BookingConfirmationController@show`, `BookingPaymentController@show` |
| C2 | Booking mutations unauthenticated + no ownership: anyone can `cancel`, `rebook` (cart injection), create payment sessions, or **mark any approved booking PAID** via `simulatorConfirm` / `return` | `routes/bookingRoute.php`, `BookingPaymentController` (all methods) |

### HIGH
| # | Finding | Location |
|---|---------|----------|
| H1 | No rate limiting anywhere — named limiters (`admin`, `users`, `chatbot`) are dead code; chatbot registered twice (guest 5/min overwritten by 10/min); booking-code brute force practical | `AppServiceProvider@boot` |
| H2 | Unauthenticated PII writes via guest checkout feed C1/C2 | `routes/checkoutRoute.php`, `CheckoutController@process` |
| H3 | `GET /check` public, echoes raw DB exception text (DSN/host/creds) | `routes/web.php`, `ConnectionController` |

### MEDIUM
| # | Finding | Location |
|---|---------|----------|
| M1 | Cart sold-out check uses stale statuses `['confirmed','pending']` — approved/paid inventory not counted (oversell) | `CartController@store` |
| M2 | Admin `removed_images` accepts arbitrary paths → deletes files not owned by entity | `Admin\HotelController`, `Admin\RoomController`, `Admin\ActivityController` |
| M3 | `AdminPackageController` `image_url` has no `url` validation | `Admin\AdminPackageController@store/update` |
| M4 | Duplicate `/admin/login` definitions (same route name registered twice) | `routes/destinationRoute.php` |
| M5 | Guest cart token fallback `md5(ip+UA)` predictable | `CartController@getSessionToken` (kept, flagged) |

### OK (verified)
- NotificationController, OnboardingController, ProfileController, MyBookingsController: owner-scoped ✓
- CartController mutations: scoped by user_id/session_token ✓
- All admin CRUD: `auth:admin` ✓ (missing `no.cache` only on addons)
- Payment webhook: HMAC-verified (Stripe), fails closed (simulator) ✓
- Login (user+admin): rate-limited in app code ✓

## Fix Plan

1. **[MODIFY] `routes/bookingRoute.php`** — wrap ALL `/bookings` routes in `auth` middleware + `throttle:users`.
2. **[MODIFY] `routes/checkoutRoute.php`** — `/checkout` + `/checkout/process` + `/booking/{code}/success` get `auth` + `throttle:users`. Guest checkout removed; login required to book.
3. **[MODIFY] `BookingConfirmationController`** — load booking via owner/admin gate (`user_id === auth()->id()` or admin guard), else 404.
4. **[MODIFY] `BookingPaymentController`** — same owner/admin gate on ALL methods (show, pay, simulatorShow, simulatorConfirm, return, cancel, rebook); add expiry check to simulatorConfirm.
5. **[MODIFY] `AppServiceProvider`** — rewrite limiters: `users` (30/min, user-id|IP), `admin` (120/min), `cart` (30/min), `ai` (10/min), `webhook` (10/min IP); remove chatbot double registration.
6. **[MODIFY] Route files** — apply `throttle:` middleware:
   - `auth.php`: POST register/login/forgot/reset/confirm + PUT password → `throttle:users`
   - `adminAuth.php`: POST admin login/forgot/reset + dashboard → `throttle:admin`
   - `web.php`: dashboard → `throttle:ai`; notifications + profile groups → `throttle:users`
   - `cartRoute.php`: all cart routes → `throttle:cart`
   - `paymentWebhookRoute.php`: → `throttle:webhook`
   - All admin CRUD groups → append `throttle:admin`
7. **[MODIFY] `CartController@store`** — availability statuses use `Booking::HOLD_STATUSES`.
8. **[MODIFY] `ConnectionController`** — stop echoing raw exception; generic response + log.
9. **[MODIFY] `routes/destinationRoute.php`** — remove duplicate `/admin/login`.
10. **[MODIFY] Admin package/hotel/room/activity controllers** — `image_url` → `nullable|url`; `removed_images` deletion restricted to entity-owned paths.
11. **[MODIFY] `tests/Feature/BookingFlowTest.php`** — add IDOR tests: unauthenticated → redirect; non-owner → 404; owner ok.
12. **[NEW] `SECURITY_REPORT.md`** — vulnerabilities, severity, impact, remediation, implemented changes.

## Out of scope / accepted risks
- Simulator pay is a demo payment provider; now owner-only + auth-gated (documented).
- `GET /pay/return` must remain GET (Stripe redirect target); now owner-only.
- Guest cart is not migrated on login (UX note).
