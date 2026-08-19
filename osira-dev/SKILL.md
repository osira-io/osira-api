---
name: osira-dev
description: Use when working on Osira open-source development tasks such as API features, backend refactors, web pages, Rust agent or ingest work, tests, PR reviews, CI fixes, architecture changes, validations, and contribution workflows across the Osira ecosystem.
---

# Osira Development

Use this skill for permanent Osira engineering conventions across `osira-api`,
`osira-web`, `osira-agent`, `osira-ingest`, and repo-level contribution work.
Do not encode temporary feature requirements here.

## Inspect First

Inspect the target repository before changing anything.

- Do not assume paths, versions, services, conventions, or dependencies.
- Reuse the patterns already present in the repo.
- Prefer the real local commands and workflows over guessed ones.

## Scope

- Keep tasks small, focused, and auditable.
- Do not expand scope automatically.
- A branch or PR should address one main responsibility.
- Do not add speculative features or unrelated refactors.

## Git Workflow

- Work from `develop`, not `main`.
- Branches should follow `feat/*`, `fix/*`, `refactor/*`, `perf/*`, `docs/*`,
  or `chore/*`.
- Open PRs against `develop`.
- Do not commit directly to `main` or `develop`.
- Use Conventional Commits.
- Do not create commits unless explicitly asked.

Read [references/git-workflow.md](references/git-workflow.md) when the task
involves branching, commit structure, or PR preparation.

## Engineering Rules

- Follow Red -> Green -> Refactor for new logic. Do not claim TDD when tests
  were written after the implementation.
- Prefer feature-first organization and keep code close to its feature.
- Keep the Domain layer independent from transport, framework, and frontend
  concerns.
- Treat the API as an independent product, not as an implementation detail of
  the frontend.
- Use explicit REST semantics and avoid ambiguous create-or-update endpoints.
- Add dedicated RBAC permissions for sensitive capabilities when needed.
- Never use translated labels or business role names as technical permission
  checks.
- Never persist raw secrets, expose hashes or secret material, or log
  credentials.
- JWT carries identity; the database remains the source of truth for current
  roles and permissions.
- Keep PostgreSQL compatibility in mind for API work; do not treat SQLite-only
  validation as sufficient for PostgreSQL-specific behavior.
- Keep OpenAPI accurate and review contract changes during API refactors.

## Component Routing

Read only the references relevant to the task:

- For `osira-api`, read [references/api.md](references/api.md).
- For `osira-web`, read [references/web.md](references/web.md).
- For `osira-agent`, read [references/agent.md](references/agent.md).
- For `osira-ingest`, read [references/ingest.md](references/ingest.md).
- For validation expectations, read [references/quality.md](references/quality.md).

## Completion

Before calling a task done:

- Run the checks that actually exist in the target repo.
- Report created or modified files.
- Report tests and validations performed.
- Report assumptions, constraints, and any limits still present.
