#!/bin/sh
# =====================================================================
# Promote an approved artifact to STAGING (Laboratory 8)
# =====================================================================
# Runs ON the staging host (called by the "Deploy to staging" workflow
# after the approval gate, or manually with recorded approval).
#
# Usage:
#   sh scripts/deploy.sh --version 1.0.0 \
#       --app-ref ghcr.io/owner/repo-app@sha256:... \
#       --web-ref ghcr.io/owner/repo-web@sha256:... \
#       [--migrate] [--known-good]
#
# Steps:
#   1. record the currently running release (rollback target)
#   2. pull the exact approved images
#   3. check migration status with the NEW artifact; stop if a schema change
#      is pending and --migrate was not approved
#   4. if --migrate: take a database recovery point, then migrate --force
#   5. replace ONLY app + web (db and its db_data volume are untouched)
#   6. wait for /health and confirm the running version matches
#   7. write the release record (.releases/)
#
# Rollback = run this script (or the workflow) again with the known-good
# version/refs. The database is never dropped or reset.
# =====================================================================
set -eu
cd "$(dirname "$0")/.."

VERSION="" APP_REF="" WEB_REF="" MIGRATE=0 KNOWN_GOOD=0
while [ $# -gt 0 ]; do
    case "$1" in
        --version) VERSION="$2"; shift 2 ;;
        --app-ref) APP_REF="$2"; shift 2 ;;
        --web-ref) WEB_REF="$2"; shift 2 ;;
        --migrate) MIGRATE=1; shift ;;
        --known-good) KNOWN_GOOD=1; shift ;;
        *) echo "Unknown option $1" >&2; exit 2 ;;
    esac
done
[ -n "$VERSION" ] && [ -n "$APP_REF" ] && [ -n "$WEB_REF" ] || { echo "--version, --app-ref and --web-ref are required" >&2; exit 2; }
[ -f .env.staging ] || { echo ".env.staging is missing on this host (approved runtime procedure)" >&2; exit 2; }
[ -f .env.db ] || { echo ".env.db is missing on this host" >&2; exit 2; }

PROJECT="${STAGING_PROJECT:-lrs-staging}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:${WEB_PORT:-8081}/health}"
export APP_IMAGE_REF="$APP_REF" WEB_IMAGE_REF="$WEB_REF"
DC="docker compose -p $PROJECT -f compose.yaml -f compose.staging.yaml"

mkdir -p .releases backups
ts() { date -u +%Y-%m-%dT%H:%M:%SZ; }
log() { echo "[$(ts)] $*" | tee -a .releases/deploy.log; }

log "=== Deploy request: version=$VERSION migrate=$MIGRATE"
log "app_ref=$APP_REF"
log "web_ref=$WEB_REF"

# 1. Remember what is running now
if [ -f .releases/current ]; then
    cp .releases/current .releases/previous
    log "previous release: $(grep '^version=' .releases/previous | cut -d= -f2)"
fi

# 2. Pull the exact approved artifact (no-op for locally built images)
case "$APP_REF" in
    */*) docker pull -q "$APP_REF" >/dev/null && docker pull -q "$WEB_REF" >/dev/null ;;
esac

# Make sure the database service is running and healthy (never recreated here)
$DC up -d --wait db

# 3. Migration status, evaluated with the NEW artifact
STATUS_OUT="$($DC run --rm --no-deps -T app php artisan migrate:status --no-ansi 2>&1 || true)"
echo "$STATUS_OUT" | tee -a .releases/deploy.log
if echo "$STATUS_OUT" | grep -q "Pending"; then
    SCHEMA_CHANGE=yes
else
    SCHEMA_CHANGE=no
fi
log "schema change required: $SCHEMA_CHANGE"

if [ "$SCHEMA_CHANGE" = "yes" ] && [ "$MIGRATE" -ne 1 ]; then
    log "ABORT: pending migrations but the approval did not include a migration. Nothing was replaced."
    exit 3
fi

# 4. Recovery point before an approved schema change
if [ "$SCHEMA_CHANGE" = "yes" ]; then
    BACKUP="backups/predeploy-$VERSION-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
    # credentials are read inside the db container from its own env; never echoed
    $DC exec -T db sh -c 'exec mysqldump --single-transaction --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' 2>/dev/null | gzip > "$BACKUP"
    chmod 600 "$BACKUP"
    log "recovery point: $BACKUP ($(wc -c < "$BACKUP") bytes)"
fi

# 5. Replace the application artifact only (db + db_data untouched)
$DC up -d --no-deps --force-recreate app
$DC up -d --no-deps --wait app
$DC up -d --no-deps --force-recreate web

if [ "$SCHEMA_CHANGE" = "yes" ]; then
    $DC exec -T app php artisan migrate --force --no-ansi | tee -a .releases/deploy.log
fi

# 6. Health + version verification
ok=0
for _ in $(seq 1 30); do
    body="$(curl -fsS --max-time 5 "$HEALTH_URL" 2>/dev/null || true)"
    if echo "$body" | grep -q '"status":"ok"' && echo "$body" | grep -q "\"version\":\"$VERSION\""; then
        ok=1; break
    fi
    sleep 3
done
log "health: $body"
if [ "$ok" -ne 1 ]; then
    log "FAILED: health check did not report status ok with version $VERSION"
    exit 4
fi

# 7. Release record
cat > .releases/current <<EOF
version=$VERSION
app_ref=$APP_REF
web_ref=$WEB_REF
deployed_at=$(ts)
schema_change=$SCHEMA_CHANGE
EOF
[ "$KNOWN_GOOD" -eq 1 ] && cp .releases/current .releases/known-good
echo "$(ts) $VERSION $APP_REF" >> .releases/history
log "SUCCESS: staging is running $VERSION"
$DC ps
