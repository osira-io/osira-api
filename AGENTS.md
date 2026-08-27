# Osira API Agent Guide

## Scope

- Repository: `osira-api`
- Namespace root: `App\`
- Do not commit unless the user explicitly asks.
- Do not widen scope beyond the request.
- Source of truth for framework behavior: official documentation matching the installed stack.

## Installed stack

- PHP `8.5.9` locally; project constraint `>=8.5.3`
- Symfony `8.1.x`
- API Platform `4.3.x`
- Doctrine ORM `3.6.x`
- DoctrineBundle `3.3.x`
- Symfony Validator `8.1.x`
- Symfony Security `8.1.x`
- Symfony HttpClient `8.1.x`
- Symfony Console `8.1.x`
- DoctrineFixturesBundle `4.3.x`
- PHPUnit `12.5.x`
- PHPStan `2.2.x`
- DH AuditorBundle `7.2.x`
- PostgreSQL `16` is the target database

## Mandatory workflow

1. Inspect the repository before coding.
2. Read the root `AGENTS.md`.
3. Load the repository skill `osira-api-dev`.
4. Consult the relevant official docs before making structural choices.
5. Follow TDD: Red -> Green -> Refactor.
6. Keep changes focused and auditable.
7. Self-review the diff before finishing.

Future prompts may be short, for example:

`Implement SLA management in osira-api using the repository conventions.`

Expected agent behavior:

- inspect the repo
- read `AGENTS.md`
- load `osira-api-dev`
- consult the relevant official docs
- apply TDD
- update RBAC if a sensitive capability is added
- update audit if the state change is business-significant
- update OpenAPI, migrations, fixtures, and docs when needed
- run the quality gates
- report the outcome
- do not commit

## Architecture

The repository uses the technical-type plus domain layout below and the namespace must match the path exactly:

```text
src/
├── Factory/<Domain>/
├── Entity/<Domain>/
├── Repository/<Domain>/
├── Service/<Domain>/
├── Dto/<Domain>/
├── State/Provider/<Domain>/
├── State/Processor/<Domain>/
├── Security/<Domain>/
├── Command/<Domain>/
├── DataFixtures/<Domain>/
├── EventSubscriber/<Domain>/
├── Validator/<Domain>/
└── ...
```

Examples:

- `src/Entity/Monitoring/MonitoringTemplate.php` -> `App\Entity\Monitoring\MonitoringTemplate`
- `src/Service/Monitoring/EffectiveNodeMonitoringResolver.php` -> `App\Service\Monitoring\EffectiveNodeMonitoringResolver`

`make architecture` must stay green.

## Symfony / API Platform / Doctrine rules

- Prefer constructor injection and explicit typed dependencies.
- Do not inject `ContainerInterface` or read `$_ENV` directly from application code.
- Keep API Platform `Provider` classes for reads and `Processor` classes for writes when that matches the feature.
- Use API Platform `exception_to_status` for HTTP translation when application services throw non-HTTP exceptions.
- Do not expose Doctrine entities directly only to save classes.
- Use Symfony Validator constraints for request-shape validation; keep complex invariants in domain/application services.
- Keep Doctrine mappings explicit, initialize collections in constructors, and maintain owning/inverse sides deliberately.
- Do not add interfaces, factories, traits, listeners, or subscribers mechanically.
- Entity creation for non-trivial Doctrine resources must follow `Processor / Command -> Service -> Factory -> Entity -> EntityManager/Repository`.
- Place entity factories under `src/Factory/<Domain>/`.
- A factory returns a fully initialized new entity and never persists, flushes, authorizes, or maps HTTP.
- Do not scatter `new Entity(...)` across processors, commands, or application services once a factory exists.
- Add a factory only when construction has meaningful invariants, service dependencies, clocks, or secret/token generation.
- Add an interface when it forms a stable boundary, especially for external infrastructure.

## Timestamps and deletion

- Audit `createdAt` and `updatedAt` changes carefully; do not standardize them blindly.
- `Timestampable` is a valid option only if it clearly improves consistency for this repository and stays compatible with the installed stack.
- Do not add soft delete globally. `SoftDeleteable` is a business decision per entity, not a default.
- System objects that must not disappear are protected first by domain rules.

## Security / RBAC / Audit

- Any sensitive new API capability needs a dedicated RBAC permission.
- RBAC checks must use stable permission codes, not translated labels or role names.
- Never persist or expose raw secrets, token values, password hashes, or credential material.
- Control-plane Agent API endpoints must authenticate with dedicated `AgentCredential` bearer secrets (`osi_agent_*`), never with the user JWT.
- The enrollment contract is `EnrollmentToken -> Symfony enrollment -> AgentCredential -> /api/agent/config`; keep the raw agent secret one-time only and store only its hash.
- `/api/agent/metrics` authenticates with the dedicated `AgentCredential`, derives the Node from that identity, and must never accept a caller-selected Node or use the Symfony user JWT path.
- Audit business-significant changes when the resource is already part of the audited surface or when the new capability materially changes system state.
- Keep OpenAPI accurate after API changes.

## Alert evaluation and incidents

- Alert evaluation runs server-side outside the HTTP request path and only evaluates rules returned by `EffectiveNodeMonitoringResolver::getEffectiveAlertRules()`.
- Alert evaluation must check `MaintenanceResolver` before metric reads. Active maintenance suppresses new incidents for the Node only.
- Maintenance must never resolve an existing `FIRING` incident, treat `NO_DATA` or backend errors as recovery, or delete incident history.
- VictoriaMetrics is the source of metric samples; PostgreSQL stores only incident business state, never evaluation samples.
- Incident identity is the combination of Node, AlertRule, and normalized metric dimension labels.
- `NO_DATA` and VictoriaMetrics/backend errors never resolve an active incident.
- Incident `FIRING`/`RESOLVED` status is automatic and cannot be changed by users; V1 has no manual resolve, close, or reopen.
- Acknowledgement and comments are human interactions independent from lifecycle status. Acknowledgement is unique and only allowed while `FIRING`; comments remain allowed after resolution.
- The incident timeline is a product feature. DH Auditor remains the separate technical/security trace and must not replace the product timeline.

## Monitoring product model

- Product bootstrap creates zero `ItemDefinition`, zero `MonitoringTemplate`, and zero `AlertRule`; development fixtures are examples, never a product catalog.
- All items are user-defined collection commands: Bash on Linux and PowerShell on Windows. Remote actions are a separate future capability.
- Effective item inheritance has one path only: `ItemDefinition -> MonitoringTemplate -> NodeGroup -> Node`. Items are never assigned directly to groups/nodes, and templates are never assigned directly to nodes.
- `/api/agent/config` exposes only the command compatible with the Node OS. Unknown OS values and incompatible or string-valued items fail safe and are not collected.
- VictoriaMetrics uses the generic `osira_item_value` series scoped by controlled `node_id` and `item_key` labels; no item-key mapping or user MetricsQL is allowed.
- Creating or changing Bash/PowerShell requires `item_definitions.manage_commands`, and command changes remain on the audited `ItemDefinition` surface.
- `AlertRule` is fully configurable through `/api/alert-rules` (CRUD). A rule targets exactly one `ItemDefinition` (immutable after creation) and may additionally be assigned to any mix of MonitoringTemplate, NodeGroup, and Node — assignment is deliberately more flexible than the Item inheritance chain and reuses `AlertRuleAssignmentValidator` for every scope (a Template-scoped rule's item must belong to that Template; a NodeGroup-scoped rule's item must be served by one of the group's enabled Templates; a Node-scoped rule's item must be effective for that Node — enabled, OS-compatible command, metric-compatible value type). A rule can never target a `string`-valued `ItemDefinition`: string items are not collected as metrics in V1.
- `AlertRule.impactType` (`availability`, `performance`, `informational`) is mandatory on create and explicitly chosen by the caller — never inferred from the ItemDefinition key, operator, or severity. Only `availability` Incidents contribute to SLA downtime.
- `AlertRule.expectedValue` is the final, single name for the comparison value used by every operator (thresholds and equality checks alike); there is no separate `threshold` field. The Incident's `title` is always derived from `AlertRule.name`, and its `message` is always derived from `AlertRule.description` (falling back to `name` when no description is set). `title`/`message` are not independently configurable in V1.

## Agent metric ingestion

- `POST /api/agent/metrics` accepts an atomic batch of 1 to 500 samples using the stable `ItemDefinition.key`; `nodeId` is not part of the contract.
- Each sample contains `itemKey`, a strictly typed JSON `value`, and an optional RFC3339 `collectedAt` with an explicit timezone. Missing `collectedAt` uses server time; timestamps more than five minutes in the future are rejected.
- Only float, integer, and boolean effective items are accepted. Float accepts JSON integers or numbers, integer requires a JSON integer, and boolean requires a JSON boolean. Numeric strings are rejected.
- Resolve effective items once per request and index them locally. Reject the complete batch before writing when any sample is invalid or inaccessible to the authenticated Node.
- Persist samples in one VictoriaMetrics JSONL import request using `osira_item_value{node_id,item_key}`. PostgreSQL never stores metric samples.
- Evaluate only the affected ItemDefinitions after the write. A retry with the same Node, item key, and collection timestamp reuses the VictoriaMetrics sample identity and must not increment an Incident twice. `IncidentManager` guards against this once an Incident already exists (`evaluatedAt <= lastTriggeredAt` is a no-op), but before the first `FIRING` transition there is no Incident row to guard with — so `AlertRuleEvaluator` deduplicates same-timestamp points before counting `requiredOccurrences`, keeping the pending-phase count equal to the number of distinct collection instants regardless of how many raw duplicate points VictoriaMetrics returns for a retried timestamp.
- An evaluation only looks backward from the batch's own latest `collectedAt` per item, across the whole window already stored in VictoriaMetrics. An out-of-order (older) sample arriving after a newer one does not retroactively re-run an evaluation that already happened; a subsequent request whose own latest timestamp reaches far enough forward will see every previously stored instant regardless of arrival order. No sample is lost or double-counted, but firing can be delayed until such a request arrives.

## Incident notifications

- Incident notifications are asynchronous and originate only from business lifecycle transitions: `incident.firing` when an Incident is opened and `incident.resolved` when it resolves.
- Never dispatch notifications for recurring FIRING evaluations or `lastValue` updates. Incident acknowledgement exists, but the `incident.acknowledged` notification event is outside V1.
- Keep channels separate from routing rules. Routing may filter by severity, NodeGroup, and Node.
- Use Symfony Messenger for delivery orchestration, Symfony Mailer for email, and Symfony HttpClient for webhooks; alert evaluation must never wait for transport I/O.
- Persist a stable delivery identity for each Incident, event, and channel so Messenger retries are reasonably idempotent.
- Webhook delivery is at-least-once. Expose the stable delivery identity as `deliveryId` in the signed payload, `Osira-Delivery-Id`, and `Idempotency-Key`; receivers can deduplicate on it for effectively-once effects.
- Webhook secrets are write-only, encrypted at rest, excluded from audit, logs, exceptions, outputs, and OpenAPI examples, and may only be used to sign the exact request payload.
- Maintenance suppression remains exclusively in incident creation. Notification code must not implement a second maintenance decision.

## SLA reports

- SLA availability is calculated exclusively from PostgreSQL Incident lifecycle intervals and MaintenanceWindows, never from VictoriaMetrics samples.
- Merge overlapping incident and maintenance intervals per Node before counting seconds. A FIRING Incident extends to the report end.
- Multi-node SLA availability is weighted by eligible node-seconds (`sum(uptime) / sum(eligible)`), never averaged from node percentages. Direct and NodeGroup scopes are deduplicated.
- Ad hoc `from`/`to` report bounds never modify the persisted SLA configuration.
- Only Incidents whose AlertRule has `impactType = availability` contribute to SLA downtime. Performance and informational Incidents never affect availability. The classification is an explicit, user-set field on AlertRule — never inferred from the ItemDefinition key.
- Zero eligible seconds (an SLA scope resolved to no Node, or a period fully excluded by MaintenanceWindows) is never reported as 100% available. It is `no_data`: `availabilityPercentage` and `compliant` are `null`. A `no_data` report is neither compliant nor non-compliant.
- In multi-node aggregation, Nodes with zero eligible seconds are excluded from the weighted average (they never count as 100%) but are still listed in `nodes[]` with `status = no_data`. If every Node in scope is `no_data`, the whole report is `no_data`.

## Migrations and fixtures

- Never rewrite an old migration that may already have been applied.
- Create incremental migrations for mapping changes.
- Validate against PostgreSQL behavior when the change is database-sensitive.
- Update development fixtures when the feature changes the catalog, RBAC, or seeded domain state.

## Quality gates

The minimum repository gates are:

- `make architecture`
- `make ci`

Also run the repo-specific checks that apply to the diff, including when relevant:

- `php bin/console doctrine:migrations:migrate --no-interaction`
- `php bin/console doctrine:schema:validate`
- `php bin/console doctrine:mapping:info`
- `php bin/console api:openapi:export --output=var/openapi.json`
- `php bin/console debug:router`
- `make test-postgres`
- `git diff --check`

## Skills

- Repository-local skills must live under `.agents/skills/`.
- Only create deeper `AGENTS.md` files when a subtree genuinely needs stricter or different rules than this root file.
