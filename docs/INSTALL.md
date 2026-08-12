# INSTALL

Two supported paths: the **deploy package** (a ZIP that already contains
production dependencies and built assets — the path for hosting) and a
**source checkout** (the path for development).

## 1. Requirements

- PHP **8.3+** with: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `ctype`,
  `json`, `curl`, `fileinfo`, `gd`
- MySQL 8 / MariaDB 10.6+ (the runtime gate in this repo runs MariaDB 11.4)
- The web server's document root MUST point at `public/` — never expose the
  project root through `public_html`, or `.env`, `storage/` and the whole
  codebase become downloadable.

## 2. Installing the deploy package (hosting)

The deploy ZIP already includes:

- `vendor/` installed with `composer install --no-dev --optimize-autoloader`
  (production dependencies only — no Composer needed on the host);
- `public/build/` — the compiled Vite assets (no Node needed on the host).

Steps:

1. Upload and extract the ZIP **outside** the public web root.
2. Point the domain's document root at the package's `public/` directory.
3. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP user.
4. Create an empty database and a database user with full rights on it.
5. Open `https://your-domain/install` and follow the wizard:
   server checks → database credentials (connection is tested before `.env`
   is written) → admin account (your own email/password — nothing is seeded)
   → review → install (migrations, roles/plans, full Bulgarian EKATTE
   geography). On first boot the app creates `.env` itself with a freshly
   generated `APP_KEY`, so no shell access is required.
6. The installer writes `storage/app/installed.lock` and locks itself out;
   `/install` returns 404 from then on.

If you ever need to restore dependencies manually, use exactly:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Plain `composer install` would pull dev dependencies (test frameworks,
analysers) onto a production host — never run it there.

## 3. Installing from source (development)

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL
php artisan migrate --seed
php artisan storage:link
npm ci && npm run build      # or `npm run dev` while working
```

The full test suite runs on SQLite `:memory:` (`vendor/bin/pest`) and is also
gated against MariaDB — see `docs/RUNTIME_VERIFICATION_LOG.md`.

## 4. After installation

- `/admin` — dashboard, moderation queue, listings, categories, leads, users,
  settings (moderation toggles live here).
- Optional `.env` keys: `MAP_TILE_URL` (map tile server),
  `MAP_MAX_FEATURES` (map point cap, clamped to 5000), SMTP settings for
  email verification.
- Production cron, if the host supports it:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```
