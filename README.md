# Osira API

[![CI](https://github.com/osira-io/osira-api/actions/workflows/ci.yml/badge.svg)](https://github.com/osira-io/osira-api/actions/workflows/ci.yml)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)

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

PHP 8.4, Composer, PostgreSQL, FrankenPHP and Caddy are provided by the Docker
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
FrankenPHP and Caddy at `http://localhost:8000`. The first
administrator can be created from another terminal:

```bash
make console ARGS="osira:user:create-admin"
```

For local configuration overrides, create `.env.local`. Never commit secrets;
use environment variables or Symfony's secrets management in production. Set a
strong `APP_SECRET` and `JWT_PASSPHRASE` before generating the JWT key pair.
Private and public PEM files under `config/jwt/` are ignored by Git.

### Production

Provision `APP_SECRET`, `JWT_PASSPHRASE`, a PostgreSQL password and the JWT key
pair before starting the application. Then run, for example:

```bash
APP_SECRET='replace-me' \
JWT_PASSPHRASE='replace-me' \
POSTGRES_PASSWORD='replace-me' \
SERVER_NAME='api.example.com' \
make prod
```

This builds an immutable production image with PHP's production configuration,
authoritative Composer autoloading and OPcache; applies migrations; warms the
Symfony cache; then starts Caddy and FrankenPHP in worker mode. Caddy manages
HTTPS automatically when `SERVER_NAME` contains a real domain.

Use `make prod-prepare` as a deployment stage when the application container is
started separately by an orchestrator.

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

User accounts and JWTs authenticate administrators and operators of the control
plane. Osira agents never use these accounts: initial registration uses a
single-use `EnrollmentToken`, then the agent uses its own `AgentCredential`.
`POST /api/agents/enroll` therefore intentionally remains public at the user
authentication layer.

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
| `make prod` | Build and start the production FrankenPHP/Caddy stack |
| `make prod-prepare` | Build the production image and apply migrations |
| `make prod-stop` | Stop the production stack without deleting its data |
| `make prod-logs` | Follow FrankenPHP production logs |
| `make install` | Install locked dependencies and initialize Git hooks |
| `make qa` | Run linting, coding standards, PHPStan, PHPUnit, and security checks |
| `make ci` | Reproduce the complete CI gate locally; requires Docker |
| `make cs-fix` | Automatically fix PHP coding-standard violations |
| `make analyse` | Run PHPStan at the maximum level |
| `make test` | Run PHPUnit; use `ARGS="--filter Name"` to select tests |
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
