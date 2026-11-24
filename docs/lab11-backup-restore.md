# Laboratory 11 – Back up and restore the application database

## 1. Starting environment

| Item | Value |
|---|---|
| Starting revision | `3afbe6a` (Lab 10 merged, PR #9) |
| Source database | `laravel_request_lab10_db` (fictional records only), app on http://localhost:8000 |
| Restoration database | `lab11_restore` (new, empty before restore), app copy `C:\lab11-restore-app` on http://localhost:8001 |
| Database engine | MariaDB 10.11.16 (XAMPP), all tables InnoDB |
| Local environment | Windows 11, PHP 8.5, Laravel 13.43.0, Git Bash |
| Roles | Operator (backup, restore): Cyrene Jane C. Teodocio. Reviewer (integrity and access checks): Cyrene Jane C. Teodocio (solo, no partner assigned) |

## 2. Backup, replication and high availability

- **Backup** is a saved copy of the data at one point in time. It is used to
  go back to that point after data loss, a bad change or corruption.
- **Replication** copies every change to another database server almost
  right away. It keeps a second copy up to date, but a mistake (for example
  a deleted record) is also copied, so it does not replace backups.
- **High availability (HA)** means the service keeps running when one part
  fails, for example by switching to a replica automatically. It reduces
  downtime but does not protect against bad data.

## 3. Backup plan

| Item | Proposal |
|---|---|
| Scope | The MySQL database (all tables: `users`, `requests`, `migrations`, `sessions`, `cache`, `jobs`, ...). The app has **no uploaded files**, so no file backup is needed. The protected config (`.env` with `APP_KEY` and DB credentials) is kept separately in a private place, never in Git or in the report. |
| Method | `mysqldump --single-transaction --quick` (consistent InnoDB snapshot without locking; never copy the live data directory) |
| Schedule | Daily at 01:00, plus one before every release |
| Retention | 7 daily + 4 weekly backups; older files deleted |
| Access | Backup folder only for the operator account; outside the Git repository (`.gitignore` blocks `*.sql` and `lab11_backup*`) |
| Restore verification | Restore into a separate database, then check table counts, a known record, readiness and the smoke tests (this lab) |
| Recovery targets | **RPO 24 hours** (accepted data loss, measured in time = at most one day of changes, because backups are daily). **RTO 15 minutes** (target time to restore the service). Agreed before the exercise. |

## 4. Backup record

Source prepared with fictional records. BACKUP-LAB-11 was created by
studenta and approved by the test administrator. (The form was submitted
twice, so #7 is a pending duplicate; the selected record is #8.)

| Baseline (before backup) | Value |
|---|---|
| `users` rows | 3 |
| `requests` rows | 8 |
| Selected record | ID **8**, "HDMI Adapter BACKUP-LAB-11", owner `studenta@example.edu`, status **approved** |

Command (Git Bash, password entered at the prompt; XAMPP local account):

```
mysqldump --single-transaction --quick -u <account> -p laravel_request_lab10_db > lab11_backup.sql
sha256sum lab11_backup.sql
```

| Backup result | Value |
|---|---|
| Start | 2026-11-24 11:38:29 |
| Completed (recovery point) | **2026-11-24 11:38:39** (`-- Dump completed`) |
| Exit code | 0 |
| Location | `C:\lab11-backup\lab11_backup.sql` (outside the repository) |
| Size | 19,956 bytes |
| SHA-256 | `68c98aef39768be57a19c3b7b2b5a51e01ba11e174b4d60ffe1e04353aeded88` |

## 5. Restore log

| No. | Time | Step | Result |
|---|---|---|---|
| 1 | 11:39:51 | Simulated incident (source treated as lost) | – |
| 2 | 11:39:51 | `CREATE DATABASE lab11_restore`, `SHOW TABLES` | Name differs from source; no tables |
| 3 | **11:40:04** | Recovery timer start; `mysql -u <account> -p lab11_restore < lab11_backup.sql` | Exit code 0, no errors |
| 4 | 11:40–11:43 | App copy `C:\lab11-restore-app`, `.env` `DB_DATABASE=lab11_restore`, same `APP_KEY`, `config:clear`, `php artisan serve --port=8001` | Server running on 8001 |
| 5 | 11:43 | Integrity, readiness and smoke tests (below) | All passed |
| 6 | **11:43:43** | Recovery timer stop | – |

## 6. Verification (expected vs actual)

| Check | Expected | Actual | Result |
|---|---|---|---|
| Integrity – `users` | 3 | 3 | PASS |
| Integrity – `requests` | 8 | 8 | PASS |
| Integrity – record #8 | BACKUP-LAB-11, studenta, approved | BACKUP-LAB-11, studenta, approved | PASS |
| Readiness `/health/ready` | HTTP 200 | `{"status":"ready"}` HTTP 200 | PASS |
| S1 health | status ok, version 1.0.0 | ok, 1.0.0 | PASS |
| S2 student login | 302 → requests page 200 | 302, 200 | PASS |
| S3 new request created and reopened | RESTORE-LAB-11 shown | id 9, reopen 200 | PASS |
| S4 admin updates status | PATCH 302, approved | 302 | PASS |
| S5 student cannot update status | 403, status unchanged | 403 | PASS |
| S6 own restored record | #8 shows BACKUP-LAB-11 | 200 | PASS |
| S7 another student's record (#2) | 403 | 403 | PASS |
| Source database unchanged | `requests` = 8 | 8 (restored copy = 9 after S3) | PASS |

## 7. Recovery measurements

| Item | Value |
|---|---|
| RPO / RTO targets | 24 hours / 15 minutes |
| Backup recovery point | 11:38:39 |
| Simulated incident | 11:39:51 |
| Restoration start / end | 11:40:04 / 11:43:43 |
| **Recovery interval** | **3 min 39 s** → meets the 15-minute RTO |
| **Potential data-loss window** | 11:39:51 − 11:38:39 = **1 min 12 s** → meets the 24-hour RPO |

With the proposed daily schedule, the worst case would be just under
24 hours of lost changes, which is still inside the RPO.

## 8. Final release rehearsal

| Item | Result |
|---|---|
| Candidate commit | `3afbe6a` (plus the S7 smoke check on this branch) |
| `php artisan test` | 36 passed (143 assertions), separate in-memory SQLite test database |
| Build command | Not applicable: the app has no Vite/npm build; CSS is plain `public/css/app.css` |
| Dependencies | PHP 8.5, Composer packages from `composer.lock`, MariaDB 10.11 |
| Migration status | All 6 migrations `Ran` on `lab11_restore` |
| Health and smoke on restored app | Readiness 200, S1–S7 PASS |
| Monitoring readiness | Readiness checker (Lab 10) on the restored app: 20/20 HTTP 200, SLO met |
| Recovery readiness | Backup + restore procedure tested, RTO and RPO met |
| Decision | **Proceed** (reviewed by Cyrene Jane C. Teodocio, solo) |

**Monitoring on the restored app (11:45:44–11:47:42):** 20/20 readiness
checks HTTP 200 (100%), 20/20 under 500 ms. Response times were 40 ms to
167 ms, average about 77 ms.

**Bottleneck:** the local app runs on `php artisan serve`, which handles
one request at a time, and every readiness check opens a new MySQL
connection. Sessions and cache are also stored in the database, so each
page needs extra queries. The times jumping from about 40 ms up to 167 ms
show this. With many users at once, requests would wait in a queue behind
each other. For real use the app should run on PHP-FPM with a web server
(like the Docker stack from Lab 4) with several workers.

## 9. Operational readiness checklist

| Item | Evidence | Status |
|---|---|---|
| Deployment instructions | `README.md`, `docs/lab8-staging-release.md`, `scripts/deploy.sh` | PASS |
| Backup schedule and retention | Proposal in section 3 (daily, 7 daily + 4 weekly) | UNVERIFIED (not yet automated) |
| Recovery targets | RPO 24 h, RTO 15 min; measured 1 min 12 s and 3 min 39 s | PASS |
| Access controls – application | Student/admin roles, S5 and S7 403 | PASS |
| Access controls – backup and database | Backup outside Git; local MySQL account has no password | FAIL |
| Monitoring and alerts | `/health/live`, `/health/ready`, checker with one alert (Lab 10) | PASS |
| Runbook | `docs/lab10-monitoring-incident.md` and this document | PASS |
| Assigned owners | Cyrene Jane C. Teodocio for all items (solo) | PASS |
| Original database preserved | Source `requests` still 8 after the exercise | PASS |
| Restored application works | Readiness 200, S1–S7 PASS | PASS |

## 10. Unresolved risks

| Risk | Owner | Next step |
|---|---|---|
| Local MySQL `root` has no password and was used for backup and restore | Cyrene Jane C. Teodocio | Create separate `lab_backup` (read-only) and `lab_restore` accounts with passwords |
| The backup is on the same computer as the database | Cyrene Jane C. Teodocio | Keep an encrypted copy on another drive or cloud storage |
| Backups are manual, not scheduled | Cyrene Jane C. Teodocio | Add a scheduled task that runs the dump daily and deletes old files |
| Readiness can time out instead of a fast 503 when the DB port is wrong | Cyrene Jane C. Teodocio | Issue #8 (corrective action from Lab 10) |
| Double form submission created a duplicate request (#7) | Cyrene Jane C. Teodocio | Disable the submit button after the first click |
| Local `/health` shows revision `unknown` (APP_REVISION not set outside Docker) | Cyrene Jane C. Teodocio | Set APP_REVISION in the release process for every environment |

## 11. Summary

The backup and restore passed. The backup was taken with `mysqldump` in a
consistent way, its checksum was recorded, and it was restored into the
separate database `lab11_restore` without errors. The table counts and the
BACKUP-LAB-11 record matched the baseline, and the restored app passed
readiness and all smoke tests (login, ownership, admin update, restriction,
new request). Recovery took 3 min 39 s (RTO 15 min) and the data-loss window
was 1 min 12 s (RPO 24 h). The original database was not changed. The
release rehearsal passed (36 tests), so the decision is to proceed, with the
risks above tracked.
