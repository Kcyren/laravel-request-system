# Release notes

Versions follow `MAJOR.MINOR.PATCH` and are recorded in the `VERSION` file.
Each release is built once by the **Release** workflow. The workflow pins the
app and web images by sha256 digest and records the commit, which goes into
`/health` as `revision`.

## 1.0.0 — first staged release (Laboratory 8)

| Field | Value |
|---|---|
| Candidate version | `1.0.0` |
| Commit SHA | _fill in: `git rev-parse HEAD`_ |
| Artifact | _fill in: `ghcr.io/<owner>/<repo>-app@sha256:…` and `-web@sha256:…`_ |
| Manifest checksum | _fill in: sha256 from `release-manifest.json.sha256`_ |
| CI result | _fill in: link to the passing CI / Release run_ |
| Migration plan | No new migration compared with the Lab 4 schema. `migrate:status` must show all migrations as `Ran`. If any migration is pending, the deploy script stops unless a migration was approved. |
| Approver | _fill in_ |
| Rollback criteria | Any failed required smoke test (S1–S5), especially **S3 request creation**. The trigger is that `STAGE-LIB-08` cannot be created or reopened. |

**Changes**
- Release metadata: `VERSION`, a baked-in `REVISION`, and a `/health` endpoint
  that reports status, version, revision and database reachability.
- Versioned, immutable Docker artifacts, with a GitHub Release workflow and a
  staging deploy workflow that stops at an approval gate.
- Smoke-test, deploy and rollback scripts.
- Lab 9 configuration audit (`php artisan app:config-audit`,
  `config-audit/audit.sh`) and a non-secret baseline.
- Lab 3 hardening: forbidden fields are now rejected through validation, the
  `role` field can no longer be mass-assigned, list results are paginated,
  and search and status filters stay scoped to the user.

## 1.0.1-lab-fault — CONTROLLED FAULTY ARTIFACT (isolated staging only)

Built with `lab_fault=true`. It uses the same schema as 1.0.0 but disables the
request form, so request creation returns HTTP 503 and smoke test S3 fails.
It exists only to practise the rollback and must never be promoted beyond
isolated staging.
