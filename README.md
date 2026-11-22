# Laravel Request System

A Laravel web application where students submit equipment and service
requests and an administrator reviews them. It is built across the DevOps
and Security laboratories:

| Lab | Topic |
|---|---|
| 1 | Laravel project, MySQL, Git/GitHub, DevOps workflow |
| 2 | `requests` data model and migration |
| 3 | Secure request access (login, ownership, policy, validation, CSRF) through reviewed changes |
| 4 | Reproducible Docker container environment |
| 8 | Controlled staging release: versioned artifact, approval gate, smoke tests, rollback |
| 9 | Configuration audit, idempotent configuration task, drift correction |

- **Student:** Cyrene Jane C. Teodocio
- **Course / Year / Section:** BSIT 4-3
- **Repository:** https://github.com/Kcyren/laravel-request-system
- **Deployed / staging URL:** _fill in if available_

---

## 1. Software requirements

| Software | Version used |
|---|---|
| PHP | 8.3+ (local XAMPP: 8.5; containers: 8.4) with `pdo_mysql`, `mbstring`, `openssl` |
| Composer | 2.x |
| MySQL / MariaDB | XAMPP MySQL (local) · MySQL 8.4 (containers) |
| Laravel | 13.x (`php artisan --version`) |
| Git | 2.x |
| Docker Desktop / Engine + Compose | Compose v2.24+ (Labs 4, 8, 9) |

## 2. Local installation (Lab 1)

```sh
git clone https://github.com/Kcyren/laravel-request-system.git
cd laravel-request-system
composer install
cp .env.example .env          # then set DB_USERNAME / DB_PASSWORD privately
php artisan key:generate
```

**Database name:** `laravel_request_system_db`

1. Start Apache and MySQL in XAMPP.
2. Create the database in phpMyAdmin: **New → `laravel_request_system_db` → utf8mb4_unicode_ci**.
3. Build the schema and the fictional accounts:

```sh
php artisan migrate
php artisan db:seed          # 2 fictional students + 1 administrator (trusted setup)
php artisan serve            # http://127.0.0.1:8000  (local only)
```

**Database import instructions.** The repository intentionally contains **no
`.sql` export**. The schema is recreated with `php artisan migrate`. If your
instructor gives you an export, import it in phpMyAdmin (**Import → choose
file → Go**) into `laravel_request_system_db`. Never commit it.

**Secret handling.** `.env` and all real `.env.*` files are git-ignored.
`.env.example`, `.env.container.example`, `.env.db.example` and
`.env.staging.example` contain variable names and placeholders only.

### DevOps workflow (summary)

| Stage | Main activity | Tool | Responsible | Basic security control | Expected output |
|---|---|---|---|---|---|
| Plan | User stories, issues | GitHub Issues | Both partners | Access rules written as acceptance criteria | Issue with acceptance checks |
| Code | Feature branch changes | VS Code, Git | Driver | No secrets in code; `.gitignore` | Commits on `feature/*` |
| Build | Dependencies, image build | Composer, Docker | Driver / CI | `composer.lock`, `.dockerignore` | Versioned images |
| Test | Feature tests, audits | PHPUnit, `composer audit`, GitHub Actions | CI / Reviewer | Required checks before merge | Green CI run |
| Release | Tag, artifact manifest | Release workflow, GHCR | Driver | Immutable digest, checksum | `release-manifest.json` |
| Deploy | Promote to staging | Deploy workflow + approval | Approver / Driver | Environment approval gate, `APP_DEBUG=false` | Running approved revision |
| Operate | Health and smoke tests, rollback | `/health`, `smoke-test.sh` | Reviewer | Fictional data only | Smoke-test log |
| Monitor | Config audit, drift | `audit.sh`, `app:config-audit` | Reviewer | Least privilege, TLS, secrets presence-only | Audit report |

## 3. Request data model (Lab 2)

Migration: `database/migrations/2026_11_22_021137_create_service_requests_table.php`
creates the **`requests`** table. `down()` drops it.

| Field | Type | Constraint | Purpose |
|---|---|---|---|
| `id` | bigint unsigned | PK, auto-increment | Unique request number |
| `user_id` | bigint unsigned | FK → `users.id`, cascade on delete | Owner (added in Lab 3) |
| `requester_name` | varchar(100) | required | Person submitting the request |
| `requester_email` | varchar(255) | required | Contact address |
| `item_name` | varchar(150) | required | Requested item or service |
| `quantity` | int unsigned | required, ≥ 1 (validation) | Requested quantity |
| `purpose` | text | required | Reason for the request |
| `status` | varchar(20) | default `pending` | `pending` / `approved` / `rejected` |
| `created_at`, `updated_at` | timestamp | nullable | Creation and update times |

`quantity` is unsigned, which still allows `0`, so the application rejects
values below 1, because a request for zero items is meaningless. A new
request always starts as `pending` because nobody has reviewed it yet.

```sh
php artisan make:migration create_requests_table   # (how it was created)
php artisan migrate
php artisan migrate:status
```

**Verify:** in phpMyAdmin, open `laravel_request_system_db → requests →
Structure`, then run
`SELECT id, requester_name, item_name, quantity, status FROM requests;`.
A row inserted without a status shows `pending`.

**User stories**
1. *As a requester, I want to submit an item, quantity and purpose so that
   staff know what I need and why.* Acceptance criteria: a saved request has
   quantity ≥ 1, and a new request has status `pending`.
2. *As a staff reviewer, I want to change a request's status so that the
   requester knows the decision.* Acceptance criteria: only `pending`,
   `approved` and `rejected` are accepted, and `updated_at` changes after an
   update.
3. *As a record keeper, I want every request to keep its requester, item and
   timestamps so that I can audit the request history.* Acceptance criteria:
   every row has a requester name and email, and `created_at` is set
   automatically.

## 4. Secure request access (Lab 3)

**Fictional accounts** (local seed only; passwords are set by the seeder and
are not shown here): `studenta@example.edu` (Student A),
`studentb@example.edu` (Student B), `admin@example.edu` (Administrator).
The admin role is assigned only by the seeder (trusted setup). `role` cannot
be mass-assigned.

**Routes** (all except login and health require `auth`):

| Method | URI | Action | Rule |
|---|---|---|---|
| GET | `/login`, POST `/login` | AuthController | guests only, throttled |
| GET | `/requests` | `index` | students: own records only (`where user_id`), including search, status filter and pagination; admin: all |
| GET | `/requests/{id}` | `show` | owner or administrator (`Gate::authorize('view')`) |
| POST | `/requests` | `store` | students only. `item_name` required, string, ≤150; `quantity` required, integer, ≥1; `purpose` required, string, ≤2000; `user_id`, `status`, `is_admin`, `role` **prohibited**. Owner, name, email and `pending` are set on the server. |
| PATCH | `/requests/{id}/status` | `updateStatus` | administrator only (`Gate::authorize('updateStatus')` before any write); status ∈ pending/approved/rejected |
| GET | `/requests/create` | `create` | students only (new-request page) |
| GET | `/account` | AccountController@show | own profile only |
| PUT | `/account/password` | AccountController@updatePassword | current password required; new password ≥ 8 chars with letters and numbers; hashed; throttled |
| GET | `/accounts` | AccountController@index | administrator only, read-only (roles are never editable in the UI) |
| GET | `/health` | HealthController | public, stateless, non-secret status, version, revision |

**Denial response:** another student's record returns **403 Forbidden**
(policy denial), consistently for viewing and for status updates.

**Other controls:** escaped Blade output (`{{ }}`) everywhere, `@csrf` on all
forms with `@method('PATCH')`, Eloquent only (no raw SQL concatenation, no
`$request->all()`), and password hashing through the `hashed` cast.

**File responsibilities:**

| File | Maintainer |
|---|---|
| `app/Policies/ServiceRequestPolicy.php` | _Reviewer_ |
| `app/Http/Controllers/*` | _Driver_ |
| `routes/web.php` | _Driver_ |
| `resources/views/*` | _Driver_ |
| `tests/Feature/*` | _Reviewer_ |

See also `.github/CODEOWNERS`.

**Testing:**

```sh
php artisan test        # automated T01–T08, T10 + health/fault tests
composer audit          # dependency audit
```

T09 (missing or invalid CSRF token returns **419**) must be tested against
the live app, because Laravel disables CSRF verification in automated tests.
For example, remove the `_token` field in the browser dev tools and submit the
form. An exposed secret must be **revoked or rotated**, then removed from the
repository and its history. Deleting the file alone is not enough.

## 5. Docker environment (Lab 4)

```text
Browser ──http://127.0.0.1:8080──▶ web (Nginx, unprivileged, serves public/ only)
                                     │ FastCGI :9000   [frontend + backend networks]
                                     ▼
                                   app (PHP-FPM 8.4, user www-data)
                                     │ TCP 3306        [backend network, internal]
                                     ▼
                                   db (MySQL 8.4) ── volume db_data
```

| Service | Purpose | Connection | Configuration |
|---|---|---|---|
| `web` | Serves `public/` and forwards PHP requests to `app` | Host `127.0.0.1:8080` → container 8080; `app:9000` | `docker/nginx/default.conf`, health `/nginx-health` |
| `app` | Runs Laravel with PHP-FPM | `db:3306` on the private network | `.env.container`, `docker/php/*`, FPM ping health check |
| `db` | MySQL database | No published port | `.env.db`, named volume `db_data`, `mysqladmin ping` health check |

The `Dockerfile` is multi-stage. The `vendor` stage installs PHP dependencies
from `composer.lock` with Composer. The `app` stage is a small PHP-FPM
runtime without build tools. The `web` stage is Nginx with only `public/`.
The final images contain no compilers, Composer cache or secrets. The views
use no Vite assets, so no Node stage is needed.

```sh
cp .env.container.example .env.container   # fill in privately; DB_HOST=db
cp .env.db.example .env.db                 # same DB name/user/password
docker compose run --rm --no-deps app php artisan key:generate --show   # paste into APP_KEY once
#   (if the image isn't built yet: run  docker compose build  first)

docker compose config --quiet
docker compose up -d --build
docker compose ps
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed --force   # needs SEED_* passwords outside local
```

Open http://127.0.0.1:8080. The container database
`laravel_request_container_db` is separate from the XAMPP database.

**Persistence test:**

```sh
docker compose ps -q app                                  # container ID before
docker compose up -d --force-recreate --no-deps app
docker compose restart web
docker compose ps -q app                                  # new ID; request still there
docker compose down && docker compose up -d               # still there
```

Never use `docker compose down -v`, because it deletes `db_data`. A volume
is not a backup: it does not protect against deletion, corruption or a bad
migration.

**Reproduction by the reviewer** (separate project name, port and volume):

```sh
COMPOSE_PROJECT_NAME=lrs-review WEB_PORT=8090 docker compose up -d --build
```

**Security:** `docker compose exec app id` shows `www-data`. The containers
run with `no-new-privileges` and `cap_drop: ALL`. MySQL has no host port. The
web service binds only `127.0.0.1`. Credentials stay in runtime env files and
are never placed in images or build arguments, because anyone who can pull
an image can read its layers.

## 6. Staging release (Lab 8)

Full procedure, concepts and evidence tables:
**[docs/lab8-staging-release.md](docs/lab8-staging-release.md)**.
Release notes are in **[CHANGELOG.md](CHANGELOG.md)**.

| Piece | File |
|---|---|
| Version | `VERSION` (plus `REVISION` baked into the image) |
| Health check | `GET /health` → `status`, `version`, `revision`, `checks.database` |
| CI (required checks) | `.github/workflows/ci.yml` |
| Versioned artifact | `.github/workflows/release.yml` (GHCR images pinned by digest, `release-manifest.json` + sha256) |
| Approval gate and deploy | `.github/workflows/deploy-staging.yml` (environment `staging` with required reviewers) |
| Host scripts | `scripts/deploy.sh`, `scripts/rollback.sh`, `compose.staging.yaml` |
| Smoke tests | `scripts/smoke-test.sh` (S1 health and revision, S2 student login, S3 `STAGE-LIB-08` creation, S4 admin update, S5 student restriction, S6 record still present) |
| Faulty artifact | Release workflow with `lab_fault=true` (e.g. `1.0.1-lab-fault`, which disables the request form; same schema) |

```sh
git tag v1.0.0 && git push origin v1.0.0         # Release workflow builds 1.0.0
# Actions → Deploy to staging → version=1.0.0 → wait for approval → smoke tests
# Faulty: Release (1.0.1-lab-fault, lab_fault) → Deploy → S3 fails → Deploy 1.0.0 again
```

Local rehearsal without a registry:
`sh scripts/build-release.sh 1.0.0`, then
`sh scripts/deploy.sh --version 1.0.0 --app-ref laravel-request-system-app:1.0.0 --web-ref laravel-request-system-web:1.0.0 --known-good`,
using `.env.staging` and `.env.db` on your machine with `APP_URL=http://127.0.0.1:8081`.

**Recovery result:** _fill in after the lab: faulty version, the failed test,
the restored version, and the STAGE-LIB-08 ID before and after._

## 7. Configuration audit (Lab 9)

Details: **[docs/lab9-config-audit.md](docs/lab9-config-audit.md)**

```sh
php artisan app:config-audit                                 # app-context checks vs baseline
sh config-audit/audit.sh --base-url https://staging.example  # + docroot, ports, MySQL exposure, TLS
sh config-audit/permission-task.sh ./lab9-permissions-demo 750   # run twice (idempotence)
```

The non-secret baseline is `config-audit/baseline.example`. The output shows
`PASS`, `FAIL` or `UNVERIFIED` for each check, and the script exits with
status 1 when a required check fails. Secrets are reported as present or
absent only.

## 8. Never commit

`.env`, `.env.container`, `.env.db`, `.env.staging`, keys, tokens, `*.sql`
exports, `backups/`, `bootstrap/cache/config.php`, and `smoke-results/`
logs. All of these are covered by `.gitignore` and `.dockerignore`.
