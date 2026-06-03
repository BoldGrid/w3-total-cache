# W3TC Puppeteer Coverage — qa-secops-2026-q2

**Scope.** Branch `qa-secops-2026-q2` lands 60+ commits closing 142 vulnerability items, plus a comprehensive QA coverage build-out that takes the puppeteer suite from 85 specs to 124. Every feature, setting, engine, and extension visible on the W3TC admin sidebar now has at least page-render + form-save coverage, and every closed security fix has a UI-level regression spec. This document is the audit trail.

**Conventions.** Tests live in `qa/tests/<feature>/<scenario>.js`, share helpers from `qa/lib/{sys,w3tc,wp,dom,environment}.js`, and execute through the AWS-provisioned matrix in `qa/env/`. All specs in this round were written against the source state on this branch; the first lab run will surface selector / DOM drift on a small number of specs as expected.

---

## 1. Current test inventory (124 specs)

| Directory | Count | Notes |
|---|---|---|
| `activation/` | 2 | unchanged from baseline |
| `browsercache/` | 12 | unchanged |
| `cdn/` | 22 | +8 — 9 engine specs (down from 13 after cotendo/edgecast/att/akamai removal) + surgical regression + XSS / header-injection / OAuth |
| `cdnfsd/` | 3 | NEW |
| `dbcache/` | 6 | unchanged |
| `extensions/` | 8 | NEW (incl. AlwaysCached worker-auth regression) |
| `generic/` | 22 | +9 — security regressions + setup-guide happy path + nginx-emitter assertion |
| `minify/` | 16 | unchanged |
| `objectcache/` | 5 | +1 — rt9-12 test-button-method regression |
| `pagecache/` | 23 | unchanged |
| `pagespeed/` | 1 | NEW |
| `stats/` | 1 | NEW |
| `support/` | 1 | NEW |
| `userexperience/` | 2 | NEW |

39 net new specs (Phases 0–7 + AlwaysCached worker-auth + nginx plugin-dir deny, minus 4 specs for the cotendo/edgecast/att/akamai engines removed because their upstream services are gone). Baseline was 85; current is 124.

---

## 2. Feature surface map

Same as the original audit — admin pages, extensions, and admin-action handlers reachable from the UI. Now every entry below has at least one corresponding spec.

**Core admin pages (16)**

| Page slug | Spec(s) |
|---|---|
| `w3tc_dashboard` | covered via repeat-w3tc, util-admin-redirect, page-cache specs |
| `w3tc_general` | options.js, import-export.js, plus indirect from every engine spec |
| `w3tc_pgcache` | 23 pagecache specs |
| `w3tc_minify` | 16 minify specs |
| `w3tc_dbcache` | 6 dbcache specs |
| `w3tc_objectcache` | 5 objectcache specs |
| `w3tc_browsercache` | 12 browsercache specs |
| `w3tc_cdn` | 13 engine specs + ftp suite + surgical regressions |
| `w3tc_cdnfsd` | 3 CDNFSD engine specs |
| `w3tc_userexperience` | userexperience/lazyload-settings.js, defer-scripts.js |
| `w3tc_pagespeed` | pagespeed/dashboard-render.js |
| `w3tc_extensions` | 7 extension specs |
| `w3tc_stats` | stats/page-render.js |
| `w3tc_support` | support/page-render.js |
| `w3tc_cachegroups` | user-agent-groups.js, referrer-groups-*.js, cachegroups-xss-regression.js |
| `w3tc_setup_guide` | setup-guide-happy-path.js, setupguide-subscriber-deny.js |

**Extensions (10)**

| Extension | Spec |
|---|---|
| AlwaysCached | extensions/alwayscached-settings.js |
| CloudFlare | extensions/cloudflare-settings.js |
| NewRelic | extensions/newrelic-settings.js |
| Swarmify | extensions/swarmify-settings.js |
| AMP | extensions/amp-settings.js |
| ImageService | extensions/imageservice-activate.js (activation-only — no settings page exists) |
| Genesis / FragmentCache | extensions/genesis-fragmentcache-smoke.js |
| Wpml | passive — no UI to test |
| WordPressSeo | passive — no UI to test |

**CDN engines (11) — all have form-save coverage**

ftp · s3 · s3_compatible · cf · cf2 · rscf · azure · azuremi · mirror · bunnycdn · google_drive (OAuth binding only — see Phase 0). The akamai / cotendo / edgecast / att engines were removed from the plugin entirely because their upstream services are gone (Akamai CCU SOAP v1 retired with no v3 / EdgeGrid rewrite, Cotendo acquired-and-shut by Akamai, EdgeCast→Edgio bankruptcy Jan 2025, AT&T CDN a white-label of EdgeCast); an in-plugin migration shim auto-disables CDN on installs that still carry one of those engines, purges the orphaned `cdn.<engine>.*` keys, and surfaces an admin notice on next admin load.

**CDNFSD engines (3)** — cloudfront · bunnycdn · transparentcdn

---

## 3. Security regression matrix

Every security fix that landed on this branch and reaches an admin UI surface has a puppeteer regression spec. Format: rt9-* finding → spec.

| Finding | Spec |
|---|---|
| rt9-12 (memcached/redis test-button password leak) | objectcache/test-button-method.js |
| rt9-28 (sample-config files reachable) | generic/sample-config-deny.js |
| rt9-98 (Util_Admin::redirect open-redirect) | generic/util-admin-redirect.js |
| rt9-178 (w3tc_rewrite_test probe token) | generic/rewrite-test-probe-token.js |
| rt9-180 sub-C (multisite blogmap rate limit) | helper clearBlogmapRateLimit + user-signup-subdomain.js |
| rt9-210 (CDN credential page-boundary) | cdn/credential-page-boundary.js |
| rt9-233 (Google Drive OAuth state binding) | cdn/google-drive-oauth-binding.js |
| sec-xss CacheGroups UserAgent | generic/cachegroups-xss-regression.js |
| sec-xss BunnyCDN exception (PR E) | cdn/bunnycdn-exception-xss.js |
| sec-header-injection X-W3TC-CDN | cdn/header-injection.js |
| sec-missing-auth-setupguide-ajax | generic/setupguide-subscriber-deny.js |
| sec-missing-auth-public-endpoints (pub/sns.php) | generic/public-endpoint-deny.js |
| sec-missing-auth-public-endpoints (AlwaysCached worker) | extensions/alwayscached-worker-auth.js |
| sec-missing-auth-public-endpoints (nginx pub/+ini/ deny block) | generic/nginx-plugin-dir-deny.js |
| Admin-note dismiss endpoint cap-gate | generic/dismiss-note-cap.js |

---

## 4. Helpers + scaffolding added this round

`qa/lib/w3tc.js`:

- `clearBlogmapRateLimit(pPage)` — wipes the `_transient_w3tc_blogmap_register_rate_*` rows so multisite specs can register >5 brand-new blog URLs in one 60s window without tripping the rt9-180 sub-C cap.
- `assertEngineSaveRoundTrip(pPage, family, engine, values)` — common CDN / CDNFSD engine save + readback contract. Activates the engine via the General page, writes config keys (including secret-flagged keys) via `setOptionInternal`, reads back via wp-cli, asserts each round-tripped. Used by all 16 engine specs (13 CDN + 3 CDNFSD).

`qa/lib/sys.js`:

- `skipIfMissingEnv(testCtx, envKeys)` — `testCtx.skip()` when any required env-var is unset. Lets engine and extension specs gate their live-API path on credential presence without failing in credential-less CI runs.

`qa/plugins/generic/clear-blogmap-rate.php` (new) — DB-level transient delete fixture for `clearBlogmapRateLimit()`.

---

## 5. Likely-broken existing tests — status carry-over

The original §4 hypotheses, updated:

| Spec(s) | Status |
|---|---|
| `generic/user-signup-subdomain.js` | Mitigated — calls `clearBlogmapRateLimit()` before per-blog asserts. `user-signup-single.js` left untouched (single-site doesn't trigger the rate limit). |
| `cdn/ftp-test-button.js` | Verified safe — POSTs via jQuery.post; rt9-12 method gate is satisfied. |
| `cdn/ftp-*.js` / `file-upload.js` / etc. | Verified safe — credential page-boundary gate keys on `$this->_page === 'w3tc_cdn'` which these tests use. |
| `pagecache/dynamic-{gzip,late-init,nogzip}.js`, `browsercache/check-mfunc-gzip.js` | Already updated on this branch to use `call:slug` dispatcher. |
| `generic/import-export.js` | Status unchanged — ConfigKeysSchema schema-import path. Worth a live-box pass to confirm. |
| Setup Guide skip | Verified safe — wizard-skip selector is intact and the SetupGuide cap gate doesn't affect admin sessions. |

---

## 6. Out-of-scope (intentional, deferred)

The following gaps remain by design — they require infrastructure the CI matrix doesn't currently provide. None are in this branch's scope.

- **Live external-API end-to-end tests** for engines and extensions that need real accounts:
  - CDN: AWS S3 / CloudFront, Azure Blob Storage / AzureMI, Rackspace CloudFiles, Akamai, Cotendo, EdgeCast, AT&T, BunnyCDN
  - CDNFSD: CloudFront FSD, BunnyCDN FSD, TransparentCDN
  - Extensions: CloudFlare zone-picker live flow, NewRelic Configure popup, ImageService SaaS auth, Swarmify validation
  - PageSpeed Insights live API call (requires Google PSI OAuth2 access token)

  Each engine spec exercises the form-save / config-loader path with fake credentials. Live API tests can be added as a follow-up pass when the lab provisions credentials; the existing specs surface env-var gates (`CLOUDFLARE_EMAIL`, `CLOUDFLARE_KEY`, `NEWRELIC_API_KEY`, etc.) via `sys.skipIfMissingEnv`.

- **Pro-license-gated runtime paths**: Delay Scripts (covered by spec with a graceful skip), Lazy Load Google Maps, the full UsageStatistics dashboard, Preload Requests / Remove CSS-JS sub-pages on the UserExperience side. The form-save side is covered; the actual feature exercise behind `Util_Environment::is_w3tc_pro()` needs a Pro-license env.

- **Performance / regression baselines** (cache hit ratios, response time deltas, payload-size assertions) — separate effort, not a unit-test concern.

- **Cross-browser sweep** — the suite is Chromium-only via puppeteer.

---

## 7. Verification (per phase)

Every spec passes `node --check`; every PHP fixture passes `php -l`. None have been executed end-to-end on a live AWS box yet — the first lab run is the source of truth for selector / DOM drift. Expected adjustments on first run:

- `util-admin-redirect.js` uses `#wp-admin-bar-w3tc_flush_all a` (always in the bar when W3TC is enabled); per-module flush links such as `#wp-admin-bar-w3tc_flush_browsercache a` are config-gated and absent on stock QA boxes.
- The `_wpnonce` hex shape in `google-drive-oauth-binding.js` should be longer than 8 chars on some WP versions; the `[a-f0-9]+` regex should match either, but worth verifying.
- The `#mobile_groups_<group>_agents` selector in `cachegroups-xss-regression.js` depends on the CacheGroups page mounting the group block server-side after a POST — verified by reading the view template, but the DOM-render timing on slow boxes is worth a look.
- Several extension specs (`alwayscached-settings.js`, `cloudflare-settings.js`, `newrelic-settings.js`) may need to wait longer for their settings pages to mount when the extension is freshly activated; the 30s default Puppeteer timeout should suffice but it's the most likely flakiness source.
- The `expect([403, 404]).contains(r.statusCode)` allowances in `sample-config-deny.js` and `public-endpoint-deny.js` deliberately accept either Apache-deny or missing-file-404 — the nginx skip has been lifted now that `Generic_Environment::get_required_rules()` emits the equivalent `location ~*` deny block. The static-emission counterpart is `generic/nginx-plugin-dir-deny.js`.

The full sweep took 7 commits over Phases 0–7. Each commit is independently runnable, so any regression surfaced by the lab signal can be reverted in isolation.

---

## 8. Sweep commit history

| Commit | Phase | Subject |
|---|---|---|
| Phase 0 | `e7ab383b` | QA: land surgical regression specs for rt9-12/98/178/210/233 |
| Phase 1 | `994a01aa` | QA: regression specs for 7 remaining sec-fix UI gaps |
| Phase 2 | `294489e4` | QA: page-render + form-save specs for 5 zero-tested admin pages |
| Phase 3 | `673e9d8a` | QA: setup-guide wizard happy-path spec |
| Phase 4 | `733c1f1d` | QA: extension settings + activation specs (7 extensions) |
| Phase 5 | `db43cebb` | QA: form-save coverage for 13 CDN engines (env-gated credentials) |
| Phase 6 | `2e05e374` | QA: form-save coverage for 3 CDNFSD engines |
| Phase 7 | (this commit) | QA: COVERAGE.md — close-out of the comprehensive coverage sweep |
