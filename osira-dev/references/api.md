# Osira API

Use this reference for work in `osira-api`.

## Stack

- PHP 8.4+
- Symfony 8.1
- API Platform
- Doctrine ORM
- PostgreSQL 16 as the target database
- LexikJWTAuthenticationBundle
- DH AuditorBundle 7.2.1
- PHPUnit
- PHPStan level max
- GrumPHP

## Current Feature-First Shape

The repository follows feature-first organization under `src/`:

- `Agent`
- `Audit`
- `Auth`
- `Enrollment`
- `Node`
- `NodeGroup`
- `Rbac`
- `Shared`
- `User`

Features generally use `Domain`, `Application`, `Infrastructure`, and
`Presentation` only when needed. Keep classes inside their feature instead of
reintroducing broad global folders such as `Entity`, `Dto`, `Repository`, or
`Security` when the code clearly belongs to one feature.

## Layer Rules

- `Presentation -> Application -> Domain`
- Infrastructure contains technical details.
- Domain must not depend on HTTP, OpenAPI, API Platform, or frontend concerns.
- Stay pragmatic; avoid academic layering that adds no value.

## Control Plane Model

Symfony is the control plane for:

- users
- authentication
- roles and permissions
- nodes
- node groups
- enrollment
- configuration
- audit trails
- future control-plane features such as alerting or incidents

High-frequency metrics and heartbeats do not belong in the Symfony API.

## Main Domain Model

- `User` many-to-many `Role`
- `Role` many-to-many `Permission`
- `Node` one-to-many `Agent`
- `Node` many-to-many `NodeGroup`
- `EnrollmentToken` for initial agent enrollment
- `AgentCredential` for the permanent agent credential

## Enrollment Flow

1. An admin creates an `EnrollmentToken`.
2. The agent calls `POST /api/agents/enroll`.
3. Symfony validates the token and creates `Node`, `Agent`, and
   `AgentCredential`.
4. Heartbeat and metrics are handled later by the Rust data plane.

Keep enrollment in the API. Do not move heartbeat or metrics processing into
Symfony.

## API Contracts

- The API is an independent product.
- `osira-web` is a client of the API, not its owner.
- Preserve standard REST semantics:
  - `POST` collection to create
  - `GET` to read
  - `PATCH` item for partial update
  - `DELETE` item to delete
- Do not create ambiguous create-or-update endpoints.
- If integrations later need upserts, keep them explicit and idempotent.

## RBAC

- Permissions are the stable system catalog.
- Roles are configurable groups of permissions.
- Users can hold multiple roles.
- Avoid business authorization based on static role names such as `ROLE_ADMIN`.
- Use stable permission codes such as `nodes.read`, `nodes.update`,
  `users.create`, and `audit_logs.read`.
- Permission codes are not translated.
- Frontend and API both rely on those codes.
- Protect the Super Admin role and the last Super Admin user.

## Current User Contract

`GET /api/me` returns the current user context, including:

- `id`
- `email`
- `locale`
- role summaries
- effective permission codes

Frontend authorization checks should look like `can('nodes.update')`. The API
remains the source of truth for every protected operation.

## Locale

- `en` is the only supported locale currently.
- Permission codes stay technical and are never translated.

## Security

- Never persist raw secrets.
- Never expose password hashes, token hashes, secret hashes, or credential
  material.
- Never log credentials.
- Do not use dynamic permissions inside the JWT as the source of truth.
- JWT represents identity; current authorization state comes from the database.

## Audit

- Audit uses DH AuditorBundle with the viewer disabled.
- `GET /api/audits` is protected by `audit_logs.read`.
- The PostgreSQL-specific audit reader uses `UNION ALL` and pagination.
- Never audit or expose secret material.

## Database and Migrations

- PostgreSQL 16 is the target database.
- Do not validate a PostgreSQL-sensitive change only on SQLite.
- Keep Doctrine migrations incremental.
- Do not rewrite already-applied migrations without an explicit reason.
- Preserve data during schema evolution.
- Validate schema consistency after ORM changes.

## Docker

This repository contains only the API development stack:

- FrankenPHP
- PostgreSQL

Do not introduce production infrastructure assumptions or a global Caddy setup
from this repo alone.
