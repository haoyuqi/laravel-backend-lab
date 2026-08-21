# Contributing to Laravel Example

Thank you for considering contributing to Laravel Example! The contribution guide can be found below.

## Bug Reports

To encourage active collaboration, we strongly encourage pull requests, not just bug reports. "Bug reports" may also be sent in the form of a pull request containing a failing test.

If you file a bug report, your issue should contain a title and a clear description of the issue. You should also include as much relevant information as possible and a code sample that demonstrates the issue.

## Feature Requests

Feature requests are welcome, but we do ask that you open an issue and discuss the feature first before taking the time to write the code. This ensures that the feature is aligned with the goals of the project.

## Development Workflow

### Branching

All pull requests should be submitted from a feature or bugfix branch. Please use the following branch naming convention:

*   **Format:** `<type>/<issue-number>-<kebab-case-description>`
*   **Types:**
    *   `feat`: A new feature
    *   `fix`: A bug fix
    *   `docs`: Documentation only changes
    *   `style`: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc)
    *   `refactor`: A code change that neither fixes a bug nor adds a feature
    *   `perf`: A code change that improves performance
    *   `test`: Adding missing tests or correcting existing tests
    *   `chore`: Changes to the build process or auxiliary tools and libraries such as documentation generation

**Examples:**
*   `feat/90-install-filament`
*   `fix/82-visitor-log-error`
*   `chore/88-remove-deprecated-packages`

### Commit Messages

Commit messages should follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

*   **Format:** `<type>(<scope>): <imperative summary>`
*   The `type` must be one of the types listed above (e.g., `feat`, `fix`, `chore`).
*   The `scope` is optional and indicates the area of the codebase affected (e.g., `admin`, `visitor`, `deps`).
*   The summary must be in English, written in the imperative mood, start with a lowercase letter, and not end with a period. It should not exceed 72 characters.

**Examples:**
*   `feat(admin): add filament panel provider`
*   `chore(deps): remove fruitcake/laravel-cors`
*   `refactor(visitor): migrate resource to filament`

### Pull Requests

*   Fill out the Pull Request template completely.
*   Ensure your code follows the project's coding standards.
*   Write tests for your changes, if applicable.
*   Make sure all tests pass before submitting the PR.
*   Keep pull requests small and focused on a single issue.

## Code of Conduct

In order to ensure that the Laravel Example community is welcoming to all, please review and abide by our Code of Conduct. (To be added)
