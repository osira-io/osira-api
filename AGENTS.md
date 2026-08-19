# Osira API Agent Guide

## Scope

- Repository: `osira-api`
- Namespace root: `App\`
- Do not commit unless the user explicitly asks.
- Do not widen scope beyond the request.
- Source of truth for framework behavior: official documentation matching the installed stack.

## Installed stack

- PHP `8.5.9` locally; project constraint `>=8.4`
- Symfony `8.1.x`
- API Platform `4.3.x`
- Doctrine ORM `3.6.x`
- DoctrineBundle `3.3.x`
- PHPUnit `12.5.x`
- PHPStan `2.2.x`
- DH AuditorBundle `7.2.x`
- Symfony HttpClient `8.1.x`
- PostgreSQL `16` is the target database

## Mandatory workflow

1. Inspect the repository before coding.
2. Read the root `AGENTS.md`.
3. Load the repository skill `osira-api-dev`.
4. Consult the relevant official docs before making structural choices.
5. Follow TDD: Red -> Green -> Refactor.
6. Keep changes focused and auditable.
7. Self-review the diff before finishing.

Future prompts may be short, for example:

`Implement SLA management in osira-api using the repository conventions.`

The expected agent behavior is to inspect the repo, read `AGENTS.md`, load `osira-api-dev`, consult the relevant references and official docs, apply TDD, update RBAC/audit/OpenAPI/migrations/fixtures when needed, run the quality gates, report the result, and not commit.

## Architecture

The repository uses the technical-type plus domain layout below and the namespace must match the path exactly:

```text
src/
├── Entity/<Domain>/
├── Repository/<Domain>/
├── Service/<Domain>/
├── Dto/<Domain>/
├── State/Provider/<Domain>/
├── State/Processor/<Domain>/
├── Security/<Domain>/
├── Command/<Domain>/
├── DataFixtures/<Domain>/
├── EventSubscriber/<Domain>/
└── ...
```

Examples:

- `src/Entity/Monitoring/MonitoringTemplate.php` -> `App\Entity\Monitoring\MonitoringTemplate`
- `src/Service/Monitoring/EffectiveNodeMonitoringResolver.php` -> `App\Service\Monitoring\EffectiveNodeMonitoringResolver`

Use `make architecture` to enforce the static path/namespace convention.

## Symfony / API Platform / Doctrine rules

- Prefer constructor injection and explicit typed dependencies.
- Do not inject `ContainerInterface` or read `$_ENV` directly from application code.
- Keep API Platform `Provider` classes for reads and `Processor` classes for writes when that matches the feature.
- Do not expose Doctrine entities directly only to save classes.
- Use Symfony Validator constraints for request-shape validation; keep complex invariants in domain/application services.
- Keep Doctrine mappings explicit, initialize collections in constructors, and maintain owning/inverse sides deliberately.
- Do not add interfaces, factories, traits, listeners, or subscribers mechanically.
- Add a factory only when construction has meaningful invariants, service dependencies, or secret/token generation.
- Add an interface when it forms a stable boundary, especially for external infrastructure.

## Timestamps and deletion

- Audit `createdAt` and `updatedAt` changes carefully; do not standardize them blindly.
- `Timestampable` is a valid option only if it clearly improves consistency for this repository and stays compatible with the installed stack.
- Do not add soft delete globally. `SoftDeleteable` is a business decision per entity, not a default.
- System objects that must not disappear are protected first by domain rules.

## Security / RBAC / Audit

- Any sensitive new API capability needs a dedicated RBAC permission.
- RBAC checks must use stable permission codes, not translated labels or role names.
- Never persist or expose raw secrets, token values, password hashes, or credential material.
- Audit business-significant changes when the resource is already part of the audited surface or when the new capability materially changes system state.
- Keep `OpenAPI` accurate after API changes.

## Migrations and fixtures

- Never rewrite an old migration that may already have been applied.
- Create incremental migrations for mapping changes.
- Validate against PostgreSQL behavior when the change is database-sensitive.
- Update development fixtures when the feature changes the catalog, RBAC, or seeded domain state.

## Quality gates

The minimum repository gates are:

- `make architecture`
- `make ci`

Also run the repo-specific checks that apply to the diff, including when relevant:

- `php bin/console doctrine:migrations:migrate --no-interaction`
- `php bin/console doctrine:schema:validate`
- `php bin/console api:openapi:export --output=var/openapi.json`
- `php bin/console debug:router`
- `make test-postgres`
- `git diff --check`

## Skills

- Repository-local skills must live under `.agents/skills/`.
- Only create deeper `AGENTS.md` files when a subtree genuinely needs stricter or different rules than this root file.
