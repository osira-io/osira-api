# Contributing to Osira API

Thank you for helping improve Osira API. Contributions of code,
documentation, tests, bug reports, and design feedback are welcome.

## Before you start

- Search existing issues and pull requests to avoid duplicate work.
- Open an issue before starting a large change so the approach can be agreed
  on early.
- Do not open a public issue for a security vulnerability. Follow
  [SECURITY.md](SECURITY.md) instead.
- Follow the [Code of Conduct](CODE_OF_CONDUCT.md) in all project spaces.

## Local setup

You need PHP 8.4 or newer and Composer.

```bash
git clone https://github.com/osira-io/osira-api.git
cd osira-api
composer install
php bin/console about
```

Store local configuration in `.env.local`. Never commit credentials, tokens,
private keys, production data, or other secrets.

## Making a change

1. Create a focused branch from the default branch.
2. Keep the change small enough to review and avoid unrelated refactors.
3. Add or update tests when behavior changes.
4. Update documentation when configuration, APIs, or user-visible behavior
   changes.
5. Run the project checks locally.

```bash
composer validate --strict
php bin/console lint:yaml config
php bin/console lint:container
```

If a test suite is present for the area you change, run it and include the
command and result in your pull request.

## Commits and pull requests

Write clear, imperative commit messages. A pull request should explain the
problem, the chosen solution, how it was tested, and any compatibility or
deployment impact. Link related issues with GitHub closing keywords when
appropriate.

Draft pull requests are welcome for early feedback. A pull request is ready
for review when its checks pass, its documentation is current, and it contains
no known unrelated changes.

## Review process

Maintainers may request changes to preserve security, maintainability,
backward compatibility, or project direction. Approval does not guarantee an
immediate merge, and substantial changes may be split into follow-up work.

By contributing, you agree that your contribution is licensed under the
project's [AGPL-3.0 license](LICENSE).
