# Legacy Parity Matrix

Compares the original Directory Hub (CodeCanyon reference, upgrade_step_1 + upgrade_step_2
treated as a single deployment) against ListingHub. Every legacy feature carries one of
four decisions:

| Decision | Meaning |
|----------|---------|
| **KEEP** | Functionally restored in ListingHub |
| **REDESIGN** | Purpose preserved, implementation replaced |
| **DROP** | Intentionally not carried forward |
| **DEFER** | Planned for a later version |

---

## Geo & Location

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| Multi-country geo (states/cities) | REDESIGN | 3.2.3 | Bulgaria-only: regions → municipalities → settlements |
| Country selector in listing form | DROP | — | Single country; no UI selector |
| International city SQL dumps (AU/BR/CA/DE/FR/GB/IN/MX/NL/RO/US …) | DROP | — | Only verified BG geo dataset ships |
| Latitude/longitude on city | KEEP | 3.2.3 | Moved to `settlements`; rounded display optional |
| Administrative boundaries / map | REDESIGN | 3.3.0 | GeoJSON per region; map locked to Bulgaria |

## Catalog & Listings

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| Public listing index (browse/search) | KEEP | 3.3.0 | Category + location + keyword filter |
| Category hierarchy | KEEP | 3.3.0 | Unlimited depth via `parent_id` |
| Listing detail page | KEEP | 3.3.0 | Canonical slug URL |
| Search / filter / sort | KEEP | 3.3.0 | By category, region, municipality, settlement, keyword |
| Pagination | KEEP | 3.3.0 | Cursor + query-string preservation |
| Featured listings | KEEP | 3.3.0 | `is_featured` flag, plan-gated |
| Sitemap | REDESIGN | 3.3.0 | BG-only; spatie/laravel-sitemap |
| Custom fields per category | KEEP | 3.3.0 | Declarative field schema (see DECLARATIVE_FIELDS.md) |
| Product/menu items on listing | KEEP | 3.4.0 | `products` table |
| Gallery images | REDESIGN | 3.4.0 | Unified `media_assets` (polymorphic) |
| Working hours + exceptions | KEEP | 3.4.0 | `listing_hours` + `listing_hour_exceptions` |
| Listing claim | KEEP | 3.5.0 | `listing_claims` with moderation flow |

## Members & Ownership

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| User registration | KEEP | 3.4.0 | Email + password; Socialite optional |
| Email verification | KEEP | 3.4.0 | Laravel built-in |
| Password reset | KEEP | 3.4.0 | Laravel built-in |
| Member profile | KEEP | 3.4.0 | Avatar, bio, contact |
| Owner CRUD for own listings | KEEP | 3.4.0 | Policy-gated |
| Favorites / saved listings | KEEP | 3.4.0 | Pivot table |

## Trust & Interaction

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| Reviews (rating + text) | KEEP | 3.5.0 | `reviews` with approval flow |
| Leads / contact form | KEEP | 3.5.0 | `listing_leads` |
| Messaging between users | DEFER | 3.5.0 | Scoped to lead threads initially |
| Moderation queue | KEEP | 3.5.0 | Admin review for listings, reviews, claims |
| User management (admin) | KEEP | 3.5.0 | Role-based via spatie/laravel-permission |

## Commerce & Billing

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| Plans & subscriptions | KEEP | 3.6.0 | `plans` + `subscriptions` |
| Stripe payments | KEEP | 3.6.0 | Idempotency key, `payment_events` |
| Stripe webhooks | KEEP | 3.6.0 | Signature verified; idempotent handler |
| Invoices | KEEP | 3.6.0 | `invoices` with PDF export |
| Refunds | KEEP | 3.6.0 | Via Stripe API |
| Bank transfer / manual payment | DEFER | post-3.6.0 | Only if real business need confirmed |
| PayU Money / Instamojo gateways | DROP | — | Not relevant for Bulgarian market |
| Trial periods | KEEP | 3.6.0 | `trial_ends_at` on subscriptions |

## Content & Internationalisation

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| About / Contact pages | KEEP | 3.7.0 | Static CMS pages |
| Terms & Privacy pages | KEEP | 3.7.0 | Legal content, versioned |
| FAQ | KEEP | 3.7.0 | CMS-managed |
| Testimonials | KEEP | 3.7.0 | Admin-managed |
| Blog | KEEP | 3.7.0 | Simple post model |
| Bulgarian UI translations | KEEP | 3.7.0 | `lang/bg/` only |
| Multi-language listings | DROP | — | Single-language platform (Bulgarian) |

## Admin & Settings

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| Admin dashboard | KEEP | 3.5.0 | Stats, moderation queue |
| Settings management | REDESIGN | — | Grouped settings model (not monolithic table) |
| SEO meta per page | KEEP | 3.7.0 | Separate `seo_meta` CMS model |

## Data & Import

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| CSV listing import | KEEP | 3.8.0 | Staged validation + queue |
| Import recovery / resume | KEEP | 3.8.0 | Resumable jobs |
| Geo dataset update tooling | KEEP | 3.8.0 | Versioned BG geo.json pipeline |

## Security & Infrastructure

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| Public `/utils/link` and `/utils/cache` routes | DROP | — | No open utility endpoints |
| Domain-license verification | DROP | — | Not carried forward |
| Runtime theme upload | DROP | — | No executable code uploads |
| Default admin credentials (seeded) | DROP | — | Admin created during install only |
| `.env` echoed to browser | DROP | — | Gitleaks gate + no debug routes |

## Frontend

| Legacy feature | Decision | Target version | Notes |
|----------------|----------|----------------|-------|
| jQuery / Bootstrap frontend | DROP | — | Replaced by Blade + Tailwind + Alpine |
| Theme marketplace / upload | DROP | — | Design tokens only |
| Leaflet / Google Maps (runtime key) | REDESIGN | 3.3.0 | Map provider swappable via config |

---

*Last updated: 2026-08-11 — covers upgrade_step_1 + upgrade_step_2 as a single legacy baseline.*
