# Security and RBAC

Use this reference for authorization, secrets, and audit-sensitive changes.

## Permissions

- Any sensitive new capability needs a dedicated permission code.
- Reuse the repository permission naming pattern: `<resource>.<action>`.
- Do not authorize with translated labels or fixed business role names.

## Secrets

- Never persist or expose raw tokens, secrets, or password material.
- Hash or derive secret material before persistence when the design requires storage.

## Audit

- Audit meaningful state changes when the entity is part of the audited surface or the new feature materially changes business state.
- Do not audit secret-bearing fields.

## Metrics

- Metrics read access is protected by RBAC like other public API capabilities.
- Do not let clients inject arbitrary infrastructure-native queries.
