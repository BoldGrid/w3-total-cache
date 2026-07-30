# W3TCQA environment reference

## Three machines in the system

### 1. Orchestrator (controller EC2)

- Operators often call the clone **`~/ci/`** — it is the **`w3-total-cache` git repository** (or a copy) whose QA subtree is `qa/env/`.
- Working directory for runs: `qa/env/` (not the plugin root).
- Key outputs: `working/reports/`, `working/reports-by-test/`, `working/w3tc/`, `working/w3tc_zip_url.yml`.

### 2. Test box (matrix EC2 instance)

- WordPress docroot: `/var/www/wp-sandbox/` (`W3D_WP_PATH`).
- Plugin mount target: `${W3D_WP_PLUGINS_PATH}w3-total-cache/` — populated by `w3tc-mount.sh` from `/share/w3tc/`.
- Shared QA layout under `/share/`:

| Path | Contents |
|------|----------|
| `/share/w3tc/` | Unzipped plugin from S3 (same as `working/w3tc` on orchestrator) |
| `/share/scripts/` | Copy of `qa/env/scripts/` (+ `init-box/*` promoted at box init) |
| `/share/environments/` | `export W3D_*` fragments; merged per subenv |
| `/share/reports/` | Per-spec `.log` files during run |
| `/root/w3tcqa/` | Symlink to `qa/tests` (see `800-w3tc.sh`) |

- Backup snapshots (not in CI zip):
  - `/var/www/backup-final-wp-sandbox/` + `/var/www/backup-final.sql` — post-activate golden state
  - `/var/www/backup-w3tc-inactive-wp-sandbox/` — pre-W3TC state

### 3. Developer machine / agent workspace

- Plugin repo: `w3-total-cache/` with `qa/` subtree.
- Uploaded artifacts: extract to `.cursor/working/`.

---

## Orchestrator `qa/env/` layout

```
qa/env/
├── 100-generate-envs      # Ruby: builds amis/, boxes/
├── 350-upload-w3tc        # Zip working/w3tc → S3
├── 400-run-tests          # Python: EC2 matrix execution
├── 800-report-generate    # Ruby: summary.html + reports-by-test/
├── 900-report-send
├── w3tc-clone             # Clone into working/w3tc
├── lib/                   # aws.py, box.py, shell.py, pid.py
├── amis/{ami-name}/       # AMI build descriptors
├── boxes/{box-name}/      # Per-matrix-cell descriptor
│   ├── export.sh          # Box-level W3D_* exports
│   ├── vars.yml           # YAML for Python (AMI id lookup)
│   ├── environments/      # Subenv fragments (*.sh)
│   └── Vagrantfile        # Local vagrant (optional)
├── scripts/               # Copied to /share/scripts on boxes
└── working/               # Run artifacts (often bundled in ci zip)
    ├── w3tc/              # Plugin clone used for zip
    ├── w3tc_zip_url.yml   # S3 URL for boxes
    ├── reports/{box}/*.log
    ├── reports-by-test/{spec}/{pass|fail}-{box}@{subenv}
    └── summary.html
```

---

## Box initialization sequence (`box.init` in `lib/box.py`)

1. SSH fixups, copy `boxes/{box}/export.sh` → `/etc/environment`
2. Copy `boxes/{box}/environments/*` → `/share/environments/`
3. `wget` plugin zip from `W3D_W3TC_ZIP_URL`, unzip to `/share/w3tc`
4. Copy `qa/env/scripts` → `/share/scripts`
5. Run init scripts: `400-http-server`, `600-wp-cli`, `700-wordpress`, `800-w3tc`, `905-validate-wordpress`

`800-w3tc.sh` highlights:

- Sets `W3TC_DEBUG` + `W3TC_DEVELOPER` in `wp-config.php`
- Activates plugin via wp-cli
- Optional `755-w3tcqa-php-output-buffering.sh` when `W3D_QA_PHP_OUTPUT_BUFFERING_OFF=1`
- Creates `backup-final-wp-sandbox` + `backup-final.sql` after activation

---

## Per-test lifecycle (`qa/lib/sys.js`)

Each Mocha spec `before`: `restore-final.rb` → rsync golden WP + reload DB → restart HTTP if plugin files changed.

Typical test flow:

1. Admin login (separate browser from frontend `page`)
2. `setOptions` via Puppeteer (or `setOptionInternal` for secrets)
3. `afterRulesChange` — nginx/litespeed restart HTTP; Apache often no-op
4. Frontend `page` visits + assertions

---

## Test discovery and environments (`w3test`)

- Specs declare matrix in block comment: `/**environments: multiply(...) */`
- `w3test` evals that expression; loads `/share/environments/{name}.sh` files
- Helpers like `environments('blog')` glob `blog-*.sh`; `environments('pagecache')` glob `pagecache-*.sh`
- `multiply(a,b)` cartesian-products subenv keys and concatenates source files

Log naming (`w3test`):

```
print_filename = test_path_without_js + '@' + environment_name
file_name      = print_filename.gsub('/', '-') + '.log'
```

---

## Report collection (`400-run-tests` → `stop_box`)

1. On box: `zip -j /share/reports.zip ./reports`
2. SCP to orchestrator: `working/{box_instance_name}.zip` (e.g. `apache-php74-wp69-single-00.zip`)
3. Unzip into `working/reports/{box}/`

`800-report-generate` scans each log for `testResultFailed|Passed|Skipped` and builds:

- `summary.html` — failed runs only (with links)
- `reports-by-test/{spec}/pass|fail-{box}@{subenv}` — copies for histograms

---

## `w3tcqa-ci.zip` layouts

**Type A — Orchestrator bundle (common):**

```
ci/                          # Full plugin repo at run revision
ci/qa/env/working/reports/   # All box logs
ci/qa/env/working/summary.html
ci/qa/env/a1.log             # Controller orchestration log
```

`ci/` root contains plugin PHP files (`PgCache_*.php`, etc.) — not a separate subfolder.

**Type B — Single-box worker zip:**

```
pagecache-basic@blog-0-pagecache-file.log
...
```

From `reports.zip` flat `-j` unzip (no box prefix in filename).

**Type C — Pasted summary:**

HTML only — same content as `working/reports/summary.html`.

---

## Common `W3D_*` variables

| Variable | Used for |
|----------|----------|
| `W3D_BOX_NAME` | Box slug |
| `W3D_HTTP_SERVER` | `apache`, `nginx`, `litespeed` |
| `W3D_PHP_VERSION` | e.g. `7.4`, `8.5` |
| `W3D_WP_NETWORK` | `single`, `subdomain`, `subdir` |
| `W3D_WP_PATH` | Filesystem path to WP root |
| `W3D_WP_CONTENT_PATH` | `wp-content/` path with trailing slash |
| `W3D_WP_BLOG_*` | Per-blog URLs, IDs, hosts (subenv) |
| `W3D_CACHE_ENGINE_LABEL` | `file`, `file_generic`, `redis`, … |
| `W3D_CACHE_ENGINE_NAME` | Human label for footer assertions |
| `W3D_HTTP_SERVER_SCHEME` | `http` or `https` |
| `W3D_HTTP_SERVER_PORT` | `80`, `8080`, … |

Tests read these via `qa/lib/environment.js`.

---

## QA backend probe scripts

Used by specs to mutate or inspect cache outside Puppeteer:

| File | Called by |
|------|-----------|
| `qa/plugins/cache-entry.php` | `w3tc.pageCacheEntryChange` |
| `qa/plugins/browsercache/compression.php` | `browsercache/compression.js` |
| `qa/plugins/generic/user-agent-groups.php` | `generic/user-agent-groups.js` |

Copied to WP root or invoked via `blogSiteUrl` + script name.

**Known limitation:** probes use simplified keys (`md5(host+path)` + suffixes) vs `PgCache_ContentGrabber::_get_page_key()` — footer-based specs may pass while probes fail.

---

## Utility script

From repo root:

```bash
.claude/skills/analyze-w3tcqa-environment/scripts/summary-spec-counts.sh \
  path/to/summary.html
```

Counts `<li>` entries in the **Failed tests** section only (stops at `<h1>All tests</h1>`).

---

## Useful grep patterns

```bash
# Failing spec families in summary
grep '^<p>' qa/env/working/reports/summary.html

# All fail logs for one box
ls qa/env/working/reports/apache-php74-wp69-single/* | wc -l
grep -l testResultFailed qa/env/working/reports/apache-php74-wp69-single/*

# Pass vs fail for one spec across matrix
ls qa/env/working/reports-by-test/pagecache-basic/

# Probe miss string
grep -r 'no entry found' qa/env/working/reports/apache-php74-wp69-single/
```
