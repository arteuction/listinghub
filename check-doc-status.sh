#!/usr/bin/env bash
# Verifies that required documentation files exist and are not stale placeholders.
set -euo pipefail

REQUIRED_DOCS=(
    "docs/ARCHITECTURE.md"
    "docs/LEGACY_PARITY_MATRIX.md"
    "docs/INSTALL.md"
)

ERRORS=0

for doc in "${REQUIRED_DOCS[@]}"; do
    if [[ ! -f "$doc" ]]; then
        echo "MISSING: $doc"
        ERRORS=$((ERRORS + 1))
    elif [[ ! -s "$doc" ]]; then
        echo "EMPTY:   $doc"
        ERRORS=$((ERRORS + 1))
    else
        echo "OK:      $doc"
    fi
done

# Warn if LEGACY_PARITY_MATRIX has no KEEP/REDESIGN/DROP entries
if [[ -f "docs/LEGACY_PARITY_MATRIX.md" ]]; then
    COUNT=$(grep -cE '\|\s+(KEEP|REDESIGN|DROP|DEFER)\s+\|' docs/LEGACY_PARITY_MATRIX.md || true)
    if [[ "$COUNT" -lt 10 ]]; then
        echo "WARN: LEGACY_PARITY_MATRIX.md has fewer than 10 decisions ($COUNT found)"
    fi
fi

if [[ "$ERRORS" -gt 0 ]]; then
    echo ""
    echo "check-doc-status: $ERRORS required document(s) missing or empty."
    exit 1
fi

echo ""
echo "check-doc-status: all required documents present."
