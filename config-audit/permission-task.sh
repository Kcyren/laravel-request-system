#!/bin/sh
# =====================================================================
# Laboratory 9 - Idempotent configuration task (run it TWICE)
# =====================================================================
# Sets mode 750 on the instructor-created scratch directory and records
# mode/owner/group before and after. Running it a second time must leave
# exactly the same result as the first run (idempotence) and report
# "no change".
#
# Usage:  sh config-audit/permission-task.sh [DIR] [MODE]
#   DIR  default ./lab9-permissions-demo   MODE default 750
# The directory is NOT created unless CREATE_IF_MISSING=1 (the instructor
# normally creates it, owned by the lab account).
# =====================================================================
set -eu

DIR="${1:-./lab9-permissions-demo}"
MODE="${2:-750}"
LOG="${LOG:-config-audit/permission-task.log}"

if [ ! -d "$DIR" ]; then
    if [ "${CREATE_IF_MISSING:-0}" = "1" ]; then
        mkdir -p "$DIR"
    else
        echo "Directory $DIR not found (ask the instructor, or set CREATE_IF_MISSING=1 on a disposable clone)." >&2
        exit 2
    fi
fi

state() { stat -c '%a %U %G' "$DIR"; }

before="$(state)"
current_mode="${before%% *}"

if [ "$current_mode" = "$MODE" ]; then
    action="no change (already $MODE)"
else
    chmod "$MODE" "$DIR"
    action="chmod $MODE applied"
fi

after="$(state)"
stamp="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

line="$stamp dir=$DIR before=[$before] after=[$after] action=$action"
echo "$line"
mkdir -p "$(dirname "$LOG")" 2>/dev/null || true
echo "$line" >> "$LOG" 2>/dev/null || true

[ "${after%% *}" = "$MODE" ] || { echo "FAIL: mode is ${after%% *}, expected $MODE" >&2; exit 1; }
echo "PASS: $DIR is $MODE"
