# Clean Migration Cutover — Drop DB + Promote `Database Migrations/Clean Migration/` → `database/migrations`

## 0. Context — Current State (Verified 2026-08-23)

| Location | Count | Notes |
|---|---|---|
| `database/migrations` (live — source of truth) | **59 files** | Incremental history `0001_*` → `2026_08_22_001443` |
| `Database Migrations/Original Migration/` (backup) | **59 files** | SHA256-identical to live (recently synced) |
| `Database Migrations/Clean Migration/` | **35 files** | Squashed (24 patches inlined) + `0000_vector` first + 6 new files synced: `2026_08_21_*`×4, `2026_08_21_165716`, `2026_08_22_001443` |
| `database/migrations` ← Clean diff | 24 incremental patches absent in Clean (squashed) + `2026_07_23_vector` renamed → `0000_vector` | Intentional |
| Final schema after `migrate:fresh` | **Clean == Live** (audit: 0 missing columns) | `is_shown`, `vibe_tags`, `ban_level` replacing `is_banned`, `coordinates`, `extra_person_fee`, `gateway_data`/`payment_url:text`, `recommendation_explanations`, `bookings` CHECK `cancellation_requested`/`denied` all present |

**Goal:** Drop the whole PostgreSQL database, replace `database/migrations` (59) with `Database Migrations/Clean Migration` (35), then `migrate:fresh --seed` from clean history.

**Why dropping first matters:** `migrations` table has 59 rows vs filesystem 35 → on an existing DB, next `migrate` would show "Migration not found" for 24 squashed patches + pending `0000_vector`. On a **dropped DB** this is irrelevant (`migrate:fresh` starts at 0) — which is why the drop-first approach is correct.

## 1. Preconditions (Do Not Skip)

- [ ] `DB_CONNECTION=pgsql` in `.env` (not `sqlite` default of `.env.example`). Audit confirms `.env.testing` is `pgsql` `localhost:5432` `SunnyTripsCapstoneV2_test` `postgres/kenly123` — live `.env` must match.
- [ ] PostgreSQL running + `pgvector` extension available (`CREATE EXTENSION vector` is `Database Migrations/Clean Migration/0000_01_01_000000_add_vector_extension.php:10` pgsql-guarded).
- [ ] `Database Migrations/Original Migration/` (59) retained as incremental backup — do NOT delete until cutover verified.
- [ ] Optional but recommended: set `GEMINI_API_KEY`, `EMAIL` + `ADMIN_PASSWORD` in `.env` before seeding (`GEMINI_API_KEY` null → embeddings `warn` + `null`, tag-match fallback; `EMAIL` null → `AdminSeeder.php:20` warns `Skipped admin seed` and creates **no admin**).

## 2. Plan — 4 Phases

### Phase 1 — Backup (5 min)

```powershell
Copy-Item -Recurse "database/migrations" "database/migrations.bak.$(Get-Date -Format yyyyMMddHHmm)"
Copy-Item -Recurse "Database Migrations" "Database Migrations.bak.$(Get-Date -Format yyyyMMddHHmm)"
pg_dump -h localhost -U postgres SunnyTripsCapstoneV2 > "storage/backups/pre-clean-cutover-$(Get-Date -Format yyyyMMddHHmm).sql"
psql -h localhost -U postgres -d SunnyTripsCapstoneV2 -c "SELECT migration FROM migrations ORDER BY batch,id;" > "storage/backups/migrations-table-pre.txt"
```

### Phase 2 — Promote Clean → Live (1 min)

```powershell
# Keep Original Migration (59) intact; only overwrite live
Remove-Item -LiteralPath "database/migrations" -Recurse -Force
New-Item -ItemType Directory -Path "database/migrations" | Out-Null
Copy-Item -LiteralPath "Database Migrations/Clean Migration/*.php" -Destination "database/migrations/" -Force

# Verify
(Get-ChildItem database/migrations).Count          # expect 35
(Get-ChildItem "Database Migrations/Clean Migration").Count  # 35 — must match
Get-FileHash database/migrations/0000_01_01_000000_add_vector_extension.php
php -l database/migrations/0000_01_01_000000_add_vector_extension.php  # repeat for 6 new files
```

Preserves: squashed history (24 patches stay inlined), `0000_vector` first (fixes live ordering bug where `2026_07_23` ran after `users`), 6 new files byte-identical to live's final schema.

### Phase 3 — Drop + Fresh Migrate + Seed (3–10 min)

```powershell
php artisan config:clear
# Option A — via artisan (drops all tables, reruns 35 migrations, then DatabaseSeeder)
php artisan migrate:fresh --seed --force
# Option B — if encoding/lock errors, explicit DB drop/create then migrate
psql -h localhost -U postgres -c "DROP DATABASE IF EXISTS SunnyTripsCapstoneV2 WITH (FORCE);"
psql -h localhost -U postgres -c "CREATE DATABASE SunnyTripsCapstoneV2 OWNER postgres;"
php artisan migrate --force
php artisan db:seed --force

# Post-seed optional
php artisan embed:all --force   # requires GEMINI_API_KEY; otherwise "only warning + embedding=null" per AGENTS.md
php artisan cache:clear
```

**Expected `DatabaseSeeder` order:** `DestinationSeeder` → `AdminSeeder` → `OnboardingOptionSeeder` (24 rows) → `HotelSeeder` (19) → `RoomSeeder`/`ElNidoRoomSeeder` → `RoomExtraPersonFeeSeeder` → `ActivitySeeder`/`ElNidoActivitySeeder` → `ActivityCoordinatesSeeder` → `LegalDocumentsSeeder` → `AddOnSeeder` → `PackageSeeder` → `PassengerCategoryRuleSeeder` → `ReviewSeeder` → `FaqSeeder` — all columns verified present in Clean.

### Phase 4 — Verify (2 min)

```powershell
php artisan migrate:status                          # 35 / 35 Ran
psql -h localhost -U postgres -d SunnyTripsCapstoneV2 -c "\d bookings"  # status enum 7 → CHECK 9, payment_url text, gateway_data jsonb, cancellation_request_* present
psql -h localhost -U postgres -d SunnyTripsCapstoneV2 -c "\d hotels"    # vibe_tags, featured_amenities, is_shown true
psql -h localhost -U postgres -d SunnyTripsCapstoneV2 -c "SELECT count(*) FROM onboarding_options;"  # 24
psql -h localhost -U postgres -d SunnyTripsCapstoneV2 -c "SELECT count(*) FROM destinations;"        # 2
composer test --compact                              # must pass on pgsql (Postgres must be running per AGENTS.md); single: php artisan test --filter=Name
```

## 3. Rollback

If Phase 3 fails:

```powershell
Remove-Item database/migrations/*.php -Force
Copy-Item database/migrations.bak.*/*  database/migrations/  # restore 59
psql -h localhost -U postgres -d SunnyTripsCapstoneV2 < storage/backups/pre-clean-cutover-*.sql
```

`Database Migrations/Original Migration/` (59) remains your discrete-file backup even if live `.bak` is lost.

## 4. Tradeoffs / Alternatives Considered

- **Keep live incremental (recommended for prod with data) vs Clean cutover (your choice):** Incremental retains stepwise `down()` rollbacks + data-only migration `2026_08_06_154424_normalize_reviewable_type_hotels` (no schema effect on fresh, but preserves history). Clean eliminates duplicate `2026_08_08_000000` timestamp bug + fixes `vector` ordering (`0000` before `users` vs live `2026_07_23` after). Since you will drop the DB, the incremental history has no value — dropping is the correct moment to switch.
- **Squash the 6 new files vs keep them:** Keep them as discrete files (as done) — matches live final schema and retains history for `cancellation_request` flow; squashing would diverge from live without benefit.

## 5. Inventory Reference

- **24 squashed patches (do NOT copy):** All inlined into Clean `create_*` — verified 0 missing columns. Intentionally absent: `target_audience`, legacy `is_banned` boolean, text→vector intermediates.
- **1 vector rename:** Live `2026_07_23_093801_add_vector_extension.php` → Clean `0000_01_01_000000_add_vector_extension.php` (identical `CREATE EXTENSION IF NOT EXISTS vector`).
- **6 new files (synced):** `2026_08_21_001823_create_onboarding_options_table.php`, `2026_08_21_001824_create_user_preferences_table.php`, `2026_08_21_012220_add_recommendation_explanations_to_user_preferences_table.php`, `2026_08_21_165110_add_cancellation_request_to_bookings.php`, `2026_08_21_165716_update_bookings_status_check_for_cancellation.php`, `2026_08_22_001443_expand_bookings_qrph_storage.php` — SHA256 identical to live.

## 6. Execution Checklist

- [ ] Backup completed
- [ ] `database/migrations` replaced with 35 clean files
- [ ] `migrate:fresh --seed` succeeded
- [ ] `migrate:status` shows 35 Ran
- [ ] `onboarding_options` 24, `destinations` 2 verified
- [ ] `composer test` passed on pgsql
