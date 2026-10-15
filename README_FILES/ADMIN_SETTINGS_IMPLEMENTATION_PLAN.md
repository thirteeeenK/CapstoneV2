# Admin Settings and Customer Account Deletion — Implementation Plan

Status: Proposed; awaiting review. Planning only. No application changes or database operations are authorized by this document alone.

Date: October 15, 2026 (Asia/Manila).

## 1. Scope for approval

Build an Admin Settings page containing the five sections discussed:

1. Business information.
2. Account deletion policy.
3. Human support availability.
4. Guest chatbot limits.
5. Admin email notification preferences.

Also add customer account deletion to Registered Users, with separate scheduled and permanent deletion actions.

This proposal does not include the other settings suggested earlier: booking windows, cancellation policy configuration, recommendation controls, payment-method configuration, or maintenance mode. It also excludes deleting admin accounts, adding an admin role system, and editing API keys or infrastructure credentials through the application.

## 2. Findings from the current code

- The application uses Laravel 13.21.1, Pest 4.7.5, Blade, Alpine, and Tailwind 3. There is no existing shared settings model or settings page.
- Admin accounts use `AdminModel` and the separate `admin` guard. No roles or super-admin designation currently exist. New privileged routes should require `auth:admin`, `admin.2fa`, `no.cache`, and `throttle:admin`.
- `AdminAuditService` and `admin_audit_logs` already record the acting admin and changes. Reuse these instead of creating another audit system.
- `AccountDeletionService` already supports the recently added `ACCOUNT_DELETION_GRACE_PERIOD` switch, five-day recovery, permanent cleanup, and session/reset-token revocation. Google Login restores soft-deleted accounts during their recovery window.
- `RegisteredUserController` and `admin/users` already provide search, moderation tabs, and an AJAX results partial. Soft-deleted users are currently excluded from those queries.
- The public footer hardcodes the office address and the displayed 8 AM–8 PM PHT support hours. `SupportQueueService::requestHandoff()` does not enforce office hours.
- Guest chat limits are hardcoded at 15 messages/day and 5/minute in `EnforceGuestChatLimits`. A separate `chat-guest` limiter is also declared in `AppServiceProvider`, although `/chat` currently uses the `ai` limiter plus the middleware. Avoid creating competing limits.
- Cancellation requests already notify all admins through `BookingCancellationRequested` using mail and database channels. New booking, manual payment submission, and new support ticket admin email alerts need new integration work.
- Permanent user deletion already leaves bookings in place through `bookings.user_id ON DELETE SET NULL`. Preferences and several other dependent records cascade. Chat/support records have nullable user references; polymorphic notifications need explicit cleanup.
- Existing `implementation_plan.md` concerns a different FAQ task. Preserve it. Existing uncommitted changes, including the previous deletion-switch work and chatbot changes, must also be preserved.
- `.ai/rules` is absent at planning time. Recheck before implementation in case rules are added.

## 3. Settings sections and proposed behavior

### A. Business information

Fields:

- Public business/display name (maximum 120 characters).
- Support email and phone number (maximum 254 and 40 characters).
- Office address as plain text (maximum 500 characters).
- Optional Facebook, Instagram, and website links (HTTPS URLs; maximum 2,048 characters each).

Use these values in the public footer/contact links and human-support contact messaging. Hide empty optional links. Preserve the current office address as the fallback. Use `config('app.name')` as the display-name fallback.

This is public business/contact information. Changing the display name will not rewrite the code-based logo, OAuth app branding, infrastructure application name, mail sender identity, or existing legal documents. Those are separate concerns.

### B. Account deletion policy

One choice: **Five-day recovery period** or **Delete immediately**.

- The saved database value takes priority over `config('auth.account_deletion_grace_period')`.
- Until an admin saves this section, retain the environment-controlled behavior. With no environment override, the default remains five days.
- Resolve the effective policy once when a deletion request starts. Return/use that result for the confirmation notice so a simultaneous settings change cannot make the notice describe a different outcome.
- The profile warning, confirmation form, and post-deletion message must use the same policy source.
- Changes apply to new deletion requests. Already scheduled accounts retain their original five-day deadline and recovery behavior.
- Do not automatically purge pending accounts when an admin selects immediate deletion.

### C. Human support availability

Fields:

- Enable/disable acceptance of new human-support requests (default: enabled).
- Enforce office hours (default: disabled, preserving current acceptance behavior).
- Operating days and one opening/closing interval per selected day.
- Plain-text unavailable message (maximum 500 characters).

Use Asia/Manila consistently for the schedule; no global application timezone change. Prefill the displayed schedule with the footer's current daily 08:00–20:00 hours. The admin must explicitly enable enforcement. Require opening time before closing time; overnight and split shifts are outside this first version. An explicit all-day option may represent 24-hour operation without an ambiguous 00:00–00:00 interval.

- Reject only new handoff requests outside the allowed schedule or when intake is disabled. Return a structured `support_unavailable` response with the configured message and availability information; do not create a ticket.
- Existing pending/active inquiries remain readable, claimable, and replyable. Repeated handoff requests for an existing open ticket retain the existing ticket.
- AI chat and its normal safety checks remain available.
- The widget must display the unavailable response instead of silently ignoring it. The server is authoritative, including when an old browser tab still shows the handoff button.
- Reuse one availability service for the controller, footer schedule display, and relevant chatbot support prompts. Avoid promising immediate agent availability when intake is closed.

### D. Guest chatbot limits

Fields:

- Guest daily message limit: integer, 1–1,000; default 15.
- Guest messages per minute: integer, 1–10; default 5. The upper bound reflects the current independent `ai` throttle.

Apply settings through `EnforceGuestChatLimits`; retain IP-based counters and the Asia/Manila midnight reset. Do not reset consumed counters when an admin saves settings. A higher limit allows remaining capacity; a lower limit applies on the next request.

Keep authenticated-user behavior and the independent AI throttle unchanged. Resolve the dormant `chat-guest` declaration from the same setting source if retained; do not attach an additional duplicate throttle to `/chat`.

Correct the guest limiter's response so a short burst limit is described as a per-minute limit, rather than incorrectly calling every 429 a daily limit. Preserve fields/status values required by the current widget and return an accurate retry interval.

### E. Admin email notifications

Fields:

- Recipients selected from existing admin accounts, displayed by name/email.
- Explicit recipient mode: **All admins** or **Selected admins**. Require at least one valid selected recipient when email alerts are enabled.
- Email toggles for customer-submitted booking requests, new GoTyme payment submissions awaiting review, cancellation requests, and new customer support inquiries.

Defaults preserve current behavior: cancellation-request email enabled for all admins; the three newly introduced email alert types disabled. Settings control operational admin email only. Customer notifications, password reset mail, moderation notices, and existing admin database notifications remain intact.

Implement a shared dispatch service and one reusable mail-only operational alert notification. Preserve the existing cancellation database notification exactly once; separate its database delivery from configurable mail delivery to avoid duplicate emails.

Dispatch only after the relevant transaction commits and only for a newly persisted event. Repeated support requests returning an existing inquiry and rejected/duplicate payment submissions must not send another alert. A mail/queue failure must not roll back a successful booking, payment submission, cancellation request, or support ticket. Log dispatch failures without secrets or full customer data.

This first version covers customer-created bookings and customer-initiated support tickets. Admin-created bookings and direct admin messages do not generate redundant new-work alerts. SMTP/Resend credentials and sender configuration stay in environment variables. No test-email feature is included.

## 4. Settings storage and access

Create `system_settings` with a small, fixed set of section records:

| Column | Purpose |
| --- | --- |
| `id` | Standard project bigint primary key. |
| `section` | Unique, bounded section identifier: business, accounts, support, chatbot, notifications. |
| `values` | JSONB object, validated against that section's allowed fields. |
| `revision` | Integer for detecting concurrent edits. |
| `updated_by` | Nullable indexed foreign key to admins; null on admin deletion. |
| timestamps | Match the project's timestamp convention. |

No JSONB search index is needed for five small records. No default seeder should snapshot environment values into the database: an unsaved section must continue to use its fallback. Create a factory for meaningful settings tests.

`SystemSettingsService` will:

- Expose typed section/policy getters, rather than letting callers write arbitrary setting names.
- Load the small settings collection lazily once per request or queue-job scope. Use a scoped container binding; do not keep mutable settings in a process-wide singleton/static cache or load them in the service provider's boot method.
- Read saved values first, then explicit configuration defaults. Treat `false` and empty optional values as valid saved values, not as missing values.
- Save one validated section transactionally with its audit entry; use revision checks to reject stale submissions with a clear reload message. Handle concurrent first saves through the unique section constraint.
- Clear its scoped memo after a successful save. New requests/jobs read current database values; no shared-cache invalidation or worker restart is needed solely for a settings edit.
- Use fallback values only when no setting exists. Do not swallow database errors and silently change account-deletion policy.

No schema queries are performed during this planning task. Before authoring migrations, inspect the current database schema using Boost if available; otherwise use a read-only schema inspection supported by the installed framework. Apply the additive migration before enabling the new application code in production.

## 5. Admin deletion of customer accounts

Add a Delete account control on the Registered Users list and customer detail page. Use one accessible confirmation modal with:

- Target customer's name and email.
- Required deletion mode: **Schedule deletion — five days** or **Permanently delete now**.
- Required confirmation email, matched to the selected customer on the server.
- Required admin reason (maximum 500 characters).
- Clear description of recoverability, retained booking history, and removed account/preferences.

The admin explicitly chooses the mode. Scheduled deletion always grants five days; immediate deletion explicitly overrides the default self-service policy. Never silently convert a scheduled admin action into immediate deletion because of the global setting.

Backend behavior:

1. Authenticate through the admin guard and enforce the existing two-factor requirement. Customer sessions and unauthenticated requests cannot invoke the endpoint.
2. Validate only supported modes and confirm the current target email. Read the target under a transaction/row lock, including soft-deleted customers where appropriate.
3. Reuse/extend `AccountDeletionService` for self-service and admin entry points. Keep policy selection, session revocation, support cleanup, and permanent cleanup centralized.
4. Scheduled deletion removes remembered access, database sessions, and password reset tokens. A repeat request must not extend the original recovery deadline.
5. Permanent deletion performs the same access revocation, closes open support inquiries, removes customer-owned polymorphic notifications, and force-deletes the customer. An admin can also permanently delete an already-pending customer without waiting for cleanup.
6. Preserve bookings, booking items, and payment history through current foreign-key behavior; show a Deleted customer fallback wherever a user relationship becomes null. Preserve the existing booking contact snapshot. Deletion does not issue refunds or cancel bookings.
7. Preserve unrelated bans/IP restrictions. This feature is not a replacement for moderation. Explain in the confirmation that removing a customer's identity also removes account-bound flags under the current cascade behavior.
8. Record the acting admin, target ID, requested mode, reason, original deadline where relevant, and outcome in the existing audit system. Use an allowlisted snapshot; never log password hashes, remember tokens, embeddings, or an unrestricted `getOriginal()` array.
9. Redirect to the list after deletion, preserving applicable search/filter context. Handle concurrent deletion/cleanup safely with a clear result, not a server error.

Add a Pending deletion tab showing the original deadline and permanent-delete action. Preserve the existing `#listings-results` AJAX partial pattern. Existing moderation Unban/Restore access actions must not implicitly cancel scheduled deletion; label the states separately.

Retain current Google Login behavior: a scheduled account can recover before its deadline; a permanently deleted customer's next verified Google sign-in creates a new account and starts onboarding without old preferences.

Admin accounts remain outside these routes because they use a different model/table. All existing admins currently have equal authority; this plan does not invent a super-admin role. If restricted staff roles are desired, that is a separate scope decision before implementation.

## 6. UI structure

- Add Settings to the existing admin sidebar, using `layouts.admin` and current Blade input/error/modal components.
- One page, five clearly labelled sections, with independent Save changes buttons and success/error feedback. Show whether account deletion currently comes from the environment fallback or an admin override.
- Follow the current Sora/DM Sans typography and ocean/sand/ink/coral palette. Reuse existing layouts instead of introducing a new visual system or dependency.
- Use semantic labels, fieldsets for choices, keyboard-operable controls, visible focus, and associated validation errors. Render messages as escaped text. Validate social links before rendering them.
- The customer deletion modal must name the customer, return focus to its trigger on close, show validation errors, and remain usable after AJAX list replacement.
- Check narrow screens and desktop layouts; avoid horizontal overflow or hidden destructive controls.

## 7. Proposed file changes

Paths below are relative to the repository root. Migration filenames receive the actual timestamp from Artisan at implementation time.

### New files

| File | Purpose |
| --- | --- |
| `[NEW] database/migrations/<timestamp>_create_system_settings_table.php` | Add settings table, unique section constraint, and admin reference. |
| `[NEW] app/Models/SystemSetting.php` | Fillable attributes, casts, and updater relationship. |
| `[NEW] database/factories/SystemSettingFactory.php` | Settings fixtures for tests. |
| `[NEW] config/system_settings.php` | Typed application defaults for the five sections; reference the existing auth config for the deletion fallback. |
| `[NEW] app/Services/SystemSettingsService.php` | Effective settings, scoped reads, section saves, concurrency checks, and auditing. |
| `[NEW] app/Services/Support/SupportAvailabilityService.php` | Shared intake/schedule calculation in Asia/Manila. |
| `[NEW] app/Services/AdminNotificationService.php` | Recipient selection and after-commit operational email dispatch. |
| `[NEW] app/Notifications/AdminOperationalAlert.php` | Queued mail-only operational alerts. |
| `[NEW] app/Http/Controllers/Admin/AdminSettingsController.php` | Render settings and delegate validated section updates. |
| `[NEW] app/Http/Requests/Admin/UpdateSystemSettingsRequest.php` | Section-specific validation and admin authorization. |
| `[NEW] app/Http/Requests/Admin/DeleteCustomerAccountRequest.php` | Validate customer email, deletion mode, and reason. |
| `[NEW] routes/adminSettingsRoute.php` | Named GET settings and PATCH section-update routes. |
| `[NEW] resources/views/admin/settings/index.blade.php` | Five settings forms using existing admin styling. |
| `[NEW] resources/views/admin/users/_delete_account_modal.blade.php` | Reusable deletion confirmation for list/detail pages. |
| `[NEW] tests/Feature/AdminSettingsTest.php` | Settings authorization, validation, persistence, fallbacks, concurrency, and rendering. |
| `[NEW] tests/Feature/AdminAccountDeletionTest.php` | Admin deletion modes, failures, retention, and audit coverage. |
| `[NEW] tests/Feature/AdminNotificationSettingsTest.php` | Recipient/type controls, existing cancellation channel compatibility, and dispatch behavior. |

### Existing files to modify after approval

| File | Planned change |
| --- | --- |
| `[MODIFY] app/Providers/AppServiceProvider.php` | Scoped settings binding; consistent guest limiter configuration and targeted public view data. |
| `[MODIFY] routes/web.php` | Require the new settings route file. |
| `[MODIFY] resources/views/adminComponents/sidebar.blade.php` | Settings navigation entry. |
| `[MODIFY] app/Services/AccountDeletionService.php` | Resolve settings once; explicit admin mode; shared cleanup; transactional admin audit. |
| `[MODIFY] app/Http/Controllers/ProfileController.php` | Use the deletion outcome for accurate flash messaging. |
| `[MODIFY] resources/views/profile/edit.blade.php` | Effective deletion-policy warning. |
| `[MODIFY] resources/views/profile/partials/delete-user-form.blade.php` | Effective confirmation wording. |
| `[MODIFY] resources/views/components/frontend/layout.blade.php` | Accurate deletion outcome notice using the existing dialog. |
| `[MODIFY] app/Http/Controllers/Admin/RegisteredUserController.php` | Deletion endpoint, pending-deletion listing/detail support, and safe action availability. |
| `[MODIFY] routes/userManagementRoute.php` | Dedicated protected DELETE route for customer deletion. |
| `[MODIFY] resources/views/admin/users/index.blade.php` | Pending-deletion tab and modal state compatible with live search. |
| `[MODIFY] resources/views/admin/users/_table.blade.php` | Deadline badges and deletion controls. |
| `[MODIFY] resources/views/admin/users/show.blade.php` | Deletion controls and separate pending/moderation states. |
| `[MODIFY] app/Services/AdminAuditService.php` | Allowlisted explicit action records while keeping existing log callers compatible. |
| `[MODIFY] resources/views/admin/audit/index.blade.php` | Clearly label settings changes and scheduled/permanent customer deletion, including removed targets. |
| `[MODIFY] resources/views/components/frontend/footer.blade.php` | Managed business/contact data and displayed support schedule. |
| `[MODIFY] app/Services/Support/SupportQueueService.php` | New-ticket availability enforcement, after-commit alert, and existing-ticket preservation. |
| `[MODIFY] app/Http/Controllers/ChatbotController.php` | Structured unavailable handoff response. |
| `[MODIFY] resources/views/components/frontend/chat-widget.blade.php` | Display unavailable messages and accurate limiter feedback. |
| `[MODIFY] app/Services/Chat/ChatbotService.php` | Small, targeted changes to human-support availability wording/actions; preserve ongoing chatbot work. |
| `[MODIFY] app/Http/Middleware/EnforceGuestChatLimits.php` | Settings-based caps, consistent daily boundary, accurate retry/message details. |
| `[MODIFY] app/Services/BookingRequestService.php` | Emit a new-booking operational alert after a successful customer checkout transaction. |
| `[MODIFY] app/Services/Payment/GoTymePaymentService.php` | Alert once after a new manual payment submission commits. |
| `[MODIFY] app/Http/Controllers/BookingPaymentController.php` | Route cancellation admin email through shared preferences while preserving database notifications. |
| `[MODIFY] app/Notifications/BookingCancellationRequested.php` | Support independent channel delivery without duplicating current database notifications. |
| `[MODIFY] tests/Feature/AccountDeletionTest.php` | Database override, unchanged pending deadlines, and common cleanup regressions. |
| `[MODIFY] tests/Feature/Auth/GoogleAuthTest.php` | Fresh onboarding after admin permanent deletion; scheduled recovery remains valid. |
| `[MODIFY] tests/Feature/ProfileTest.php` | Self-service uses the saved policy and matching warnings. |
| `[MODIFY] tests/Feature/HandoffTest.php` | Availability boundaries, existing-ticket continuity, and unavailable response behavior. |
| `[MODIFY] tests/Feature/ChatbotTest.php` | Configurable guest caps and per-minute/day feedback regressions. |
| `[MODIFY] tests/Feature/Security/AdminAuditTrailTest.php` | Explicit deletion/settings actions without secret leakage. |
| `[MODIFY] .env.example` | Clarify that the existing deletion flag is the fallback until an admin saves the policy. |

No file deletions, dependency changes, modification of historic migrations, or production-data changes are proposed. Any additional consumer discovered during implementation must be justified by one of the approved settings rather than expanded into unrelated cleanup.

## 8. Implementation order after approval

1. Recheck working-tree changes, instructions, and schema. Confirm the actual installed package APIs with Boost documentation search where available, or installed framework source when unavailable. Use Artisan generators for new Laravel files.
2. Build/test the additive settings schema, model, defaults, scoped service, validation, revision handling, and audit persistence.
3. Add the protected settings routes/page/sidebar entry and business/footer integration.
4. Wire account policy resolution and customer admin deletion, including pending listings, common cleanup, retained booking history, and audit rendering.
5. Implement support hours and widget feedback, then configurable guest chat limits.
6. Add configurable admin email dispatch at the four existing business entry points; preserve existing customer/database notifications.
7. Run the affected feature tests, formatting, build, and the available Brave/Playwright UI checks. Inspect the final diff for unrelated edits.

## 9. Verification and acceptance criteria

- Guest and ordinary customer sessions cannot read/write admin settings or delete other accounts. Admins must satisfy the existing two-factor requirement.
- Saving a section does not overwrite another section. Unknown fields, invalid social schemes, malformed emails, invalid time ranges, unsupported deletion modes, and out-of-range caps are rejected with useful feedback.
- Database overrides win over defaults, including a saved `false`; unsaved account policy still follows the existing environment flag. A stale form cannot silently overwrite a newer admin save.
- Self-service and admin deletion show the correct outcome, revoke access, retain required booking/payment records, and never log account secrets. Existing pending deadlines are unchanged. Concurrent schedule/delete/recovery/cleanup paths are covered where they share a user lock.
- After permanent deletion, a verified Google sign-in creates a different customer ID, has no preferences, and redirects to onboarding. Scheduled accounts retain recovery until the original deadline.
- Human-support intake respects the selected days/hours at exact opening and closing boundaries in Asia/Manila. Disabled intake does not terminate existing human conversations or block AI chat.
- Guest caps take effect on the next request without clearing existing counters. Per-minute and daily failures show the correct reason and retry time; authenticated chat behavior remains intact.
- Notification tests fake external delivery and prove that selected recipients/types are respected, existing cancellation database delivery is preserved exactly once, rolled-back/rejected operations dispatch nothing, and repeated requests returning existing records do not duplicate alerts. Test after-commit behavior with the appropriate transaction isolation rather than assuming it from a dispatch call.
- Public business fields are escaped; URLs are validated; optional empty contact fields are hidden. No settings lookup occurs at provider boot or persists between request/job scopes.
- Run the narrowest affected Pest files, then relevant checkout/GoTyme, chatbot compound-intent, and audit regressions. Run Pint only on the feature's changed PHP files and `npm run build`. Ask the user to run the complete suite with `php artisan test --compact` after focused checks pass.
- For UI verification use the configured local Playwright MCP with Brave, as required by this project. Check settings forms, deletion confirmation, pending lists after AJAX replacement, unavailable support feedback, keyboard focus, and narrow-screen layouts. If the required MCP is unavailable, report that limitation rather than using a different browser silently.

No tests, builds, migrations, application edits, live deletions, emails, or deployments are part of the present planning-only request. Once this plan is approved, implementation can begin in the order above.
