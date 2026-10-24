# Trip journey email implementation plan

**Prepared:** 22 October 2026 (Asia/Manila)  
**Responsibilities:** Claude implements; Codex independently validates.  
**Status:** Planning only. This document changes no application behavior.

## 1. Confirmed request and working scope

Allow registered users to receive trip journey emails whether their account email is verified or unverified. Keep the recipient as the account's saved email address, and retain reminder preferences, explicit marketing opt-in, booking/service eligibility, deduplication, and delivery-time checks.

Apply this consistently to all five existing journey kinds: pre_trip, departure, review, continue, and repeat. Removing verification must not mark accounts verified or weaken login, booking ownership, review authorization, or unrelated verified-only pages.

The user recalled a review prompt before the trip ends, but has not explicitly selected a new timing policy. **Baseline implementation retains the current post-trip review timing.** The optional timing changes in section 8 are not instructions to implement automatically.

This task does not authorize sending real emails to users. Use mail/queue fakes for automated verification. Any live delivery check needs an explicitly designated test recipient.

## 2. What the existing emails do

Source of truth: [TripJourneyService](C:/xampp/htdocs/SunnyTripsCapstoneV2/app/Services/TripJourneyService.php). The earlier [trip planner plan, email sequence](C:/xampp/htdocs/SunnyTripsCapstoneV2/TRIP_PLANNER_IMPLEMENTATION_PLAN.md:149) contains additional proposals that are not all implemented.

| Email / internal kind | Current content and action | Current timing / eligibility | Preference |
|---|---|---|---|
| Pre-trip preparation / pre_trip | “Your island days are almost here”; review hotel dates, meeting points, visits, weather restrictions. “Review your trip” button. | Trip starts within the next 24 hours; eligible paid/cancellation-denied booking. | Trip reminders |
| Trip check-in and departure / departure | “Before you head home”; asks “How is your trip going?” and reminds users about checkout/onward transport. “Check departure details” button. | Trip has started and its computed end is within the next 24 hours. | Trip reminders |
| Thank-you and review invitation / review | “Thank you for traveling with SunnyTrips”; asks for feedback on completed services. “Leave a review” button goes to the booking's reviews section. | Booking completed; at least 24 hours after computed trip end, within the 14-day eligibility window; at least one included, fulfilled reviewable service. | Trip reminders |
| Saved-plan follow-up / continue | “Your [trip title] is waiting”; resume planning. “Continue planning” button. | Saved non-draft plan inactive for 1–7 days, future arrival, nonempty choices, no transferred itinerary. One dedupe key per plan. | Marketing opt-in |
| Return-trip suggestion / repeat | “Ready for another island?”; explore destinations. “Explore destinations” button. | Completed trip ended 30–37 days ago. | Marketing opt-in |

Important timing details:

- Current trip end is the latest included item's final date at **23:59:59 Asia/Manila**, unless extended. It is not the hotel's actual checkout time.
- Consequently, the departure window commonly falls on the final calendar day. It is not a dedicated “day before checkout at 16:00” schedule.
- The scheduler runs hourly. The eligibility windows are not promises of delivery at an exact minute.
- The existing shared cap allows at most one newly queued journey message per recipient within 20 hours for pending/sending/sent rows. Another journey message can defer or crowd out a short reminder window.
- Jobs recheck eligibility and suppress messages older than six hours. Review/marketing windows and preference checks still apply.
- Booking-based journey emails currently require stored trip_itineraries. Expanding them to every ordinary booking or guest contact is a separate scope decision.

### What “leave a review in Gmail” means here

The existing HTML email displays a **Leave a review** button. Clicking it opens the SunnyTrips booking page's reviews section; the user signs in if needed and submits feedback in the app. This plan does not add an embedded review form inside Gmail or a Gmail-specific integration.

There are also separate, existing booking/payment notifications and a user-requested “Email itinerary” notification with a PDF. They are outside the five-email automation and should not be duplicated or broadly refactored for this change.

## 3. Current blockers and exact change points

### A. Three verification gates must be handled

In [TripJourneyService](C:/xampp/htdocs/SunnyTripsCapstoneV2/app/Services/TripJourneyService.php):

1. **process(), saved-plan loop:** requires email_verified_at before queuing continue.
2. **bookingEligible():** rejects unverified users for pre_trip, departure, review, and repeat.
3. **deliveryEligible():** rejects unverified users again immediately before delivery.

Changing only bookingEligible() is insufficient: continue would remain blocked at enqueue, and all unverified recipients would still be suppressed at delivery.

Remove only verification as an eligibility prerequisite. Retain a real account and a usable saved recipient email; do not substitute arbitrary request input as the recipient. Preserve the existing account-email recipient policy.

### B. Automatic processing must actually be enabled

[config/services.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/config/services.php:10) defaults TRIP_JOURNEY_ENABLED to false. The inspected local .env has no explicit TRIP_JOURNEY_ENABLED assignment; .env.example explicitly sets false.

This establishes a **configuration risk**, not proof of effective runtime state: external environment values or cached configuration may override the file/default.

The [scheduled command](C:/xampp/htdocs/SunnyTripsCapstoneV2/routes/console.php) only runs when this setting is enabled. [scripts/dev.js](C:/xampp/htdocs/SunnyTripsCapstoneV2/scripts/dev.js) already starts the server, queue listener, scheduler, and Vite through composer dev.

Implementation handoff:

- Verify the effective journey flag, mail transport, scheduler, and queue worker.
- Keep activation as a distinct deployment step after tests pass. Enabling it can queue real mail for existing eligible users, including marketing-opted-in users.
- Preserve the disabled-by-default example for fresh environments unless a different default is explicitly requested.
- Do not silently switch production mailers, edit credentials, clear shared configuration, or manually run the journey command during a read-only validation session.
- Do not use Codex automations; this belongs to the Laravel scheduler/queue.

### C. Email links must work for an unverified owner

[Booking routes](C:/xampp/htdocs/SunnyTripsCapstoneV2/routes/bookingRoute.php) and [review routes](C:/xampp/htdocs/SunnyTripsCapstoneV2/routes/reviewRoute.php) currently require authentication but do not declare verified middleware.

Test the complete destination flow for an unverified, onboarded owner. Retain ownership checks and actual review eligibility. Global onboarding redirects can affect a user who has not completed/skipped onboarding; distinguish that behavior from email verification.

## 4. Proposed files and boundaries

| File | Planned action |
|---|---|
| [app/Services/TripJourneyService.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/app/Services/TripJourneyService.php) | **[MODIFY]** Remove the three journey verification gates. Keep all other rules; use a small shared recipient predicate only if it removes meaningful repetition. |
| [tests/Feature/TripJourneyTest.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/tests/Feature/TripJourneyTest.php) | **[MODIFY]** Parameterize verified/unverified recipients for all five journey kinds; test actual job delivery with fakes and remaining suppression rules. |
| [tests/Feature/ReviewTest.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/tests/Feature/ReviewTest.php) | **[MODIFY if existing coverage is insufficient]** Verify unverified owners can use the review CTA destination/submit eligible reviews, while foreign or unfulfilled items remain rejected. Keep coverage in the most appropriate existing test file. |
| [app/Jobs/SendTripJourneyMail.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/app/Jobs/SendTripJourneyMail.php) | **[INSPECT / preserve]** It already calls deliveryEligible(). No independent email-verification bypass should be added here. |
| [app/Mail/TripJourneyMail.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/app/Mail/TripJourneyMail.php) | **[INSPECT / preserve]** Preserve subjects, HTML/plain text delivery, and provider idempotency key. |
| [email HTML](C:/xampp/htdocs/SunnyTripsCapstoneV2/resources/views/emails/trip-journey.blade.php) and [plain text](C:/xampp/htdocs/SunnyTripsCapstoneV2/resources/views/emails/trip-journey-text.blade.php) | **[INSPECT / preserve]** No redesign needed for verification removal. Copy/CTA changes require the separately chosen scope below. |
| [config/services.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/config/services.php), [routes/console.php](C:/xampp/htdocs/SunnyTripsCapstoneV2/routes/console.php), [scripts/dev.js](C:/xampp/htdocs/SunnyTripsCapstoneV2/scripts/dev.js) | **[VERIFY]** Existing flag, hourly schedule, and dev worker/scheduler wiring; no automatic file changes planned. |

No dependency changes or schema migration should be necessary for the baseline.

The workspace already has ongoing edits to planner, cart, checkout, support, and tests. Claude must inspect the current diff, preserve that work, and keep this change scoped. Do not overwrite the existing general implementation_plan.md or other feature plans.

## 5. Implementation sequence for Claude

1. Re-read repository instructions, relevant rules/skills, and the current diff. Confirm installed package versions before relying on version-sensitive APIs.
2. Add meaningful failing regression coverage for the unverified-recipient case using the existing factories and test conventions.
3. Remove verification requirements at all three identified gates, leaving reminder/marketing consent and eligibility unchanged.
4. Verify queued delivery through SendTripJourneyMail, not merely the boolean helper.
5. Verify the review button's booking link and eligibility with an unverified owner. Keep authentication and ownership.
6. Run affected tests and required formatting. Review the final diff for unrelated changes.
7. Report exact files changed, tests run, failures/limitations, and effective activation status. Do not equate “queued” or a fake mail assertion with Gmail receipt.
8. Hand off to Codex for independent validation. Live activation and a real delivery test are separate steps with a specified environment and recipient.

## 6. Regression and acceptance matrix

| Scenario | Expected result |
|---|---|
| Each of the five email kinds, verified recipient | Existing eligible behavior preserved. |
| Each of the five email kinds, otherwise-identical unverified recipient | Same eligibility and queued delivery as verified recipient. Test both process() and the mail job. |
| Verification remains null after sending | Account stays unverified; sending does not mutate authentication state. |
| Reminders disabled | pre_trip, departure, and review do not queue/send. |
| Marketing disabled | continue and repeat do not queue/send, even for a verified account. |
| Preferences turned off after queuing | Job suppresses the appropriate email. |
| Missing/deleted account or unusable email | No provider attempt to an unintended/empty recipient. |
| Booking cancelled, rejected, expired, or cancellation requested | Existing suppression remains. |
| Trip extended or dates changed after queuing | Old date-signature job suppressed; new eligibility follows current dates. |
| Review case before trip end or before the grace window | No early review invitation under the baseline. |
| Completed booking with eligible fulfilled item | Review invitation eligible within current window. |
| No eligible fulfilled reviewable item | No review invitation. |
| Repeat scheduler/job execution | Existing dedupe/locking/idempotency behavior preserved. |
| Shared 20-hour cap or stale job beyond six hours | Existing cap/suppression respected; tests isolate their effects from verification. |
| Unverified authenticated owner follows review CTA | Reaches intended booking/reviews flow; can submit an otherwise eligible review. |
| Another account follows the same link or submits foreign item IDs | Access rejected. |
| Ordinary booking without trip_itineraries / guest-only booking | Existing journey exclusion preserved; no accidental scope expansion. |

Use separate fixtures/time windows for email kinds so the 20-hour recipient cap does not obscure a failure. Use Queue::fake and Mail::fake appropriately; do not fake away the job execution when proving send-time eligibility.

Suggested implementation-stage commands, following project rules:

- php artisan test --compact tests/Feature/TripJourneyTest.php
- Run the specific affected ReviewTest cases if added/changed.
- vendor/bin/pint --dirty --format agent after PHP edits; inspect any formatting interaction with concurrent work.
- After focused tests pass, request the project-wide suite: php artisan test --compact.

These tests were not run during plan creation.

## 7. Codex validation handoff

Codex should independently:

- Inspect Claude's diff against this plan and ongoing unrelated changes.
- Confirm all three verification gates are removed for journey emails, with no global auth changes.
- Run the narrow regression tests against the designated test database when validation execution is authorized.
- Check timing boundaries, marketing opt-in, send-time suppression, ownership, and deduplication.
- Review HTML/plain-text CTA output and existing preference management.
- Report implementation correctness separately from runtime readiness and real delivery.

For a later authorized real-email check, use one explicitly supplied test address, one eligible test record, and the configured mail transport. Verify the journey row/job result and provider delivery event, then have the recipient confirm receipt and the CTA. Provider acceptance is not proof of inbox placement. Avoid processing all existing eligible users just to test one email.

## 8. Earlier ideas to remember — optional, not silently included

The earlier trip-planner document proposed more than the current five kinds:

| Earlier proposal | Current state | Decision needed before expansion |
|---|---|---|
| Preparation 72 hours before travel | Current pre_trip uses a 24-hour window. | Add a separate preparation message or change the existing timing? |
| Evening-before-arrival digest, around 18:00 | No separate daily/arrival digest kind in this service. | Is one digest enough, or are daily messages wanted for longer trips? |
| Mid-trip check-up with support CTA | Current departure copy asks how the trip is going, but its primary CTA is departure details. | Add a separate long-trip check-up or enrich the existing combined email? |
| Last full-day departure/check-in around 16:00 | Current window is based on computed end at 23:59:59. | Schedule on the calendar day before checkout, or retain the final-24-hour rule? |
| Review prompt one day before the trip ends | Not the implemented review timing. | If chosen, distinguish a reminder to review later from permission to submit reviews for unfulfilled services. |
| One review reminder seven days later | No separate review-reminder kind. | Add only for remaining eligible, unreviewed services, with a new dedupe key and tests. |
| Second saved-plan reminder after 72 hours | Current continue is one message per plan. | Is a second reminder desired? |
| Quiet sending hours and refined caps | Current code uses an hourly scheduler and a shared 20-hour cap. | Choose local-time window and service-versus-marketing priority. |
| Personalized return-trip suggestions | Current repeat copy is a generic invitation. | Choose scope, data source, and marketing preferences. |

**Recommended first delivery:** make all five existing kinds work for unverified accounts while preserving their current timing and consent. Treat new timing, additional emails, recipient expansion, and richer marketing content as separate product choices.

## 9. Known adjacent findings to avoid losing

These remain useful follow-up work from [the diagnostic report](C:/xampp/htdocs/SunnyTripsCapstoneV2/SunnyTrips_Diagnostic_Report.md), but are not prerequisites for the narrow verification change:

- Journey payload uses only the first itinerary snapshot in a booking containing multiple plans.
- Review-email eligibility checks for a fulfilled reviewable item but does not itself filter out items already reviewed.
- Saved-plan follow-up currently links to the general planner rather than directly selecting that saved plan.
- A shared cap can suppress a timely operational reminder after another journey message.
- Gmail receipt and active scheduling were not established by this source inspection.

Do not claim these are fixed unless included, implemented, and separately validated.

