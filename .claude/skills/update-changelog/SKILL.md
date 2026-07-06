---
name: update-changelog
description: Add or verify W3 Total Cache release changelog bullets in readme.txt and changelog.txt. Use when preparing a release branch, adding changelog lines for a new version, syncing readme.txt with changelog.txt, or when the user mentions readme changelog, changelog.txt, or upgrade notice text.
---

# Update changelog (readme.txt + changelog.txt)

W3TC ships two public changelog surfaces:

| File | Contents |
|------|----------|
| `readme.txt` | `== Changelog ==` (WordPress.org) and optional `== Upgrade Notice ==` |
| `changelog.txt` | Full version history (`=== W3 Total Cache Changelog ===` header, then every version) |

**Rule:** Changelog bullets for a version must be **identical** in both files. `changelog.txt` does not include Upgrade Notice text.

Release-branch work may edit these files even though `AGENTS.md` normally defers `readme.txt` to the build process.

## When to use

- User asks to add changelog lines for a new version
- Preparing `release-X.Y.Z` branch notes before tagging
- Verifying `readme.txt` and `changelog.txt` stayed in sync after manual edits

## Bullet format

Follow existing entries:

```
* Fix: Area: Short user-facing description
* Feature: ...
* Update: ...
* Enhancement: ...
* Security: ...   (only on public surfaces when already approved for release notes)
```

- One bullet per line, prefix `* `
- Newest version first in both files
- Derive bullets from `git log <prev-tag>..HEAD --no-merges` on the release branch
- Omit routine `Update deps` unless a dependency change is user-visible
- Follow `AGENTS.md` security vocabulary rules on all public text

## Workflow

```
Changelog update:
- [ ] Identify version (w3-total-cache.php / w3-total-cache-api.php / readme Stable tag)
- [ ] Draft bullets from commits since previous tag
- [ ] Add bullets to readme.txt under == Changelog ==
- [ ] Add the same bullets to changelog.txt (after the title block, before prior version)
- [ ] Add Upgrade Notice in readme.txt when warranted (patch/security/regression)
- [ ] Run bin/update-changelog.sh check <version>
```

### Option A — edit both files, then verify (preferred when bullets need review)

1. Insert under `== Changelog ==` in `readme.txt`:

```text
= X.Y.Z =
* Fix: ...
```

2. Insert the **same block** in `changelog.txt` immediately after the header blank line (before the previous version heading).

3. Optionally add under `== Upgrade Notice ==` in `readme.txt` only:

```text
= X.Y.Z =
One or two sentences encouraging update when appropriate.
```

4. Verify:

```bash
bin/update-changelog.sh check X.Y.Z
```

### Option B — script inserts both files from a bullets file

Write bullets to `.cursor/working/{KEY}-changelog-bullets.txt` (one `* ` line each), then:

```bash
bin/update-changelog.sh add X.Y.Z .cursor/working/{KEY}-changelog-bullets.txt
bin/update-changelog.sh add-upgrade-notice X.Y.Z "Short upgrade notice for WordPress.org."
```

`add` refuses to run if the version already exists. Edit manually or remove the version block first.

## Examples

**Patch release (2.10.1-style):**

```text
= 2.10.1 =
* Fix: General Settings: "The link you followed has expired" when emptying all caches
* Fix: Redis/Memcached/CDN: Restore connection handling after 2.10.0 at-rest credential encryption
```

Upgrade notice (readme.txt only):

```text
= 2.10.1 =
This update resolves regressions introduced in 2.10.0. Users who upgraded to 2.10.0 are encouraged to update.
```

## Related release files

On a full release branch, also align (outside this skill's scope unless requested):

- `w3-total-cache.php` `Version:` header
- `w3-total-cache-api.php` `W3TC_VERSION`
- `readme.txt` `Stable tag:` line

Do **not** bump version numbers or edit POT/`readme.txt` stable tag on `master` per project policy — only on release branches when explicitly requested.

## Command reference

```bash
bin/update-changelog.sh check <version>              # compare bullets in both files
bin/update-changelog.sh add <version> [bullets-file] # insert into both (stdin if file omitted)
bin/update-changelog.sh add-upgrade-notice <version> "<text>"
bin/update-changelog.sh --help
```
