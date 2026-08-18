#!/usr/bin/env bash
# ListingHub upgrade script.
# Usage: ./upgrade.sh listinghub-3.6.3.tar.gz
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PHP_BIN="${PHP_BIN:-php}"
ARCHIVE="${1:-}"

if [[ -z "$ARCHIVE" ]]; then
    echo "Usage: $0 <listinghub-x.x.x.tar.gz|.zip>"
    exit 1
fi

if [[ ! -f "$ARCHIVE" ]]; then
    echo -e "${RED}Archive not found: $ARCHIVE${NC}" >&2
    exit 1
fi

if [[ ! -f storage/app/installed.lock ]]; then
    echo -e "${RED}ListingHub is not installed. Use install.sh first.${NC}" >&2
    exit 1
fi

# --- Detect current version ---------------------------------------------------

CURRENT_VERSION="unknown"
if [[ -f BUILD.json ]]; then
    CURRENT_VERSION=$(grep -oP '"version"\s*:\s*"\K[^"]+' BUILD.json 2>/dev/null || echo "unknown")
fi
echo -e "Current version: ${YELLOW}${CURRENT_VERSION}${NC}"

# --- Extract to temp and detect new version -----------------------------------

TEMP_DIR=$(mktemp -d)
trap 'rm -rf "$TEMP_DIR"' EXIT

echo "Extracting archive..."
case "$ARCHIVE" in
    *.tar.gz|*.tgz)
        tar xzf "$ARCHIVE" -C "$TEMP_DIR"
        ;;
    *.zip)
        unzip -q "$ARCHIVE" -d "$TEMP_DIR"
        ;;
    *)
        echo -e "${RED}Unsupported format. Use .tar.gz or .zip${NC}" >&2
        exit 1
        ;;
esac

# Find the wrapper directory
EXTRACTED=$(find "$TEMP_DIR" -maxdepth 1 -mindepth 1 -type d | head -1)
if [[ -z "$EXTRACTED" || ! -f "$EXTRACTED/artisan" ]]; then
    echo -e "${RED}Invalid archive: no artisan found${NC}" >&2
    exit 1
fi

NEW_VERSION="unknown"
if [[ -f "$EXTRACTED/BUILD.json" ]]; then
    NEW_VERSION=$(grep -oP '"version"\s*:\s*"\K[^"]+' "$EXTRACTED/BUILD.json" 2>/dev/null || echo "unknown")
fi
echo -e "New version: ${GREEN}${NEW_VERSION}${NC}"

# --- Verify package integrity -------------------------------------------------

if [[ -f "$EXTRACTED/verify.sh" ]]; then
    echo "Running package verification..."
    bash "$EXTRACTED/verify.sh" --package || {
        echo -e "${RED}Package verification failed. Aborting.${NC}" >&2
        exit 1
    }
fi

# --- Backup -------------------------------------------------------------------

BACKUP_DIR="$SCRIPT_DIR/backups/$(date +%Y%m%d-%H%M%S)-${CURRENT_VERSION}"
mkdir -p "$BACKUP_DIR"

echo "Backing up to $BACKUP_DIR ..."
cp -a .env "$BACKUP_DIR/.env"
[[ -f BUILD.json ]] && cp BUILD.json "$BACKUP_DIR/BUILD.json"
cp -a storage/app/installed.lock "$BACKUP_DIR/installed.lock"

# Backup database
if "$PHP_BIN" artisan db:show --no-interaction &>/dev/null; then
    echo "Dumping database..."
    "$PHP_BIN" artisan schema:dump --path="$BACKUP_DIR/db-backup.sql" 2>/dev/null || \
        echo -e "${YELLOW}Database dump skipped (schema:dump not available). Back up manually.${NC}"
fi

# --- Maintenance mode ---------------------------------------------------------

echo "Enabling maintenance mode..."
"$PHP_BIN" artisan down --retry=60 --refresh=15 2>/dev/null || true

# --- Rollback function --------------------------------------------------------

rollback() {
    echo -e "${RED}Upgrade failed. Rolling back...${NC}"
    cp -a "$BACKUP_DIR/.env" .env
    cp -a "$BACKUP_DIR/installed.lock" storage/app/installed.lock
    "$PHP_BIN" artisan up 2>/dev/null || true
    echo -e "${YELLOW}Rollback complete. Check $BACKUP_DIR for database backup.${NC}"
    exit 1
}

trap rollback ERR

# --- Sync files ---------------------------------------------------------------

echo "Syncing files..."
rsync -a --delete \
    --exclude='.env' \
    --exclude='storage/app/' \
    --exclude='storage/logs/' \
    --exclude='storage/framework/sessions/' \
    --exclude='backups/' \
    --exclude='public/storage' \
    "$EXTRACTED/" "$SCRIPT_DIR/"

# Restore installed.lock (rsync --exclude above keeps storage/app/ contents,
# but the source archive intentionally omits installed.lock)
cp -a "$BACKUP_DIR/installed.lock" storage/app/installed.lock

# --- Post-upgrade artisan commands --------------------------------------------

echo "Running migrations..."
"$PHP_BIN" artisan migrate --force --no-interaction

echo "Clearing caches..."
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

echo "Restarting queue workers..."
"$PHP_BIN" artisan queue:restart 2>/dev/null || true

# --- Storage link check -------------------------------------------------------

if [[ ! -L public/storage ]]; then
    echo "Recreating storage symlink..."
    "$PHP_BIN" artisan storage:link --force
fi

# --- Bring back online --------------------------------------------------------

"$PHP_BIN" artisan up

# --- Verify -------------------------------------------------------------------

echo ""
echo "Running post-upgrade verification..."
bash verify.sh --installed || echo -e "${YELLOW}Some checks didn't pass. Review above.${NC}"

echo ""
echo -e "${GREEN}Upgrade to ${NEW_VERSION} complete.${NC}"
echo "Backup saved at: $BACKUP_DIR"

trap - ERR EXIT
