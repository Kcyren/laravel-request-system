#!/bin/sh
# =====================================================================
# Roll staging back to the recorded known-good artifact (Laboratory 8)
# =====================================================================
# Preferred path: run the "Deploy to staging" workflow again with the
# known-good version (keeps approval + log in one place). Use this script
# on the host only for the instructor-controlled manual procedure.
#
#   sh scripts/rollback.sh            # uses .releases/known-good
#   sh scripts/rollback.sh previous   # uses .releases/previous
#
# Only the application artifact is replaced. The database schema and
# records (db_data volume) are kept - no migrate:rollback, no restore.
# =====================================================================
set -eu
cd "$(dirname "$0")/.."

SOURCE=".releases/${1:-known-good}"
[ -f "$SOURCE" ] || { echo "No release record at $SOURCE" >&2; exit 2; }

get() { grep "^$1=" "$SOURCE" | cut -d= -f2-; }

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] ROLLBACK decision -> $(get version)" | tee -a .releases/deploy.log
exec sh scripts/deploy.sh --version "$(get version)" --app-ref "$(get app_ref)" --web-ref "$(get web_ref)"
