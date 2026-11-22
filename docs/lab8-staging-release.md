# Laboratory 8 — Controlled staging release

This guide covers how the request system goes from a commit to a versioned
artifact, through an approval gate, onto staging, through smoke tests, and
back to the last known-good version when a release fails.

> Replace every _fill in_ value with your actual data. Use only fictional
> accounts and records. Never paste passwords, `APP_KEY`, tokens or `.env`
> contents into the report.

---

## 1. Starting environment (record)

| Item | Value | Command |
|---|---|---|
| Laravel version | _fill in_ (e.g. Laravel Framework 13.x) | `php artisan --version` |
| Starting commit | _fill in_ | `git rev-parse HEAD` |
| Staging target | _fill in_ (URL from the instructor) | — |
| Current known-good release | _fill in_ (e.g. `1.0.0`) | `curl -s $STAGING_URL/health` |
| Workflow name | **Deploy to staging** (`.github/workflows/deploy-staging.yml`) | — |
| Driver / Reviewer | _fill in_ / _fill in_ (swap before smoke testing) | — |

## 2. How the team uses each environment

| Environment | Purpose | Who / how | Data |
|---|---|---|---|
| **Development** | Write and try changes (`php artisan serve` with XAMPP MySQL, or Docker Compose from Lab 4) | Each partner, on a feature branch | Local fictional data |
| **Test** | Automated checks on every push and PR: feature tests that cover the Lab 3 access matrix, `composer audit`, script and Compose validation | GitHub Actions **CI** | Throwaway in-memory SQLite |
| **Staging** | Production-like rehearsal: the *exact* built artifact, `APP_DEBUG=false`, real web service, MySQL, approval gate and smoke tests | **Deploy to staging** workflow, after the approver accepts | Separate staging DB with fictional records |
| **Production** | Real users | Not used in this lab. The same artifact that passed staging would be promoted, again after approval. | Real data |

**Continuous delivery vs. continuous deployment.** In continuous *delivery*,
every change that passes CI is built into a releasable artifact, but a person
still decides when it goes to production. In continuous *deployment*, the
release happens automatically once the required checks pass, with no manual
gate. This project uses **continuous delivery**: CI and the Release workflow
run automatically, and the **staging** environment's required reviewer must
approve before the deploy job runs.

## 3. Deployment strategies

| Strategy | How it works | One requirement | One trade-off |
|---|---|---|---|
| **Rolling** | Instances are replaced with the new version a few at a time. | At least two instances behind a load balancer, and old and new versions that both work with the same schema. | Both versions serve traffic during the rollout, and rolling back is another gradual rollout. |
| **Blue-green** | A complete second environment ("green") runs the new version, and traffic switches over from "blue" all at once. | Double the capacity, plus a router or load balancer that can switch traffic quickly. | It costs more, and the shared database must stay compatible with both colours. |
| **Canary** | A small share of users or traffic goes to the new version first and grows as metrics stay healthy. | Traffic splitting plus good monitoring and metrics to judge the canary. | It is the most complex to operate, and some users do see a faulty release. |

**What our workflow supports:** a single staging instance with **in-place
replacement** (recreate). `scripts/deploy.sh` replaces only the `app` and `web`
containers with the approved images. The `db` service and its `db_data` volume
are left alone. To roll back, run the same workflow again with the previous
known-good version. This is the "single staging instance" model in the lab
handout. It causes a short interruption and has no traffic splitting.

## 4. Release checklist

| # | Check | Evidence | ✔ |
|---|---|---|---|
| 1 | Candidate version set in `VERSION` and tagged `vX.Y.Z` | `cat VERSION`, `git tag` | ☐ |
| 2 | Commit SHA recorded | `git rev-parse HEAD` | ☐ |
| 3 | CI passed for that commit (tests + `composer audit`) | CI run link | ☐ |
| 4 | Release workflow built the artifact; image digests and manifest sha256 recorded | Release run summary + `release-manifest.json.sha256` | ☐ |
| 5 | Release notes updated (`CHANGELOG.md`) | file / PR | ☐ |
| 6 | Migration plan decided (none / approved migration with recovery point) | `migrate:status` output | ☐ |
| 7 | Staging verified: URL, database, web service, `.env.staging` present (not shown), `APP_DEBUG=false`, same `APP_KEY` as before | audit output (`debug_off PASS`, `app_key_present PASS`) | ☐ |
| 8 | Approver named, and not the driver | — | ☐ |
| 9 | Smoke tests S1–S5 defined, with fictional accounts available | `scripts/smoke-test.sh` | ☐ |
| 10 | Rollback criteria and known-good version recorded | this table | ☐ |

**Rollback criteria (concrete trigger):** roll back when any required smoke
test fails, and in particular when **S3 (request creation)** fails because the
`STAGE-LIB-08` request cannot be created or reopened, or when `/health` reports
a version other than the approved one.

## 5. Preparing staging (one time)

**Staging target used by this team:** the team PC running Docker Desktop and
a GitHub **self-hosted runner** with the label `staging`. Staging is the Compose
project `lrs-staging`, published only on `http://127.0.0.1:8081`. It is fully
separate from the Lab 4 stack on port 8080, with its own database volume
`lrs-staging_db_data` and its own `APP_KEY`.

1. The staging folder `STAGING_DIR` (`C:\lrs-staging`) is outside the repository.
   It holds these private, never-committed files:
   - `.env.staging` — from `.env.staging.example`, with `APP_ENV=staging`,
     `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8081`,
     `SESSION_SECURE_COOKIE=false` (no HTTPS on a local PC), the database
     credentials, the fictional seed passwords, and **one `APP_KEY` that stays
     the same across every release and rollback**.
   - `.env.db` — from `.env.db.example` (database `laravel_request_staging_db`).
   - `.releases/` — the release records (`current`, `previous`, `known-good`,
     `history`, `deploy.log`), written by `scripts/deploy.sh`.
2. Verify the target: `curl http://127.0.0.1:8081/health` (URL and database),
   and `docker compose -p lrs-staging ... ps` (the web, app and db services are healthy).
3. The `web` container (Nginx) is the staging web service.
   `php artisan serve` is for local development only.
4. Register the runner: **Settings → Actions → Runners → New self-hosted
   runner → Windows**. Follow the commands GitHub shows and add the label
   `staging` when `config.cmd` asks. Keep `run.cmd` open while deploying.
5. **Settings → Environments → New environment `staging`**:
   - **Required reviewers:** the designated approver. A solo student records
     this limitation in the report.
   - **Variables:** `STAGING_URL=http://127.0.0.1:8081`, `STAGING_DIR=C:/lrs-staging`.
   - **Secrets:** `SMOKE_STUDENT_EMAIL`, `SMOKE_STUDENT_PASSWORD`,
     `SMOKE_ADMIN_EMAIL`, `SMOKE_ADMIN_PASSWORD`. Use the fictional seed
     accounts; the passwords are in `C:\lrs-staging\.env.staging`.
6. The first deploy to an empty database needs the initial schema, so the
   deploy script stops unless **run_migrations** was approved. Seed the
   fictional accounts once:
   `docker compose -p lrs-staging -f compose.yaml -f compose.staging.yaml exec app php artisan db:seed --force`

**If your instructor provides a remote staging server instead**, run the same
`scripts/deploy.sh` on that host, either through its own self-hosted runner or
by manual promotion with recorded approval.

## 6. Release procedure

```text
1. Merge the reviewed PR to main  ->  CI passes
2. git tag v1.0.0 && git push origin v1.0.0
       -> "Release" workflow: CI again, build app+web images, push to GHCR,
          write release-manifest.json (+ .sha256)
3. Actions -> "Deploy to staging" -> Run workflow
       version=1.0.0  run_migrations=false  mark_known_good=true  reason="Lab 8 release"
4. Job "prepare" prints the approval request (version, commit, digests, CI link,
   target, migration plan)
5. Job "deploy" shows  "Waiting for review: staging needs approval"   <- screenshot
6. Approver checks CI result, artifact digest, target, migration plan -> Approve
   (GitHub records approver + timestamp)                               <- screenshot
7. deploy.sh: pull exact digests -> migrate:status -> replace app+web ->
   /health shows version 1.0.0 and revision = commit                   <- log
8. smoke-test.sh S1-S5 against STAGING_URL                             <- log/screens
```

**Without platform approvals:** if your plan has no environment approvals,
the approver records the decision in the PR or issue (name, decision,
timestamp, artifact digest). The instructor-controlled manual promotion then
runs on the host:

```sh
sh scripts/deploy.sh --version 1.0.0 --app-ref <app@sha256> --web-ref <web@sha256> --known-good
```

### Migration decision

Run `php artisan migrate:status` in the approved service context:

```sh
docker compose -p lrs-staging -f compose.yaml -f compose.staging.yaml exec app php artisan migrate:status
```

`deploy.sh` also runs it with the new artifact before replacing anything.

- **All migrations `Ran`**: no schema change is needed (expected for 1.0.0 and 1.0.1-lab-fault).
- **Pending migration**: the release must have been approved with
  `run_migrations=true`. The script then writes a recovery point
  (`backups/predeploy-<version>-<time>.sql.gz`) before running
  `migrate --force`. The change must also stay compatible with the
  known-good application, so a rollback still works on the new schema.

## 7. Smoke tests (record expected vs. actual)

`scripts/smoke-test.sh` runs these tests. The workflow runs it automatically,
or you can run it manually with exported variables:

| ID | Test | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|
| S1 | Health and revision | `/health` → `status=ok`, `version`=release, `revision`=commit | | | Fig. |
| S2 | Student login | Test student signs in and reaches **My Requests** | | | Fig. |
| S3 | Request creation | `HDMI Adapter STAGE-LIB-08` saved and reopened; request ID = ____ | | | Fig. |
| S4 | Admin status update | Admin sets the request to `approved` | | | Fig. |
| S5 | Student restriction | Student PATCH to `/status` → **403**; status unchanged | | | Fig. |

## 8. Faulty release and rollback

1. With instructor approval, build the faulty artifact. Go to Actions →
   **Release** → Run workflow, then set `version=1.0.1-lab-fault` and
   `lab_fault=true`. Local alternative:
   `sh scripts/build-release.sh 1.0.1-lab-fault fault`.
2. Deploy `1.0.1-lab-fault` through **Deploy to staging** (approval gate as
   before). Do not set `mark_known_good`.
3. **S3 fails**: the request form shows "temporarily unavailable" and POST
   returns 503. Record the failing smoke test and the **time of the rollback
   decision**.
4. Redeploy the known-good release through the same workflow. Use
   `version=1.0.0`, `verify_request_id=<the STAGE-LIB-08 ID>`, and
   `reason="Rollback: S3 failed on 1.0.1-lab-fault"`.
5. Confirm that `/health` shows `1.0.0` again and that S1–S5 pass. **S6**
   confirms that the same `STAGE-LIB-08` request ID is still present.

| Item | Value |
|---|---|
| Approved (known-good) version | _1.0.0_ |
| Faulty version | _1.0.1-lab-fault_ |
| Failing test / time | _S3 request_creation — HH:MM UTC_ |
| Rollback decision time / by | _fill in_ |
| Restored version (health) | _1.0.0_ |
| Post-rollback smoke results | _S1–S6 PASS_ |
| STAGE-LIB-08 request ID before / after | _fill in_ / _same_ |

### Why the record survives

The application artifact (the `app` and `web` images) is **stateless**.
Requests live in MySQL, whose files sit in the named Docker volume
`db_data`, attached to the separate `db` service. A deploy or rollback
recreates only `app` and `web`. It does not recreate `db`, and it never
runs `migrate:fresh`, `migrate:rollback` or `down -v`. The schema and the
records therefore stay as they were, and the restored 1.0.0 code reads the
same rows. This is safe because 1.0.1-lab-fault used **the same schema**.

### Database recovery (a separate procedure, not done by the rollback)

Rolling back the application does **not** undo data or schema changes. If a
release had damaged data or applied an incompatible migration:

1. Stop writes: put the app in maintenance mode (`php artisan down`) or stop `app` and `web`.
2. Pick the recovery point (`backups/predeploy-*.sql.gz`, or the instructor's
   snapshot) and check its checksum and timestamp.
3. Restore it into the staging database (or into a fresh volume first, to verify it):
   `gunzip -c backups/<file>.sql.gz | docker compose ... exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'`
4. Run `migrate:status` and check that it matches the schema the known-good
   application expects.
5. Deploy the known-good artifact, run the smoke tests, and record any data
   written after the recovery point, because that data is lost.

A Docker volume is **not** a backup. It survives container replacement, but
not deletion (`down -v`), corruption, or a bad migration.

## 9. Evidence list for the report

1. Release checklist and release notes (sections 4 and `CHANGELOG.md`): version, commit SHA, digests or checksum, CI result.
2. Workflow run showing **Waiting for review**, then the approval (approver and timestamp), then the deploy log with the deployed revision.
3. Smoke-test log (`smoke-results/*.log`, attached to the workflow run) and numbered screenshots, including the `STAGE-LIB-08` request ID.
4. Rollback evidence: the failed S3, the decision time, the restored `/health` version, repeated smoke tests including S6, and the database recovery explanation above.
5. Reviewer verification: the reviewer compares this report with the workflow log and records agreement.
