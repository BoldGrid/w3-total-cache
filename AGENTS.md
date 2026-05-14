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

## Security
- **Never put security-related information anywhere that could be public.** This is a strict rule, not a guideline.
- Treat the following surfaces as public-by-default and keep them free of security details: commit messages, commit bodies, branch names, tag names, PR titles, PR descriptions, PR review comments, PR inline/code comments, GitHub issue titles and bodies, GitHub issue comments, GitHub Discussions, release notes, `readme.txt`, source-code comments, and any other artifact that ships in the repo or is visible on the public GitHub project. Even on a draft PR or a private fork, assume content may become public (force-pushes, branch transfers, accidental visibility flips, contributor forks, search indexes, mirrors).
- "Security-related information" includes — but is not limited to — vulnerability descriptions, attack vectors, exploit steps or payloads, affected versions, CVE/GHSA/Patchstack/finding IDs, reporter or researcher identities, severity assessments, internal threat-model notes, references to private advisories or embargoed disclosures, and any phrasing that telegraphs "this commit fixes a security issue" (e.g., "fix XSS in...", "patch SSRF", "sanitize user input to prevent RCE").
- **Sensitive words blocklist.** The following terms must not appear (in any case, and including obvious variants/derivatives — e.g., `vuln` covers `vulnerable`, `vulnerability`, `vulnerabilities`) on any public-facing surface listed above — commit messages, branch/tag names, PR titles/descriptions/comments, issue titles/bodies/comments, source-code comments, etc. Treat this list as a hard tripwire: if you're about to type one of these into a public surface, stop and route the content to the private Jira ticket or draft GHSA instead. The list is intentionally broad; when in doubt, treat a borderline term as in-scope.
  - General security vocabulary: `secret`, `vulnerability` (`vuln`), `exploit`, `exploitable`, `attacker`, `malicious`, `payload`, `backdoor`, `0-day` / `zero-day` / `0day`, `unsafe` (when describing a flaw rather than a `*-unsafe` API name).
  - Disclosure / advisory identifiers and processes: `CVE`, `CWE`, `GHSA`, `Patchstack`, `VDP`, `advisory`, `disclosure`, `embargo`, `embargoed`, `responsible disclosure`, `coordinated disclosure`, `reporter` / `researcher` (in the security-reporter sense).
  - Vulnerability class names and acronyms: `XSS` / `cross-site scripting`, `CSRF` / `cross-site request forgery`, `SSRF`, `RCE` / `remote code execution`, `SQLi` / `SQL injection`, `LFI`, `RFI`, `XXE`, `SSTI`, `IDOR`, `path traversal` / `directory traversal`, `prototype pollution`, `command injection`, `code injection`, `header injection`, `open redirect`, `insecure deserialization`, `ReDoS`, `TOCTOU`, `race condition` (when used as a vuln class), `nonce bypass`, `auth bypass` / `authentication bypass` / `authorization bypass`, `privilege escalation` / `privesc`, `sandbox escape`, `arbitrary file read` / `arbitrary file write` / `arbitrary code execution`.
  - Credential / secret material when describing a leak, hard-coding, or exposure (the words themselves can legitimately appear in feature work — e.g., a password-reset form — but never in the context of "this fixes a leaked/exposed/hard-coded X"): `password`, `passphrase`, `credential`, `API key`, `auth token` / `bearer token` / `session token`, `private key`, `signing key`.
  - Soft signals — pause and reword before posting publicly. These appear in plenty of neutral refactors, but in a commit/PR for a security-flavored change they typically telegraph the underlying issue: `patch` (in the "patch a flaw" sense), `harden` / `hardening`, `sanitize` / `sanitization` (when it implies a previously-missing sanitizer), `mitigate` / `mitigation`, `bypass`, `escape` (when describing previously-missing output escaping).
- Commit messages and PR descriptions for security fixes must describe the change in neutral, refactor/hardening language that does not advertise the underlying vulnerability. Save the real context for the private Jira ticket or the GitHub Security Advisory (GHSA) draft.
- Keep all sensitive descriptive content — vulnerability scope, finding IDs, reproduction steps, exploit-shaped review commentary, reporter coordination — in the internal Jira ticket and/or the draft GHSA. Cross-link the public PR ↔ private Jira (and PR ↔ GHSA when applicable) but do not copy sensitive content back to the public side.
- If sensitive content has already been posted publicly, do not just edit/delete it — GitHub retains edit history, notification emails, and search caches. Follow the documented remediation skills:
  - `.claude/skills/pr-content-to-jira/SKILL.md` — relocate sensitive descriptive content from a public PR into the internal Jira ticket.
  - `.claude/skills/move-pr-to-private-ghsa/SKILL.md` — move an in-progress public security-fix PR into a GHSA's temporary private fork and close + delete the public PR.
- Report vulnerabilities via the [Patchstack VDP](https://patchstack.com/database/vdp/d5047161-3e39-4462-9250-1b04385021dd), not via public issues or PRs.
- When in doubt, default to silence on the public side and ask the maintainers in Jira/Slack before posting.

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
