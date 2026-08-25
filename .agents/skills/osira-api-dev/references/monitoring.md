# Monitoring

Use this reference for monitoring catalog and metrics read-path work.

## Osira control plane boundary

- Symfony is the control plane.
- High-frequency ingestion does not belong in `osira-api`.
- `osira-ingest` or another data-plane component handles writes later.

## Monitoring catalog

- `ItemDefinition.key` is the stable public key.
- Product bootstrap creates no items, templates, or alert rules. Development fixtures may contain clearly identified examples only.
- Every item is user-created and contains at least one collection command: Bash for Linux and/or PowerShell for Windows.
- Effective items follow only `ItemDefinition -> MonitoringTemplate -> NodeGroup -> Node`; there are no direct Item -> Group/Node or Template -> Node assignments.
- Filter disabled templates/items, unsupported string metrics, and OS-incompatible commands fail-safe. Unknown operating systems receive no items.
- Command creation/change requires `item_definitions.manage_commands` and is audited. Collection commands are not remote actions.

## VictoriaMetrics read path

- Use a dedicated VictoriaMetrics client boundary.
- Support only the read APIs needed by the feature, not a public query proxy.
- Node metric reads must always be scoped by the target node identifier.
- Use the centralized generic series `osira_item_value` with controlled `node_id` and `item_key` labels for any valid `ItemDefinition.key`.
- Treat all remaining non-infrastructure labels as dimensions; never derive a metric name or MetricsQL fragment from user input.
- Public responses expose Osira DTOs, not raw VictoriaMetrics payloads.

## Alert evaluation

- Resolve alert rules through `EffectiveNodeMonitoringResolver::getEffectiveAlertRules()` so Template, NodeGroup, Node, enabled filtering, and deduplication stay centralized.
- Query samples from VictoriaMetrics over each rule's evaluation window. PostgreSQL stores only Incident business state and minimal snapshots.
- Incident identity is Node + AlertRule + normalized dimension labels. Never collapse distinct devices, interfaces, or containers.
- `NO_DATA` and infrastructure/invalid-response errors are not recovery signals and must leave active incidents firing.
- Run evaluation periodically outside HTTP; the manual command and scheduler must delegate to the same application service.
