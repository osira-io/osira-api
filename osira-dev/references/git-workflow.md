# Git Workflow

Use this reference when preparing branches, commits, and pull requests for
Osira work.

## Branching

- `main` is the stable branch.
- `develop` is the integration branch.
- Start work from `develop`.
- Use focused branches:
  - `feat/*`
  - `fix/*`
  - `refactor/*`
  - `perf/*`
  - `docs/*`
  - `chore/*`

Do not push commits directly to `main` or `develop`.

## Pull Requests

- Keep PRs small.
- One PR should cover one main responsibility.
- Write a clear description of the problem, solution, and validation.
- Include tests and avoid unrelated refactors.
- Squash merge is preferred when appropriate for repo history.

## Commits

Use Conventional Commits, for example:

- `feat(nodes): add node groups`
- `fix(auth): reject invalid token`
- `perf(audit): paginate logs in PostgreSQL`
- `refactor: adopt feature-first architecture`
- `docs(openapi): stabilize tag ordering`
