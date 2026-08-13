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

- PHP 8.4 or newer
- [Composer](https://getcomposer.org/)
- GNU Make
- Docker with Compose, for the local PostgreSQL database

Docker is also used to reproduce the GitHub Actions lint locally with
`make workflow-lint` or the complete `make ci` target.

## Getting started

```bash
git clone https://github.com/osira-io/osira-api.git
cd osira-api
make install
make database-up
make migrate
make console ARGS="about"
```

For local configuration overrides, create `.env.local`. Never commit secrets;
use environment variables or Symfony's secrets management in production.

## Agent enrollment

An enrollment token is single-use, expires after 15 minutes by default, and is
returned in clear text only once:

```bash
curl --request POST http://localhost:8000/api/enrollment-tokens \
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

> [!WARNING]
> Authentication and administrator identities do not exist yet. Consequently,
> the enrollment-token creation endpoint is not access-controlled in this first
> domain slice. It must be protected when administrative authentication lands.

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
| `make install` | Install locked dependencies and initialize Git hooks |
| `make qa` | Run linting, coding standards, PHPStan, PHPUnit, and security checks |
| `make ci` | Reproduce the complete CI gate locally; requires Docker |
| `make cs-fix` | Automatically fix PHP coding-standard violations |
| `make analyse` | Run PHPStan at the maximum level |
| `make test` | Run PHPUnit; use `ARGS="--filter Name"` to select tests |
| `make database-up` | Start the local PostgreSQL database |
| `make migrate` | Apply Doctrine migrations |
| `make schema-validate` | Validate Doctrine mapping and the database schema |
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
