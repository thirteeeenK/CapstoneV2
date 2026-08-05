# Security Report — Symptom-Testing & Hardening Pass

Date: 2026-08-06
Scope: authentication, authorization, rate limiting, and data-leak surface across all controllers, routes, middleware, and limiter config.

## Vulnerabilities Found

### CRITICAL

| ID | Finding | Severity | Impact | Status |
|----|---------|----------|--------|--------|
| C1 | Booking status/PII pages were unauthenticated. `booking.show`, `booking.success`, `booking.pay` exposed contact name/email/phone, guest manifest, special requests, and payment state to **anyone holding a booking code** (no auth, no ownership check). | Critical | PII disclosure — data breach of full legal names, contact details of every traveler | FIXED |
| C2 | Booking mutations were unauthenticated: `cancel`, `rebook`, `pay`, `simulatorConfirm` had no auth and no ownership, so any visitor could cancel another traveler's booking, inject its items back into their own cart, or **mark an approved booking as paid**. The failed payment state machine could be driven by an external party. | Critical | Booking integrity destroyed; inventory hijack; revenue loss; denial-of-service | FIXED |

### HIGH

| ID | Finding | Severity | Impact | Status |
|----|---------|----------|--------|--------|
| H1 | No rate limiting applied anywhere. Named limiters (`admin`, `users`, `chatbot`) were dead code; `chatbot` was registered twice so the guest limit (5/min) was silently overwritten by the logged-in limit (10/min). Login, register, password reset, checkout, payment, and admin actions adopted no throttle → brute-force and booking-code probing were practical. | High | Credential stuffing; enumeration; DoS via repeated DB writes | FIXED |
| H2 | Guest checkout fed C1/C2: creating a booking did not require a session/user, so the unauthenticated booking surface was both readable and writable. | High | Compounds C1/C2 | FIXED (checkout now requires `auth`) |
| H3 | `GET /check` returned the raw database exception message (PDO DSN, host, credentials fragments) to any visitor. | High | INFOSEC — internal infrastructure disclosure | FIXED |

### MEDIUM

| ID | Finding | Severity | Impact | Status |
|----|---------|----------|--------|--------|
| M1 | Cart room-availability used stale statuses `['confirmed','pending']`; `approved`/`paid` bookings were not counted → oversell. | Medium | Double-booking beyond physical inventory | FIXED (uses `Booking::HOLD_STATUSES`) |
| M2 | Admin `removed_images` accepted arbitrary storage paths → admins could delete files **not owned by the entity** (path traversal-lite on the storage disk). | Medium | Arbitrary storage deletion by an authenticated admin | FIXED (deletion restricted to entity-owned paths) |
| M3 | `AdminPackageController` `image_url` had no `url` validation (other controllers used `nullable\|url`). | Medium | Malformed/javascript URLs persisted into the catalog | FIXED |
| M4 | Duplicate `/admin/login` registered (same route name twice) — resource listed both in `adminAuth.php` and `destinationRoute.php`. | Low | Route ambiguity; double registration | FIXED |
| M5 | Guest cart token fallbacks to `md5(ip + UA)` when the session is somehow absent. | Low | Predictable token for a fallback path | Accepted + flagged |

### VERIFIED SAFE (no change)
- Notifications, onboarding, profile, My Bookings: owner-scoped
- Cart mutations: scoped by `user_id` / `session_token`
- All admin CRUD: `auth:admin` group (except addons = same middleware)
- Payment webhook: HMAC-verified (Stripe), fails closed on simulator
- User + admin login already rate-limited in app code (5 attempts)

## Changes Implemented

### Authorization (IDOR mitigation)
- `routes/bookingRoute.php`: entire `/bookings` group now requires `auth` + `throttle:users`.
- `route/checkoutRoute.php`: `/checkout`, `/checkout/process`, and the legacy `/booking/{code}/success` require `auth`.
- `BookingConfirmationController@show` and **all** `BookingPaymentController` methods (`show`, `pay`, `simulatorShow`, `simulatorConfirm`, `return`, `cancel`, `rebook`) resolve bookings through `loadOwnedBooking` — 404 for any non-owner/ non-admin, so codes cannot be probed.
- `simulatorConfirm` now respects the 48-hour expiry like every other payment path.

### Rate limiting (enforced)
- `AppServiceProvider`: replaced the dead/contradictory limiter config with five named limiters, keyed by user id where available else IP:
  - `users` — 30/min, applied to: auth (register, login, forgot/reset, confirm/password update), onboarding, profile, notifications, `/check`, bookings, checkout.
  - `admin` — 120/min, applied to admin auth (login, forgot/reset) + every admin CRUD group beside `no.cache`.
  - `cart` — 30/min, applied to all cart routes.
  - `ai` — 10/min, applied to dashboard (Gemini-backed recommendations); ready for the planned chatbot.
  - `webhook` — 10/min, applied to payment webhook (IP keyed; signature check still fails closed).

### Hardening
- `Booking::HOLD_STATUSES` now drives cart/inventory availability.
- `removed_images` in Hotel/Room/Activity admin controllers deletes `file:` only when the path belongs to that entity's own image list.
- `AdminPackageController` `image_url` validated as `nullable|url|max:500`.
- Removed duplicate `/admin/login` from `destinationRoute.php`.
- `ConnectionController` no longer echoes exception text; logs instead.

## Verification
- `php artisan test --filter=BookingFlowTest`: 12 tests, 66 assertions, green — includes new cases:
  - unauthenticated visitor → redirect to login on booking pages
  - non-owner → 404 on show/cancel/simulator (IDOR)
  - owner → 200
- Full suite: 30/41 pass; 11 failures are the known pre-existing scaffold Auth/Profile cases (factory users redirected to /onboarding by `CheckUserOnboarding`) — unchanged by this work.

## Out of scope / accepted risks (documented)
- Simulator provider is a demo; now non-owner-accessible on the customer side (requires auth + ownership).
- `GET /pay/return` must remain a GET (Stripe redirect target); now owner-only.
- Guest carts are not auto-migrated on login — `cart.index` shows both; acceptable UX note.