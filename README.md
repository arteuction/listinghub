# ListingHub

**Национална платформа за обяви — само България.**
Laravel 13 · PHP 8.3+ · MySQL 8.

Концептуален наследник на Directory Hub (CodeCanyon reference) — **оригинален код**,
не копие на продукта. Вижте [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) за пълния дизайн
и [`docs/LEGACY_PARITY_MATRIX.md`](docs/LEGACY_PARITY_MATRIX.md) за функционалния паритет.

**Продуктово ограничение:** ListingHub е национална платформа само за България.
Няма многоезично съдържание, международни адреси или избор на държава.

Platform model: **Variant 3 (prepared hybrid)** — shared catalog с nullable `organization_id`.

## Еволюционна карта

| Версия | Обхват | Статус |
|--------|--------|--------|
| 3.2.1 | Quality & Parity Baseline — CI, Pint, Larastan, Gitleaks, Renovate, LEGACY_PARITY_MATRIX | ✅ done |
| 3.2.2 | Architecture Gate — Deptrac, Lefthook, check-doc-status | ✅ done |
| 3.2.3 | Bulgaria Geo Foundation — Region/Municipality/Settlement, BG-only geo, map config | ✅ done |
| 3.3.0 | Public Marketplace — категории, локации, search/filter/sort, pagination, sitemap | ✅ done |
| 3.4.0 | Members & Ownership — регистрация, email verification, профили, owner CRUD, favourites, галерии | ✅ done |
| 3.4.1 | Bulgaria Map & Geo Search — GeoJSON map endpoint, List/Map toggle, EKATTE autocomplete | ✅ done |
| 3.4.2 | Listing detail completion — продукти/меню CRUD, работно време + изключения | ✅ done |
| 3.5.0 | Trust & Interaction — reviews, claims, leads, moderation queue, user management, админ панел (навигация, статистики, listing статуси + RequestChanges, категории, запитвания, настройки) | ✅ done |
| 3.5.1 | Public Hour Exceptions — ефективно работно време (Europe/Sofia), „Днес“ на публичната страница | ✅ done |
| 3.5.2 | Verified Claim Documents — content-sniffed upload, private disk, SHA-256, admin-only download | ✅ done |
| 3.5.3 | Product Completion — attributes, gallery, публична продуктова страница | ✅ done |
| 3.6.0 | Commerce — plans, subscriptions, Stripe, invoices, refunds | ⏳ planned |
| 3.7.0 | Content & i18n — BG съдържание, CMS, SEO, FAQ, blog | ⏳ planned |
| 3.8.0 | Data & Import — CSV importer, staged validation, BG geo updates | ⏳ planned |
| 4.0.0 | Production Platform — atomic deploy, backup/restore, monitoring, smoke tests | ⏳ planned |

Every push runs the full gate in CI (see [`.github/workflows`](.github/workflows)):
Pint, Larastan, Deptrac, Gitleaks, the Vite build, and the Pest suite against
MySQL 8. For a local run see [`docs/INSTALL.md`](docs/INSTALL.md).

**Hardening pass 2.6 applied** (see `docs/ARCHITECTURE.md` §15): Laravel-13-compatible
dependency pins, `strict_types` in all PHP files, real `organization_id` FKs on all five
boundary tables, business logic moved into Actions/Services/Support, currency-aware `Money`,
installer gate extended to the API, and the standard config + Vite/Tailwind/Alpine setup.

## What ships now (after consolidation pass 2.5)

- **37 tables** across 14 migrations (users, geo, roles, catalog, listings, products, billing, reviews, settings, organizations, unified media).
- **25 Eloquent models** with relationships, casts, and **10 status enums**.
- **Integer money** everywhere (`*_minor` bigint + ISO 4217 `currency`) — no floats in the domain; `App\Support\Money` for display.
- **Unified `media_assets`** — one polymorphic gallery for listings, products and reviews (replaces per-entity image tables).
- **Idempotent billing** — payments carry a UUID `idempotency_key`; `payment_events` are unique per `(gateway, external_event_id)` with `attempts`/`last_error` observability.
- **Immutable subscription snapshots** — plan slug/name/price/currency/interval frozen at purchase.
- **12 factories + Pest tests** (hybrid-boundary, payment idempotency, config).
- **Seeders:** roles/permissions (`admin`/`staff`/`member`) and starter plans — **no default admin account** (created during install with an operator-chosen password).
- **Installer stub:** `/install` welcome + server-requirements check, self-locking via `storage/app/installed.lock`.

## Security posture (vs. the reviewed original)

- No seeded default credentials.
- `.env.example` ships with an **empty** `APP_KEY`.
- No open utility routes; no `.env` echoed to the browser.
- Optional license check is HTTPS-only with the key sourced from `.env`.

## Quick start (on a PHP 8.3+ host)

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB in .env, then browse to /install
```
