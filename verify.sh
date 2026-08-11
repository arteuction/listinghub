#!/usr/bin/env bash
#
# ListingHub — runtime verification gate.
#
# Self-contained and safe: throwaway SQLite DB + APP_ENV=testing, ephemeral
# APP_KEY, and it NEVER modifies your .env or a real database. Exits non-zero
# on the first failing command. See docs/VERIFICATION.md.
#
# Run either way:  ./verify.sh   or   bash verify.sh
#
set -Eeuo pipefail

say()  { printf '\n\033[1;36m== %s ==\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$1" >&2; exit 1; }

# --- 0. toolchain checks BEFORE doing anything else ------------------------
say "0. Toolchain"
command -v php >/dev/null      || fail "php not found on PATH"
command -v composer >/dev/null || fail "composer not found on PATH"
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || fail "PHP 8.3+ required, found $(php -r 'echo PHP_VERSION;')"
php -m | grep -qi '^pdo_sqlite$' || fail "PHP extension pdo_sqlite is required"
command -v sqlite3 >/dev/null   || printf '   (note: sqlite3 CLI not found — not required, continuing)\n'
php -v | head -1
composer --version

# --- record .env checksum so we can prove we never touched it --------------
ENV_SUM_BEFORE=""
if [ -f .env ]; then
  ENV_SUM_BEFORE="$(cksum .env)"
fi

# --- throwaway SQLite DB + throwaway .env, cleaned up on success AND failure -
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

# Framework bootstrap reads .env; in a fresh checkout there is none. Create a
# throwaway from .env.example (removed on exit). A pre-existing .env is left
# untouched and guarded by the checksum above.
if [ ! -f .env ]; then
  cp .env.example .env
  CREATED_ENV=1
fi

# Isolated runtime env — overrides only for this process; .env untouched.
export APP_ENV=testing
export APP_DEBUG=true
export DB_CONNECTION=sqlite
export DB_DATABASE="$TMP_DB"
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"

say "1. composer validate --strict"
composer validate --strict

say "2. composer install"
composer install --no-interaction --prefer-dist

say "3. migrate:fresh --seed (throwaway SQLite)"
php artisan migrate:fresh --seed

say "4. composer test"
composer test

say "5. php artisan about"
php artisan about

say "ALL GREEN — verification contract satisfied"
