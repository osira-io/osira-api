# Osira API

[![CI](https://github.com/osira-io/osira-api/actions/workflows/ci.yml/badge.svg)](https://github.com/osira-io/osira-api/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/osira-io/osira-api/branch/develop/graph/badge.svg)](https://codecov.io/gh/osira-io/osira-api)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![PHP 8.5.3+](https://img.shields.io/badge/PHP-8.5.3%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000.svg?logo=symfony&logoColor=white)](https://symfony.com/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)

Codecov uploads use GitHub OIDC and do not require a repository token. The
[Codecov GitHub App](https://github.com/apps/codecov) must nevertheless be
installed for `osira-io/osira-api`, and the repository must be activated in
Codecov before its first report can be processed.

Osira API is the Symfony and API Platform control plane for an open-source,
self-hosted monitoring platform. It uses Doctrine ORM and PostgreSQL. A separate
Rust service will handle high-frequency metrics and heartbeats; those concerns
are intentionally outside this repository's current scope.

> [!IMPORTANT]
> Osira API is at an early stage of development. APIs and setup instructions
> may change before the first stable release.

## Requirements

- GNU Make
- Docker with Compose

PHP 8.5.3, Composer, PostgreSQL and FrankenPHP are provided by the development
stack. A compatible local PHP installation remains useful for quality checks,
but is not required to start Osira.

## Getting started

```bash
git clone https://github.com/osira-io/osira-api.git
cd osira-api
make dev
```

`make dev` builds the PHP image, installs dependencies, starts PostgreSQL,
generates missing JWT keys, applies migrations, then serves the API through
FrankenPHP at `http://localhost:8000`. This address redirects to the Swagger UI,
which is also available directly at `http://localhost:8000/api/docs`. The first
administrator can be created from another terminal:

```bash
make console ARGS="osira:user:create-admin"
```

For local configuration overrides, create `.env.local`. Never commit secrets.
Private and public PEM files under `config/jwt/` are ignored by Git. This Docker
configuration is intended for development only; production deployment is not
defined by this repository yet.

For non-interactive administrator provisioning, avoid a plaintext command-line
option and read the password from a protected file or standard input:

```bash
docker compose run --rm -T \
  --volume /secure/path/admin-password:/run/secrets/osira_admin_password:ro \
  app php bin/console osira:user:create-admin \
  --no-interaction \
  --email=admin@example.com \
  --password-file=/run/secrets/osira_admin_password
```

## Control-plane authentication

Authenticate with the administrator account:

```bash
curl --request POST http://localhost:8000/api/auth/login \
  --header 'Content-Type: application/json' \
  --data '{"email":"admin@example.com","password":"REPLACE_ME"}'
```

The returned JWT is valid for one hour. Send it as a Bearer token for protected
control-plane operations such as `GET /api/nodes` and enrollment-token creation.
Swagger UI exposes the same Bearer mechanism through its Authorize button.

## Role-based access control

Control-plane authorization is stored in PostgreSQL and follows this model:

```text
User ── many-to-many ──> Role ── many-to-many ──> Permission
```

Osira defines the permission catalog. Administrators can compose custom roles
from those permissions and assign one or more roles to each user. Permissions
cannot be created, changed, or deleted through the API. The protected Super
Admin role always bypasses individual permission checks, cannot be deleted, and
cannot lose its complete permission set. The last user holding that role cannot
be deleted or have the role removed.

| Category | Permissions |
| --- | --- |
| Users | `users.read`, `users.create`, `users.update`, `users.delete` |
| Access control | `roles.read`, `roles.create`, `roles.update`, `roles.delete`, `permissions.read` |
| Nodes | `nodes.read`, `nodes.update` |
| Node groups | `node_groups.read`, `node_groups.create`, `node_groups.update`, `node_groups.delete` |
| Monitoring | `monitoring_templates.read`, `monitoring_templates.create`, `monitoring_templates.update`, `monitoring_templates.delete`, `item_definitions.read`, `item_definitions.create`, `item_definitions.update`, `item_definitions.delete`, `item_definitions.manage_commands`, `metrics.read`, `incidents.read` |
| Maintenance | `maintenance_windows.read`, `maintenance_windows.create`, `maintenance_windows.update`, `maintenance_windows.delete` |
| Enrollment | `enrollment_tokens.create` |
| Audit | `audit_logs.read` |

The initial system roles are:

| Role | Initial permissions |
| --- | --- |
| Super Admin | Every permission; protected and non-deletable |
| Admin | Every permission |
| Operator | Node read/update, full node-group management, `metrics.read`, `incidents.read`, and maintenance read/create/update |
| Viewer | `nodes.read`, `node_groups.read`, `metrics.read`, `incidents.read`, and `maintenance_windows.read` |

All system roles are non-deletable. Admin, Operator, and Viewer permission
mappings may be adjusted through `PATCH /api/roles/{id}`; running
`osira:rbac:sync` restores the documented catalog and initial mappings. Super
Admin permissions cannot be adjusted.

Users use standard REST endpoints at `/api/users` and `/api/users/{id}`. Roles
use `/api/roles` and `/api/roles/{id}`. The permission catalog is read-only at
`/api/permissions` and `/api/permissions/{id}`. Collection responses use the
same `items` and `metadata` pagination envelope as nodes and node groups.

The RBAC migration preserves existing access: users previously carrying
`ROLE_ADMIN` become Super Admins, while other existing users become Viewers.
Technical `ROLE_USER` remains only for Symfony authentication; Osira business
authorization relies exclusively on permission codes.

Future integrations may add explicit idempotent upsert endpoints such as
`PUT /api/integrations/{source}/users/{externalId}`. No such integration or
upsert behavior is implemented by the standard REST endpoints today.

## Audit logs

Osira records insert, update, removal, association, and dissociation events for
the control-plane `User`, `Role`, `Permission`, `Node`, `NodeGroup`,
`MonitoringTemplate`, `ItemDefinition`, `MaintenanceWindow`, `Incident`, `Agent`,
`AgentCredential`, and `EnrollmentToken` entities. Audit rows use `audit_*` tables in the same
PostgreSQL database and transaction as the corresponding business change.

Authenticated HTTP changes include the user's ULID, e-mail address, client IP,
and firewall context when available. Console changes use the Symfony command
name as their actor. Passwords, technical Symfony roles, token hashes, and
routine `createdAt`/`updatedAt` changes are excluded; the API also removes
sensitive-looking fields as a defensive output safeguard.

`GET /api/audits` is available only with `audit_logs.read`, granted initially
to Super Admin and Admin. Operator and Viewer do not receive it. The endpoint
uses the standard `items`/`metadata` pagination envelope and supports `entity`,
`entityId`, `action`, `actor`, `dateFrom`, and `dateTo` filters. `entity` uses
the short names listed above, `action` accepts `insert`, `update`, `remove`,
`associate`, or `dissociate`, and `actor` matches a user ULID or CLI command
identifier. Dates are inclusive ISO 8601 bounds. The third-party audit viewer
is disabled; audit access remains inside the Osira API and RBAC model.

## Current user context

An authenticated frontend retrieves its current context with `GET /api/me`.
The response contains the user ID, email, locale, lightweight role summaries,
and the effective permission codes calculated by the API:

```json
{
  "id": "01K...",
  "email": "operator@example.com",
  "locale": "en",
  "roles": [
    {"id": "01K...", "name": "Operator", "slug": "operator"}
  ],
  "permissions": [
    "node_groups.read",
    "nodes.read",
    "nodes.update"
  ]
}
```

The permission list is the deduplicated union of every assigned role. Super
Admins receive every known permission code. A frontend can use that list for
presentation decisions such as `can('nodes.update')`; it must never infer
access from a role name such as `role === 'Admin'`. The API remains the source
of truth and rechecks authorization for every protected operation.

Roles and permissions are deliberately not embedded in the JWT as the business
authorization source. Changes made in PostgreSQL therefore take effect on the
next request without waiting for token expiration.

`PATCH /api/me` currently accepts only `{"locale":"en"}`. The supported locale
list is centralized in the application and currently contains only `en`; no
automatic `Accept-Language` selection is performed. Permission codes such as
`nodes.read` are stable technical identifiers and are never translated. A
future localization layer may translate human-facing permission names,
descriptions, and categories without changing those codes.

## Agent enrollment

An enrollment token is single-use, expires after 15 minutes by default, and is
returned in clear text only once:

```bash
curl --request POST http://localhost:8000/api/enrollment-tokens \
  --header 'Authorization: Bearer REPLACE_WITH_USER_JWT' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{}'
```

The agent exchanges it for a permanent credential:

```bash
curl --request POST http://localhost:8000/api/agents/enroll \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "enrollmentToken": "osi_enroll_REPLACE_ME",
    "hostname": "srv-prod-01",
    "os": "linux",
    "architecture": "x86_64",
    "agentVersion": "0.1.0"
  }'
```

Only keyed hashes are persisted. Raw `osi_enroll_` and `osi_agent_` values are
never stored and cannot be retrieved later. Nodes can be listed with
`GET /api/nodes` and read with `GET /api/nodes/{id}`; neither response contains
credentials.

## Nodes and node groups

A **Node** is the logical machine supervised and administered in Osira. An
**Agent** is the technical Osira software installation enrolled on that Node;
users do not create Nodes manually. Once enrollment has created a Node, its
display name, environment, tags, and group memberships can be managed through
`PATCH /api/nodes/{id}`.

A **NodeGroup** is a user-managed logical grouping of Nodes. For example:

```text
Production
├── web-01
├── web-02
└── database-01
```

Groups are available under `/api/node-groups`. They are intended to support
shared monitoring configuration.

## Custom monitoring model

Osira starts with no item, monitoring template, or alert rule. There is no
Linux, Windows, Docker, `system.*`, or `container.*` product catalog. Development
fixtures contain a few explicit examples only and are never part of product
bootstrap.

All effective collection follows one deterministic path:

```text
ItemDefinition -> MonitoringTemplate -> NodeGroup -> Node
```

There is no direct Item-to-Node/Group or Template-to-Node assignment. A Node
without a group carrying an enabled template has zero effective items and its
agent collects nothing. Items contain a user-provided Bash command for Linux,
a PowerShell command for Windows, or both. `item_definitions.manage_commands`
is required in addition to the CRUD permission when commands are created or
changed; only Super Admin and Admin receive it by default. Command diffs are
audited. This mechanism is collection only, not remote action or remediation.

V1 time-series values are float, integer, or boolean (normalized to `0`/`1`).
The legacy `string` enum value remains readable for compatibility but is not an
effective VictoriaMetrics collector type.

## Monitoring metrics read API

Osira reads stored metrics through Symfony and never lets the frontend talk
directly to VictoriaMetrics. The API currently exposes three read-only metrics
endpoints:

- `GET /api/metrics/query`
- `GET /api/metrics/query-range`
- `GET /api/nodes/{id}/metrics`

The two `/api/metrics/*` endpoints require a known Osira `itemKey` and a
concrete `nodeId`. They do not accept arbitrary MetricsQL. Osira resolves the
`ItemDefinition`, checks that it is enabled, metric-compatible, OS-compatible,
and effective through the Node groups, builds a VictoriaMetrics selector, then
returns a stable Osira JSON contract.

The public instant-query contract is:

```json
{
  "nodeId": "01K...",
  "itemKey": "custom.cpu.usage",
  "samples": [
    {
      "metricKey": "custom.cpu.usage",
      "labels": {"device": "cpu0"},
      "timestamp": "2026-08-19T12:00:00+00:00",
      "value": "42.5"
    }
  ]
}
```

The public range-query contract is:

```json
{
  "nodeId": "01K...",
  "itemKey": "custom.disk.usage",
  "series": [
    {
      "metricKey": "custom.disk.usage",
      "labels": {"device": "nvme0n1p1"},
      "points": [
        {"timestamp": "2026-08-19T12:00:00+00:00", "value": "77.1"},
        {"timestamp": "2026-08-19T12:01:00+00:00", "value": "77.4"}
      ]
    }
  ]
}
```

`GET /api/nodes/{id}/metrics` is the node-centric snapshot endpoint. It returns
the current values of every compatible enabled `ItemDefinition` inherited from
the enabled templates assigned to that Node's groups.

## Maintenance windows

Maintenance windows are planned periods during which targeted Nodes are
intentionally under maintenance. They are managed through:

- `GET /api/maintenance-windows`
- `GET /api/maintenance-windows/{id}`
- `POST /api/maintenance-windows`
- `PATCH /api/maintenance-windows/{id}`
- `DELETE /api/maintenance-windows/{id}`

A window has `name`, optional `description`, `startsAt`, `endsAt`, `isEnabled`,
and Node/NodeGroup scopes. API inputs require explicit timezone offsets; dates
are persisted and returned in UTC. A Node is in maintenance when at least one
enabled window has started, has not ended, and targets that Node directly or
through any of its groups. Multiple matching group windows are all effective,
deduplicated by window, and returned in deterministic order by the resolver.

Alert evaluation checks maintenance before reading metrics or evaluating rules
for a Node. Active maintenance suppresses new incidents only. It does not
resolve an existing `FIRING` incident, does not convert `NO_DATA` or
VictoriaMetrics errors into recovery, and does not delete incident history.
When maintenance ends, normal evaluation resumes; if the condition is still
firing and no active incident exists, Osira opens a new incident.

The shared read/write contract for future ingestion is a single generic series:

```text
osira_item_value{node_id="01K...",item_key="custom.nginx.connections",...} 42
```

`item_key` is always a controlled label value, never a metric-name fragment or
user-supplied MetricsQL. Additional non-infrastructure labels are metric
dimensions. `/api/agent/config` includes each effective item in its canonical
ETag and exposes only `execution: {shell, command}` for the Node OS: `bash` on
Linux, `powershell` on Windows. Changing an ineffective other-OS command does
not alter that Node's ETag.

VictoriaMetrics connectivity is configured by environment variables:

- `VICTORIAMETRICS_URL`
- `VICTORIAMETRICS_TIMEOUT`

The development Compose stack includes a local VictoriaMetrics container on
`http://localhost:8428`. This is for development and testing only; the API
still acts strictly as the control-plane read proxy.

Node and node-group collections are paginated with `page` and `itemsPerPage`
(25 items by default, 100 maximum). Collection responses expose the records in
`items` and pagination information in `metadata`:

```json
{
  "items": [],
  "metadata": {
    "currentPage": 1,
    "itemsPerPage": 25,
    "totalItems": 0,
    "totalPages": 0,
    "hasPreviousPage": false,
    "hasNextPage": false
  }
}
```

User accounts and JWTs authenticate administrators and operators of the control
plane. Osira agents never use these accounts: initial registration uses a
single-use `EnrollmentToken`, then the agent uses its own `AgentCredential`.
`POST /api/agents/enroll` therefore intentionally remains public at the user
authentication layer.

## Alert evaluation and incidents

Alert evaluation runs only on the server. `osira:alerts:evaluate` and the
`scheduler_alerts` worker both delegate to the same batch service; the scheduler
frequency is configured with `ALERT_EVALUATION_INTERVAL_SECONDS` (60 seconds by
default). Run the periodic worker with:

```bash
php bin/console messenger:consume scheduler_alerts
```

For every Node, the engine uses only rules returned by
`EffectiveNodeMonitoringResolver::getEffectiveAlertRules()`. It groups those
rules by ItemDefinition and reads their required ranges from the existing
VictoriaMetrics client. User-provided MetricsQL is never accepted.

V1 `requiredOccurrences` means the number of samples satisfying the trigger
comparison within the inclusive `evaluationWindowSeconds` interval ending at
evaluation time. Each VictoriaMetrics dimension series is evaluated separately.
The incident identity is a SHA-256 key over Node ULID, AlertRule ULID, and sorted
public dimension labels, so devices, interfaces, and containers never collapse
into one incident.

An active incident recovers from `gt`/`gte` only below its recovery threshold,
and from `lt`/`lte` only above it. Equality operators use their logical inverse.
Without a recovery threshold, the trigger operator's logical inverse is used.
The boundary is deliberately strict when a recovery threshold exists, providing
hysteresis (for example, `gt 90`, recovery `80`, resolves only below 80).
`NO_DATA`, timeouts, unavailable backends, invalid responses, and invalid value
comparisons never resolve an active incident. PostgreSQL stores only incident
state and the latest observed value; metric samples remain in VictoriaMetrics.

The read-only API exposes `GET /api/incidents` and `GET /api/incidents/{id}` to
holders of `incidents.read`. Collection filters are `status`, `severity`,
`node`, `alertRule`, and `date` (first trigger at or after the timestamp).

## Development checks

Run the application quality gate:

```bash
make qa
```

Use `make ci` to run the strictest local gate, including Composer validation,
GrumPHP, and GitHub Actions linting. Run `make help` to list every available
command.

| Command | Purpose |
| --- | --- |
| `make dev` | Prepare and start the complete local development environment |
| `make dev-setup` | Prepare development without starting the HTTP server |
| `make dev-stop` | Stop the development stack without deleting its data |
| `make dev-logs` | Follow FrankenPHP development logs |
| `make install` | Install locked dependencies and initialize Git hooks |
| `make qa` | Run linting, coding standards, PHPStan, PHPUnit, and security checks |
| `make ci` | Reproduce the complete CI gate locally; requires Docker |
| `make cs-fix` | Automatically fix PHP coding-standard violations |
| `make analyse` | Run PHPStan at the maximum level |
| `make test` | Run PHPUnit; use `ARGS="--filter Name"` to select tests |
| `make coverage` | Generate `var/coverage.xml` with PCOV or Xdebug |
| `make database-up` | Start the local PostgreSQL database |
| `make migrate` | Apply Doctrine migrations |
| `make schema-validate` | Validate Doctrine mapping and the database schema |
| `make jwt-keys` | Generate the ignored JWT signing key pair |
| `make openapi` | Export the API contract to `var/openapi.json` |
| `make console ARGS="about"` | Run a Symfony console command |

GrumPHP installs Git hooks through Composer and runs the relevant checks before
every commit. Commit messages must follow the Conventional Commits format, for
example `feat(api): add host registration`.

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before
opening an issue or pull request. By participating, you agree to follow the
[Code of Conduct](CODE_OF_CONDUCT.md).

For general help, see [SUPPORT.md](SUPPORT.md). Please report vulnerabilities
privately as described in [SECURITY.md](SECURITY.md).

## License

Osira API is licensed under the [GNU Affero General Public License v3.0](LICENSE).
