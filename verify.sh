#!/usr/bin/env bash
#
# ListingHub — runtime verification gate.
#
# Works in TWO modes:
#   1. Deploy package (no .git, no dev deps): checks PHP, extensions,
#      writable dirs, vendor autoload, Vite manifest, BUILD.json.
#   2. Source checkout (has .git + dev deps): full verify including
#      composer validate, migrate:fresh --seed, and test suite.
#
# Exits non-zero on the first failing command.
# Safe: uses throwaway SQLite + APP_ENV=testing; never modifies .env.
#
set -Eeuo pipefail

say()  { printf '\n\033[1;36m== %s ==\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$1" >&2; exit 1; }
pass() { printf '   \033[32m✓\033[0m %s\n' "$1"; }
warn() { printf '   \033[33m⚠\033[0m %s\n' "$1"; }

# --- 0. toolchain ---------------------------------------------------------
say "0. Toolchain"
command -v php >/dev/null || fail "php not found on PATH"
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || fail "PHP 8.3+ required, found $(php -r 'echo PHP_VERSION;')"
pass "PHP $(php -r 'echo PHP_VERSION;')"

# --- 1. Required PHP extensions ------------------------------------------
say "1. PHP extensions"
REQUIRED_EXTS="pdo pdo_mysql mbstring openssl tokenizer ctype json curl fileinfo gd intl dom libxml xmlreader exif zip zlib"
ALL_OK=true
for ext in $REQUIRED_EXTS; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
        pass "ext-${ext}"
    else
        printf '   \033[31m✗\033[0m ext-%s MISSING\n' "$ext"
        ALL_OK=false
    fi
done
# Function check
if php -r 'exit(function_exists("imagewebp") ? 0 : 1);'; then
    pass "imagewebp()"
else
    printf '   \033[31m✗\033[0m imagewebp() MISSING (GD compiled without WebP)\n'
    ALL_OK=false
fi
$ALL_OK || fail "Missing PHP extensions — install them and re-run"

# --- 2. File structure ----------------------------------------------------
say "2. File structure"
[ -f vendor/autoload.php ] || fail "vendor/autoload.php missing — run composer install"
pass "vendor/autoload.php"

[ -f public/build/manifest.json ] || warn "public/build/manifest.json missing — Vite assets not built"
[ -f public/build/manifest.json ] && pass "Vite manifest"

if [ -f BUILD.json ]; then
    pass "BUILD.json present"
    cat BUILD.json
else
    warn "BUILD.json not found (not a release build)"
fi

# Writable directories
for dir in storage/app storage/framework storage/logs bootstrap/cache; do
    if [ -w "$dir" ]; then
        pass "$dir/ writable"
    else
        printf '   \033[31m✗\033[0m %s/ NOT writable\n' "$dir"
    fi
done

# --- 3. Detect mode -------------------------------------------------------
IS_SOURCE=false
if [ -d .git ] && command -v composer >/dev/null; then
    IS_SOURCE=true
fi

if $IS_SOURCE; then
    say "3. Source checkout — full verification"

    # --- record .env checksum so we can prove we never touched it ----------
    ENV_SUM_BEFORE=""
    if [ -f .env ]; then
        ENV_SUM_BEFORE="$(cksum .env)"
    fi

    TMP_DB="$(mktemp -t listinghub_verify_XXXXXX.sqlite)"
    CREATED_ENV=""
    cleanup() {
        rm -f "$TMP_DB"
        [ -n "$CREATED_ENV" ] && rm -f .env
        if [ -n "$ENV_SUM_BEFORE" ] && [ "$ENV_SUM_BEFORE" != "$(cksum .env 2>/dev/null)" ]; then
            printf '\n\033[1;31m!! pre-existing .env changed during verification — investigate\033[0m\n' >&2
        fi
    }
    trap cleanup EXIT

    if [ ! -f .env ]; then
        cp .env.example .env
        CREATED_ENV=1
    fi

    export APP_ENV=testing
    export APP_DEBUG=true
    export DB_CONNECTION=sqlite
    export DB_DATABASE="$TMP_DB"
    export CACHE_STORE=array
    export SESSION_DRIVER=array
    export QUEUE_CONNECTION=sync
    export MAIL_MAILER=array
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"

    say "3a. composer validate --strict"
    composer validate --strict

    say "3b. migrate:fresh --seed (throwaway SQLite)"
    php artisan migrate:fresh --seed

    say "3c. test suite"
    composer test

    say "3d. php artisan about"
    php artisan about
else
    say "3. Deploy package — structural verification only"
    warn "No .git or composer — skipping migrate/seed/test"
    warn "Run the /install wizard to complete setup"
fi

say "ALL GREEN — verification passed"
