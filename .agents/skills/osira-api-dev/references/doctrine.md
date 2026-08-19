# Doctrine

Use this reference for entity, mapping, and persistence decisions.

## Entities

- Keep Doctrine mappings explicit with attributes.
- Initialize Doctrine collections in constructors.
- Maintain bidirectional relations deliberately on the owning and inverse sides.
- Do not add `cascade` or `orphanRemoval` for convenience only.

## Timestamps

- Audit `createdAt` and `updatedAt` usage before changing them.
- `Timestampable` is a valid standard mechanism only if it reduces real inconsistency and stays compatible with the entity model already in place.
- Do not add `updatedAt` to entities that do not need it semantically.

## Soft delete

- `SoftDeleteable` is not a default.
- Decide entity by entity based on business rules, uniqueness constraints, and relationship behavior.
- If there is no demonstrated business need, keep hard delete or business protection rules.

## Transactions

- Use transactions for genuinely multi-entity atomic workflows.
- Do not open a transaction for simple `persist` / `flush` operations.

## Factories and construction

- In `osira-api`, non-trivial Doctrine entity creation should go through `Factory/<Domain>/<Entity>Factory`.
- Keep factories focused on construction, normalization, and creation-time invariants only.
- Do not let factories persist, flush, authorize, or orchestrate use cases.
- Direct `new Entity(...)` remains acceptable in narrow entity-focused unit tests when it keeps the test simpler.

## Database work

- PostgreSQL 16 is the target database.
- Create incremental migrations only; never rewrite old applied migrations.
- For mapping changes, validate migrations and schema sync explicitly.
