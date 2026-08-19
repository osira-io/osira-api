# Osira Ingest

Use this reference for work in `osira-ingest`.

## Role

`osira-ingest` is the Rust data plane:

`agent -> osira-ingest -> VictoriaMetrics`

## Responsibilities

- authenticate `AgentCredential`
- accept heartbeats
- ingest metric batches
- validate payloads
- support compression or protocol evolution
- keep ingestion retry-safe

## Non-Responsibilities

Do not add control-plane concerns here:

- users
- RBAC
- groups
- UI
- administration

Symfony remains the control plane. Rust ingest remains the data plane.

## Architecture Expectations

- Small installations may run a single instance.
- Horizontal scaling can come later when justified.
- Do not introduce Kafka, NATS, Redis, or Kubernetes without a demonstrated
  need.
