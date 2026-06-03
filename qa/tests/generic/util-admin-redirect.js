/**
 * File: qa/tests/generic/util-admin-redirect.js
 *
 * rt9-98 puppeteer regression — open redirect via `?redirect=` on
 * any handler that completes via `Util_Admin::redirect()`.
 *
 * Before rt9-98, `Util_Admin::redirect()` read `?redirect=` from
 * `$_REQUEST` and passed the raw value to `Util_Environment::redirect()`,
 * which emits a raw `Location:` header. An admin tricked into clicking
 * a crafted URL (e.g. an "empty cache" link with
 * `?redirect=https://evil.example/`) followed the redirect off-host
 * with no host validation — a phishing-grade open redirect, and a
 * chain-amplifier when paired with any downstream same-origin XSS.
 *
 * The fix passes `$url` through
 * `wp_validate_redirect( $url, admin_url() )`. WP returns the URL
 * unchanged when it points at an allowed host (the WP install by
 * default), otherwise returns the `admin_url()` fallback. Relative
 * paths pass through because hostless URLs are treated as same-host.
 *
 * Posture: feature side asserts a legitimate same-host redirect
 * (relative `admin.php?page=...`) is preserved. Regression side
 * asserts that an off-host redirect target is replaced with the
 * admin URL — the browser must NOT land on `evil.example`.
 *
 * Implementation: trigger `w3tc_flush_all` (always present in the
 * admin bar when the plugin is enabled; its `_redirect_after_flush()`
 * calls `Util_Admin::redirect()`), grabbing its admin-bar URL + nonce
 * from the dashboard page. Both the legitimate and malicious test
 * cases share the same trigger; only the `redirect=` query value changes.
 *
 * Note: `w3tc_flush_browser_cache` also routes through
 * `Util_Admin::redirect()`, but its admin-bar item is omitted unless
 * browser-cache "replace" options are on — not true on a stock QA box.
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');

const env  = requireRoot('lib/environment');
const sys  = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: environments('blog') */

const EVIL_HOST = 'evil.example';

describe('rt9-98 Util_Admin::redirect open-redirect regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Helper: load the dashboard, grab the admin-bar flush-browser-
	 * cache link, and return its URL. The URL embeds the w3tc nonce
	 * so we can append `&redirect=...` without needing to re-mint it.
	 */
	async function getFlushRedirectTriggerUrl() {
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
			{waitUntil: 'domcontentloaded'});

		if (await adminPage.$('#w3tc-wizard-skip') != null) {
			let wizardSkip = '#w3tc-wizard-skip';
			await Promise.all([
				adminPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
				adminPage.waitForNavigation({timeout: 300000})
			]);
			await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
				{waitUntil: 'domcontentloaded'});
		}

		/**
		 * "Purge All Caches" is always in the admin bar when W3TC is
		 * enabled (unlike per-module flushes gated on module config).
		 */
		let href = await adminPage.evaluate(() => {
			let a = document.querySelector('#wp-admin-bar-w3tc_flush_all a');
			return a ? a.href : null;
		});

		expect(href).is.not.null;
		log.log('w3tc_flush_all trigger URL: ' + href);
		return href;
	}

	/**
	 * Feature side: a relative-path `?redirect=admin.php?page=w3tc_general`
	 * MUST be preserved by `wp_validate_redirect()` (hostless → same-
	 * host). After the flush, the admin lands on the General page.
	 */
	it('relative ?redirect= same-host path is preserved', async() => {
		let triggerUrl = await getFlushRedirectTriggerUrl();
		/**
		 * `&redirect=` query value. The redirect target is a relative
		 * admin.php URL — hostless, therefore same-host per WP's rule.
		 */
		let redirected = triggerUrl + '&redirect=' +
			encodeURIComponent('admin.php?page=w3tc_general');

		await adminPage.goto(redirected, {waitUntil: 'domcontentloaded'});
		let finalUrl = adminPage.url();

		log.log('legitimate redirect landed at ' + finalUrl);
		expect(finalUrl).contains('page=w3tc_general');
		expect(finalUrl).not.contains(EVIL_HOST);
	});

	/**
	 * Regression side: an off-host `?redirect=https://evil.example/`
	 * MUST be replaced with `admin_url()` by `wp_validate_redirect()`.
	 * The admin's browser must NOT land on `evil.example`.
	 */
	it('off-host ?redirect= URL is rejected and replaced with admin_url()', async() => {
		let triggerUrl = await getFlushRedirectTriggerUrl();
		let redirected = triggerUrl + '&redirect=' +
			encodeURIComponent('https://' + EVIL_HOST + '/');

		await adminPage.goto(redirected, {waitUntil: 'domcontentloaded'});
		let finalUrl = adminPage.url();

		log.log('off-host redirect landed at ' + finalUrl);
		expect(finalUrl).not.contains(EVIL_HOST);
		/**
		 * `wp_validate_redirect()` falls back to admin_url(), so the
		 * final URL must be on the WP install host (i.e. share host
		 * with `env.adminUrl`).
		 */
		let installHost = new URL(env.adminUrl).host;
		expect(new URL(finalUrl).host).equals(installHost);
	});

	/**
	 * Also: a protocol-relative `?redirect=//evil.example/` (the
	 * classic open-redirect bypass) must be rejected. WP's
	 * `wp_validate_redirect()` strips it because the implied host
	 * fails the same-host check.
	 */
	it('protocol-relative ?redirect=// off-host URL is rejected', async() => {
		let triggerUrl = await getFlushRedirectTriggerUrl();
		let redirected = triggerUrl + '&redirect=' +
			encodeURIComponent('//' + EVIL_HOST + '/');

		await adminPage.goto(redirected, {waitUntil: 'domcontentloaded'});
		let finalUrl = adminPage.url();

		log.log('protocol-relative redirect landed at ' + finalUrl);
		expect(finalUrl).not.contains(EVIL_HOST);
	});
});
