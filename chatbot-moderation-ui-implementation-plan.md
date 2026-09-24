# Chatbot Moderation UI Plan

## Objective

Correct the chat widget so an authenticated user whose message is blocked by
the safety guard sees a non-blocking moderation notice and can continue
chatting. Centralize prohibited-content detection so guests and authenticated
users evaluate the same categorized terms and aliases. Preserve the existing
guest rate-limit lock panel and actual account-suspension behavior.

## Confirmed cause

The server correctly identifies some authenticated abuse reports and returns a
`403` response with `status: blocked` plus the administrator-review message.
However, the expanded prohibited-term list currently exists only in the guest
branch of `ChatbotService`; authenticated requests use a separate, much smaller
list in `GeminiService`. As a result, terms such as `fuck`, the phrase “what
the fuck”, `wtf`, and much of the listed Tagalog/scam/prompt-injection
vocabulary are not consistently detected for signed-in users.

The widget then treats every safety-blocked `403` as a guest limit: it calls
`showLockPanel()`, sets `guestLimited = true`, hides the input, and renders
Log in / Sign up actions—even when `isAuthed` is true.

An account-level `warning` is already non-restrictive: it is prepended once to
the next successful response for users with `ban_level = warning`. A new abuse
report does not automatically assign that level; it creates a pending report
for administrator review.

## Response contract

| Situation | Server result | Widget behavior |
| --- | --- | --- |
| Guest reaches chat quota | `429`, `guest_limit_reached` | Keep the existing login/sign-up lock panel. |
| Authenticated user sends prohibited content | `403`, `blocked` | Display the safety message in the conversation or a transient warning; keep the input available. |
| Guest sends prohibited content | `403`, `blocked` | Display the safety message; do not present it as a login requirement unless the server explicitly signals a guest quota. |
| Temporarily/permanently suspended account | Existing suspension response/middleware | Preserve the existing access restriction and suspended-account flow. |
| Admin-issued account warning | Successful chat response with warning marker | Preserve the current once-per-session notice and continued chatting. |

## Files

- `[NEW] app/Services/Chat/ChatbotModerationPolicy.php`
  - Become the single source of truth for prohibited categories, terms, and
    accepted aliases used by every chat request.
  - Move the full current list into explicit categories, preserving the
    existing labels used by moderation reports where feasible.
  - Add intentional shorthand/phrase aliases such as `wtf` and “what the
    fuck”; do not rely on the literal word `fuck` to detect an acronym.
  - Normalize case and whitespace before matching and use boundary-aware
    matching for individual words, while supporting multi-word phrases and
    hyphenated terms deliberately. This avoids accidental substring matches
    such as unrelated words containing `sex`.
  - Return structured match data (category, matched canonical term, and safe
    report reason), or no match. Keep raw user messages out of client-facing
    diagnostics.

- `[MODIFY] app/Services/Chat/ChatbotService.php`
  - Remove the guest-only `bannedPatterns` list and delegate guest detection to
    the shared moderation policy.
  - Preserve the guest-specific operational behavior: safety events are logged
    without creating a user-linked report when no authenticated user exists.

- `[MODIFY] app/Services/GeminiService.php`
  - Remove the smaller authenticated-only category list and delegate to the
    shared moderation policy.
  - Preserve the existing authenticated enforcement behavior: create a pending
    `ChatbotAbuseReport`, increment `chatbot_flag_count`, and return the
    administrator-review response for a matched violation.
  - Keep account-suspension checks intact and before normal chat processing.

- `[MODIFY] resources/views/components/frontend/chat-widget.blade.php`
  - Split the current `403 && data.status === 'blocked'` handling from guest
    quota handling.
  - Use the existing `isAuthed` state only for presentation branching; do not
    trust it for enforcement.
  - For a blocked message, append the server-provided safety text as a bot
    message (or render an equivalent non-blocking warning) without calling
    `showLockPanel()` or setting `guestLimited`.
  - Keep `showLockPanel()` exclusive to the explicit `429 /
    guest_limit_reached` contract, including its login and registration URLs.
  - Ensure the message composer remains usable after an authenticated safety
    block, and preserve scroll and sending-state cleanup in `finally`.

- `[MODIFY] tests/Feature/ChatbotTest.php`
  - Retain the server-side assertion that an authenticated safety violation
    returns `403` / `blocked` and produces an abuse report.
  - Add regression cases proving the same prohibited vocabulary is detected
    for guests and authenticated users, including an English profanity phrase,
    its `wtf` acronym, one Tagalog term, and one prompt-injection/scam term.
  - Assert category/report metadata for authenticated matches and confirm guest
    matches do not create a user-linked abuse report.
  - Add an assertion that the response remains the administrator-review copy,
    not the guest-only "Please log in to continue chatting" copy.
  - Keep the guest abuse and repetition-guard tests, since this change must
    not alter moderation enforcement or reporting.

- `[NEW] tests/Feature/ChatWidgetModerationTest.php` *(only if the project has
  an established browser/component test approach available)*
  - Exercise the rendered widget with an authenticated session and a mocked
    `403` / `blocked` response.
  - Assert that the safety text is visible, the composer is still visible and
    enabled after the request completes, and the Log in / Sign up lock panel
    is absent.
  - Exercise the `429` / `guest_limit_reached` response separately to confirm
    the guest lock panel remains intact.
  - If no browser/component test harness exists, cover the JavaScript behavior
    with the project-approved UI test mechanism rather than adding a new test
    framework solely for this change.

## Implementation steps

1. Extract the full moderation vocabulary into one shared, categorized policy
   with controlled aliases and boundary-aware matching.
2. Replace both current detection paths with that policy while retaining their
   distinct reporting behavior for guest versus authenticated requests.
3. Confirm the existing widget’s `isAuthed`, `guestLimited`, and
   `showLockPanel()` state transitions in the browser.
4. Change only the `403` / `blocked` frontend branch so it renders a
   non-blocking moderation notice instead of invoking the guest lock panel.
5. Leave the backend guard, `403` status, abuse-report creation, flag counts,
   and admin review workflow unchanged; the API semantics already distinguish
   quota exhaustion (`429`) from safety blocking (`403`).
6. Add focused regression coverage for shared detection, the authenticated
   response copy and, where supported, the widget’s unlocked state after a
   block.
7. Manually verify in the Brave/Playwright browser: submit a prohibited message
   while logged in, see the warning, then submit a normal travel question and
   receive a reply. Repeat with `wtf` and the full profanity phrase.

## Out of scope

- Automatically changing a newly created abuse report into an account
  `warning`. That is a product-policy decision and would alter the admin
  moderation workflow.
- Changing ban thresholds, IP restrictions, or suspension enforcement.
- Rewording the guest guard’s current login prompt; this plan only prevents it
  from being shown for an authenticated safety block.

## Verification

1. Run the focused chatbot feature tests, including the authenticated abuse,
   guest abuse, repetition-guard, and warning-notice cases.
2. Run `vendor/bin/pint --dirty --format agent` if a PHP test is changed.
3. Use the mandated local Brave/Playwright flow to verify the logged-in UI
   behavior and that guest quota lockout remains unchanged.
4. Ask for a full-suite run with `php artisan test --compact` after focused
   checks pass.

## Acceptance criteria

- A logged-in user who submits prohibited content sees the administrator-review
  message, not a request to log in or register.
- The user can send a subsequent permitted travel question without refreshing
  the page.
- A guest and a logged-in user are both detected for the same centralized
  prohibited terms, including `wtf` and “what the fuck”.
- Guest quota exhaustion continues to show the existing lock panel.
- Actual suspended accounts remain unable to use the chatbot.
- Existing account-warning behavior remains non-blocking and appears only once
  per chat session.
