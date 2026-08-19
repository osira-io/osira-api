# Symfony

Use this reference for framework-level choices.

## Services

- Prefer constructor injection with explicit types.
- Do not inject the container or use service locators for normal application code.
- Do not read environment variables directly from services; bind them through configuration.

## Configuration

- Use Symfony configuration files under `config/packages/` and `config/services.yaml`.
- Keep environment-driven settings as parameters backed by `%env(...)%`.

## HTTP clients

- Prefer Symfony HttpClient for external HTTP integrations already supported by the installed stack.
- Surface infrastructure failures as explicit application exceptions close to the client boundary.

## Events and lifecycle

- Use a local lifecycle callback, entity listener, or Doctrine extension only when the behavior genuinely belongs there.
- Do not create global subscribers for behavior that is really use-case logic.
