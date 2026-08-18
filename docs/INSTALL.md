# INSTALL

Three supported paths: the **deploy package** (tar.gz/ZIP with production
dependencies and built assets), **CLI installer** (`install.sh`), and a
**source checkout** (development).

## 1. Requirements

- PHP **8.3+** with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`,
  `tokenizer`, `ctype`, `json`, `curl`, `fileinfo`, `gd` (with WebP:
  `imagewebp()`), `intl`, `dom`, `libxml`, `xmlreader`, `exif`, `zip`, `zlib`
- MySQL 8 / MariaDB 10.6+ (the runtime gate in this repo runs MariaDB 11.4)
- The web server's document root MUST point at `public/` — never expose the
  project root through `public_html`, or `.env`, `storage/` and the whole
  codebase become downloadable.

The installer checks all of the above before proceeding.

## 2. Deploy package (hosting)

The deploy archive already includes:

- `vendor/` installed with `composer install --no-dev --optimize-autoloader`
  (no Composer needed on the host);
- `public/build/` — compiled Vite assets (no Node needed on the host).

### 2a. Web installer

1. Upload and extract the archive **outside** the public web root.
2. Point the domain's document root at the package's `public/` directory.
3. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP user.
4. Create an empty database and a database user with full rights on it.
5. Open `https://your-domain/install` and follow the wizard:
   server checks → database credentials (connection tested before `.env`
   is written) → admin account → review → install (migrations, roles/plans,
   full Bulgarian EKATTE geography).
6. The installer writes `storage/app/installed.lock` and locks itself out;
   `/install` returns 404 from then on.

### 2b. CLI installer

If you have SSH access, use the interactive CLI installer instead:

```bash
./install.sh
```

This is a thin wrapper around `php artisan listinghub:install`, which runs
the same `InstallManager` logic as the web wizard. It will:

1. Check PHP version and extensions.
2. Prompt for database and admin credentials (passwords are never echoed).
3. Test the database connection.
4. Write `.env` with a generated `APP_KEY`.
5. Run migrations, seed roles/permissions/EKATTE data.
6. Create the admin account.
7. Set up the storage symlink.
8. Write `installed.lock` on success.

## 3. Verification

```bash
# Before installation — check archive integrity:
./verify.sh --package

# After installation — check runtime health:
./verify.sh --installed

# Development — full migrate+test (throwaway SQLite):
./verify.sh --source
```

## 4. Upgrading

```bash
./upgrade.sh listinghub-3.6.3.tar.gz
```

The upgrade script will:

1. Verify the new package integrity.
2. Back up `.env`, `installed.lock`, and attempt a database dump.
3. Enable maintenance mode.
4. Sync files (preserving `.env`, `storage/app/`, `storage/logs/`).
5. Run forward migrations.
6. Clear and rebuild caches.
7. Restart queue workers.
8. Run post-upgrade verification.
9. Roll back on failure.

Backups are saved to `backups/<timestamp>/`.

## 5. Installing from source (development)

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
gated against MariaDB.

## 6. After installation

- `/admin` — dashboard, moderation queue, listings, categories, leads, users,
  settings.
- Optional `.env` keys: `MAP_TILE_URL` (map tile server),
  `MAP_MAX_FEATURES` (map point cap, clamped to 5000), SMTP settings for
  email verification.
- Production services:

```bash
# Cron (required):
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1

# Queue worker (required for background jobs):
# Use Supervisor or systemd to keep this running:
php artisan queue:work --sleep=3 --tries=3 --max-time=3600

# After every deploy/upgrade:
php artisan queue:restart
```

- HTTPS: set `APP_URL=https://...` and configure your web server for TLS.
- `APP_DEBUG=false` in production (the installer sets this by default).
- Back up your database and `storage/app/public/` regularly.

If you ever need to restore vendor dependencies manually:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```
