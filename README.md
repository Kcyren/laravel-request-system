# Laravel Service Request System - Lab 3 & Lab 4

## Overview
This repository contains the implementation of secure request ownership, role-based authorization, server-side data validation, mass assignment protection, Blade escaping, and CSRF protection for Laboratory 3[span_3](start_span)[span_3](end_span), combined with a containerized reproducible environment using Docker and Docker Compose for Laboratory 4[span_4](start_span)[span_4](end_span).

---

## Access Control Matrix (Lab 3)

| Action / Route | Guest | Student (Owner) | Student (Non-Owner) | Admin |
| :--- | :--- | :--- | :--- | :--- |
| `GET /requests` (List) | Redirect to `/login`[span_5](start_span)[span_5](end_span) | View owned only[span_6](start_span)[span_6](end_span) | View owned only[span_7](start_span)[span_7](end_span) | View all[span_8](start_span)[span_8](end_span) |
| `GET /requests/{id}` (Detail) | Redirect to `/login`[span_9](start_span)[span_9](end_span) | HTTP 200 (Allowed)[span_10](start_span)[span_10](end_span) | HTTP 403 Forbidden[span_11](start_span)[span_11](end_span) | HTTP 200 (Allowed)[span_12](start_span)[span_12](end_span) |
| `POST /requests` (Create) | Redirect to `/login`[span_13](start_span)[span_13](end_span) | Create (Own ID)[span_14](start_span)[span_14](end_span) | Create (Own ID)[span_15](start_span)[span_15](end_span) | Create[span_16](start_span)[span_16](end_span) |
| `PATCH /requests/{id}/status` | Redirect to `/login`[span_17](start_span)[span_17](end_span) | HTTP 403 Forbidden[span_18](start_span)[span_18](end_span) | HTTP 403 Forbidden[span_19](start_span)[span_19](end_span) | Allowed (pending/approved/rejected)[span_20](start_span)[span_20](end_span) |

---

## Containerization & Reproducible Environments (Lab 4)

### Services Architecture
The application runs across three multi-container services on an isolated bridge network (`app-network`)[span_21](start_span)[span_21](end_span)[span_22](start_span)[span_22](end_span)[span_23](start_span)[span_23](end_span):
* **`web` (`request-web`)**: Nginx web server mapping host port `8080` to container port `80`[span_24](start_span)[span_24](end_span)[span_25](start_span)[span_25](end_span).
* **`app` (`request-app`)**: PHP-FPM container running the Laravel application logic[span_26](start_span)[span_26](end_span)[span_27](start_span)[span_27](end_span).
* **`db` (`request-db`)**: MySQL 8.0 relational database with persistent storage via `db_data` volume[span_28](start_span)[span_28](end_span)[span_29](start_span)[span_29](end_span).

```text
[ Browser ] ──> [ web (Nginx:8080) ] ──> [ app (PHP-FPM) ] ──> [ db (MySQL:3306) ] ──> [ Volume: db_data ]
