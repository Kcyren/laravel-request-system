# Laboratory 10 – Monitor Laravel and respond to an incident

Private local copy only: `php artisan serve` on **http://localhost:8000** with
the XAMPP MySQL test database **laravel_request_lab10_db**, which holds
fictional records only. No shared or production service is used.

## 1. Logs, metrics and traces

- **Logs** are timestamped records of single events, for example
  `request.created` with a record ID. They show *what happened*.
- **Metrics** are numbers measured over time, for example the percentage of
  successful readiness checks or the response time. They show *how well the
  service is doing*.
- **Traces** follow one request through every step it passes. They show
  *where time was spent* in a request.
- **Request identifier:** every HTTP request gets an `X-Request-Id`
  (middleware `AssignRequestId`). The ID is written into each log line of
  that request and returned in the response header. This lets you connect
  related events, such as a `request.created` log line, its HTTP response
  and any warning from the same request.
- **Centralized collection** sends the logs from every instance to one
  place (for example a log server), so they can be searched together and
  kept after a container is replaced. **Sensitive-data redaction** removes
  or never writes passwords, tokens, request descriptions and personal
  information, so the logs can be shared with the team safely.

## 2. SLI and SLO (local application)

| Item | Value |
|---|---|
| SLI 1 – availability | % of readiness checks (`GET /health/ready`) that return HTTP 200 |
| SLI 2 – latency | % of successful checks with a response time ≤ 500 ms |
| SLO | ≥ 95% successful readiness checks across 20 checks, and ≤ 500 ms for ≥ 95% of the successful checks |
| Sampling interval | 5 seconds (20 checks ≈ 95 seconds) |
| Timeout | 5 seconds per check (`curl --max-time 5`) |
| Threshold / alert | 2 consecutive readiness failures → one alert |
| Configuration | `monitoring/health-check.conf`, script `scripts/health-check.sh` |

- **Availability** is the share of time or checks for which the service works.
- **Error rate** is the share of checks or requests that fail (here, non-200 readiness results).
- **Latency** is how long a response takes (here, curl `time_total`).
- **Alert fatigue:** if the same alert repeats every 5 seconds during one
  outage, people stop reading the alerts and may miss a new problem. That's
  why the checker sends **one** alert when the rule is met and stays quiet
  until the condition changes (a single RECOVERED notice).

## 3. Structured logging

`ServiceRequestController` writes, **only after the database operation
succeeds**, one `info` event to Laravel's existing log
(`storage/logs/laravel.log`, channel `stack` → `single`):

| Event | Context fields |
|---|---|
| `request.created` | `request_id`, `record_id`, `outcome` |
| `request.status_updated` | `request_id`, `record_id`, `status`, `outcome` |

The timestamp is added by the logger. Passwords, tokens, request
descriptions (purpose) and personal information (names, emails) are never
logged.

## 4. Health routes (`routes/web.php`)

| Route | Meaning | Response |
|---|---|---|
| `GET /health/live` | The app process is running (no database call) | `200 {"status":"ok"}` |
| `GET /health/ready` | The app can reach its database (`DB::select("SELECT 1")` in try/catch) | `200 {"status":"ready"}` or `503 {"status":"unavailable"}` |

Both are stateless: they don't use sessions, which are stored in the
database, so they still answer during a database outage. Only a generic
status is returned, never exception details or credentials. The MySQL
connection timeout is short (`DB_TIMEOUT=3` seconds, `PDO::ATTR_TIMEOUT`).

## 5. Incident response runbook (prepared before the fault)

Roles: **Driver / incident lead:** Cyrene Jane C. Teodocio.
**Reviewer / communications:** Cyrene Jane C. Teodocio (solo; no partner assigned).

| Step | What to do | Responsible |
|---|---|---|
| 1. Detection | The checker prints `ALERT` after 2 consecutive readiness failures (time, service, observed failure, responder). Record the incident start time. | Driver |
| 2. Triage | Compare `/health/live` (app process) with `/health/ready` (database). Live 200 + ready 503 → the app is running but cannot reach the database. Check that MySQL is running in XAMPP, and check `DB_HOST`/`DB_PORT` in the local `.env`. | Driver |
| 3. Containment | Suspend test writes (no new requests or status changes) until readiness is back. | Driver |
| 4. Communication | Notify the partner/reviewer with: time, service, symptom, impact, next update. Post an update at recovery. | Reviewer |
| 5. Evidence preservation | Save the checker output, the alert line, the readiness/liveness responses and the related lines of `storage/logs/laravel.log` (sanitized, without secrets). | Reviewer |
| 6. Recovery | Restore the saved `DB_PORT` in the local `.env`, run `php artisan config:clear`, restart `php artisan serve` if needed. | Driver |
| 7. Verification | `/health/ready` returns HTTP 200, the checker shows RECOVERED, and the smoke tests pass: student login, create and reopen `MON-RECOVERY-10`, admin status update, student restriction, `MON-LAB-10` still present. | Reviewer |

**Expected recovery procedure:** restore the original database port, clear
the config cache, then confirm readiness 200 and run the post-recovery
smoke tests.

## 6. Exercise results (2026-11-24, local time UTC+8)

Starting revision `f0916fd` (Lab 9 checkpoint), Laravel 13.43.0,
http://localhost:8000, database `laravel_request_lab10_db` (fictional records only).

| Evidence | Result |
|---|---|
| Structured logs | `request.created` and `request.status_updated` for record 4 (MON-LAB-10) and record 5, each with `request_id`, `record_id` and `outcome` (`success`); no personal data |
| Student restriction | Student status PATCH → 403; status unchanged |
| Baseline checker (10:47:10–10:49:19) | 20/20 HTTP 200 = **100%** (SLO ≥ 95%: met); 20/20 ≤ 500 ms (slowest 0.22 s): met |
| During outage (two runs) | 0/20 and 0/20 successful (timeouts after 5 s): SLO **not met**; exactly one ALERT per run |
| After recovery (11:20:49–11:22:46) | 20/20 HTTP 200 = **100%**: met |

## 7. Incident timeline

| Time | Event | Runbook step |
|---|---|---|
| 11:03 | `DB_PORT` in the private local `.env` changed from 3306 to the unused port 3399; `php artisan config:clear`; server restarted | (fault introduced) |
| 11:03:49 | First failed readiness check (timeout) | Detection |
| **11:04:01** | **ALERT**: 2 consecutive readiness failures, service `laravel-request-system (local)`, responder Cyrene Jane C. Teodocio. **Incident start.** | Detection |
| 11:09:03 | Second checker run: one ALERT, 0/20 successful | Detection |
| 11:17 | Triage: `/health/live` 200, `/health/ready` 503 → app running, database unreachable | Triage |
| 11:18 | GitHub issue #7 opened: time, service, symptom, impact, test writes suspended | Communication, containment |
| 11:19:25 | Evidence preserved: checker logs, `alerts.log`, `health.ready_failed` log lines | Evidence preservation |
| **11:20:29** | `DB_PORT` restored to 3306, `config:clear`, server restarted → readiness **HTTP 200**. **Recovery.** | Recovery |
| 11:20:49–11:22:46 | Checker 20/20 (100%) | Verification |
| 11:24:44 | Post-recovery smoke tests S1–S6 PASS: MON-RECOVERY-10 = request #6, MON-LAB-10 (#4) still present | Verification |
| 11:25 | Recovery comment on issue #7; issue closed | Communication |

**Duration:** about 16.5 minutes from the first alert (11:04:01) to recovery (11:20:29).

## 8. Blameless incident review

- **Impact:** for about 16 minutes, every database-backed function of the
  local application (login, request list, request creation, status updates)
  was unavailable. The application process stayed up (liveness 200). No
  data was lost: MON-LAB-10 (#4) was still present after recovery. Only the
  private local copy with fictional records was affected.
- **Verified cause:** the local `.env` pointed `DB_PORT` to 3399, where no
  MySQL server listens. This was confirmed by restoring 3306 and seeing
  readiness return 200 immediately.
- **Contributing factors:**
  1. A configuration change (`.env`) was applied without checking the
     database connection right after the restart.
  2. While the database port was unreachable, a readiness request took
     longer than the checker's 5-second timeout (connection attempts to the
     closed port on Windows), so the checker recorded timeouts (HTTP 000)
     instead of a fast 503. The alert still fired, but the exact symptom
     was less clear.
- **What went well:** the alert fired once per run with no duplicate
  notifications; liveness vs readiness showed straight away that the
  database was the problem; the runbook steps were followed in order;
  recovery was verified with the checker and the smoke tests.
- **Corrective action (backlog):** *"Fail fast and verify after config
  changes"*: lower `DB_TIMEOUT` and confirm `/health/ready` answers 503 in
  under 5 seconds when the database is unreachable, and run
  `curl /health/ready` immediately after every `.env` change and restart.
  **Owner:** Cyrene Jane C. Teodocio. Tracked as a GitHub issue in the
  project backlog.
- **Reviewer verification:** solo student; the monitoring evidence was
  checked against the checker logs, `alerts.log`, `laravel.log` and issue #7.
