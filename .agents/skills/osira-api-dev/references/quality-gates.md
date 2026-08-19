# Quality Gates

Use this reference before declaring `osira-api` work complete.

## Mandatory baseline

- `make architecture`
- `make ci`

## Apply additionally when relevant

- `php bin/console doctrine:migrations:migrate --no-interaction`
- `php bin/console doctrine:schema:validate`
- `php bin/console doctrine:mapping:info`
- `php bin/console api:openapi:export --output=var/openapi.json`
- `php bin/console debug:router`
- `make test-postgres`
- `git diff --check`

## Skill validation

- Validate repository-local skills with the Codex-supported validator when available.
- Keep `AGENTS.md` and the skill aligned; `AGENTS.md` is the always-on layer, the skill is the workflow layer.
