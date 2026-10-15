# SunnyTrips registration and email-change security plan

Status: implemented locally. Verification results and remaining release checks are recorded in section 11. Production code and settings have not been changed.

## 1. Verified baseline and scope

The following baseline describes the code before this implementation.

- Current local packages: Laravel 13.21.1 and Socialite 5.31.0. Confirm production versions/revision again before release.
- All three local Turnstile settings are present and nonempty. `TURNSTILE_EXPECTED_HOSTNAME` matches `sunnytripsv2.up.railway.app`. This confirms presence, not whether Cloudflare accepts the keys. No secret values were displayed.
- Password registration and Google redirect/callback currently use the shared `users` limiter: 30 requests/minute, authenticated ID or guest IP.
- Password registration validates contact details and the consent modal, creates the user, dispatches `Registered`, logs in, and redirects to onboarding.
- Google login uses a stateful Socialite exchange and a verified Google email. Existing accounts are found by Google ID, then email; otherwise `SocialAuthService::createFromGoogle()` creates an account with a null password.
- Profile updates currently accept name/email without reauthentication and call `fill($request->validated())`.
- Google-only users can currently set their first password with only their application session. That would bypass an email-change password requirement and must be secured in this work.
- `SocialAuthService::linkGoogleAccount()` currently marks the local email verified even when a returning linked Google identity has a different email. Preserve identity-based login but stop incorrectly verifying a changed local address.
- Socialite uses a generic session `state` key. Sensitive-operation state must be isolated or purpose-bound so ordinary login/signup callbacks cannot authorize profile changes.
- `.ai/rules` is absent. The existing `implementation_plan.md` contains unrelated plans and will remain untouched.

In scope: password registration abuse controls; Google new-account quotas; password-confirmed email changes; Google-only account confirmation; protection against setting a password to bypass that confirmation; directly related tests and UI.

Email verification remains disabled. No new ownership-verification workflow, database migrations, package installations, admin-auth changes, or production deployment are included in implementation authorization by this plan alone.

## 2. Decisions and security contracts

### Registration

1. Password signup requires valid consent, valid form fields, and a server-verified Turnstile token.
2. Google signup retains provider verification and gains new-account creation limits. Existing Google login does not consume creation quotas.
3. Both signup methods share a longer-window creation allowance so switching methods does not bypass it.
4. Turnstile is not required on ordinary returning Google login. Mandatory Turnstile on every Google signup would require a separate pending-signup challenge page; it is not part of the initial implementation.
5. Rate limits reduce abuse, not establish one person per account. Google email ownership does not prove that someone controls only one Google identity.

### Email changes

| Account state | Required proof |
| --- | --- |
| Local password exists, including linked Google accounts | Correct current password on the email-changing submission |
| No local password, linked Google identity exists | New, purpose-bound Google confirmation round trip for this specific operation |
| No password and no linked Google identity | Reject the sensitive change; no session-only fallback |
| Name-only edit or unchanged email | Existing authenticated profile flow; no additional proof |

Google confirmation proves access to the linked Google identity through a new OAuth interaction. Account selection/consent does not guarantee that Google asks for a password or MFA again. Do not label it a forced password challenge or assume a newly issued token proves recent credential entry. A stronger requirement for fresh provider credentials needs verified provider authentication-age support and must not be silently substituted with `prompt=select_account`.

## 3. Registration abuse implementation

### 3.1 Configuration

- Add `services.turnstile` entries for site key, secret key, and expected hostname. Verification uses an eight-second request timeout and a three-second connection timeout.
- Document empty variable placeholders in `.env.example`. Do not edit or commit populated `.env` values.
- Production hostname is `sunnytripsv2.up.railway.app`; no scheme, path, port, or trailing slash.
- Do not add a production bypass for missing keys, provider errors, or an environment-name check. Tests explicitly supply fake configuration and mocked provider responses.
- Use Cloudflare test keys for local browser testing; do not relax the production hostname allowlist merely to make local testing work.

### 3.2 Limits and ordering

Initial tunable values:

| Control | Limit | Key and application |
| --- | --- | --- |
| Password registration submissions | 5/minute and 20/hour | Resolved client IP; POST /register only; includes invalid submissions |
| Google OAuth traffic | Preserve existing general throttle initially | Guest redirect and callback; protects token exchanges |
| Shared new-account creation attempts | 20/hour | Resolved IP across both signup methods, immediately before creating a new account |
| Sensitive profile submissions | 5/minute and 20/hour | Authenticated user ID; email-change and first-password confirmation attempts |

Use distinct prefixes for minute, hour, and shared-creation counters. Password requests consume the perimeter attempt counters once and the separate creation counter once only when reaching account creation. Google existing-account login/linking does not consume the creation counter.

- Register route limiters in `AppServiceProvider`; add a focused `RegistrationAbuseService` for the shared creation gate called by both creation paths.
- Enforce shared check/reservation atomically using the existing persistent cache lock support. Concurrent valid Google callbacks must not exceed the configured creation allowance through a check/hit race.
- An attempt reservation may remain consumed if creation fails; document this conservative behavior. Do not clear quotas on login/logout or successful signup.
- Return 429 with Retry-After when blocked. Show a readable wait message for browser forms; do not expose cache keys or internal infrastructure details.
- Confirm shared persistent cache storage and trustworthy client-IP resolution behind Railway before release. Do not key primary creation limits by client-supplied session IDs or email alone.

Password signup order: guest/CSRF checks -> submission throttle -> ordinary field/consent validation -> Turnstile -> shared creation gate -> existing creation/event/login/onboarding flow.

Google order: existing guest/rate checks -> stateful OAuth exchange -> verified provider email -> existing account lookup -> returning-account handling OR shared creation gate followed by new-account creation.

### 3.3 Turnstile service

- New `TurnstileService` uses Laravel HTTP client and the fixed Cloudflare Siteverify endpoint.
- Require a nonempty token with a bounded length; send server secret and token. Optional remote IP must come from trusted framework IP resolution.
- Check HTTP success, JSON shape, strict success value, expected hostname, and action `register`.
- Reject missing, forged, expired, reused, wrong-hostname, wrong-action, malformed-response, timeout, and connection-error outcomes before user creation.
- Validate once per submission. Do not blindly retry a single-use token; any retry design must use the provider's idempotency mechanism deliberately.
- Keep error messages useful but generic. Log a safe failure category, not keys, challenge tokens, request bodies, or provider credentials.

### 3.4 Consent-modal UI

- Integrate Managed Turnstile into the final registration/consent step, associated with `register-form`.
- Preserve required consent checks and existing legal text. Ensure external modal controls and the response field are submitted with the correct form.
- Combine challenge readiness with consent readiness; do not let the existing checkbox JavaScript re-enable submission when the challenge is incomplete.
- Provide pending, failed, expired, and retry states. Obtain a fresh token after a consumed-token submission fails elsewhere.
- Prevent double clicks and preserve ordinary form data after errors, never password values.
- Verify keyboard navigation, modal focus, announced errors, and mobile layout. Avoid redesigning the page.
- Review existing CSP first: production currently permits HTTPS scripts/frames, so no broader permission is expected to be necessary.

## 4. Password-account email change

- Normalize email consistently (trim/lowercase) before comparison; validate scalar shape before applying string helpers so array-shaped input cannot cause a server error.
- Conditionally require `current_password:web` when a different email is submitted and `hasPassword()` is true.
- Apply the account-based sensitive-action throttle before credential validation. Unchanged-email edits do not consume this allowance.
- `ProfileController::update()` must save only allowed profile fields, never control fields such as `current_password`.
- Validate the entire submission before saving. Wrong/missing password must not partially save the name or email.
- Preserve unique-email enforcement and clear `email_verified_at` on a real email change.
- Never modify `google_id` when the local contact email changes.
- On UI: show a labeled current-password input for changed email, use `autocomplete=current-password`, associate errors, and never restore the password from old input.
- UI visibility is convenience only; a direct PATCH request must face the same server checks.

## 5. Google-only confirmation design

### 5.1 Dedicated authenticated routes

Add `routes/accountSecurityRoute.php`, required from web.php, rather than changing the existing guest-only OAuth routes into identity-switching routes.

Proposed route contracts:

| Method/path | Purpose |
| --- | --- |
| POST /account-security/google/confirm | Begin a CSRF-protected sensitive operation; validate and store its pending data |
| GET /account-security/google/callback | Validate the provider response and mark the pending operation confirmed; never create/link/login another account |
| POST /account-security/confirm | Final CSRF-protected commit of the confirmed pending operation |

Use `auth:web`, normal ban checks, and suitable throttles. Existing profile access requires onboarding; keep the same boundary for these operations and use onboarded users in tests. Do not exempt broad account routes from onboarding without a concrete need.

Register a separate Google Cloud authorized redirect URI:

`https://sunnytripsv2.up.railway.app/account-security/google/callback`

Add `services.google.reauth_redirect`, optionally backed by `GOOGLE_REAUTH_REDIRECT_URI`, using the same existing OAuth client ID/secret. Keep the normal login callback intact. Use an isolated provider instance/configuration so a reauth redirect does not mutate the normal login driver's redirect URI or cached identity.

### 5.2 Pending-operation contract

Use a small `AccountSecurityService` with shared-cache state and locks; no new table is needed.

- Create an opaque random operation ID bound to user ID, session, purpose, linked Google ID, original local email, and validated target email/name where relevant.
- Pending operation expires after 10 minutes. Provider-confirmed authorization expires after 5 minutes and never extends the original deadline.
- One active operation per session: starting another invalidates the first, avoiding ambiguous multi-tab writes.
- Keep passwords out of pending state. First-password setup returns to the password form after confirmation; the user enters the new password only on final POST.
- Validate OAuth state with Socialite and bind it to the operation purpose. Do not use `stateless()` or trust an operation ID from a query string as proof.
- Preserve separation from Socialite's generic login state. Concurrent login/confirmation may fail safely and ask for retry; it must never authorize the wrong purpose.
- Request an explicit Google account-selection interaction. Callback must match returned immutable provider ID to the stored and current linked `google_id`; a matching email alone is insufficient.
- Callback requires the same authenticated user/session and a live pending operation. Reject denied consent, state failure, provider/network failure, wrong identity, expiry, and replay.
- Never call normal `findExistingForGoogle`, `linkGoogleAccount`, `createFromGoogle`, or `Auth::login` from the confirmation callback.
- Callback marks proof and redirects to a local confirmation view. The final POST consumes proof atomically while committing the operation.
- At commit, re-read account state, original email, current Google ID, password presence, and target-email uniqueness. Reject stale state or concurrent changes rather than silently changing confirmation methods.
- Use a fixed local return route, never arbitrary redirect URLs. Clear operation state on success, cancellation, invalid identity, and logout.

### 5.3 Preserve email semantics

- Show the exact proposed email on the final review screen; final POST cannot swap it for another target.
- Update only pending validated name/email, clear local verification timestamp, and retain `google_id`.
- Change `SocialAuthService::linkGoogleAccount()` so Google verification stamps the local email only when normalized provider email equals the local email.
- Existing linked Google login must continue by Google ID even if the local email changes.
- Do not let an email-only fallback overwrite an already different linked Google ID. Add a focused conflict rejection as part of preserving identity binding.
- This confirms who authorized the change, not ownership of the proposed local address; application email verification remains disabled.

## 6. Close first-password bypass

- Existing-password users retain current-password validation for password updates.
- Google-only users must obtain a separate Google authorization with purpose `set-password` before the initial password is accepted.
- Reuse the confirmation service/routes but keep `set-password` and `change-email` proofs non-interchangeable.
- First-password proof is consumed once; password/password_confirmation are supplied only in the final password POST and never stored across OAuth redirects.
- A previously confirmed email operation cannot authorize password setup, and a completed password setup does not leave a generic reusable confirmation flag.
- Update the current regression test that intentionally permits session-only initial password creation. Preserve legitimate password reset behavior: mailbox-based reset is a separate existing recovery proof.
- If account linking changes or a password is created concurrently, fail stale operations safely.

Account deletion currently has a different Google-only confirmation policy. It is not changed by this plan; a broader sensitive-account-action audit can follow separately.

## 7. File-by-file implementation map

All paths below are relative to the SunnyTrips repository root.

### New files

- `app/Services/TurnstileService.php`: bounded provider verification and safe error mapping.
- `app/Services/RegistrationAbuseService.php`: atomic shared creation quota.
- `app/Services/AccountSecurityService.php`: pending operations, identity matching, expiry, and one-time proof consumption.
- `app/Http/Controllers/Auth/GoogleAccountConfirmationController.php`: authenticated start/callback/final confirmation endpoints.
- `app/Http/Requests/AccountSecurityConfirmationRequest.php`: purpose and pending profile-data validation.
- `routes/accountSecurityRoute.php`: feature-scoped authenticated confirmation routes.
- `resources/views/profile/confirm-account-change.blade.php`: purpose-specific final review, cancel/retry states, no credential display.
- `tests/Feature/RegistrationAbuseTest.php`: limiter and shared-creation security behavior.
- `tests/Feature/GoogleAccountConfirmationTest.php`: sensitive-operation boundaries and first-password proof behavior.

### Modified files

- `app/Providers/AppServiceProvider.php`: named submission/sensitive-action rate limiters; reuse services rather than duplicate checks.
- `config/services.php`: Turnstile and separate Google confirmation callback configuration.
- `.env.example`: empty Turnstile and Google confirmation callback placeholders only.
- `routes/auth.php`: password-registration submission limiter.
- `routes/web.php`: require new feature routes and protect profile update attempts.
- `app/Http/Controllers/Auth/RegisteredUserController.php`: challenge and shared creation gate before account writes; preserve registration events and onboarding.
- `app/Http/Controllers/Auth/GoogleAuthController.php`: creation gate only on new-account branch; existing login unchanged.
- `app/Services/SocialAuthService.php`: preserve Google-ID binding and do not verify a different local email.
- `resources/views/auth/register.blade.php`: challenge integrated with consent submission and retries.
- `app/Http/Requests/ProfileUpdateRequest.php`: normalization, conditional credential checks, Google-only direct-update rejection.
- `app/Http/Controllers/ProfileController.php`: explicit saved-field allowlist and protected email-change flow.
- `app/Http/Controllers/Auth/PasswordController.php`: require purpose-bound Google proof for first-password setup.
- `resources/views/profile/partials/update-profile-information-form.blade.php`: current-password or Google-confirmation path based on server-provided account state.
- `resources/views/profile/partials/update-password-form.blade.php`: first-password Google confirmation and recovery messages.
- `tests/Feature/Auth/RegistrationTest.php`: mock challenge verification for existing valid-signup cases; preserve consent coverage.
- `tests/Feature/Auth/GoogleAuthTest.php`: creation quotas, identity-link conflict, changed-email verification, revised first-password behavior.
- `tests/Feature/ProfileTest.php`: conditional proof, atomic update, sensitive-field exclusion, and unchanged-email behavior.
- `tests/Feature/Auth/PasswordUpdateTest.php`: reuse existing-password regression coverage; confirmation-proof cases live in `GoogleAccountConfirmationTest.php`.

Review `SecurityHeaders.php`, `CheckUserOnboarding.php`, and exception input-flashing behavior; edit only if a concrete integration issue requires it. No schema or dependency change is planned. Generate new PHP files through Artisan during implementation.

## 8. Regression tests and manual QA

Use existing Pest factories and onboarded-user helpers. Isolate cache keys between tests and use mocked HTTP/Socialite responses; automated tests must not call production Google/Cloudflare or create production accounts.

Registration:
- Valid challenge/consent reaches existing onboarding flow; each rejection creates no user and dispatches no Registered event.
- Missing, forged, expired/reused, wrong-host/action, malformed and timed-out challenge responses.
- Minute/hour submission thresholds, Retry-After, fresh sessions with same IP, independent IPs, and recovery after time advance.
- Shared quota across password/Google signups and no new-account quota use on existing Google login.
- Atomic quota reservation prevents concurrent overshoot; account creation remains protected by existing unique constraints.

Profile and Google confirmation:
- Name-only and unchanged-email updates do not require credentials; missing/wrong password rejects all profile writes.
- New local email uniqueness and verification-timestamp semantics; no password/control field saved or flashed.
- Google-only direct PATCH and direct initial-password PUT cannot bypass confirmation.
- Wrong Google ID, no linked identity, missing authenticated session, expired/wrong OAuth state, denial, provider failure, expired operation, changed user/session, changed Google link, and replay all reject.
- Proof bound to exact email and purpose; tampering and cross-purpose reuse reject.
- Concurrent final submissions cannot consume one proof twice; unchanged name/email/password remain intact on failure.
- Returning linked Google account still signs in after a local email change without re-verifying the different local address.
- Existing normal Google login/signup and password recovery remain functional.

Manual QA: registration consent modal and token expiry; profile name-only edit; password-confirmed email change; Google account selection/mismatch/cancel/retry; first-password setup; mobile and keyboard/screen-reader error behavior. Do not promise a Google password prompt on every confirmation.

## 9. Implementation order and release

1. Confirm repository status and latest scoped rules; recheck package APIs against installed versions. Do not overwrite unrelated plans or changes.
2. Add targeted failing regression cases for each security contract.
3. Implement config, Turnstile verification, submission limits, shared creation gate, and registration UI.
4. Implement password-account email confirmation and explicit saved-field allowlisting.
5. Implement isolated Google pending-operation confirmation and final POST; close first-password bypass and identity-verification edge cases in the same release.
6. Run focused tests after each change. Format modified PHP using `vendor/bin/pint --dirty --format agent`; build with `npm run build`.
7. Run combined affected tests, then request the full-suite run under repository policy. PostgreSQL testing DB must be available.
8. Perform browser QA using the required local Brave/Playwright MCP; if unavailable, use another browser only with the user's existing authorization and clearly report the limitation.
9. Before any production release, separately obtain deployment authorization. Plan approval alone authorizes implementation, not live changes.

Production prerequisites for the NEW Railway project:
- Web service receives the actual Turnstile variables; local `.env` does not configure Railway automatically.
- `APP_URL` is the new production URL, HTTPS session behavior is correct, and trusted proxy/cache configuration is verified.
- Google Cloud authorized redirect URIs include both normal login and the new confirmation callback on `sunnytripsv2.up.railway.app`. Retain required local/staging callbacks separately.
- Do not reuse old Railway project/service IDs from the earlier mail investigation. Identify the new project's web/worker services before any change.
- Rebuild configuration cache after variables are available; restart/redeploy long-running processes as appropriate without altering the working Resend configuration.
- Deploy related confirmation/first-password changes together. Verify one designated registration and account change with permission; no production spam test.
- Monitor challenge rejection categories, 429 counts, confirmation failures, and successful writes without logging passwords, provider tokens, challenge secrets, or full pending payloads.
- Rollback uses the previous application revision and compatible config; do not silently disable verification because an external provider is unavailable.

## 10. Completion criteria and open limits

Implementation is complete when all scoped tests pass, controlled browser flows work, both signup paths obey shared creation limits, profile writes require the correct account-specific proof, first-password setup cannot bypass it, existing Google identity binding survives email changes, and no credential/control fields leak into model writes or logs.

This plan does not claim that a Turnstile key's presence proves it is valid, that a Google account-selection screen forces fresh password entry, that email-change confirmation verifies the new mailbox, or that IP throttling guarantees one account per person.

## 11. Implementation and verification record

Implemented locally on 2026-10-15:
- Server-verified Turnstile for password registration; minute/hour submission throttles; atomic creation allowance shared with new Google accounts.
- Current-password validation for email changes, an explicit profile-field allowlist, and account-based sensitive-action limits.
- Dedicated Google confirmation routes and a session/user/identity/purpose-bound pending operation. Pending requests expire in ten minutes; confirmed proof expires in five minutes and is consumed once under a cache lock and database transaction.
- Google-only first-password setup requires its own confirmation. Successful setup returns to profile rather than revisiting the consumed confirmation page.
- Google linking rejects conflicting provider IDs and does not verify a different local contact email. Logout/cancel clear pending state.
- Turnstile tokens are excluded from flashed input. Challenge load, expiry, retry, and busy-request failures reject safely.

Validation completed:
- Combined affected regression tests: **80 passed, 412 assertions**. Includes registration consent, provider failure, shared quotas, profile validation, Google identity/state/session/purpose/expiry/replay boundaries, first-password setup, normal login, and password recovery.
- PHP formatting passed; production asset build passed. The build retains the existing large ApexCharts chunk warning.
- Browser check on desktop and a 320px mobile viewport: consent modal opens, failed challenge leaves submission disabled, and the mobile page has no horizontal overflow.
- The configured production Turnstile widget reports a domain error on localhost. No challenge was solved, account created, or real Google confirmation performed through the browser. Successful live-provider checks remain release QA after the correct hostname/callback configuration is available.
- Google/Cloudflare network responses were mocked in automated tests. Busy-lock tests simulate contention; they are not a production load test.

Remaining release work:
- Run the complete suite with `php artisan test --compact` under the project's full-suite policy.
- Add the dedicated Google Cloud authorized redirect URI and configure `GOOGLE_REAUTH_REDIRECT_URI` if using an explicit URL. Preserve the existing login callback.
- Put the three Turnstile values in the new Railway web service, verify persistent shared cache and trusted-proxy client IP behavior, then refresh cached configuration during an authorized deployment.
- Perform designated live registration, Google-only email-change, and first-password checks on the allowed deployment hostname. No production changes were made during implementation.

References:
- https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
- https://developers.cloudflare.com/turnstile/troubleshooting/testing/
- https://developers.google.com/identity/openid-connect/openid-connect
- Installed Laravel and Socialite sources for version-specific APIs; no Boost documentation-search tool is available in this session.
