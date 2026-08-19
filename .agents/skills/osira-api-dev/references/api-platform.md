# API Platform

Use this reference for public API design and API Platform wiring.

## Contracts

- Public API contracts are DTO-first in this repository.
- Keep Doctrine entities internal unless there is a demonstrated reason to expose them directly.

## Reads and writes

- Use `Provider` classes for read models.
- Use `Processor` classes for mutations.
- Keep controllers out of the path unless API Platform state providers/processors genuinely do not fit.

## Validation

- Use Symfony Validator constraints for request shape and simple invariants.
- Keep richer business rules in domain/application services.

## Public API shape

- Prefer stable Osira concepts over infrastructure-native payloads.
- OpenAPI must stay deterministic and updated after contract changes.
