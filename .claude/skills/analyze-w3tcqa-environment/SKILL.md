---
name: analyze-w3tcqa-environment
description: Maps the W3TCQA AWS test matrix (orchestrator ~/ci, EC2 boxes, /share paths, report artifacts, box naming, log formats). Use when analyzing AWS QA failures, uploaded w3tcqa-ci.zip bundles, summary.html, per-box logs, restore-final state, or planning fixes to qa/plugins probe scripts.
---

# Analyze W3TCQA environment

W3TCQA runs Mocha/Puppeteer specs across a matrix of EC2 boxes (HTTP server × PHP × WordPress layout × cache engine). This skill orients agents on **where code lives**, **how artifacts are named**, and **how to triage failures** without re-deriving the layout each time.

## Quick orientation

| Layer | Typical path | Role |
|-------|----------------|------|
| Orchestrator repo | `~/ci/` on controller EC2 (= `qa/env/` in the plugin repo) | Spawn boxes, upload plugin zip to S3, collect logs, build `summary.html` |
| Plugin under test | `qa/env/working/w3tc/` (orchestrator) → S3 zip → `/share/w3tc/` (box) | Branch built and mounted into WordPress |
| Test specs | `qa/tests/**/*.js` → on box: `/root/w3tcqa/` (symlink to `qa/tests`) | Mocha tests |
| Box scripts | `qa/env/scripts/` → on box: `/share/scripts/` | Init, restore, `w3test`, `batch-test` |
| Per-subenv vars | `boxes/{box}/environments/*.sh` → `/share/environments/` | `W3D_*` exports for each matrix cell |
| Reports (orchestrator) | `qa/env/working/reports/{box}/*.log` | Collected after each run |
| Reports (by test) | `qa/env/working/reports-by-test/{spec}/{pass\|fail}-{box}@{subenv}` | Copied by `800-report-generate` |

Uploaded **`w3tcqa-ci.zip`** artifacts are usually the orchestrator's **full plugin clone** at top-level `ci/` (same tree as GitHub `w3-total-cache`) plus `ci/qa/env/working/` report outputs from one run. They do **not** include live box disks (`backup-final-wp-sandbox`, MySQL dumps).

For path maps, box naming, log markers, and orchestration flow details, see [REFERENCE.md](REFERENCE.md).

For parallel fix tracks (QA probes, Disk Enhanced, isolated failures), see [IMPLEMENTATION-PLANS.md](IMPLEMENTATION-PLANS.md).

---

## When to read this skill

- User uploaded `w3tcqa-ci.zip`, `summary.html`, or per-box log ZIPs
- Triaging AWS matrix failures (`20260610w-AWS-testing.txt` style summaries)
- Questions about `~/ci/`, `/share/w3tc`, `restore-final`, or `W3D_*` variables
- Planning changes to `qa/plugins/*.php` probe scripts vs plugin cache keys
- Correlating **footer-pass** vs **probe-fail** patterns on the same box

---

## Analysis workflow

Copy this checklist and tick as you go:

```
W3TCQA triage:
- [ ] Identify artifact type (summary only | full ci zip | single-box logs | live box)
- [ ] List failing spec families from summary.html (grep ^<p> or spec headings)
- [ ] Sample one fail log + one pass log on same box/spec family
- [ ] Classify failure mode (probe | rewrite | setOptions | env corruption | skip)
- [ ] Check plugin revision (git log / commit in ci zip)
- [ ] Note box dimensions (apache vs nginx, file_generic, https, subdomain, etc.)
- [ ] Draft fix owner (qa probe | plugin pgcache | env script | flaky retry)
```

### Step 1 — Locate artifacts

| User provided | Open first |
|---------------|------------|
| `w3tcqa-ci.zip` | Extract to `.cursor/working/{date}-w3tcqa-ci/`; read `ci/qa/env/working/reports/summary.html` |
| `summary.html` / pasted HTML | `grep -E '^[a-z]|testResult'`; count `<li>` for failure volume |
| `{box}-{NN}.zip` | Per-worker logs from one box; unzip under `apache-php74-wp69-single-zips/` |
| Single `.log` | Parse `testResultFailed|Passed|Skipped` footer; read last AssertionError block |

Orchestrator log filename pattern:

`{spec-path-with-slash-as-dash}@{subenvironment}.log`

Example: `pagecache/basic.js` + `blog-0-pagecache-file` → `pagecache-basic@blog-0-pagecache-file.log`

### Step 2 — Parse summary.html

- Each `<p>` block is a **spec family** (dirname + test file stem, slashes → hyphens in logs).
- Each `<li>` is one **environment run**: `{box} {subenv}` with link to log.
- Failure count ≈ number of `<li>` under failed specs (plus `none-executed` if a box produced no logs).

Generate a spec failure histogram:

```bash
grep '^<p>' summary.html | sed 's/<[^>]*>//g' | grep -v '^$'
```

### Step 3 — Compare pass vs fail on same box

High-signal pattern (page-cache regressions):

| Often passes | Often fails | Implication |
|--------------|-------------|-------------|
| `pagecache/cache-hit-single-footer` | `pagecache/basic` | Browser footer OK; `qa/plugins/cache-entry.php` probe cannot read backend entry |
| `pagecache/change-post` | `browsercache/compression` | Caching works in browser; `compression.php` backend lookup wrong |
| `pagecache/user-roles` | `generic/user-agent-groups` | Footer checks OK; `user-agent-groups.php` probe keys wrong |

If **both** footer and probe fail, suspect pgcache not writing (drop-in, rules, reject reason) before probe key mismatch.

### Step 4 — Read a fail log structure

Typical sections (top to bottom):

1. `Restore wp state - to final` — `restore-final.rb` reset
2. `Restarting http server` — source mount or rules change
3. `set options` — Puppeteer admin save + DOM verification
4. Mocha assertion or `testResultFailed` marker

Log outcome markers (written by `w3test`):

| Marker | Meaning |
|--------|---------|
| `testResultPassed` | Spec completed success |
| `testResultFailed` | Assertion or hook failure |
| `testResultSkipped` | Environment filter excluded run |

Probe failures often show `checking wp.…` (from `cache-entry.php` line 46) without `Page Caching using …` in the body → miss path or wrong cache key.

### Step 5 — Map box name to dimensions

Box slug pattern: `{http}-{php}{-port|-https?}-{wp-layout}[-{variant}]`

Examples:

- `apache-php74-wp69-single` — Apache, PHP 7.4, WP 6.9, single site, port 80
- `nginx-php85-wp70-pathwp-subdir` — nginx, PHP 8.5, WP in `/wp/` subdir multisite
- `apache-php85-https-wp70-subdomain` — HTTPS, subdomain multisite

Subenvironment suffix (after `@` in log name): `{blog}-{pagecache|cache}-{engine}`

Examples: `blog-0-pagecache-file`, `blog-1-pagecache-redis`, `blog-2-cache-memcached`

Parse `boxes/{box}/environments/*.sh` in the repo (or `ci/qa/env/boxes/` in a zip if present) for exact `W3D_*` values.

### Step 6 — Correlate with plugin revision

On orchestrator or in `ci/` zip:

```bash
git log -1 --oneline
git branch -v
```

Boxes run whatever was in `working/w3tc.zip` at upload time (`350-upload-w3tc`), fetched to `/share/w3tc.zip` during `box.init`.

---

## Failure mode taxonomy

Use this to split work across agents:

| Mode | Symptoms | First files |
|------|----------|-------------|
| **Backend probe** | `pageCacheEntryChange`, `plain not found`, `user-agent-groups` `error` | `qa/plugins/cache-entry.php`, `compression.php`, `user-agent-groups.php` |
| **Disk Enhanced / rewrite** | `w3tc_php: executed`, missing `page_enhanced/*.html` | `PgCache_Environment.php`, Apache htaccess templates, `accept-qs.js` |
| **Admin / setOptions** | `failed to find _wpnonce`, wizard not skipped | `qa/lib/w3tc.js`, setup guide state in backup-final |
| **Box state corruption** | `Dispatcher not found` in `db.php`, `ERR_CONNECTION_REFUSED`, mysql errors mid-spec | `restore-final.rb`, `w3tc-mount.sh`, engine flip tests (`generic/options`) |
| **Flaky / infra** | pass on retry, `boxValidSuccess` missing, SSH timeout | `400-run-tests` retry loop, AWS AMI |

---

## Orchestrator commands (reference)

Run from `qa/env/` on controller (paths relative to that directory):

| Script | Purpose |
|--------|---------|
| `./100-generate-envs` | Regenerate `amis/`, `boxes/` descriptors |
| `./w3tc-clone` | Clone plugin into `working/w3tc` |
| `./350-upload-w3tc` | Zip + S3 upload; writes `working/w3tc_zip_url.yml` |
| `./400-run-tests N M` | N parallel box instances, M threads |
| `./800-report-generate` | Build `working/reports/summary.html` |
| `./900-report-send` | Email report |

---

## Scratch file convention

When triaging a run, prefix scratch files per AGENTS.md:

- Full CI zip: `20260610w-w3tcqa-ci-summary-notes.md`
- Single spec: `ENG7-XXXX-w3tcqa-pagecache-basic-triage.md` (if Jira-linked)

Store under `.cursor/working/`, not `/tmp/`.

---

## Additional resources

- [REFERENCE.md](REFERENCE.md) — full path map, mount lifecycle, `W3D_*` index, zip layouts
- [IMPLEMENTATION-PLANS.md](IMPLEMENTATION-PLANS.md) — parallel agent work packages for common fix tracks
- `scripts/summary-spec-counts.sh` — failure histogram from `summary.html`
