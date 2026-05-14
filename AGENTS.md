# Contributor Guide

## Project Overview
This project is a WordPress plugin designed to enhance website performance through caching and other optimization techniques.

## Coding Standards
- Follow the coding standards defined in the ./phpcs.xml file.
- This is a WordPress plugin, so the coding standards must adhere to the WordPress coding standards.
- This plugin must be compatible with PHP 7.2.5 through 8.3, as defined in the main plugin file "w3-total-cache.php" and "readme.txt".
- This plugin must be compatible with WordPress 5.3 and up, as defined in the main plugin file "w3-total-cache.php" and "readme.txt".
- Do not use spaces for indentation; use 4-space tabs instead.
- Use single quotes for strings unless double quotes are necessary (e.g., when using variables inside the string).
- Do not make coding standards changes in changed files unless it is directly related to the functionality being modified.
- Opening parenthesis of a multi-line function call must be the last content on the line (PEAR.Functions.FunctionCallSignature.ContentAfterOpenBracket).
- Prefix all global namespace functions with a backslash.
- Use `/** ... */` doc-block syntax for all multi-line comments and function/class/file doc-blocks, per the WordPress inline-documentation standard. Do not use stacked `//` lines for multi-line commentary; reserve `//` for single-line trailing or explanatory remarks.

## References
- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
- WordPress Coding Standards for PHP: https://developer.wordpress.org/coding-standards/php/
- WordPress Coding Standards for JavaScript: https://developer.wordpress.org/coding-standards/javascript/
- WordPress Coding Standards for HTML: https://developer.wordpress.org/coding-standards/html/
- WordPress Coding Standards for CSS: https://developer.wordpress.org/coding-standards/css/
- WordPress Coding Standards for Accessibility: https://developer.wordpress.org/coding-standards/accessibility
- WordPress Documentation Standards for PHP: https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/
- WordPress Documentation Standards for JavaScript: https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/

## Contribution Process
- Add `@since X.X.X` to all new doc blocks -- it's updated in our build process.
- Do not update POT files -- it's done in our build process.
- Do not change the `readme.txt` file -- it's done on release branches.
- Do not increment the plugin version number -- it's done in our build process.
- All changes must be submitted via pull requests.
- Public-facing work may originate from GitHub issues to ensure public visibility.
  - Create a GitHub issue describing the change, implement the change in a branch, and open a pull request that references the GitHub issue.
- Internal work may originate from JIRA issues for internal tracking.
  - Create a JIRA issue, implement the change in a branch, and open a pull request that references the JIRA issue.
- Ensure each pull request references its originating issue (GitHub or JIRA) and includes a clear description of the change.

## Dependency Management
- Use `yarn run upgrade:deps` to refresh JS packages and Composer libraries in one step; this enforces the PHP 7.2.5–8.3 constraint declared in `composer.json`.
- When running Composer directly, keep `composer update --with-all-dependencies` targeted at the repo root so the generated lock file honors the configured PHP platform (7.2.5).

## Working Files
- For ad-hoc agent scratch files (PR-comment bodies, JSON payloads for `gh api`, draft patches, intermediate tool output, etc.), write to `.cursor/working/` — it is covered by `.gitignore` (`.cursor/working/`).
- Do not write to `/tmp/` or to any path outside the project tree.
- Leave scratch files in place for traceability rather than deleting on task completion; the directory is gitignored so they will not pollute commits.
- **Prefix task-specific scratch files with the primary identifier the user is working from** so concurrent task workflows don't collide in the directory:
  - PR-driven task → `{PR}-{role}.{ext}` (e.g., `1313-pr-body.json`, `1313-jira-jacobd91-comment.md`, `1313-anchor-comment.md`).
  - Jira-driven task → `{KEY}-{role}.{ext}` (e.g., `ENG7-2908-summary.md`).
  - Both relevant → `{PR}-{KEY}-{role}.{ext}` (e.g., `1313-ENG7-2908-jira-cssjoe.md`).
  - Non-task-specific persistent agent references (playbooks, conventions) → `PLAYBOOK-{topic}.md` or `CONVENTION-{topic}.md`.
- Long-lived agent playbooks are committed under `.claude/skills/{skill-name}/SKILL.md`. Read the relevant skill at the start of a matching task before improvising — skills encode caveats discovered the hard way on prior runs. Current skills:
  - `.claude/skills/pr-content-to-jira/SKILL.md` — move sensitive descriptive content (vuln scope, finding IDs, exploit-shaped review commentary) off a public GitHub PR and into an internal Jira ticket. Use when a PR has accumulated content that should not stay in the default public view and a parent Jira ticket exists.
  - `.claude/skills/move-pr-to-private-ghsa/SKILL.md` — move an in-progress public security-fix PR's branch into a GitHub Security Advisory's temporary private fork, then close + delete the original public PR. Use when the team decides remaining review and additional commits should happen privately. Pair with `pr-content-to-jira` when the public PR also has descriptive content to preserve.
