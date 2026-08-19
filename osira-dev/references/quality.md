# Quality Expectations

Use this reference when deciding what to validate before considering Osira work
complete.

Always use the commands that actually exist in the target repository instead of
assuming a script name.

## API

For `osira-api`, validate as applicable with the real repo commands for:

- PHPUnit
- PostgreSQL-specific tests when behavior depends on PostgreSQL
- PHPStan at max level
- PHP CS Fixer
- GrumPHP
- Doctrine schema validation
- Symfony container lint
- YAML lint
- OpenAPI export or diff review
- router inspection when routing changes
- Actionlint
- `composer validate`
- `composer normalize`
- `composer audit`
- `git diff --check`

Current repo examples include `make qa`, `make ci`, `make test-postgres`,
Composer scripts such as `analyse`, `lint`, `cs:check`, `grumphp`, `test`, and
`security`, plus project-specific Make targets discovered locally.

## Rust

For `osira-agent` and `osira-ingest`, expect to validate with the commands
defined in those repos, typically:

- `cargo fmt --check`
- `cargo clippy`
- `cargo test`
- Linux and Windows builds when the repo supports them
- dependency audit if the repo provides it

## Web

For `osira-web`, expect to validate with the commands defined in that repo,
typically:

- typecheck
- lint
- tests
- Nuxt build
