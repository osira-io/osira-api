# Security Policy

## Supported versions

Osira API is currently in early development. Security fixes are applied to the
latest code on the default branch. No released version is guaranteed to receive
security updates until a stable release policy is announced.

## Reporting a vulnerability

Do not report suspected vulnerabilities in a public issue, discussion, or pull
request.

Use [GitHub private vulnerability reporting](https://github.com/osira-io/osira-api/security/advisories/new)
to send the maintainers:

- a description of the vulnerability and its impact;
- affected versions, commits, endpoints, or components;
- reproducible steps or a proof of concept;
- any known mitigations;
- whether and where the issue has already been disclosed.

Avoid accessing data that does not belong to you, disrupting services, or
performing destructive testing. Give maintainers a reasonable opportunity to
investigate and release a fix before public disclosure.

Maintainers will acknowledge reports as soon as reasonably possible, assess
their severity, and coordinate remediation and disclosure with the reporter.
Response and resolution times depend on the issue's complexity and the
availability of project maintainers.

## Scope

Reports should concern code maintained in this repository. Vulnerabilities in
third-party dependencies should normally be reported to their upstream
maintainers, unless Osira API uses the dependency in a way that creates a
project-specific vulnerability.
