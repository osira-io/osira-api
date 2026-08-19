# Osira Web

Use this reference for work in `osira-web`.

## Stack Target

- Nuxt
- Vue
- TypeScript

## Contract with the API

- The frontend consumes only the public API.
- It must not know Doctrine models or Symfony internals.
- OpenAPI is the contract whenever possible.
- Do not invent frontend-only endpoints.

If required data is missing, identify the API gap and propose a separate
backend change instead of silently working around it in the frontend.

## Auth and Authorization

Expected flow:

1. Login to obtain a JWT.
2. Fetch `GET /api/me`.
3. Hydrate the auth store from API data.

Frontend checks should rely on permission codes such as `can('nodes.update')`.
Never gate business access with role-name checks like `role === 'Admin'`.

The frontend may hide actions for UX, but actual security stays enforced by the
API.

## Data Modeling

- Prefer a generated TypeScript client from OpenAPI when practical.
- Do not hand-maintain duplicate API interfaces when they can be generated.
- Keep API types aligned with the published contract.

## UI Quality

- Build accessible components.
- Handle loading, error, and empty states explicitly.
- Keep the design modern and consistent with Osira.
