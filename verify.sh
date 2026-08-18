#!/usr/bin/env bash
# ListingHub verification script.
#
# Three modes:
#   ./verify.sh --package    Archive integrity (pre-install)
#   ./verify.sh --installed  Runtime health (post-install)
#   ./verify.sh              (no args) Legacy: auto-detect source vs package
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[1;36m'
NC='\033[0m'

PASS=0
FAIL=0
WARN=0

say()  { printf "\n${CYAN}== %s ==${NC}\n" "$1"; }
pass() { printf "   ${GREEN}✓${NC} %s\n" "$1"; ((PASS++)) || true; }
fail() { printf "   ${RED}✗${NC} %s\n" "$1"; ((FAIL++)) || true; }
warn() { printf "   ${YELLOW}⚠${NC} %s\n" "$1"; ((WARN++)) || true; }
die()  { printf "\n${RED}!! %s${NC}\n" "$1" >&2; exit 1; }

PHP_BIN="${PHP_BIN:-php}"

# --- Shared: toolchain check --------------------------------------------------

check_php() {
    command -v "$PHP_BIN" &>/dev/null || die "PHP not found on PATH"
    local php_ver
    php_ver=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    if [[ "$(printf '%s\n' "8.3" "$php_ver" | sort -V | head -1)" == "8.3" ]]; then
        pass "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;')"
    else
        fail "PHP 8.3+ required, found $php_ver"
    fi
}

check_extensions() {
    local REQUIRED_EXTS="pdo pdo_mysql mbstring openssl tokenizer ctype json curl fileinfo gd intl dom libxml xmlreader exif zip zlib"
    for ext in $REQUIRED_EXTS; do
        if "$PHP_BIN" -m 2>/dev/null | grep -qi "^${ext}$"; then
            pass "ext-${ext}"
        else
            fail "ext-${ext} MISSING"
        fi
    done
    if "$PHP_BIN" -r 'exit(function_exists("imagewebp") ? 0 : 1);' 2>/dev/null; then
        pass "imagewebp()"
    else
        fail "imagewebp() MISSING (GD compiled without WebP)"
    fi
}

# --- Package verification (pre-install) ---------------------------------------

verify_package() {
    say "Package verification"

    say "1. Toolchain"
    check_php
    check_extensions

    say "2. Archive structure"
    [[ -f artisan ]]         && pass "artisan exists"             || fail "artisan missing"
    [[ -d vendor ]]          && pass "vendor/ present"            || fail "vendor/ missing"
    [[ -f vendor/autoload.php ]] && pass "vendor/autoload.php"    || fail "vendor/autoload.php missing"
    [[ -d public/build ]]    && pass "public/build/ present"      || fail "public/build/ missing"
    [[ -f public/build/manifest.json ]] && pass "Vite manifest"   || fail "Vite manifest missing"
    [[ -f .env.example ]]    && pass ".env.example present"       || fail ".env.example missing"
    [[ -f composer.json ]]   && pass "composer.json present"      || fail "composer.json missing"

    say "3. Clean state"
    [[ ! -f .env ]]          && pass ".env absent (clean)"        || fail ".env found — package contains secrets"
    [[ ! -f storage/app/installed.lock ]] \
                             && pass "No installed.lock"          || fail "installed.lock found — pre-installed"
    [[ ! -d node_modules ]]  && pass "No node_modules/"           || warn "node_modules/ present"
    [[ ! -d .git ]]          && pass "No .git/"                   || warn ".git/ present"
    [[ ! -d tests ]]         && pass "No tests/"                  || warn "tests/ present"
    [[ ! -f database/database.sqlite ]] \
                             && pass "No SQLite DB"               || fail "SQLite database found"

    local has_logs=0
    for f in storage/logs/*.log; do [[ -f "$f" ]] && has_logs=1 && break; done
    [[ $has_logs -eq 0 ]]    && pass "No log files"              || fail "Log files in storage/logs/"

    say "4. Directory scaffold"
    for dir in storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
        [[ -d "$dir" ]]      && pass "$dir/"                     || fail "$dir/ missing"
    done

    say "5. Build metadata"
    if [[ -f BUILD.json ]]; then
        pass "BUILD.json present"
        local version
        version=$(sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' BUILD.json 2>/dev/null | head -1)
        [[ -n "$version" ]] && pass "Version: $version"          || warn "No version in BUILD.json"
    else
        warn "BUILD.json missing (not a release build)"
    fi

    # Checksum verification (if SHA256SUMS exists alongside the archive)
    if [[ -f SHA256SUMS ]]; then
        if sha256sum -c SHA256SUMS --quiet 2>/dev/null; then
            pass "SHA256 checksums valid"
        else
            fail "SHA256 checksum mismatch"
        fi
    fi
}

# --- Installed verification (post-install) ------------------------------------

verify_installed() {
    say "Installation verification"

    say "1. Toolchain"
    check_php

    say "2. Configuration"
    [[ -f .env ]]            && pass ".env exists"               || { fail ".env missing"; return; }

    grep -q '^APP_KEY=.\+' .env \
                             && pass "APP_KEY is set"             || fail "APP_KEY is empty"

    grep -q '^APP_DEBUG=false' .env \
                             && pass "APP_DEBUG=false"            || warn "APP_DEBUG is not false"

    local app_url
    app_url=$(sed -n 's/^APP_URL=//p' .env | tr -d '"' || true)
    if [[ "$app_url" == https://* ]]; then
        pass "APP_URL uses HTTPS"
    else
        warn "APP_URL not HTTPS ($app_url)"
    fi

    say "3. Installation state"
    [[ -f storage/app/installed.lock ]] \
                             && pass "installed.lock present"     || fail "installed.lock missing"

    say "4. Writable directories"
    for dir in storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
        [[ -w "$dir" ]]      && pass "$dir writable"             || fail "$dir not writable"
    done

    say "5. Storage symlink"
    if [[ -L public/storage ]]; then
        local target
        target=$(readlink -f public/storage 2>/dev/null || readlink public/storage)
        [[ -d "$target" ]]   && pass "public/storage → $target"  || fail "public/storage symlink broken"
    else
        fail "public/storage symlink missing (run: php artisan storage:link)"
    fi

    say "6. Database"
    if "$PHP_BIN" artisan db:show --no-interaction 2>/dev/null | grep -q 'Connection'; then
        pass "Database connection OK"
    else
        fail "Database connection failed"
    fi

    local pending
    pending=$("$PHP_BIN" artisan migrate:status --no-interaction 2>/dev/null | grep -c 'Pending' || true)
    if [[ "$pending" -eq 0 ]]; then
        pass "All migrations applied"
    else
        fail "$pending pending migration(s)"
    fi

    say "7. Services"
    if crontab -l 2>/dev/null | grep -q 'schedule:run'; then
        pass "Cron schedule:run configured"
    else
        warn "No cron for schedule:run"
    fi

    say "8. Health check"
    if [[ -n "${app_url:-}" ]] && command -v curl &>/dev/null; then
        local status
        status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "${app_url}/" 2>/dev/null || echo "000")
        if [[ "$status" -ge 200 && "$status" -lt 400 ]]; then
            pass "HTTP $status"
        else
            warn "HTTP $status (may need web server config)"
        fi
    else
        warn "Skipped (curl not available or APP_URL not set)"
    fi
}

# --- Source verification (dev/CI) ---------------------------------------------

verify_source() {
    say "Source checkout — full verification"

    check_php
    check_extensions

    [[ -f vendor/autoload.php ]] && pass "vendor/autoload.php"    || die "vendor/autoload.php missing"
    [[ -f public/build/manifest.json ]] && pass "Vite manifest"   || warn "Vite manifest missing"
    [[ -f BUILD.json ]] && pass "BUILD.json" || warn "BUILD.json not found"

    for dir in storage/app storage/framework storage/logs bootstrap/cache; do
        [[ -w "$dir" ]] && pass "$dir/ writable" || fail "$dir/ NOT writable"
    done

    local ENV_SUM_BEFORE="" CREATED_ENV=""
    [[ -f .env ]] && ENV_SUM_BEFORE="$(cksum .env)"

    TMP_DB="$(mktemp -t listinghub_verify_XXXXXX.sqlite)"
    cleanup() {
        rm -f "$TMP_DB"
        [[ -n "$CREATED_ENV" ]] && rm -f .env
    }
    trap cleanup EXIT

    [[ ! -f .env ]] && cp .env.example .env && CREATED_ENV=1

    export APP_ENV=testing APP_DEBUG=true
    export DB_CONNECTION=sqlite DB_DATABASE="$TMP_DB"
    export CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array
    export APP_KEY="base64:$("$PHP_BIN" -r 'echo base64_encode(random_bytes(32));')"

    if command -v composer &>/dev/null; then
        say "composer validate --strict"
        composer validate --strict && pass "composer.json valid" || fail "composer.json invalid"
    fi

    say "migrate:fresh --seed (throwaway SQLite)"
    "$PHP_BIN" artisan migrate:fresh --seed && pass "Migrate + seed OK" || fail "Migrate + seed failed"

    say "Test suite"
    "$PHP_BIN" artisan test && pass "Tests passed" || fail "Tests failed"
}

# --- Main ---------------------------------------------------------------------

MODE="${1:-auto}"

case "$MODE" in
    --package)
        verify_package
        ;;
    --installed)
        verify_installed
        ;;
    --source)
        verify_source
        ;;
    auto|"")
        if [[ -d .git ]] && command -v composer &>/dev/null; then
            verify_source
        elif [[ -f storage/app/installed.lock ]]; then
            verify_installed
        else
            verify_package
        fi
        ;;
    *)
        echo "Usage: $0 [--package | --installed | --source]"
        echo ""
        echo "  --package    Verify archive integrity (pre-install)"
        echo "  --installed  Verify runtime health (post-install)"
        echo "  --source     Full dev verification (migrate, test)"
        echo "  (no args)    Auto-detect mode"
        exit 1
        ;;
esac

echo ""
echo "────────────────────"
printf "Results: ${GREEN}%d passed${NC}, ${RED}%d failed${NC}, ${YELLOW}%d warnings${NC}\n" "$PASS" "$FAIL" "$WARN"

if [[ $FAIL -gt 0 ]]; then
    printf "${RED}Verification FAILED.${NC}\n"
    exit 1
fi

printf "${GREEN}Verification passed.${NC}\n"
