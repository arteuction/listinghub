#!/usr/bin/env bash
#
# Generate BUILD.json — version, commit SHA, timestamp, checksums.
# Run from the project root: bash scripts/build-manifest.sh [output-dir]
#
set -Eeuo pipefail

OUTPUT_DIR="${1:-.}"
VERSION="$(php -r "echo require 'config/listinghub.php';" 2>/dev/null | grep -oP '(?<=version\x27 => \x27)[^\x27]+' || true)"

# Fallback: parse directly from the config file
if [ -z "$VERSION" ]; then
    VERSION="$(grep -oP "(?<='version' => ')[^']+" config/listinghub.php)"
fi

COMMIT_SHA="none"
COMMIT_SHORT="none"
if command -v git >/dev/null && [ -d .git ]; then
    COMMIT_SHA="$(git rev-parse HEAD 2>/dev/null || echo 'none')"
    COMMIT_SHORT="$(git rev-parse --short HEAD 2>/dev/null || echo 'none')"
fi

TIMESTAMP="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

cat > "${OUTPUT_DIR}/BUILD.json" <<MANIFEST
{
    "version": "${VERSION}",
    "commit": "${COMMIT_SHA}",
    "commit_short": "${COMMIT_SHORT}",
    "built_at": "${TIMESTAMP}",
    "php_min": "8.3.0",
    "laravel": "13.x"
}
MANIFEST

echo "BUILD.json written to ${OUTPUT_DIR}/BUILD.json"
echo "  version: ${VERSION}"
echo "  commit:  ${COMMIT_SHORT}"
echo "  built:   ${TIMESTAMP}"
