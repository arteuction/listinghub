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

---

## 3.4.1 — Bulgaria Map & Geo Search

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ⚠️ **254 passed, 1 failed** (679 assertions) — see note below |
| Pest suite — MariaDB 11.4.5 | ✅ **255 passed** (683 assertions) |

New public map endpoint `GET /api/catalog/map?bbox=west,south,east,north&category=...&region=...`
returns a GeoJSON `FeatureCollection` of `Published` listings inside the bbox, reusing
`PublicListingQuery` (the same filter stack as the catalog page) so map and list always agree on
the result set — verified by dedicated parity tests. `App\Support\BBox` rejects any request whose
bbox is malformed or falls outside Bulgaria's envelope (422). Coordinates resolve to the listing's
own `latitude`/`longitude` when set, otherwise the settlement centroid (`App\Services\Catalog\
MapListingQuery`, LEFT JOIN + `COALESCE`); listings with neither are excluded. Response capped at
1000 features; both the map and settlement-autocomplete endpoints are throttled (120/min, 60/min).

Public UI: a List/Map toggle on `/listings` (`resources/views/site/listings/index.blade.php`)
preserves every active filter across the switch (`fullUrlWithQuery`), plus an EKATTE settlement
autocomplete (region → municipality → settlement chain already existed; this adds free-text
search via `GET /api/catalog/settlements`). The map itself
(`resources/views/site/partials/map.blade.php`) uses MapLibre GL JS: `maxBounds` + `minZoom` lock
the viewport to Bulgaria, GeoJSON clustering groups nearby markers, a popup shows the listing title
+ link (HTML-escaped client-side), and the bbox is written back into the browser URL so the view
stays shareable. An always-in-DOM accessible list (`sr-only focus-within:not-sr-only`) gives
keyboard/screen-reader users the same listings without touching the map canvas. Tile URL and
viewport bounds are `config('listinghub.map.*')` / `.env`-driven (`MAP_TILE_URL`) — OSM is the dev
default; the docs flag that production should point at a provider with an SLA (OSM's tile policy
explicitly disallows heavy unmanaged use).

**Correction (follow-up pass):** this section originally listed the feature cap as `.env`-driven
too. It was not — `MAP_MAX_FEATURES` and `listinghub.map.max_features` were defined but never
read, and `MapListingQuery` hard-coded its `MAX_FEATURES = 1000` constant, so changing the
variable did nothing. `MapListingQuery::maxFeatures()` now reads the config value, falls back to
`DEFAULT_MAX_FEATURES` when it is absent, non-numeric or `< 1` (an unusable value must not yield
an empty map), and clamps it to `HARD_MAX_FEATURES = 5000` so raising the variable cannot defeat
the cap the endpoint depends on. Three tests cover it: the configured cap actually truncating the
FeatureCollection, the fallback across `null`/`0`/`-5`/`'many'`, and the ceiling clamp.

New composite indexes `(latitude, longitude)` on `listings` and `settlements` back the bbox range
scan (migration `2026_08_11_000200_add_coordinate_indexes_for_map`).

**Pre-existing bug found, not introduced by this iteration, documented not fixed (by decision):**
`it filters by keyword across title and description` (`PublicCatalogTest`) fails only on SQLite.
GitHub Actions CI only ever exercises MySQL (`.github/workflows/ci.yml`), so this never surfaced
before dual-gate SQLite+MariaDB testing was run against this codebase. Root cause: SQLite's built-in
`LIKE`/`LOWER()` case-fold Cyrillic only when SQLite is compiled with the ICU extension, which the
default build is not — `title LIKE '%пекарн%'` therefore misses a title beginning with the
uppercase `П` (`Пекарна Слънце`) even though it matches on MySQL/MariaDB's default collation. Not a
regression from this iteration's work; tracked for a future pass on `PublicListingQuery::
applyKeyword`. **Fixed in the follow-up pass below** — the SQLite gate is green with no exception.

**Also fixed as a prerequisite:** migration `2026_08_11_000100_reshape_geo_bulgaria_only` used
`dropForeign('cities_state_id_foreign')` (string constraint name) three times, which SQLite's
schema grammar cannot execute at all (throws unconditionally) — this blocked the SQLite gate
entirely before any of this iteration's own tests could run. Guarded with a driver check; the
array-form `dropForeign(['column'])` calls elsewhere in the same migration already work on both
drivers and were left untouched.

**Also fixed:** `MapListingQuery`'s bbox `BETWEEN` comparison silently returned zero rows on this
environment's portable PHP/PDO SQLite build, which binds PHP floats as SQLite `TEXT` rather than
`REAL` — and SQLite orders `TEXT` after every `INTEGER`/`REAL` value, so the comparison was always
false. Fixed by wrapping both the column expression and the bound parameters in `CAST(... AS
DECIMAL(10,7))`, which forces numeric affinity and is valid on both SQLite and MySQL/MariaDB.

23 new tests across `MapEndpointTest` (bbox validation, visibility — draft/pending/**suspended**
excluded, coordinate resolution and fallback, category/region filter parity, feature shape) and
`MapListToggleTest` (filter preservation across the List↔Map toggle, list/map result-set parity).

---

## 3.4.2 — Parity Closure: products & working hours CRUD

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ⚠️ **272 passed, 1 failed** (732 assertions) — pre-existing Cyrillic gap only |
| Pest suite — MariaDB 11.4.5 | ✅ **273 passed** (736 assertions) |
| PHPStan (Larastan, level 5) | ✅ No errors |
| Pint | ✅ passed |
| Deptrac | ✅ 0 violations |

Closes the gap where `products`, `listing_hours` and `listing_hour_exceptions` had
models, migrations and public read-only rendering, but no way for an owner or an
admin to actually manage the data.

**Products.** Full CRUD on both surfaces: `member.listings.products.*` and
`admin.listings.products.*`. Writes go through `CreateProduct`/`UpdateProduct`
actions, never the model directly. Two things are enforced structurally rather
than by convention:

- *Status ceiling.* `Member\ProductRequest` allows `draft|published`;
  `Admin\ProductRequest` additionally allows `suspended`. Suspension is a
  moderation act, so an owner cannot apply (or lift) it on their own row — the
  member request rejects it at validation, before the action runs.
- *Slug scope.* The schema's unique key is `[listing_id, slug]`, not `slug`, so
  `CreateProduct::uniqueSlug()` probes for collisions **within the listing**.
  Two different listings may each have a `standard-package` (covered by test).

Authorisation for the member side runs against the **parent listing**
(`ListingPolicy::update`), not the product row — otherwise `create()` would be
unreachable for a listing with no products yet. Every action that names a
specific product additionally asserts `product.listing_id === listing.id`, the
same defence `ListingMediaController` uses: owning the listing must not let a
caller pass an arbitrary product id from someone else's listing.

**Working hours.** `SyncListingHours` is a **full replacement**, not a patch: the
payload is the complete weekly state, and a day absent from it is deleted rather
than left untouched. A partial update would let a stale "open" row survive after
the owner cleared that day in the form, which is the failure mode that actually
matters here (a business shown as open when it is closed). At most 7 rows, so
delete-then-insert in one transaction beats a diffed upsert on clarity.
`is_closed` forces `opens_at`/`closes_at` to NULL so a closed day cannot carry
contradictory times.

Validation (shared shape across `Member\` and `Admin\ListingHoursRequest`):
both times present or neither, `closes_at` strictly after `opens_at`, and no
duplicate `day_of_week` within one payload.

**Hour exceptions** (holiday closures, one-off changes) default to
`is_closed = true` — the common case — and require both times to opt into a
still-open exception. Cross-listing exception ids 404.

Both features are linked from the existing listing edit forms (member and
admin) so they are reachable through the UI, not only by URL.

**Type-safety fix carried in this iteration:** `Product` gained
`@property ProductStatus $status` and a generic `BelongsTo<Listing, $this>` on
`listing()`. Without them Larastan read `$product->status` as `string` (making
`->value` an error) and could not resolve `$product->listing->user_id` in
`ProductPolicy` — the same class of annotation gap fixed for the geo models in
3.4.1, and the reason the repo keeps an empty Larastan baseline rather than
suppressing these.

18 new tests across `ListingProductTest` (member CRUD, cross-listing id
rejection, member-cannot-suspend, admin-can-suspend, per-listing slug scope,
stranger lockout on every verb) and `ListingHoursTest` (full-replacement
semantics including the dropped-day case, all three validation rules, exception
create/delete, cross-listing 404, stranger lockout).

One test-portability note worth keeping: `TIME` columns normalise differently
per driver — MySQL/MariaDB always return `H:i:s`, SQLite has no native TIME type
and echoes back exactly what was written (`H:i`). The hour assertions compare the
`H:i` prefix instead of the raw string so the same test is honest on both gates.

---

## Follow-up (post-3.4.2) — Unicode-correct free-text search

| Gate | Result |
|------|--------|
| Pest suite — SQLite | ✅ **290 passed** (771 assertions) — **no exceptions; the suite is fully green on SQLite for the first time** |
| Pest suite — MySQL 8 | ⏳ CI (no MySQL/MariaDB server available on the machine this pass ran on) |
| Pint · Larastan · Deptrac | ✅ clean (173 files analysed, 0 violations) |

Closes the SQLite Cyrillic search gap recorded under 3.4.1, and two related
defects found while fixing it.

**The gap.** SQLite's `LIKE` and `LOWER()` fold ASCII only unless the build carries ICU, which the
stock PHP build does not. `title LIKE '%пекарн%'` therefore did not match `Пекарна Слънце`, while
MySQL's default utf8mb4 collation matched it. For a Bulgarian-only platform this is the ordinary
case, not an edge one: titles are capitalised and people type lower case.

**The fix.** `App\Support\SqliteUnicode` registers a `mb_strtolower`-backed implementation of
`lower()` on every SQLite connection, and `App\Support\SearchTerm` folds the term in PHP, so both
sides of the comparison are folded and every query reads `LOWER(col) LIKE ? ESCAPE '!'`. The
SQLite function is registered under the name of the built-in on purpose: a user-defined function
takes precedence in SQLite, so one SQL string is correct on both drivers. The alternative — a
differently-named function behind a driver check at each call site — is the shape that produced
the bug in the first place. Registration hangs off `ConnectionEstablished` (per-connection, and it
must survive a reconnect), with a sweep over already-resolved connections at boot for anything
opened before the provider ran.

**Two further defects, found while fixing the first:**

1. **The escaping was driver-dependent, and the test that covered it passed for the wrong reason.**
   `PublicListingQuery` escaped LIKE metacharacters with `\` and declared no `ESCAPE` clause. MySQL
   treats `\` as the default LIKE escape, SQLite has no default at all — so on SQLite `\%` meant
   "a literal backslash, then any run of characters". `it treats LIKE wildcards in the keyword as
   literal characters` passed on SQLite only because the fixture contained no backslash. Both
   drivers now use `ESCAPE '!'`, the convention 2.9B already established for exactly this reason
   (`ESCAPE '\'` is a syntax error in a MySQL string literal).

2. **The escape convention was written out three times** — `PublicListingQuery`,
   `ListingSearchQuery` and `SettlementSearchController` — with the buggy variant in the first.
   The rule now has one definition in `SearchTerm`, which folds and escapes together, so a call
   site cannot bind a pattern without also getting the `ESCAPE` clause that pattern assumes.
   Settlement autocomplete gains case-insensitivity as a side effect; it had the same gap.

**Not verified here:** the MySQL leg. This pass ran on a machine with no MySQL/MariaDB server, so
the dual-driver gate that earlier entries in this log describe was not repeated — CI's MySQL 8 job
is the check on that side. The SQL involved (`LOWER(col) LIKE ? ESCAPE '!'`) is the construct 2.9B
already verified on MariaDB, and `LOWER()` on MySQL utf8mb4 is Unicode-aware, so no behavioural
change is expected there beyond backslashes now being literal in a search term rather than an
escape character.

**Cost:** wrapping the column in `LOWER()` means a per-row function call on MySQL. `LIKE '%term%'`
is already a full scan on both drivers — no index was being used before and none is lost.

14 tests in `CyrillicSearchTest`: case folding in both directions and mid-token, Latin text
unaffected, description as well as title, `%` / `_` / `!` / `\` all literal, a term that genuinely
contains `%`, the settlement endpoint, and the two `SearchTerm` contracts.
