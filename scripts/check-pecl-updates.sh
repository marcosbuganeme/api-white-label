#!/bin/bash
###############################################################################
# PECL Version Checker
#
# Dependabot cannot track PECL extensions. This script checks for updates
# to the pinned versions in docker/app/Dockerfile.
#
# Usage: ./scripts/check-pecl-updates.sh
# Recommended: Run weekly via CI or cron
###############################################################################

set -euo pipefail

DOCKERFILE="docker/app/Dockerfile"

echo "=== PECL Extension Version Check ==="
echo ""

# Extract current versions from Dockerfile
CURRENT=$(grep 'pecl install' "$DOCKERFILE" | sed 's/.*pecl install //' | tr ' ' '\n' | sed 's/\\$//')

for ext in $CURRENT; do
    NAME=$(echo "$ext" | cut -d'-' -f1)
    VERSION=$(echo "$ext" | cut -d'-' -f2-)

    # Fetch latest version from PECL
    LATEST=$(curl -sf "https://pecl.php.net/rest/r/$NAME/latest.txt" 2>/dev/null | tr -d '[:space:]' || echo "unknown")

    if [ "$VERSION" = "$LATEST" ]; then
        echo "[OK]      $NAME $VERSION (up to date)"
    elif [ "$LATEST" = "unknown" ]; then
        echo "[??]      $NAME $VERSION (could not check)"
    else
        echo "[UPDATE]  $NAME $VERSION → $LATEST (https://pecl.php.net/package/$NAME)"
    fi
done

echo ""
echo "Manual update: edit PECL versions in $DOCKERFILE"
