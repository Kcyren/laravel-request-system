#!/bin/sh
# =====================================================================
# Laboratory 10 - repeated readiness checker (Git Bash)
# =====================================================================
# Runs the readiness check from the lab handout CHECKS times, INTERVAL
# seconds apart:
#   curl --max-time 5 -s -o /dev/null -w "HTTP %{http_code} Time %{time_total}s\n" <READY_URL>
# Records the timestamp, HTTP status, response time and any timeout or
# connection failure, then compares the results with the SLO.
#
# Notification rule: after ALERT_AFTER_CONSECUTIVE_FAILURES failures in a
# row, ONE alert is printed (time, service, observed failure, responder).
# No duplicate alert is sent until the condition changes (a RECOVERED
# notice is printed when the check passes again).
#
# Usage:  sh scripts/health-check.sh [checks] [interval_seconds]
# Settings: monitoring/health-check.conf
# Results:  monitoring/results/ (kept out of Git)
# =====================================================================
set -u
cd "$(dirname "$0")/.."

CONF="monitoring/health-check.conf"
conf() { grep -E "^$1=" "$CONF" | tail -n 1 | cut -d= -f2-; }

SERVICE="$(conf SERVICE_NAME)"
URL="$(conf READY_URL)"
CHECKS="${1:-$(conf CHECKS)}"
INTERVAL="${2:-$(conf INTERVAL_SECONDS)}"
TIMEOUT="$(conf TIMEOUT_SECONDS)"
SLO_OK="$(conf SLO_SUCCESS_PERCENT)"
SLO_MS="$(conf SLO_LATENCY_MS)"
SLO_LAT="$(conf SLO_LATENCY_PERCENT)"
ALERT_AFTER="$(conf ALERT_AFTER_CONSECUTIVE_FAILURES)"
RESPONDER="$(conf RESPONDER)"

mkdir -p monitoring/results
STAMP="$(date +%Y%m%d-%H%M%S)"
LOG="monitoring/results/check-$STAMP.log"
ALERTS="monitoring/results/alerts.log"

say() { echo "$*" | tee -a "$LOG"; }

say "# Readiness checker  service=$SERVICE  url=$URL"
say "# checks=$CHECKS interval=${INTERVAL}s timeout=${TIMEOUT}s  SLO: >=${SLO_OK}% HTTP 200, >=${SLO_LAT}% of successes <= ${SLO_MS} ms"
say "# no  timestamp            result"

ok=0; fast=0; fails_in_row=0; alerted=0; i=1
while [ "$i" -le "$CHECKS" ]; do
    ts="$(date '+%Y-%m-%d %H:%M:%S')"
    out="$(env -u MSYS_NO_PATHCONV curl --max-time "$TIMEOUT" -s -o /dev/null -w "HTTP %{http_code} Time %{time_total}s" "$URL")"
    rc=$?
    code="$(echo "$out" | awk '{print $2}')"
    secs="$(echo "$out" | awk '{print $4}' | tr -d 's')"
    ms="$(awk -v s="${secs:-0}" 'BEGIN { printf "%d", s * 1000 }')"

    note=""
    if [ "$rc" -eq 28 ]; then note="  TIMEOUT"; elif [ "$rc" -ne 0 ]; then note="  CONNECTION FAILURE (curl exit $rc)"; fi
    say "$(printf '%02d' "$i")  $ts  $out${note}"

    if [ "$code" = "200" ]; then
        ok=$((ok + 1))
        [ "$ms" -le "$SLO_MS" ] && fast=$((fast + 1))
        if [ "$alerted" -eq 1 ]; then
            msg="[$ts] RECOVERED  service=$SERVICE  readiness HTTP 200 again  responder=$RESPONDER"
            say ">>> $msg"; echo "$msg" >> "$ALERTS"
            alerted=0
        fi
        fails_in_row=0
    else
        fails_in_row=$((fails_in_row + 1))
        if [ "$fails_in_row" -ge "$ALERT_AFTER" ] && [ "$alerted" -eq 0 ]; then
            msg="[$ts] ALERT  service=$SERVICE  observed=$fails_in_row consecutive readiness failures (last: HTTP ${code:-000}${note})  responder=$RESPONDER"
            say ">>> $msg"; echo "$msg" >> "$ALERTS"
            alerted=1
        fi
    fi

    [ "$i" -lt "$CHECKS" ] && sleep "$INTERVAL"
    i=$((i + 1))
done

pct_ok="$(awk -v a="$ok" -v b="$CHECKS" 'BEGIN { printf "%.1f", (b ? a * 100 / b : 0) }')"
pct_fast="$(awk -v a="$fast" -v b="$ok" 'BEGIN { printf "%.1f", (b ? a * 100 / b : 0) }')"
avail="$(awk -v p="$pct_ok" -v s="$SLO_OK" 'BEGIN { print (p >= s) ? "MET" : "NOT MET" }')"
lat="$(awk -v p="$pct_fast" -v s="$SLO_LAT" -v n="$ok" 'BEGIN { print (n > 0 && p >= s) ? "MET" : "NOT MET" }')"

say "# ---------------------------------------------------------------"
say "# Successful checks: $ok/$CHECKS = ${pct_ok}%   (SLO >= ${SLO_OK}%)  -> $avail"
say "# Successes <= ${SLO_MS} ms: $fast/$ok = ${pct_fast}%   (SLO >= ${SLO_LAT}%)  -> $lat"
say "# log: $LOG"
