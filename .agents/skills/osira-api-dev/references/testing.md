# Testing

Use this reference to keep implementation and validation honest.

## TDD

- Follow Red -> Green -> Refactor for new logic.
- Add the failing test first when the change introduces behavior.

## Test selection

- Use unit tests for deterministic logic.
- Use kernel/functional tests when the behavior depends on Symfony wiring, Doctrine persistence, RBAC, API Platform, or request handling.
- Prefer the smallest test level that still proves the behavior.

## Coverage-sensitive changes

- Cover both happy paths and meaningful failure paths.
- For HTTP integrations, cover timeout, invalid payload, empty payload, and infrastructure failure when those branches exist.
