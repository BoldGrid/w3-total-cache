# Split fidelity — W3TCQA two-product runs

Local HTTPS smoke (Layer 1–2) uses hub `wp-qa-smoke` and the manifests in `internal-rt-tools/.cursor/projects/w3-total-cache/`. Engine/runtime fidelity (Layer 3) is this Puppeteer suite. Run it **twice**.

## Community zip (companion off)

1. Do **not** set `W3D_W3TC_PRO_COMPANION`.
2. Box init (`800-w3tc.sh`) activates only `w3-total-cache` and exports `W3D_W3TC_PRODUCT=community`.
3. Free families must pass: `activation/`, `browsercache/`, `cdn/` (non-FSD), `dbcache/`, `minify/`, `objectcache/`, `pagecache/`, `generic/`, `pagespeed/`, `support/`, free `extensions/` (cloudflare, newrelic, swarmify, amp, imageservice).
4. Pro specs call `sys.skipIfNotPro()` and skip: `cdnfsd/`, `stats/`, `userexperience/defer-scripts.js`, `extensions/alwayscached-*`, `extensions/genesis-fragmentcache-smoke.js`.

Compare failures to the last live-master AWS run on the **same** plugin version, not a different tag.

## Community + Pro

1. Upload the companion tree to `/share/w3tc-pro` (must contain `w3-total-cache-pro.php`; folder name on the box is `w3-total-cache-pro`).
2. Set `W3D_W3TC_PRO_COMPANION=1` on the box (environment file).
3. `800-w3tc.sh` copies/activates the companion, defines `W3TC_PRO` for QA-only unlock, exports `W3D_W3TC_PRODUCT=pro`.
4. Expect the full 124-spec inventory in `COVERAGE.md` to pass (Pro specs no longer skip).

`W3TC_PRO` on the box is a local testing override, not a customer unlock. Do not ship that constant.

## Local hub smoke

```
wp-qa-smoke --site main --manifest …/w3-total-cache/smoke-paths.yaml --json --out-dir ~/tmp/w3tc-split-smoke-community
wp-qa-smoke --site main --manifest …/w3-total-cache/smoke-paths-pro.yaml --json --out-dir ~/tmp/w3tc-split-smoke-pro-only
```

Community: shared pages 200; Pro-only slugs 403 without a fatal. Community+Pro: shared pages 200; `w3tc_stats` and `w3tc_fragmentcache` 200. There is no standalone `w3tc_cdnfsd` admin page (FSD is on General / CDN).
