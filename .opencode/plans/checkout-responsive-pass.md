# Checkout page responsive pass — plan

**Status:** awaiting approval (plan mode). Nothing in this plan has been applied.

**Scope:** Blade/Tailwind classes only. No PHP logic, no Alpine logic, no new deps, no commits.

**Files touched (2):**
- `resources/views/components/frontend/guest-manifest-form.blade.php`
- `resources/views/checkout/index.blade.php`

---

## Measured defects (all reproduced at 375px, clientWidth 360)

| # | Defect | Evidence |
|---|---|---|
| 1 | **`p-4.5` is not a Tailwind v3 class → compiles to nothing** | probe element with `p-4.5` → `padding: 0px`; probe with `p-4` → `16px`. The two biggest cards report `padding: 0px` at 375px vs `28px` at 1280px. |
| 2 | **Inputs 34–38px tall at 12px font** | all 6 manifest/contact inputs measure 34–38px / `12px` at 375px. Under the 44px touch target and under the 16px iOS zoom threshold. |
| 3 | **Submit button is 40px and sits at y=1761 in a 1984px page** | measured at 375px. `pb-32` (128px) at the page bottom reserves room for a fixed bar that does not exist → 183px of dead space. |
| 4 | **Sticky summary `top: 112px` with no fixed header at lg** | `getComputedStyle(card).top` = `"112px"`, `position: sticky`. The authed layout has no top header ≥768px, so once scrolled the card parks 112px down, leaving an empty band. The cart page uses `lg:top-8`. |
| 5 | **Chat FAB covers the add-guest capacity text** | screenshot at 375px: the FAB is drawn over "Base capacity: 2 Guests · Max capacity: 2 Guests". FAB is `fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40`. |
| 6 | **"+ Add Accompanying Guest" button is 161×70px with a 3-line label** | measured at 375px: row 326×78px, button 161×70px. Long capacity string crammed beside it in a `flex items-center justify-between`. |

**Verified NOT broken (do not touch):** no horizontal overflow at 375/1280; no page-level scroll. Sticky summary does **not** overlap the FAB at 812×375 (33px clearance — noted, not proven broken).

**Out of scope this pass** (user chose "proven defects only"):
- 10 text nodes under 11px (smallest `text-[9.5px]`) in the pricing breakdown and How Booking Works list.
- `max-h-72 overflow-y-auto` on the itinerary item list (nested vertical scroller inside the sticky card).
- Duplicate full-name capture (contact card + manifest Lead Traveler row, synced via `$watch('leadName')`).
- Dead `@else` branch at `checkout/index.blade.php:144-185` that rebuilds a contact card `guest-manifest-form.blade.php:42-90` already renders.

---

## Edits

### A. `resources/views/components/frontend/guest-manifest-form.blade.php`

**A1 — the `p-4.5` bug.** L42 and L93, replaceAll:
`p-4.5 sm:p-7` → `p-4 sm:p-7`

**A2 — lead contact inputs (L64, L74, L87), replaceAll:**
`px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm`
→ `px-3.5 py-3 sm:py-2.5 rounded-xl border border-slate-200 text-base sm:text-sm`

**A3 — guest row inputs + category select (L140, L148, L175), replaceAll:**
`px-3.5 py-2 rounded-xl border border-slate-200 text-xs`
→ `px-3.5 py-3 sm:py-2 rounded-xl border border-slate-200 text-base sm:text-sm`

**A4 — add-guest row (defect 6).** L183 wrapper:
`pt-2 flex items-center justify-between`
→ `pt-2 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between`

L188 button, add `w-full sm:w-auto`.

### B. `resources/views/checkout/index.blade.php`

**B1 — hide the chat widget (defect 5).** L112, add the prop:
`<x-frontend.layout :title="...":hide-chat-widget="true">`

**B2 — sticky offset (defect 4).** L446:
`lg:sticky lg:top-28` → `lg:sticky lg:top-5 xl:top-8`

**B3 — `pb-32 sm:pb-16` on L115 stays as-is, on purpose.** It currently looks like dead space; once B4 lands it is the clearance for the fixed bar. Do not "clean it up".

**B4 — new mobile sticky submit bar (defects 3 + 5).** Last child of `#checkoutForm`, immediately before `</form>` (L555). `position: fixed` takes it out of the grid flow, so it creates no track, and placing it inside the form means the native submit fires the existing `@submit.prevent="submitCheckout()"` with no new attribute and no duplicated logic. `:disabled="isSubmitting"`, `formattedTotalNet` and the `isSubmitting` icon swap are all read from the same `checkoutEngine` scope that already owns them.

```blade
{{-- Mobile sticky submit bar — keeps the total + CTA reachable without scrolling the whole manifest --}}
<div class="lg:hidden fixed bottom-0 inset-x-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] flex items-center gap-3 font-body">
    <div class="flex-1 min-w-0">
        <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Total Net Amount</p>
        <p class="font-display text-lg font-bold text-sky-900 truncate"
            x-text="formattedTotalNet">₱{{ number_format($totalAmount, 2) }}</p>
    </div>
    <button type="submit" :disabled="isSubmitting"
        :class="isSubmitting ? 'opacity-50 cursor-wait' : 'hover:from-sky-500 hover:to-sky-600 cursor-pointer shadow-md shadow-sky-600/25'"
        class="shrink-0 inline-flex items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 text-white font-extrabold text-sm px-5 py-3.5 whitespace-nowrap">
        <span class="material-symbols-outlined text-[16px]" x-show="!isSubmitting">verified</span>
        <span class="material-symbols-outlined text-[16px] animate-spin" x-show="isSubmitting"
            x-cloak>progress_activity</span>
        <span x-text="isSubmitting ? 'Submitting...' : 'Submit Request'">Submit Request</span>
    </button>
</div>
```

**B5 — desktop submit button to 44px.** L542, `py-3` → `py-3.5`.

**B6 — input touch sizing (defect 2), replaceAll in this file:**
- L166, L172, L181, L273 share
  `px-3.5 py-2 sm:py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm`
  → `px-3.5 py-3 sm:py-2.5 rounded-xl border border-slate-200 text-base sm:text-sm`
- L301, L307 share
  `px-3.5 py-2 rounded-xl border border-slate-200 text-xs`
  → `px-3.5 py-3 sm:py-2 rounded-xl border border-slate-200 text-base sm:text-sm`

**B7 — OPTIONAL, include or skip.** L335-350, the per-item manifest add-guest row, is the identical `flex items-center justify-between` + long-label pattern as A4. It was **not measured** because the current basket has no item that renders it. One line to make it `flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between` + `w-full sm:w-auto` on its button. Say the word and I'll fold it in, otherwise I leave it alone.

---

## Verification

1. `p-4.5` gone: at 375px assert both manifest cards report `padding` ≥ 16px (was `0px`).
2. Touch sizing: at 375px assert every `input`/`select` inside `#checkoutForm` is ≥ 44px tall and `font-size` ≥ 16px; assert the desktop summary submit is ≥ 44px at 1280px.
3. Sticky: assert `getComputedStyle(summaryCard).top` is 20px/32px, not `112px`.
4. Bar visibility: `display: block` at 375/390/768, `display: none` at 1024/1280. Bar CTA ≥ 44px tall.
5. **Prove the bar actually submits — do not assume.** Assert `#checkoutForm` contains the bar button, then attach a `submit` listener to the form, click the bar CTA, and assert the event fired. `@submit.prevent` is what stops the real navigation, so the handler is the thing under test. (A `getAttribute('@click')` check is worthless here — the same vacuous trap as the cart pass.)
6. Regression: chat widget absent on `/checkout`, still present on `/dashboard`.
7. `documentElement.scrollWidth === clientWidth` and zero overflowing elements at 320/375/390/768/812×375/1280 on a clean load each time.
8. 0 console errors, checked WITHOUT `all: true` (repeated reloads trip the chatbot rate limiter).
9. `php artisan test --compact --filter=Checkout` (CheckoutEmptyLeakTest), `--filter=BookingFlow`, `--filter=Cart`, `--filter=ExpiredCartDates`; then `vendor/bin/pint --dirty --format agent`.
10. Delete every screenshot and `.playwright-mcp/`. Untracked, never committed.

## Rollback

`git checkout -- resources/views/checkout/index.blade.php resources/views/components/frontend/guest-manifest-form.blade.php` restores both files. They are currently uncommitted, but that is the only thing separating them from the last commit — confirm before relying on it.
