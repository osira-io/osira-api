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

## Alert evaluation

- Resolve alert rules through `EffectiveNodeMonitoringResolver::getEffectiveAlertRules()` so Template, NodeGroup, Node, enabled filtering, and deduplication stay centralized.
- Query samples from VictoriaMetrics over each rule's evaluation window. PostgreSQL stores only Incident business state and minimal snapshots.
- Incident identity is Node + AlertRule + normalized dimension labels. Never collapse distinct devices, interfaces, or containers.
- `NO_DATA` and infrastructure/invalid-response errors are not recovery signals and must leave active incidents firing.
- Run evaluation periodically outside HTTP; the manual command and scheduler must delegate to the same application service.
