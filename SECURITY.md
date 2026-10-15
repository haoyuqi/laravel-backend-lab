# Security Policy

## Supported Versions

Security fixes are provided for the latest release on `master`. Please upgrade
older deployments before reporting an issue that has already been fixed.

The `develop` branch contains unreleased changes. Reports affecting `develop`
are also welcome, but it is not a supported production release.

## Reporting a Vulnerability

Report suspected vulnerabilities privately through GitHub:

[Report a vulnerability](https://github.com/haoyuqi/laravel-backend-lab/security/advisories/new)

Include the affected version or commit, reproduction steps, expected and actual
behavior, and the potential impact. A minimal proof of concept is helpful.
Remove credentials, personal data, and production secrets from attachments.

Do not disclose vulnerabilities in public issues or pull requests. Use the
private advisory to coordinate a fix and disclosure with the maintainer.

The maintainer will investigate reports and coordinate any security release
through that advisory. This community-maintained project does not guarantee a
response deadline or offer a bug bounty.

## Repository Automation

Ordinary GitHub Actions workflows use a read-only `GITHUB_TOKEN`. Only the
Dependabot auto-merge job receives `contents: write` and `pull-requests: write`.
It verifies Dependabot metadata using an Action pinned to a commit SHA and
never checks out or runs pull request code. Workflows cannot approve pull
requests.

Automatic merging is limited to Composer and npm patch or minor updates from
Dependabot targeting `master`, after its required checks pass. Major updates,
other ecosystems, and unknown update types require a maintainer to review and
merge them manually. Dependabot security updates may target the default branch;
routine development still follows the branch flow in [CONTRIBUTING.md](CONTRIBUTING.md).

Secret scanning and push protection detect supported credentials. CodeQL default
setup analyzes GitHub Actions and JavaScript/TypeScript. CodeQL does not provide
PHP analysis for this application; its PHP tests and dependency checks remain
necessary.
