#!/usr/bin/env bash
# ListingHub CLI installer — thin wrapper around php artisan listinghub:install.
# This exists so the friend can run ./install.sh without knowing artisan.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# --- PHP detection -----------------------------------------------------------

PHP_BIN="${PHP_BIN:-php}"
if ! command -v "$PHP_BIN" &>/dev/null; then
    echo "ERROR: PHP not found. Install PHP 8.3+ or set PHP_BIN=/path/to/php" >&2
    exit 1
fi

PHP_VER=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
if [[ "$(printf '%s\n' "8.3" "$PHP_VER" | sort -V | head -1)" != "8.3" ]]; then
    echo "ERROR: PHP 8.3+ required, found $PHP_VER" >&2
    exit 1
fi

# --- Pre-checks ---------------------------------------------------------------

if [[ ! -f artisan ]]; then
    echo "ERROR: artisan not found. Run this script from the ListingHub root." >&2
    exit 1
fi

if [[ ! -d vendor ]]; then
    echo "ERROR: vendor/ missing. Run: composer install --no-dev --optimize-autoloader" >&2
    exit 1
fi

if [[ ! -d public/build ]]; then
    echo "ERROR: public/build/ missing. Front-end assets were not built." >&2
    exit 1
fi

if [[ -f storage/app/installed.lock ]]; then
    echo "ListingHub is already installed." >&2
    echo "To reinstall, remove storage/app/installed.lock (this will NOT drop your database)." >&2
    exit 1
fi

# --- Copy .env.example if .env is missing -------------------------------------

if [[ ! -f .env ]]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

# --- Run the canonical installer ----------------------------------------------

exec "$PHP_BIN" artisan listinghub:install
