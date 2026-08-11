# Runtime verification log — 2.7.2

Executed on a portable PHP toolchain (no system PHP/Composer on the host).

## Toolchain

| Component | Version |
|-----------|---------|
| PHP | 8.3.33 (NTS, x64) |
| Laravel Framework | 13.24.0 |
| Composer | 2.x (composer.phar, stable) |
| Test DB | SQLite (`:memory:` for Pest, file for migrate) |
| Extensions | openssl, mbstring, pdo_sqlite, sqlite3, curl, fileinfo, zip, exif |

## Contract results

| Step | Result |
|------|--------|
| `composer validate --strict` | ✅ valid |
| `composer install` (from lock) | ✅ 103 packages; **Laravel 13 + Pest 4 + Spatie 8 resolve** |
| `migrate:fresh --seed` (SQLite) | ✅ 15 migrations + RoleSeeder + PlanSeeder |
| `php artisan test` | ✅ **46 passed, 0 failed, 0 warnings (85 assertions)** |
| `php artisan about` | ✅ Laravel 13.24.0 / PHP 8.3.33 |

## Real defects found by the runtime gate (that static review missed) and fixed

1. **`nunomaduro/collision ^9.0` does not exist** — the 2.6 bump was wrong; Pest 4 requires
   collision `^8.x`. Pinned to `^8.8`. (Composer resolution failure — P0.)
2. **`ListingTransition::from()` fatal** — a backed enum already defines the reserved static
   `from()`; defining an instance `from()` is `Cannot redeclare`. Renamed to
   `fromStatus()` / `toStatus()` across enum, action, exception, test.
3. **Deferred `users.organization_id` FK not applied on SQLite** — SQLite cannot
   `ALTER TABLE ADD FOREIGN KEY`. Reordered migrations so `organizations` + geo are created
   **before** `users`, making the FKs inline (works on SQLite and MySQL); removed the
   deferred-FK migration.
4. **Boundary FK test used `Schema::getForeignKeys` on `:memory:`**, which does not report
   the FK reliably. Rewritten as a **behavioural** test (a non-existent `organization_id` is
   rejected by the live FK) across all five tables — stronger and portable.
5. **Missing `SubscriptionFactory`** — `Subscription::factory()` fatally failed. Added.
6. **Framework reads `.env`** during boot; a fresh checkout has none, raising a warning in
   every feature test. `verify.sh` now creates a throwaway `.env` from `.env.example`
   (removed on exit); a pre-existing `.env` is left untouched.
7. **`composer.lock` generated and committed** — repeat installs are now reproducible.

## MySQL / MariaDB grammar gate ✅

Executed against a portable **MariaDB 11.4.5** (LTS) server on an isolated temp datadir
(port 33061), with `pdo_mysql` enabled in PHP. Temp database dropped and datadir removed
afterwards.

| Step | Result |
|------|--------|
| `migrate:fresh --seed` (MariaDB) | ✅ all migrations + seeders |
| Full `artisan test` (MariaDB) | ✅ **46 passed, 0 failed, 0 warnings (85 assertions)** |
| Schema introspection | ✅ 38 tables, **38 FK constraints** |
| `users.organization_id` FK | ✅ → `organizations` (inline FK portable to MySQL) |
| `payments.idempotency_key` | ✅ `char(36)` (uuid) |
| `payments.currency` | ✅ `char(3)` |
| `payments.metadata` | ✅ JSON (longtext + json_valid) |
| `payment_events` composite unique | ✅ `(gateway, external_event_id)` |
| Cleanup | ✅ `DROP DATABASE` (0 remaining), server shutdown, datadir removed |

**Both gates green — schema and behaviour verified on SQLite and MariaDB.**

| Component | Version |
|-----------|---------|
| MariaDB | 11.4.5 (LTS) |
| PHP | 8.3.33 |
| Laravel | 13.24.0 |

---

## Iteration 3 — production installer (RUNTIME-VERIFIED ✅)

Executed on the same portable stack (PHP 8.3.33, Laravel 13.24.0, MariaDB 11.4.5).

| Gate | Result |
|------|--------|
| Full Pest suite — SQLite | ✅ **51 passed** (96 assertions) |
| Full Pest suite — MariaDB | ✅ **51 passed** (96 assertions) |
| **E2E clean-install — MariaDB (HTTP, artisan serve + curl)** | ✅ **16 passed, 0 failed** |

### E2E clean-install acceptance (all green)
Welcome 200 pre-install · API 503 pre-install · invalid creds → **.env unchanged, no lock** ·
non-empty foreign DB → **refused (redirect back, .env unchanged)** · valid creds → .env written,
**APP_KEY preserved** · admin submitted · finalize → **finished screen inline, installed.lock created,
pending marker removed** · `/install` → **404** post-install · API → **200** post-install ·
admin **status=active, organization_id NULL, bcrypt-hashed, not plaintext, role=admin** · **no password in logs**.

### Real defects the installer runtime gate caught (static review could not) and fixed
1. `migrate --seed` fails in a web request — `db:seed` is not registered in HTTP context. Split into
   `migrate --force` + direct `DatabaseSeeder->run()` (still never `migrate:fresh`).
2. Writing `APP_NAME` at the environment step rotated the session cookie name
   (`slug(APP_NAME)_session`), silently dropping the wizard session → CSRF 419. Pinned a fixed
   installer cookie name pre-install.
3. Post-install verify failed on MySQL REPEATABLE-READ / spatie guard-cache. Moved the check to run
   on the just-written `$user` INSIDE the transaction.
4. `email_verified_at` is guarded (not fillable) — `firstOrCreate` silently dropped it; used
   `markEmailAsVerified()`.
5. `User::status` is cast to the `UserStatus` enum — a `=== 'active'` string check always failed;
   compared on the enum value.
6. Laravel's schema introspection under-reports MariaDB **system** schemas (empty-DB check passed the
   `mysql` database); the empty-DB guard now uses `SHOW TABLES` for MySQL/MariaDB.

---

## 2.9A — declarative fields (schema + typed normalization)

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **82 passed** (184 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **82 passed** (184 assertions) |

Real defect caught only by MariaDB: FK constraint names are database-global — the
rename-to-legacy step carried `custom_field_values_custom_field_id_foreign` and the new
table's auto-named FK collided (errno 121). Fixed with explicit FK names
(`cfv_typed_field_fk` / `cfv_typed_listing_fk`). The backfill test was moved onto an
isolated `cfb` sqlite connection (DDL under RefreshDatabase commits the wrapping
transaction). Both gates green after the fix.

---

## 2.9A.1 — failure/concurrency hardening

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **91 passed** (211 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **91 passed** (211 assertions) |

Delta over 2.9A (all audit items closed):
- **P0-1** MySQL DDL autocommit: reshape rebuilt as `ReshapeCustomFieldValues` — live table stays canonical until a validated `_next` replaces it via one atomic `RENAME TABLE`; retry-safe cleanup. Driver-matched test runs the real MariaDB swap + a late-failure-then-retry case.
- **P0-2** Sync now `lockForUpdate`s definition rows (not just the listing).
- **P0-3** UpdateCustomField adds I9 (category immutable), I10 (flags↔capability), I11 (select options unique/≤255, non-select options=null).
- **P1-4** `down()` explicitly irreversible (throws).
- **P1-5** backfill aborts on cross-category legacy row.
- **P1-6** backfill preserves legacy `id`, `created_at`, `updated_at`.
- **P0-7** normalizer rejects non-scalar text; URL restricted to http/https.
- Docs (`DECLARATIVE_FIELDS.md`) aligned: no naive drop, no auto-rollback claim, status updated.

Migration-authoring lesson: a `use RuntimeException;` in a no-namespace migration is a fatal
"no effect" statement — referenced as global instead.

---

## 2.9A.2 — resume-safety + I11 completeness

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **97 passed** (223 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **97 passed** (223 assertions) |

- **P0** post-swap retry: `ReshapeCustomFieldValues` now detects on-disk state before cleanup — typed live + `_old` → finish (drop `_old`); typed live, no `_old` → idempotent no-op; legacy live + stray `_old` → safe abort. Two driver-matched resume tests (real MariaDB): crash after swap / after drop-old.
- **P0** I11 on type change: non-select options must be exactly `null`; select→non-select auto-clears stale options; each option must be an object with a string, trimmed, non-empty, unique `value`; malformed → `CustomFieldConflict` (never `TypeError`).
- Small: empty `Stringable` → delete (null); null legacy timestamps preserved as null; orphan `custom_field_id` backfill test; docs corrected ("invalid cast → reject", down by policy, concurrency claim softened).

---

## 2.9A.3 — canonical select option codes (final I11 micro-fix)

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **98 passed** (224 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **98 passed** (224 assertions) |

`UpdateCustomField` now rejects select option `value`s with surrounding whitespace
(`' red '`): they would be stored verbatim but normalized to `'red'` at read time, making
the option permanently unmatchable. Test added for `' red '` (not just fully-blank `' '`).
**2.9A is now closed.**

---

## 2.9B — ListingSearchQuery compiler

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **108 passed** (244 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **108 passed** (246 assertions) |

`App\Services\Search\ListingSearchQuery`: allowlist from field metadata (unknown/non-filterable/
cross-category/bad-operator/dup-key/>8 filters/invalid-cast → `InvalidSearchQuery`, never ignored);
`whereExists` (no row duplication); sort via dup-free LEFT JOIN with **NULLs last + `listings.id`
tie-breaker**; nothing from the request interpolated (column from type, direction mapped to a fixed
token, values bound). 10 compiler tests incl. injection attempts and a MySQL EXPLAIN check
(planning succeeds, subquery uses an index, no join-buffer Cartesian).

Real defect caught only by MariaDB: `LIKE ? ESCAPE '\'` — a backslash in a MySQL string literal
escapes the quote (`'\'` = syntax error), while SQLite accepts it. Switched the LIKE escape
character to `!` (literal on both), escaping `!`, `%`, `_` in the bound value.

---

## 2.9B.1 — query hardening (category scope + contract)

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **116 passed** (265 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **116 passed** (267 assertions) |

Functional **P0**: `category_id` was used only for metadata lookup, never applied to the listings
query — a sort-only request returned all categories (foreign ones trailing as NULL). Now
`where('listings.category_id', ?)` scopes results; positive category required for filters/sort
(0/negative rejected); category-only returns that category; empty request stays unscoped. Plus:
empty-value reject (checkbox false/'0' kept), url/email validity, strict request shape
(`array_is_list`, value presence, null-or-array sort, string direction), `MAX_IN_VALUES=50`,
literal `%`/`_`/`!` in contains, and an explicit note that visibility (published/all) is the
controller's job, not the compiler's.

---

## 3.0A — authentication + authorization shell

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **128 passed** (301 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **128 passed** (303 assertions) |

Session login/logout (`GET/POST /login`, `POST /logout`), `/admin/*` behind `auth → active →
permission:manage settings`. Permission-based (not role-hardcoded). `EnsureActiveUser` checks the
**live DB status** each admin request → a mid-session suspension terminates the session immediately.
Login: portable case-insensitive email match (`lower(email)` — SQLite is case-sensitive on `=`),
one generic error for wrong-password / unknown-email / suspended, per-(email+IP) rate limit 5/min
cleared on success, `session()->regenerate()`; logout invalidates + regenerates token; password never
flashed/logged; guest → login with intended redirect; no-permission → 403. 12 acceptance tests.

---

## 3.0A.1 — auth hardening

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **131 passed** (311 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **131 passed** (313 assertions) |
| **HTTP-level CSRF (artisan serve + curl)** | ✅ `GET /login` 200 · `POST /login` no-token **419** · `POST /logout` no-token **419** |

Hardening: `guest` middleware on `/login` (authenticated user cannot switch identity via POST);
constant-time login (one `Hash::check` against a fixed dummy hash when no user matches — timing
does not distinguish unknown vs existing); stronger tests (session-id before/after regenerate,
success clears the limiter WITHOUT manual reset, logout rotates the CSRF token, separate
unknown-email case, identity-switch case, password-in-log spy). CSRF 419 proven at HTTP level
because the test harness bypasses ValidateCsrfToken. Docs (`ITERATION_3_0.md`) reconciled: status
updated, `active` added to the admin middleware order, throttle→RateLimiter.

---

## 3.0B — admin custom-field definitions

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **141 passed** (347 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **141 passed** (349 assertions) |

CRUD at `/admin/categories/{category}/custom-fields` (behind auth → active → permission:manage
settings). Thin controller — every mutation goes through a domain action (`CreateCustomField`,
`UpdateCustomField`, `DeleteCustomField`); a `CustomFieldConflict` becomes a per-field
`definition` error. I10/I11 extracted into a shared `App\Support\CustomFieldDefinition` used by
both Create and Update (single validator). Delete allowed only with no stored values (archiving a
used field is a future schema step). Select option codes are immutable while used (I7/I8), labels
are not. No new schema. 10 acceptance tests + full regression (UpdateCustomField refactor caused
no change in behaviour).

---

## 3.0C — custom fields in listing create/edit

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **148 passed** (371 assertions) |
| Pest suite — MariaDB 11.4.5 | ✅ **148 passed** (373 assertions) |

Admin listing create/edit (`/admin/categories/{category}/listings/create`, `/admin/listings/...`)
renders the category's fields ordered by `sort_order`. The custom-field payload is applied ONLY
through `SyncListingCustomFields` — no direct `CustomFieldValue::create` in the controller — inside
the same transaction as the listing write, so an invalid field leaves nothing behind. A
`CustomFieldConflict` now carries the field key and surfaces as a per-field error
(`custom_fields.<key>`). Sync gained a full-listing purge (values whose field is not in the current
category are dropped), so a **category change** removes the old category's values in a controlled
way through Sync — never a manual delete. 7 acceptance tests; Sync (2.9A) suite still green.
