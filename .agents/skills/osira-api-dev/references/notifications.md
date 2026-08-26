# Incident notifications

Use this reference for incident notification work.

## Lifecycle boundary

- Dispatch only for Incident opening (`incident.firing`) and resolution (`incident.resolved`).
- A recurring FIRING evaluation or `lastValue` update never creates another notification event.
- Incident acknowledgement exists; only the `incident.acknowledged` notification event is outside V1.
- Maintenance is enforced by incident creation; notification services do not repeat maintenance checks.

## Async delivery

- Dispatch transition messages through Symfony Messenger and perform email/webhook I/O only in handlers.
- Use Symfony Mailer and HttpClient directly rather than custom SMTP or HTTP infrastructure.
- Let transport failures escape the handler so Messenger retry and failure transports apply.
- Store one delivery per Incident, event, and channel. A sent delivery is never sent again; a pending delivery may be retried.
- Delivery is at-least-once. Reuse the persisted delivery ULID in the webhook payload as `deliveryId` and in both `Osira-Delivery-Id` and `Idempotency-Key` headers on every retry.
- A webhook receiver can obtain an effectively-once effect by deduplicating on `deliveryId`.

## Secrets

- Webhook signing secrets are write-only and encrypted at rest with authenticated encryption.
- Never expose plaintext or ciphertext in DTO outputs, audit records, logs, exceptions, or OpenAPI examples.
- Sign the exact serialized webhook request body with HMAC-SHA256 when a secret is configured.
