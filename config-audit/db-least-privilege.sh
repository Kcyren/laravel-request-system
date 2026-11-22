#!/bin/sh
# =====================================================================
# Laboratory 9 - Corrective task: least-privilege database account
# =====================================================================
# The MySQL image grants the app account ALL PRIVILEGES on its schema.
# This task replaces that with exactly the privileges approved in the
# baseline (DB_ALLOWED_PRIVILEGES), scoped to the app schema only.
#
# Idempotent: running it again leaves the same grants. It shows the grants
# before and after (no passwords). Credentials are read inside the db
# container from its own environment and never printed.
#
# Usage (from the folder holding the compose files, Git Bash):
#   sh config-audit/db-least-privilege.sh "docker compose -p lrs-staging -f compose.yaml -f compose.staging.yaml"
#   sh config-audit/db-least-privilege.sh "docker compose"          # Lab 4 dev stack
# =====================================================================
set -eu

DC="${1:-docker compose}"
BASELINE="${BASELINE:-config-audit/baseline.example}"
PRIVS="$(grep -E '^DB_ALLOWED_PRIVILEGES=' "$BASELINE" 2>/dev/null | cut -d= -f2- || true)"
PRIVS="${PRIVS:-SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES}"

run_sql() {
    # root credentials stay inside the db container; drop only the CLI password warning
    $DC exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "$0" 2>&1' "$1" \
        | grep -v 'Using a password on the command line' || true
}

show() {
    $DC exec -T db sh -c 'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -N -e "SHOW GRANTS FOR CURRENT_USER()"' 2>/dev/null
}

echo "== Grants BEFORE"
show

SCHEMA="$($DC exec -T db sh -c 'printf %s "$MYSQL_DATABASE"')"
APPUSER="$($DC exec -T db sh -c 'printf %s "$MYSQL_USER"')"

# The MySQL image stores the grant with escaped underscores (db\_name), so
# revoke both spellings; "no such grant" on a re-run is expected and ignored.
ESCAPED="$(printf %s "$SCHEMA" | sed 's/_/\\_/g')"
for name in "$ESCAPED" "$SCHEMA"; do
    run_sql "REVOKE ALL PRIVILEGES ON \`$name\`.* FROM '$APPUSER'@'%';" | grep -v 'There is no such grant' || true
done
out="$(run_sql "GRANT $PRIVS ON \`$ESCAPED\`.* TO '$APPUSER'@'%'; FLUSH PRIVILEGES;")"
if [ -n "$out" ]; then echo "$out" >&2; exit 1; fi

echo "== Grants AFTER"
show
echo "Applied: $PRIVS on $SCHEMA.* for $APPUSER (idempotent)"
