# Contributor Guide

## Project Overview
This project is a WordPress plugin designed to enhance website performance through caching and other optimization techniques.

## Coding Standards
- Follow the coding standards defined in the ./phpcs.xml file.
- This is a WordPress plugin, so the coding standards must adhere to the WordPress coding standards.
- This plugin must be compatible with PHP 7.4 through 8.5, as defined in the main plugin file "w3-total-cache.php" and "readme.txt".
- This plugin must be compatible with WordPress 6.0 and up, as defined in the main plugin file "w3-total-cache.php" and "readme.txt".
- Do not use spaces for indentation; use 4-space tabs instead.
- Use single quotes for strings unless double quotes are necessary (e.g., when using variables inside the string).
- Do not make coding standards changes in changed files unless it is directly related to the functionality being modified.
- Opening parenthesis of a multi-line function call must be the last content on the line (PEAR.Functions.FunctionCallSignature.ContentAfterOpenBracket).
- Prefix all global namespace functions with a backslash.
- Use `/** ... */` doc-block syntax for all multi-line comments and function/class/file doc-blocks, per the WordPress inline-documentation standard. Do not use stacked `//` lines for multi-line commentary; reserve `//` for single-line trailing or explanatory remarks.
- **Keep code comments short, and add them only when required.** Required cases: an unexpected data shape, a non-obvious branch / fallthrough, a workaround for an external bug, a deliberate deviation from a coding standard. Do **not** narrate what the code already says, restate variable names, summarize the change a PR is making, or include best-practice reminders / general programming tips. The default for any new line of code is **no** comment; add one only when the next reader cannot infer the answer from the code itself. This applies to all languages in the project (PHP, JavaScript, CSS, shell, etc.) and to all comment styles (`//`, `#`, `/* ... */`, `/** ... */`, HTML comments).

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
- Add `@since X.X.X` to all new doc blocks. After bumping `Version` in `w3-total-cache.php`, run `yarn run update:since` and commit the replacements on `master` before tagging.
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

### Security advisories (GHSAs) — naming and Jira-link conventions
Every GHSA created against this repo, and every PR opened inside a GHSA's temporary private fork (TPF), is paired with a Jira ticket (`ENG7-####`). The following conventions are mandatory and apply to both new advisories and any future edits to existing ones:
- **Advisory summary** starts with `{JIRA_KEY}: ` followed by the descriptive title. Example: `ENG7-3019: Sanitize newlines from config values written to .htaccess/nginx.conf`. Do not also tack the key on the end as `(ENG7-####)` — only the front-prefix is canonical.
- **Advisory description** starts with the bare line `Jira: https://imh-internal.atlassian.net/browse/{JIRA_KEY}` followed by a blank line, before any heading or prose. Anyone landing on the advisory should reach the audit-trail ticket in zero clicks. Older "Internal tracking: …" first lines have been normalized to this form; do not reintroduce them.
- **TPF private PR title** uses the same `{JIRA_KEY}: {descriptive title}` prefix as the advisory summary. The two surfaces share the same identifier and must not drift.
- The bare `{JIRA_KEY}` (no descriptive suffix) is the title used on the **closed public PR** after relocation, by design — that is the search-engine signpost stripper, not the collaborator UX surface.
The operational details (how to PATCH an advisory, how to rename a TPF PR, how to make the rename idempotent when the source title already carries a prefix) are encoded in `.claude/skills/move-pr-to-private-ghsa/SKILL.md` caveat #18 and its Phase 2 / Phase 5 snippets. Read that skill before creating a new GHSA or amending an existing one.

## Dependency Management
- Use `yarn run upgrade:deps` to refresh JS packages and Composer libraries in one step; this enforces the PHP 7.4–8.5 constraint declared in `composer.json`.
- When running Composer directly, keep `composer update --with-all-dependencies` targeted at the repo root so the generated lock file honors the configured PHP platform (7.4).

## Working Files
- For ad-hoc agent scratch files (PR-comment bodies, JSON payloads for `gh api`, draft patches, intermediate tool output, etc.), write to `.cursor/working/` — it is covered by `.gitignore` (`.cursor/working/`).
- Do not write to `/tmp/` or to any path outside the project tree.
- Leave scratch files in place for traceability rather than deleting on task completion; the directory is gitignored so they will not pollute commits.
- **Prefix task-specific scratch files with the primary identifier(s) the user is working from** so concurrent task workflows don't collide in the directory. Always include the GitHub `{REPO}` slug when a PR is involved: two different repos can carry the same PR number (e.g., `BoldGrid/w3-total-cache#1` vs `BoldGrid/w3-total-cache-ghsa-rgpr-5m2g-gvh2#1`, both real and active in this project), and a bare `1-rereview-body.md` would clobber across them. `{REPO}` is the literal repo slug — the right-hand half of `org/repo`, lowercased (e.g., `w3-total-cache`, `w3-total-cache-ghsa-rgpr-5m2g-gvh2`). Use a lowercase `ghsa-` in the slug — even though GitHub displays advisory IDs as `GHSA-xxxx-xxxx-xxxx`, the repo slug is `w3-total-cache-ghsa-…` (all-lower) and the filename should match.
 - **PR-driven task** (e.g., reviewing or relocating a PR; no Jira yet) → `{REPO}-{PR}-{role}.{ext}` (e.g., `w3-total-cache-1313-pr-body.json`, `w3-total-cache-ghsa-rgpr-5m2g-gvh2-1-anchor-comment.md`).
 - **Jira-driven task** (e.g., ticket-scoped scratch with no PR yet) → `{KEY}-{role}.{ext}` (e.g., `ENG7-2908-summary.md`).
 - **Both relevant** (the common case — PR opened against a Jira ticket) → `{KEY}-{REPO}-{PR}-{role}.{ext}`. Examples:
   - TPF-side review: `ENG7-2908-w3-total-cache-ghsa-rgpr-5m2g-gvh2-1-rereview-body.md`
   - Public-PR-side scratch: `ENG7-2908-w3-total-cache-1313-pr-snapshot.json`, `ENG7-2908-w3-total-cache-1313-jira-cssjoe-comment.md`
   - File that spans a public PR AND its GHSA artifacts (move-pr-to-private-ghsa scratch): keep the public PR as the `{PR}` and put the GHSA slug in `{role}` — e.g., `ENG7-2908-w3-total-cache-1313-ghsa-rgpr-5m2g-gvh2-fork-response.json`. The {PR} is where the workflow originated; the GHSA reference is contextual metadata about what the file describes.
 - **GHSA-advisory-metadata task** (no PR — e.g., editing an advisory description) → `{KEY}-{REPO}-advisory-{role}.{ext}` where `{REPO}` is the GHSA TPF repo slug (e.g., `ENG7-2908-w3-total-cache-ghsa-rgpr-5m2g-gvh2-advisory-description.md`).
 - **GitHub-issue-only task** (no Jira, no PR — e.g., drafting a public bug-report issue) → `gh-issue-{N}-{REPO}-{role}.{ext}` (e.g., `gh-issue-1328-w3-total-cache-body.md`).
 - **Non-task-specific persistent agent references** (playbooks, conventions) → `PLAYBOOK-{topic}.md` or `CONVENTION-{topic}.md`.
 - **Cross-cutting inventory snapshots** that legitimately span every advisory or every PR (e.g., a one-shot dump of every draft GHSA's metadata) → `INVENTORY-{topic}.{ext}` (e.g., `INVENTORY-all-advisories-draft.json`).
- The one-off `rename-to-new-convention.sh` / `rename-pass2-loose-files.sh` scripts in `.cursor/working/` document how the directory was migrated to this convention on 2026-05-20. Read those for the JIRA→GHSA-suffix and JIRA→public-PR mapping tables before classifying any newly-discovered pre-convention scratch files.
- Long-lived agent playbooks are committed under `.claude/skills/{skill-name}/SKILL.md`. Read the relevant skill at the start of a matching task before improvising — skills encode caveats discovered the hard way on prior runs. Current skills:
 - `.claude/skills/pr-content-to-jira/SKILL.md` — move sensitive descriptive content (vuln scope, finding IDs, exploit-shaped review commentary) off a public GitHub PR and into an internal Jira ticket. Use when a PR has accumulated content that should not stay in the default public view and a parent Jira ticket exists.
 - `.claude/skills/move-pr-to-private-ghsa/SKILL.md` — move an in-progress public security-fix PR's branch into a GitHub Security Advisory's temporary private fork, then close + delete the original public PR. Use when the team decides remaining review and additional commits should happen privately. Pair with `pr-content-to-jira` when the public PR also has descriptive content to preserve.
 - `.claude/skills/repost-pr-reviews-to-tpf/SKILL.md` — repost GitHub review content (Copilot bot reviews, human-reviewer threads) from a closed public PR onto its GHSA temporary-private-fork PR, anchored to the source's `original_commit_id` at `original_line` so GitHub renders each comment as a historical outdated comment matching the original review UX exactly. Use after `move-pr-to-private-ghsa` to restore reviewer-thread continuity on the TPF — reviews/inline comments do not migrate with the commits, so the TPF starts from a blank Conversation tab without this follow-up. Attribution lives in an inline preface on every body.
 - `.claude/skills/analyze-w3tcqa-environment/SKILL.md` — map the W3TCQA AWS matrix (orchestrator `~/ci` / `qa/env`, EC2 `/share` layout, report artifacts, box naming, log triage). Use when analyzing uploaded `w3tcqa-ci.zip`, `summary.html`, per-box logs, or planning fixes to `qa/plugins` probe scripts. Implementation split: `IMPLEMENTATION-PLANS.md` in the same directory.
 - `.claude/skills/update-changelog/SKILL.md` — add or verify release changelog bullets in `readme.txt` and `changelog.txt` (plus optional Upgrade Notice). Use `bin/update-changelog.sh check|add|add-upgrade-notice`.
