# INSTALL & static verification

This project was generated without PHP/Composer on the authoring machine. It is
**static-verified** (PHP headers, brace balance, namespace↔path, FK ordering,
view/route references). Runtime verification must run on a PHP 8.3+ host.

## 1. Requirements

- PHP **8.3+** with: `pdo`, `mbstring`, `openssl`, `tokenizer`, `ctype`, `json`, `curl`, `fileinfo`
- Composer 2
- MySQL 8 / MariaDB 10.6+
- Node 18+ (only for front-end assets in later iterations)

## 2. Install

```bash
composer install
cp .env.example .env
php artisan key:generate      # generates a UNIQUE APP_KEY — never commit it
# edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL
php artisan storage:link
```

## 3. Recommended verification order on a PHP host

```bash
# a) Lint every PHP file
find app config database routes modules bootstrap -name '*.php' -print0 \
  | xargs -0 -n1 php -l

# b) Boot the framework & confirm migrations resolve (no DB writes)
php artisan --version
php artisan migrate:status || true

# c) Run migrations against a scratch DB, then seed
php artisan migrate --seed

# d) Static analysis / style (dev deps)
./vendor/bin/pint --test
./vendor/bin/pest
```

## 4. Completing the install

Browse to `/install`. The wizard (iteration 3) will check requirements, write the
environment, **create the admin account with a password you choose**, run
`migrate --seed`, and finally write `storage/app/installed.lock`. After that the
installer routes return 404. To re-run, delete the lock file.

## 5. Known follow-ups (not yet implemented)

- Installer steps beyond Welcome/Requirements (env, admin account, migrate) — iteration 3.
- `config/` currently ships `app`, `database`, and `listinghub`. On first boot Laravel 13
  uses framework defaults for the rest; publish any you need to customize with
  `php artisan config:publish` (e.g. `permission`, `medialibrary`, `purifier`).
- Auth scaffolding, controllers, policies, views — iterations 4+.
