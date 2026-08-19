---
name: osira-api-dev
description: "Use when implementing, refactoring, testing, reviewing, or validating work in `osira-api`, especially for Symfony, Doctrine, API Platform, RBAC, monitoring, audit, and CI changes. Do not use for other Osira repositories."
---

# Osira API Dev

Use this skill after reading the repository root `AGENTS.md`.

## First pass

- Inspect the repository before proposing changes.
- Confirm the installed versions and existing patterns locally.
- Consult the relevant official documentation before making structural decisions.
- Keep the scope tightly aligned with the user request.

## Workflow

1. Inspect the existing implementation and nearby patterns.
2. Choose the smallest Symfony/Doctrine/API Platform mechanism that fits.
3. Work in TDD order: Red -> Green -> Refactor.
4. Route non-trivial Doctrine entity creation through `Service/<Domain>/Factory/<Entity>Factory` when the repository already uses that convention.
5. Update RBAC for any sensitive new capability.
6. Update audit coverage when a business-significant state change is added to the audited surface.
7. Update OpenAPI, migrations, fixtures, and documentation when the diff requires them.
8. Run the applicable quality gates and report the outcome.

## Routing

Read only the references that matter for the current task:

- [references/architecture.md](references/architecture.md) for repository layout and placement rules.
- [references/symfony.md](references/symfony.md) for service wiring, configuration, HttpClient, command, and runtime conventions.
- [references/doctrine.md](references/doctrine.md) for entities, collections, timestamps, lifecycle, transactions, factories, and PostgreSQL expectations.
- [references/api-platform.md](references/api-platform.md) for DTO, Provider, Processor, validation, and exception-to-status guidance.
- [references/testing.md](references/testing.md) for TDD and test selection.
- [references/security-rbac.md](references/security-rbac.md) for permissions, secrets, audit-sensitive changes, and metrics-read constraints.
- [references/monitoring.md](references/monitoring.md) for monitoring catalog and VictoriaMetrics read-path rules.
- [references/quality-gates.md](references/quality-gates.md) for the expected validation checklist.

## Non-negotiables

- Do not commit unless explicitly asked.
- Do not broaden scope with speculative refactors.
- Do not invent custom infrastructure when the installed Symfony/API Platform/Doctrine stack already provides the right convention.
- Do not expose arbitrary MetricsQL or other infrastructure-native query languages directly in public Osira APIs.
- Do not add interfaces, factories, traits, or subscribers mechanically.
