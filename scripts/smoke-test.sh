#!/bin/sh
# =====================================================================
# Staging smoke tests (Laboratory 8)
# =====================================================================
# Uses FICTIONAL accounts only. Credentials come from environment
# variables (GitHub secrets / a private shell) and are never printed.
#
# Required env:
#   BASE_URL            e.g. https://staging.example.edu  or http://127.0.0.1:8081
#   EXPECTED_VERSION    version from the release record, e.g. 1.0.0
#   STUDENT_EMAIL / STUDENT_PASSWORD
#   ADMIN_EMAIL   / ADMIN_PASSWORD
# Optional env:
#   EXPECTED_REVISION   commit SHA from the release record
#   LABEL               request label (default STAGE-LIB-08)
#   VERIFY_LABEL        label the VERIFY_REQUEST_ID record must show (default STAGE-LIB-08)
#   VERIFY_REQUEST_ID   after rollback: ID that must still exist
#   OTHER_REQUEST_ID    another student's request; the student must get 403 (Lab 11)
#   RESULTS_DIR         where to write the log (default smoke-results)
#
# Tests: S1 health/revision, S2 student login, S3 request creation
# (+ reopen), S4 admin status update, S5 student restriction,
# S6 (optional) previously created request still present,
# S7 (optional) another student's record is restricted.
# Exit status is non-zero if any test fails -> rollback criterion.
# =====================================================================
set -u

: "${BASE_URL:?}" "${EXPECTED_VERSION:?}" "${STUDENT_EMAIL:?}" "${STUDENT_PASSWORD:?}" "${ADMIN_EMAIL:?}" "${ADMIN_PASSWORD:?}"
BASE_URL="${BASE_URL%/}"
LABEL="${LABEL:-STAGE-LIB-08}"
RESULTS_DIR="${RESULTS_DIR:-smoke-results}"
mkdir -p "$RESULTS_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG="$RESULTS_DIR/smoke-$EXPECTED_VERSION-$STAMP.log"
WORK="$(mktemp -d "$RESULTS_DIR/tmp.XXXXXX")"   # inside the project: works with or without MSYS_NO_PATHCONV
trap 'rm -rf "$WORK"' EXIT
FAILED=0
REQUEST_ID=""

record() { # id name expected actual status
    printf '%-3s %-28s %-6s expected: %s | actual: %s\n' "$1" "$2" "$5" "$3" "$4" | tee -a "$LOG"
    [ "$5" = "PASS" ] || FAILED=1
}

token() { sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$1" | head -n 1; }

# login JAR EMAIL PASSWORD -> prints "HTTP_CODE REDIRECT_URL"
login() {
    curl -s -c "$1" -b "$1" -o "$WORK/login.html" "$BASE_URL/login"
    t="$(token "$WORK/login.html")"
    curl -s -c "$1" -b "$1" -o /dev/null -w '%{http_code} %{redirect_url}' \
        --data-urlencode "_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" \
        "$BASE_URL/login"
}

echo "# Smoke tests  target=$BASE_URL  expected_version=$EXPECTED_VERSION  $(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$LOG"

# S1 - Health and revision -----------------------------------------------
health="$(curl -s --max-time 10 "$BASE_URL/health")"
hv="$(echo "$health" | sed -n 's/.*"version":"\([^"]*\)".*/\1/p')"
hr="$(echo "$health" | sed -n 's/.*"revision":"\([^"]*\)".*/\1/p')"
hs="$(echo "$health" | sed -n 's/.*"status":"\([^"]*\)".*/\1/p')"
if [ "$hs" = "ok" ] && [ "$hv" = "$EXPECTED_VERSION" ] && { [ -z "${EXPECTED_REVISION:-}" ] || [ "$hr" = "$EXPECTED_REVISION" ]; }; then s=PASS; else s=FAIL; fi
record S1 health_and_revision "status=ok version=$EXPECTED_VERSION${EXPECTED_REVISION:+ revision=$EXPECTED_REVISION}" "status=$hs version=$hv revision=$hr" $s

# S2 - Student login ------------------------------------------------------
SJAR="$WORK/student.jar"
res="$(login "$SJAR" "$STUDENT_EMAIL" "$STUDENT_PASSWORD")"
code="$(curl -s -b "$SJAR" -c "$SJAR" -o "$WORK/list.html" -w '%{http_code}' "$BASE_URL/requests")"
if [ "${res%% *}" = "302" ] && [ "$code" = "200" ] && grep -q "My Requests" "$WORK/list.html"; then s=PASS; else s=FAIL; fi
record S2 student_login "302 to /requests, request page 200" "login=${res%% *} requests_page=$code" $s

# S3 - Request creation + reopen -----------------------------------------
t="$(token "$WORK/list.html")"
out="$(curl -s -b "$SJAR" -c "$SJAR" -o "$WORK/create.html" -w '%{http_code} %{redirect_url}' \
    --data-urlencode "_token=$t" --data-urlencode "item_name=HDMI Adapter $LABEL" \
    --data-urlencode "quantity=1" --data-urlencode "purpose=Staging smoke test $LABEL (fictional)" \
    "$BASE_URL/requests")"
REQUEST_ID="$(echo "$out" | sed -n 's#.*/requests/\([0-9][0-9]*\)$#\1#p')"
if [ -n "$REQUEST_ID" ]; then
    code="$(curl -s -b "$SJAR" -c "$SJAR" -o "$WORK/show.html" -w '%{http_code}' "$BASE_URL/requests/$REQUEST_ID")"
    if [ "$code" = "200" ] && grep -q "$LABEL" "$WORK/show.html"; then s=PASS; else s=FAIL; fi
else
    s=FAIL; code="-"
fi
record S3 request_creation "302 to new record; reopened record shows $LABEL" "create=${out%% *} id=${REQUEST_ID:-none} reopen=$code" $s
[ -n "$REQUEST_ID" ] && echo "$REQUEST_ID" > "$RESULTS_DIR/request-id-$LABEL.txt"

# S4 - Administrator status update ----------------------------------------
AJAR="$WORK/admin.jar"
res="$(login "$AJAR" "$ADMIN_EMAIL" "$ADMIN_PASSWORD")"
TARGET_ID="${REQUEST_ID:-${VERIFY_REQUEST_ID:-}}"
if [ -n "$TARGET_ID" ]; then
    curl -s -b "$AJAR" -c "$AJAR" -o "$WORK/admin-show.html" "$BASE_URL/requests/$TARGET_ID"
    t="$(token "$WORK/admin-show.html")"
    code="$(curl -s -b "$AJAR" -c "$AJAR" -o /dev/null -w '%{http_code}' \
        --data-urlencode "_token=$t" --data-urlencode "_method=PATCH" --data-urlencode "status=approved" \
        "$BASE_URL/requests/$TARGET_ID/status")"
    curl -s -b "$AJAR" -c "$AJAR" -o "$WORK/admin-after.html" "$BASE_URL/requests/$TARGET_ID"
    if [ "$code" = "302" ] && grep -q 'data-testid="request-status">approved<' "$WORK/admin-after.html"; then s=PASS; else s=FAIL; fi
else
    s=FAIL; code="no request id"
fi
record S4 admin_status_update "PATCH 302; status=approved" "login=${res%% *} patch=$code" $s

# S5 - Student restriction -------------------------------------------------
if [ -n "$TARGET_ID" ]; then
    curl -s -b "$SJAR" -c "$SJAR" -o "$WORK/student-show.html" "$BASE_URL/requests/$TARGET_ID"
    t="$(token "$WORK/student-show.html")"
    code="$(curl -s -b "$SJAR" -c "$SJAR" -o /dev/null -w '%{http_code}' \
        --data-urlencode "_token=$t" --data-urlencode "_method=PATCH" --data-urlencode "status=rejected" \
        "$BASE_URL/requests/$TARGET_ID/status")"
    curl -s -b "$AJAR" -c "$AJAR" -o "$WORK/admin-check.html" "$BASE_URL/requests/$TARGET_ID"
    if [ "$code" = "403" ] && grep -q 'data-testid="request-status">approved<' "$WORK/admin-check.html"; then s=PASS; else s=FAIL; fi
else
    s=FAIL; code="no request id"
fi
record S5 student_restriction "403; status stays approved" "student_patch=$code" $s

# S6 - Earlier record still present (post-rollback) ------------------------
if [ -n "${VERIFY_REQUEST_ID:-}" ]; then
    code="$(curl -s -b "$SJAR" -c "$SJAR" -o "$WORK/verify.html" -w '%{http_code}' "$BASE_URL/requests/$VERIFY_REQUEST_ID")"
    if [ "$code" = "200" ] && grep -q "${VERIFY_LABEL:-STAGE-LIB-08}" "$WORK/verify.html"; then s=PASS; else s=FAIL; fi
    record S6 record_persisted "request #$VERIFY_REQUEST_ID still shows ${VERIFY_LABEL:-STAGE-LIB-08}" "http=$code" $s
fi

# S7 - Another student's record is restricted (Lab 11) ---------------------
if [ -n "${OTHER_REQUEST_ID:-}" ]; then
    code="$(curl -s -b "$SJAR" -c "$SJAR" -o /dev/null -w '%{http_code}' "$BASE_URL/requests/$OTHER_REQUEST_ID")"
    if [ "$code" = "403" ]; then s=PASS; else s=FAIL; fi
    record S7 other_student_record "403 for another student's request #$OTHER_REQUEST_ID" "http=$code" $s
fi

echo "# overall: $([ $FAILED -eq 0 ] && echo PASS || echo 'FAIL - rollback criteria met')" | tee -a "$LOG"
echo "# log: $LOG"
exit $FAILED
