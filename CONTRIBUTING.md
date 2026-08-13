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

You need PHP 8.4 or newer, Composer, and GNU Make. Docker is optional, but is
required to run the complete local CI target.

```bash
git clone https://github.com/osira-io/osira-api.git
cd osira-api
make install
make console ARGS="about"
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
make qa
```

Before opening a pull request, run the complete gate when Docker is available:

```bash
make ci
```

Run `make cs-fix` before committing to automatically fix coding-standard
violations. Use `make test ARGS="--filter TestName"` for a focused test run and
`make help` to discover the other commands. GrumPHP also runs the quality gate
from Git hooks. Commit messages must follow Conventional Commits, such as
`fix(api): reject an invalid host`.

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
