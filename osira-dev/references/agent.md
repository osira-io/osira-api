# Osira Agent

Use this reference for work in `osira-agent`.

## Stack and Targets

- Rust
- Linux
- Windows

The native service model is preferred:

- Linux: `systemd`
- Windows: Windows Service

Docker is optional for Linux or development use, not the main deployment mode.

## Responsibilities

- machine detection
- enrollment
- collection
- heartbeat
- metrics
- retry and backoff
- optional local buffering

## Installation Principles

The installer should require only:

- server URL
- enrollment token

The rest should be auto-detected.

Do not put groups, tags, environments, or policy configuration into the
installer. Those belong to the control plane.

## Service Boundaries

- Enrollment talks to the Symfony API.
- Heartbeat and metrics talk to `osira-ingest`.
- Do not write directly to PostgreSQL or VictoriaMetrics.
- Do not depend on the frontend.
