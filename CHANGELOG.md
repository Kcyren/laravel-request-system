# Release notes

Versions follow `MAJOR.MINOR.PATCH` and are recorded in the `VERSION` file.
Each release is built once by the **Release** workflow. The workflow pins the
app and web images by sha256 digest and records the commit, which goes into
`/health` as `revision`.

## 1.0.0 — first staged release (Laboratory 8)

| Field | Value |
|---|---|
| Candidate version | `1.0.0` (tag `v1.0.0`) |
| Commit SHA | `d193d37d69cd63d924ef13b7541f5459e1c893eb` |
| App artifact | `ghcr.io/kcyren/laravel-request-system-app@sha256:4bd5814c2c5b15f50d23732610259ca64972e0546d8fe7d68e410520b35ccfb3` |
| Web artifact | `ghcr.io/kcyren/laravel-request-system-web@sha256:a83c0c894944581f5a56d7f2dd39a01e8d1b990ffd1177230d63199ba51c22c4` |
| Manifest checksum (sha256) | `a341b65eb666b4ef6156cb2d8c07c8f08da76a2f7486800e80f782940f9a06e3` |
| CI result | Passed: Release run https://github.com/Kcyren/laravel-request-system/actions/runs/44996816728 |
| Migration plan | No new migration compared with the Lab 4 schema. `migrate:status` showed all migrations as `Ran`, so no schema change was required. |
| Approver | Kcyren (solo student; approval recorded on the `staging` environment gate) |
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
