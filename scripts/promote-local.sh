#!/bin/sh
# =====================================================================
# Manual promotion with recorded approval (Laboratory 8)
# =====================================================================
# Used when the staging host cannot run a GitHub self-hosted runner (for
# example Windows Smart App Control blocks it). The approval still happens
# in GitHub: the "Deploy to staging" workflow stops at the `staging`
# environment gate, and only AFTER approval prints this exact command with
# the approved, digest-pinned artifact.
#
# Run from the repository root in Git Bash:
#   sh scripts/promote-local.sh --version 1.0.0 \
#      --app-ref ghcr.io/...-app@sha256:... --web-ref ghcr.io/...-web@sha256:... \
#      --run-url https://github.com/.../actions/runs/123 [--known-good] [--migrate] \
#      [--verify-request-id 3]
#
# Steps: copy deploy files to the staging folder -> scripts/deploy.sh
# (pull exact digests, migrate:status, replace app+web, /health) -> smoke
# tests S1-S5 (+S6) with the fictional staging accounts -> evidence log.
# =====================================================================
set -u
cd "$(dirname "$0")/.."
REPO_DIR="$(pwd)"

VERSION="" APP_REF="" WEB_REF="" RUN_URL="" KNOWN_GOOD=0 MIGRATE=0 VERIFY_ID=""
while [ $# -gt 0 ]; do
    case "$1" in
        --version) VERSION="$2"; shift 2 ;;
        --app-ref) APP_REF="$2"; shift 2 ;;
        --web-ref) WEB_REF="$2"; shift 2 ;;
        --run-url) RUN_URL="$2"; shift 2 ;;
        --known-good) KNOWN_GOOD=1; shift ;;
        --migrate) MIGRATE=1; shift ;;
        --verify-request-id) VERIFY_ID="$2"; shift 2 ;;
        *) echo "Unknown option $1" >&2; exit 2 ;;
    esac
done
[ -n "$VERSION" ] && [ -n "$APP_REF" ] && [ -n "$WEB_REF" ] && [ -n "$RUN_URL" ] || {
    echo "Required: --version --app-ref --web-ref --run-url (copy the command from the approved workflow run)" >&2; exit 2; }

# Docker Desktop CLI may be missing from an older terminal's PATH
for d in "/c/Program Files/Docker/Docker/resources/bin" "$LOCALAPPDATA/Programs/DockerDesktop/resources/bin" "/c/Users/$USERNAME/AppData/Local/Programs/DockerDesktop/resources/bin"; do
    [ -d "$d" ] && PATH="$d:$PATH"
done
export PATH
command -v docker >/dev/null || { echo "docker not found - start Docker Desktop" >&2; exit 2; }

STAGING_DIR="${STAGING_DIR:-/c/lrs-staging}"
[ -f "$STAGING_DIR/.env.staging" ] && [ -f "$STAGING_DIR/.env.db" ] || { echo "$STAGING_DIR/.env.staging or .env.db missing" >&2; exit 2; }

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
EVIDENCE_DIR="$STAGING_DIR/evidence"
mkdir -p "$EVIDENCE_DIR" "$STAGING_DIR/.releases" "$STAGING_DIR/scripts"
LOG="$EVIDENCE_DIR/promotion-$VERSION-$STAMP.log"

say() { echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] $*" | tee -a "$LOG"; }

say "=== Manual promotion of $VERSION (approved in $RUN_URL)"
say "operator: $(git config user.name 2>/dev/null || echo unknown)  repo commit: $(git rev-parse --short HEAD 2>/dev/null)"
echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] approval: $RUN_URL" >> "$STAGING_DIR/.releases/deploy.log"

# 1. Deployment files (no secrets) into the staging folder
cp compose.yaml compose.staging.yaml "$STAGING_DIR/"
cp scripts/*.sh "$STAGING_DIR/scripts/"

# 2. Deploy the exact approved artifact
FLAGS=""
[ "$MIGRATE" -eq 1 ] && FLAGS="--migrate"
( cd "$STAGING_DIR" && WEB_PORT=8081 sh scripts/deploy.sh --version "$VERSION" --app-ref "$APP_REF" --web-ref "$WEB_REF" $FLAGS ) 2>&1 | tee -a "$LOG"
DEPLOY_STATUS=$(tail -n 20 "$LOG" | grep -c "SUCCESS: staging is running $VERSION")
if [ "$DEPLOY_STATUS" -eq 0 ]; then
    say "DEPLOY FAILED - see log above. Nothing marked known-good."
    exit 1
fi

# 3. Smoke tests with the fictional staging accounts (passwords never printed)
REVISION="$(docker image inspect --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}' "$APP_REF" 2>/dev/null)"
seed() { grep "^$1=" "$STAGING_DIR/.env.staging" | cut -d= -f2-; }
LABEL="STAGE-LIB-08"
[ -n "$VERIFY_ID" ] && LABEL="STAGE-LIB-08-R2"

say "--- smoke tests (expected version $VERSION, revision $REVISION)"
BASE_URL="http://127.0.0.1:8081" EXPECTED_VERSION="$VERSION" EXPECTED_REVISION="$REVISION" \
STUDENT_EMAIL="studenta@example.edu" STUDENT_PASSWORD="$(seed SEED_STUDENT_PASSWORD)" \
ADMIN_EMAIL="admin@example.edu" ADMIN_PASSWORD="$(seed SEED_ADMIN_PASSWORD)" \
LABEL="$LABEL" VERIFY_REQUEST_ID="$VERIFY_ID" RESULTS_DIR="$EVIDENCE_DIR" \
    sh "$REPO_DIR/scripts/smoke-test.sh" 2>&1 | tee -a "$LOG"
SMOKE_FAILED=$(tail -n 3 "$LOG" | grep -c "rollback criteria met")

if [ "$SMOKE_FAILED" -ne 0 ]; then
    say "ROLLBACK CRITERIA MET at $(date -u +%Y-%m-%dT%H:%M:%SZ): record the decision, then promote the known-good release again."
    exit 1
fi

if [ "$KNOWN_GOOD" -eq 1 ]; then
    cp "$STAGING_DIR/.releases/current" "$STAGING_DIR/.releases/known-good"
    say "Marked $VERSION as the known-good rollback target."
fi
say "PROMOTION COMPLETE: staging runs $VERSION, smoke tests passed. Evidence: $LOG"
