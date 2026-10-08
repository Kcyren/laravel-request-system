# Laravel Service Request System - Lab 3 Security Hardening
## Overview
This repository contains the implementation of secure request ownership, role-based authorization, server-side data validation, mass assignment protection, Blade escaping, and CSRF protection for Laboratory 3.
## Access Control Matrix

| Action / Route | Guest | Student (Owner) | Student (Non-Owner) | Admin |
| :--- | :--- | :--- | :--- | :--- |
| `GET /requests` (List) | Redirect to `/login` | View owned only | View owned only | View all |
| `GET /requests/{id}` (Detail) | Redirect to `/login` | HTTP 200 (Allowed) | HTTP 403 Forbidden | HTTP 200 (Allowed) |
| `POST /requests` (Create) | Redirect to `/login` | Create (Own ID) | Create (Own ID) | Create |
| `PATCH /requests/{id}/status` | Redirect to `/login` | HTTP 403 Forbidden | HTTP 403 Forbidden | Allowed (pending/approved/rejected) |

## Authorization & 403 Design Decision
When an unauthorized user attempts to view a request owned by another user or an unauthorized status change is attempted, the system immediately halts the request and returns **HTTP 403 Forbidden** via `Gate::authorize()`. This explicitly denies access without leaking private payload data or permitting privilege escalation.
## Registered Routes
* `GET /login` & `POST /login` - Authentication entrypoints
* `GET /requests` - Scoped index route (`auth` middleware)
* `POST /requests` - Secure store route (`auth` middleware, server-assigned attributes)
* `GET /requests/{serviceRequest}` - Policy-protected show route (`auth` middleware)
* `PATCH /requests/{serviceRequest}/status` - Admin-only status modification route (`auth` middleware)
