#!/bin/sh
# =====================================================================
# Build a versioned release artifact locally (Lab 8 rehearsal / no registry)
# =====================================================================
# The GitHub "Release" workflow does the same thing and pushes to GHCR.
#
# Usage:
#   sh scripts/build-release.sh 1.0.0                 # known-good candidate
#   sh scripts/build-release.sh 1.0.1-lab-fault fault # controlled faulty artifact
#
# Produces images laravel-request-system-app:<version> and
# laravel-request-system-web:<version> labelled with the commit SHA, and
# writes release-manifest-<version>.json with the image IDs (digests).
# =====================================================================
set -eu
cd "$(dirname "$0")/.."

VERSION="${1:?usage: build-release.sh <version> [fault]}"
FAULT=false
[ "${2:-}" = "fault" ] && FAULT=true

if git rev-parse HEAD >/dev/null 2>&1; then
    SHA="$(git rev-parse HEAD)"
    if [ -n "$(git status --porcelain)" ]; then
        echo "WARNING: working tree has uncommitted changes; the artifact will not match $SHA exactly." >&2
    fi
else
    SHA="unknown"
fi
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

for target in app web; do
    docker build --target "$target" \
        --build-arg APP_VERSION="$VERSION" \
        --build-arg GIT_SHA="$SHA" \
        --build-arg BUILD_DATE="$DATE" \
        --build-arg LAB_FAULT="$FAULT" \
        -t "laravel-request-system-$target:$VERSION" .
done

APP_ID="$(docker image inspect --format '{{.Id}}' "laravel-request-system-app:$VERSION")"
WEB_ID="$(docker image inspect --format '{{.Id}}' "laravel-request-system-web:$VERSION")"

cat > "release-manifest-$VERSION.json" <<EOF
{
  "version": "$VERSION",
  "commit": "$SHA",
  "built_at": "$DATE",
  "lab_fault": $FAULT,
  "app_image": "laravel-request-system-app:$VERSION",
  "app_image_id": "$APP_ID",
  "web_image": "laravel-request-system-web:$VERSION",
  "web_image_id": "$WEB_ID"
}
EOF
sha256sum "release-manifest-$VERSION.json" > "release-manifest-$VERSION.json.sha256" 2>/dev/null || true

echo "Built release $VERSION (commit $SHA)"
echo "  app: laravel-request-system-app:$VERSION  $APP_ID"
echo "  web: laravel-request-system-web:$VERSION  $WEB_ID"
echo "  manifest: release-manifest-$VERSION.json"
