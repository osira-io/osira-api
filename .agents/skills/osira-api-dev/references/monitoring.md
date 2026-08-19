# Monitoring

Use this reference for monitoring catalog and metrics read-path work.

## Osira control plane boundary

- Symfony is the control plane.
- High-frequency ingestion does not belong in `osira-api`.
- `osira-ingest` or another data-plane component handles writes later.

## Monitoring catalog

- `ItemDefinition.key` is the stable public key.
- Infrastructure metric names are internal mappings, not public API.
- System templates and items stay protected by domain rules and idempotent sync.

## VictoriaMetrics read path

- Use a dedicated VictoriaMetrics client boundary.
- Support only the read APIs needed by the feature, not a public query proxy.
- Node metric reads must always be scoped by the target node identifier.
- Keep query mapping centralized and testable.
- Public responses expose Osira DTOs, not raw VictoriaMetrics payloads.
