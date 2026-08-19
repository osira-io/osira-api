# Contributing to Osira API

Thank you for helping improve Osira API. Contributions of code,
documentation, tests, bug reports, and design feedback are welcome.

## Before you start

- Search existing issues and pull requests to avoid duplicate work.
- Open an issue before starting a large change so the approach can be agreed
  on early.
- Do not open a public issue for a security vulnerability. Follow
  [SECURITY.md](SECURITY.md) instead.
- Follow the [Code of Conduct](CODE_OF_CONDUCT.md) in all project spaces.

## Local setup

You need PHP 8.5.3 or newer, Composer, and GNU Make. Docker is optional, but is
required to run the complete local CI target.

```bash
git clone https://github.com/osira-io/osira-api.git
cd osira-api
make install
make console ARGS="about"
```

Store local configuration in `.env.local`. Never commit credentials, tokens,
private keys, production data, or other secrets.

## Making a change

1. Create a focused branch from the default branch.
2. Keep the change small enough to review and avoid unrelated refactors.
3. Add or update tests when behavior changes.
4. Update documentation when configuration, APIs, or user-visible behavior
   changes.
5. Run the project checks locally.

```bash
make qa
```

Before opening a pull request, run the complete gate when Docker is available:

```bash
make ci
```

Run `make cs-fix` before committing to automatically fix coding-standard
violations. Use `make test ARGS="--filter TestName"` for a focused test run and
`make help` to discover the other commands. GrumPHP also runs the quality gate
from Git hooks. Commit messages must follow Conventional Commits, such as
`fix(api): reject an invalid host`.

If a test suite is present for the area you change, run it and include the
command and result in your pull request.

## Test-driven development

`src/` is organized feature-first (`src/<Feature>/Domain`, `Application`,
`Infrastructure`, `Presentation`), and new behavior should be developed
test-first using a Red → Green → Refactor loop:

1. **Red** — write a failing test that expresses the desired behavior before
   writing the implementation. Prefer the narrowest test type that can
   express the behavior:
   - `tests/Unit/<Feature>/...` for pure logic with no framework/DB/HTTP
     dependency (e.g. a domain service tested against mocked or in-memory
     collaborators).
   - `tests/Integration/<Feature>/...` for behavior that needs the DI
     container and/or database but not a full HTTP request (e.g. a
     repository query, a Doctrine listener).
   - `tests/Functional/<Feature>/...` for behavior only observable through
     the HTTP API or a console command end-to-end.
2. **Green** — write the minimum implementation code needed to make the test
   pass.
3. **Refactor** — clean up the implementation and/or the test without
   changing observable behavior, keeping the suite green throughout.

This repository's existing test suite predates this convention and consists
entirely of functional-style tests under `tests/Functional/`. It has not been
retroactively split into Unit/Integration tiers, since a test should live in
the category it genuinely belongs to, not the category that fills out a
target layout. `tests/Unit/` and `tests/Integration/` will be created
organically as the corresponding test types are introduced.

Most tests run against the SQLite database configured in `.env.test`. A few
exercise PostgreSQL-specific SQL (e.g. the `Audit` feature's `UNION ALL`
reader) and must be validated against real PostgreSQL 16 in addition to the
default run. Use `make test-postgres` for that — it provisions a dedicated
`osira_test` database and runs PHPUnit against it (`make test-postgres
ARGS="tests/Functional/Audit"` to scope the run).

## Development fixtures

`make dev` (or `make dev-setup`) brings up a working stack with an empty
database. To seed it with a realistic, deterministic dataset so a frontend
(e.g. `osira-web`) has something to build against immediately:

```bash
make fixtures
```

To fully reset the local database — drop, recreate, migrate, and reseed — in
one step:

```bash
make dev-reset
```

**`make dev-reset` is DEV ONLY.** It drops the local database. It refuses to
run unless the app container resolves to the `dev` environment, and it only
ever talks to the `database` service defined in `compose.yaml` — it has no
path to a production `DATABASE_URL`. Never adapt it to run against anything
but a local development database.

Fixtures are organized by feature under `src/<Feature>/Infrastructure/Fixtures/`
and load in dependency order: RBAC catalog → users → node groups → nodes →
agents. The RBAC catalog is seeded through the same
`RbacCatalogSynchronizer` the `osira:rbac:sync` command uses — fixtures do
not duplicate the permission/role catalog. Auditing is temporarily disabled
while fixtures load (`DH\Auditor\Auditor::getConfiguration()->disable()`),
so seeding development data doesn't pollute the audit log.

### DEV accounts

**FOR DEVELOPMENT ONLY. NEVER use these credentials in production.**

| Email | Role |
| --- | --- |
| `admin@osira.local` | Super Admin |
| `admin2@osira.local` | Admin |
| `operator@osira.local` | Operator |
| `viewer@osira.local` | Viewer |
| `noc@osira.local` | NOC Operator (custom role — `nodes.read`, `nodes.update`, `node_groups.read`, `audit_logs.read`) |

Password for all of the above: `Osira123!`

### Dataset

8 node groups (Production, Staging, Linux Servers, Windows Servers,
Databases, Web Servers, Critical Infrastructure, Homelab) and 12 nodes
spanning production/staging/office/homelab environments, Linux and Windows,
with realistic hostnames, tags, and group memberships. The 10 Linux nodes
each get a descriptive `Agent` record (version `0.1.0-dev`); Windows nodes
are left agent-less on purpose so the frontend can handle both states. No
`AgentCredential` or `EnrollmentToken` is created — fixtures never hold a
usable secret.

## Commits and pull requests

Write clear, imperative commit messages. A pull request should explain the
problem, the chosen solution, how it was tested, and any compatibility or
deployment impact. Link related issues with GitHub closing keywords when
appropriate.

Draft pull requests are welcome for early feedback. A pull request is ready
for review when its checks pass, its documentation is current, and it contains
no known unrelated changes.

## Review process

Maintainers may request changes to preserve security, maintainability,
backward compatibility, or project direction. Approval does not guarantee an
immediate merge, and substantial changes may be split into follow-up work.

By contributing, you agree that your contribution is licensed under the
project's [AGPL-3.0 license](LICENSE).
