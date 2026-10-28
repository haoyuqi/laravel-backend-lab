# Releases

Push a new stable tag such as `v3.0.3` on a commit reachable from `master`.
Ordinary merges do not publish. Only canonical `vX.Y.Z` tags are supported;
prereleases are intentionally rejected. Keep existing historical tags unchanged.

The Release workflow validates the tag, runs the complete Tests workflow on the
triggering commit, and publishes only if every check succeeds. Failed checks
block publication; rerun the failed workflow after investigating the failure.
The release provides GitHub's normal source archives. No deployment is performed.
Existing releases are preserved on reruns, including manually edited notes.

## Generated notes

Configure these three GitHub Actions secrets for this repository, or make the
organization secrets available to it:

- `RELEASE_NOTES_TOKEN`: service bearer token.
- `RELEASE_NOTES_BASE_URL`: HTTPS OpenAI-compatible API base URL ending in `/v1`.
- `RELEASE_NOTES_MODEL`: permitted model identifier.

The service must support Chat Completions and `reasoning_effort: high`. Both the
English generation and Simplified Chinese translation explicitly request high
reasoning effort. Only nonmerge commit subjects are sent, capped at 80,000
characters. No source files, diffs, environment files, or other secrets are sent.
Service configuration stays in Secrets and is never printed by the helper.

The previous release is the highest lower stable version tag reachable from the
new tag. Both `3.0.2` and `v3.0.2` are recognized; actual historical tag names are
used for the comparison. Without a prior tag, all reachable commit subjects are
used for an initial release. The model groups meaningful changes, filters trivial
maintenance, and returns English Markdown. A second request translates that
Markdown; the two sections are joined with `<!-- lang:zh -->`.

Missing configuration or generation errors produce a commit-subject fallback.
Translation errors keep the English notes. These cases produce visible warnings
in the generation step. Temporary 429 responses get at most two retries; daily
quota errors and timeouts are not retried. Notes are retained as an Actions
artifact for 14 days. No CHANGELOG file is required or updated.

## Preview before publishing

In Actions → Release → Run workflow, select the default branch and enter an
**existing** tag. This manual run generates a `release-notes` artifact without
running the application checks or publishing a release. It works for historical
`vX.Y.Z` tags as well as new tags. Review the downloaded Markdown. A manual run
never publishes, even if no release exists for that tag.

After this workflow has been merged, pushing a new `vX.Y.Z` tag triggers the
publication path. Creating the tag is the explicit release action. Tags created
with the default Actions token do not trigger another workflow; use a human push
or a separately authorized token when automating tag creation.

The tag determines the GitHub release version. It does not update the application
runtime version (`APP_VERSION` / `config/app.php`); set that during deployment if
needed. Generated notes should be reviewed, especially fallback notes and any
claimed compatibility or migration changes.
