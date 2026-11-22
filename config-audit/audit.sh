#!/bin/sh
# =====================================================================
# Laboratory 9 - Configuration audit script
# =====================================================================
# Imperative checks against the declarative baseline (baseline.example).
#
#   Part A - application context: `php artisan app:config-audit` (effective
#            config() values as the running service sees them).
#   Part B - host / HTTP checks: document root, exposed ports, TLS.
#
# Output: "<check_name> PASS|FAIL|UNVERIFIED [note]"
# Exit status: 1 if any REQUIRED check FAILs, otherwise 0.
#
# Never prints raw environment variables, credentials or cached config.
# Secrets are reported only as present/absent.
#
# Usage (staging container):
#   docker compose exec app sh config-audit/audit.sh \
#       --base-url https://staging.example.edu
# Usage (host, HTTP checks only):
#   sh config-audit/audit.sh --skip-app --base-url https://staging.example.edu
# Options:
#   --baseline FILE   baseline file (default config-audit/baseline.example)
#   --base-url URL    public URL of the staging service (enables HTTP/TLS checks)
#   --skip-app        skip the artisan application-context checks
# =====================================================================
set -u

BASELINE="config-audit/baseline.example"
BASE_URL=""
SKIP_APP=0

while [ $# -gt 0 ]; do
    case "$1" in
        --baseline) BASELINE="$2"; shift 2 ;;
        --base-url) BASE_URL="${2%/}"; shift 2 ;;
        --skip-app) SKIP_APP=1; shift ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

[ -f "$BASELINE" ] || { echo "Baseline not found: $BASELINE" >&2; exit 2; }

REQUIRED_FAIL=0

# Read KEY=VALUE from the baseline without executing it.
baseline() {
    grep -E "^[[:space:]]*$1=" "$BASELINE" | tail -n 1 | cut -d= -f2- | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//'
}

# result NAME STATUS REQUIRED(1/0) NOTE
result() {
    note="$4"
    [ "$3" = "0" ] && note="(advisory) $note"
    printf '%-28s %-10s %s\n' "$1" "$2" "$note"
    if [ "$2" = "FAIL" ] && [ "$3" = "1" ]; then REQUIRED_FAIL=1; fi
}

have() { command -v "$1" >/dev/null 2>&1; }

echo "# Configuration audit  $(date -u +%Y-%m-%dT%H:%M:%SZ)  baseline=$BASELINE"
echo "# check                      result     note"

# ---------------------------------------------------------------------
# Part A - application context
# ---------------------------------------------------------------------
if [ "$SKIP_APP" -eq 0 ]; then
    if have php && [ -f artisan ]; then
        php artisan app:config-audit --baseline="$BASELINE" --no-ansi
        [ $? -ne 0 ] && REQUIRED_FAIL=1
    else
        result app_context UNVERIFIED 1 "php/artisan not available here"
    fi
fi

# ---------------------------------------------------------------------
# Part B - host / HTTP checks
# ---------------------------------------------------------------------

# Document root: Nginx config (when readable) + forbidden URLs over HTTP
DOCROOT="$(baseline DOCUMENT_ROOT)"
NGINX_CONF="${NGINX_CONF:-docker/nginx/default.conf}"
if [ -n "$DOCROOT" ] && [ -r "$NGINX_CONF" ]; then
    if grep -Eq "^[[:space:]]*root[[:space:]]+$DOCROOT;" "$NGINX_CONF"; then
        result document_root_config PASS 1 "root $DOCROOT"
    else
        result document_root_config FAIL 1 "nginx root differs from $DOCROOT"
    fi
else
    result document_root_config UNVERIFIED 1 "web server config not accessible"
fi

if [ -n "$BASE_URL" ] && have curl; then
    FORBIDDEN="$(baseline FORBIDDEN_URLS)"
    exposed=""
    for path in $(echo "$FORBIDDEN" | tr ',' ' '); do
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$BASE_URL$path")"
        [ "$code" = "200" ] && exposed="$exposed $path"
    done
    if [ -z "$exposed" ]; then
        result document_root_http PASS 1 "project files not served"
    else
        result document_root_http FAIL 1 "served:$exposed"
    fi

    health="$(curl -s --max-time 10 "$BASE_URL/health")"
    case "$health" in
        *'"status":"ok"'*) result health_endpoint PASS 1 "$(echo "$health" | sed -n 's/.*"version":"\([^"]*\)".*/version=\1/p')" ;;
        *) result health_endpoint FAIL 1 "health check did not return ok" ;;
    esac
else
    result document_root_http UNVERIFIED 1 "no --base-url or curl"
    result health_endpoint UNVERIFIED 1 "no --base-url or curl"
fi

# Writable path ownership (mode is checked in Part A)
OWNER="$(baseline WRITABLE_OWNER)"
if [ -n "$OWNER" ] && have stat; then
    for p in $(baseline WRITABLE_PATHS | tr ',' ' '); do
        if [ -d "$p" ]; then
            actual="$(stat -c '%U' "$p" 2>/dev/null)"
            if [ "$actual" = "$OWNER" ]; then
                result "owner_$(echo "$p" | tr '/' '_')" PASS 1 "owner=$actual"
            else
                result "owner_$(echo "$p" | tr '/' '_')" FAIL 1 "expected=$OWNER actual=$actual"
            fi
        fi
    done
fi

# Listening ports on this host/container (only meaningful on the host)
ALLOWED_PORTS="$(baseline EXPOSED_PORTS)"
if have ss; then
    listening="$(ss -ltnH 2>/dev/null | awk '{print $4}')"
elif have netstat; then
    listening="$(netstat -ltn 2>/dev/null | awk 'NR>2 {print $4}')"
else
    listening=""
fi
if [ -n "$listening" ]; then
    unexpected=""
    for addr in $listening; do
        port="${addr##*:}"
        host="${addr%:*}"
        case "$host" in 127.*|"[::1]"|::1) continue ;; esac   # loopback-only is not exposed
        echo ",$ALLOWED_PORTS,9000," | grep -q ",$port," || unexpected="$unexpected $port"
    done
    if [ -z "$unexpected" ]; then
        result exposed_ports PASS 1 "only approved ports listening"
    else
        result exposed_ports FAIL 1 "unexpected listening ports:$unexpected"
    fi
else
    result exposed_ports UNVERIFIED 1 "ss/netstat unavailable - inspect host firewall manually"
fi

# MySQL must not be reachable on the public URL host
if [ -n "$BASE_URL" ]; then
    pubhost="$(echo "$BASE_URL" | sed -E 's#^[a-z]+://##; s#[:/].*$##')"
    if have nc; then
        if nc -z -w 3 "$pubhost" 3306 2>/dev/null; then
            result mysql_not_public FAIL 1 "3306 reachable on public host"
        else
            result mysql_not_public PASS 1 "3306 not reachable on public host"
        fi
    else
        result mysql_not_public UNVERIFIED 1 "nc unavailable"
    fi
else
    result mysql_not_public UNVERIFIED 1 "no --base-url"
fi

# TLS certificate hostname and expiry
case "$BASE_URL" in
    https://*)
        if have openssl; then
            tlshost="$(echo "$BASE_URL" | sed -E 's#^https://##; s#[:/].*$##')"
            cert="$(echo | openssl s_client -servername "$tlshost" -connect "$tlshost:443" 2>/dev/null | openssl x509 -noout -enddate -ext subjectAltName 2>/dev/null)"
            if [ -z "$cert" ]; then
                result tls_certificate FAIL 1 "no certificate retrieved"
            else
                min_days="$(baseline TLS_MIN_DAYS_VALID)"; min_days="${min_days:-14}"
                if echo | openssl s_client -servername "$tlshost" -connect "$tlshost:443" 2>/dev/null \
                        | openssl x509 -noout -checkend $((min_days * 86400)) >/dev/null 2>&1; then
                    expiry="valid > ${min_days}d"
                    exp_ok=1
                else
                    expiry="expires within ${min_days}d"
                    exp_ok=0
                fi
                if echo "$cert" | grep -q "DNS:$tlshost"; then name_ok=1; else name_ok=0; fi
                enddate="$(echo "$cert" | sed -n 's/notAfter=//p')"
                if [ $exp_ok -eq 1 ] && [ $name_ok -eq 1 ]; then
                    result tls_certificate PASS 1 "hostname match; $expiry; notAfter=$enddate"
                else
                    result tls_certificate FAIL 1 "hostname_match=$name_ok; $expiry"
                fi
            fi
        else
            result tls_certificate UNVERIFIED 1 "openssl unavailable"
        fi
        ;;
    *) result tls_certificate UNVERIFIED 1 "no HTTPS endpoint supplied" ;;
esac

echo "# overall: $([ $REQUIRED_FAIL -eq 0 ] && echo PASS || echo FAIL)"
exit $REQUIRED_FAIL
