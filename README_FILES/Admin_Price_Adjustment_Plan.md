# Admin_Price_Adjustment_Plan.md — Admin Price Adjustment on Booking Approval

Status: Foundation plan (spec). This doc is the source-of-truth for the feature being planned. Verify against the code before trusting (it may drift during implementation).

## Concept

When an admin reviews a pending booking, they can apply a **price adjustment** before approving it:

- **Discount** — subtract an amount from the final price (e.g. `-₱2,000` "room was listed with outdated photos, sorry for the inconvenience").
- **Additional amount** — add an amount (e.g. `+₱500` "extra guest not declared in the original request").

Every adjustment **must** carry a short reason/message written by the admin. That reason is shown to the customer in the approval email and on the booking journal ("Settlement") page, next to the new total. The adjustment is **locked at approval time** — it cannot be edited later; later changes go through the existing refund/mark-paid flows.

`net_amount` remains the single source of truth for what the customer pays (Stripe charge, pay page, journal, admin list) — the adjustment simply feeds into the existing net formula.

## Non-goals

- No re-editing of an adjustment after the booking is approved (locked in).
- No adjustment on rejected/cancelled/expired bookings (nothing to charge).
- No change to passenger-category discounts (`discount_amount`/`tax_amount` stay computed from the guest manifest at checkout).
- No change to per-item include/quantity/admin_note mechanics at approval.
- No new notification center UI (the orphaned `/notifications` page stays as-is; the message reaches the user via email + booking page).

## Schema (migration on `bookings`)

| Column | Type | Notes |
|---|---|---|
| `admin_discount_amount` | decimal(10,2) | default 0.00 — subtracted from net |
| `admin_surcharge_amount` | decimal(10,2) | default 0.00 — added to net |
| `price_adjustment_reason` | text | nullable — required when either amount > 0 |
| `price_adjusted_at` | timestamp | nullable — set when an adjustment is applied |

`Booking::$fillable` extended; both money columns cast `decimal:2`.

## Totals formula

Current (in `BookingRequestService::buildFromCart` line ~135 and `repriceForApproval` line ~227):

```
net_amount = max(0, total_amount - discount_amount + tax_amount)
```

New:

```
net_amount = max(0, total_amount - discount_amount + tax_amount - admin_discount_amount + admin_surcharge_amount)
```

- Applied in **both** `buildFromCart()` (new bookings — both admin amounts are 0, formula unchanged in practice) and `repriceForApproval()` (the only post-creation recalc point, called from admin approve).
- Clamped to `0.00` — a discount can never drive the total below zero.
- `total_amount` (item subtotal) is NOT touched — the adjustment is tracked separately and visible in the ledger.

## Admin flow (`AdminBookingController::approve` + `admin/bookings/show.blade.php`)

Approve form (totals block, lines ~256–293 of the admin show view) gains, visible only for `pending` bookings:

- `admin_discount_amount` — number input, `min:0`, `step:0.01`, label "Admin discount (−)"
- `admin_surcharge_amount` — number input, `min:0`, `step:0.01`, label "Additional amount (+)"
- `price_adjustment_reason` — textarea, "Reason for adjustment (shown to customer)", placeholder e.g. "Applied a ₱500 courtesy discount because the room was unavailable on the first night."

Validation in `approve()`:

```php
'admin_discount_amount'  => 'nullable|numeric|min:0|max:999999',
'admin_surcharge_amount' => 'nullable|numeric|min:0|max:999999',
'price_adjustment_reason' => 'nullable|string|max:500',
```

Plus a rule: when `(admin_discount_amount > 0 || admin_surcharge_amount > 0)` the reason is required (422/back error "Please provide a reason for the price adjustment."). Also fail with an error if the resulting `net_amount` would be negative — it is clamped to 0 by the formula instead (no hard error).

Order of operations inside `approve()` (after `repriceForApproval`, before `markStatus`):

1. Normalize amounts (`null` → `0.00`); if both are zero, skip adjustment entirely.
2. Write amounts + reason + `price_adjusted_at = now()` onto the booking.
3. Call `repriceForApproval` with the existing item adjustments so `net_amount` reflects the new formula.
4. `markStatus(APPROVED, ...)` — note string includes the adjustment, e.g. `"Approved with 3 item(s) available. Admin discount -₱2,000.00 applied (reason)."` → shows in both admin and user status timelines automatically.
5. `BookingNotification::send($booking, new BookingApproved($booking))`.

Admin show page totals block also displays the adjustment rows (Admin discount −₱X, Additional amount +₱X) once applied, and hides the inputs for non-pending bookings.

## User-facing

**`BookingApproved` notification** (`app/Notifications/BookingApproved.php`):

- `toMail()`: after the approval/amount-due lines, add a "Price adjustment" section when any amount is set:
  - Discount: "We've applied a discount of −₱X to your booking." + reason.
  - Surcharge: "An additional amount of +₱X has been added to your booking." + reason.
  - Both possible; quote the admin's reason verbatim.
- `toArray()` (base `BookingNotification`): add `admin_adjustment: { discount, surcharge, reason }` alongside `booking_code/booking_url/status/message` so the DB notification payload carries it too (rendered nowhere new, but available).

**`resources/views/booking/show.blade.php`** — Settlement section (lines ~501–565):

- New rows after "Subtotal" when applicable:
  - "Admin discount" → `−₱X` in emerald.
  - "Additional amount" → `+₱X` in amber.
- When an adjustment exists, show a highlighted note card with the admin's reason, labeled "Note from SunnyTrips" (or similar), direction-colored (emerald for discount, amber for surcharge, both if both).
- Total due row already reads `net_amount` — unchanged, now reflects the adjustment.

**`booking/index.blade.php`** and `booking/pay.blade.php` — no changes (they already read `net_amount`).

## Edge cases

- Both fields empty / 0 → no adjustment, no reason needed, notification unchanged.
- Discount larger than the total → `net_amount` clamps to `0.00`; customer pays ₱0 at checkout/pay.
- Amount entered without reason → approval blocked with a clear error.
- Reason entered with no amounts → ignored (no adjustment, no reason shown).
- Adjustment only meaningful on the pending → approved transition; guards in `approve()` already limit to pending.
- `repriceForApproval` must **not** clobber the admin amounts (it currently recomputes from `discount_amount`/`tax_amount` only — the new formula reads the booking's own admin columns).

## Tests (`tests/Feature/AdminPriceAdjustmentTest.php`, Pest)

1. Approve with discount + reason → `net_amount` reduced, `price_adjusted_at` set, reason persisted.
2. Approve with surcharge + reason → `net_amount` increased.
3. Amount set without reason → approval rejected (session/validation error), booking still pending.
4. Discount exceeding total → `net_amount` = 0.00 (clamp).
5. Approve with no amounts → `net_amount` unchanged, no adjustment fields set.
6. DB notification (owner) carries `admin_adjustment` discount/surcharge/reason.
7. `BookingApproved` email renders the adjustment section (assert `toMail` message contains amount + reason).
8. Guest contact (no account) still gets the email via `BookingNotification::send` fallback path.
9. Admin adjustment columns survive `recreateCartFromBooking` flow (rebook carries original totals, no admin amounts).
10. `booking.show` renders the adjustment rows + reason card for a user owner.

## Files touched (expected)

- `Admin_Price_Adjustment_Plan.md` (this doc)
- `database/migrations/2026_08_08_XXXXXX_add_admin_price_adjustment_to_bookings_table.php`
- `app/Models/Booking.php` (fillable + casts)
- `app/Services/BookingRequestService.php` (formula in `buildFromCart` + `repriceForApproval`)
- `app/Http/Controllers/Admin/AdminBookingController.php` (validation + apply in `approve`)
- `app/Notifications/BookingNotification.php` + `BookingApproved.php` (payload + mail)
- `resources/views/admin/bookings/show.blade.php` (inputs + totals rows)
- `resources/views/booking/show.blade.php` (settlement rows + reason card)
- `tests/Feature/AdminPriceAdjustmentTest.php`
