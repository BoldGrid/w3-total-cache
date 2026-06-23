/**
 * File: qa/tests/userexperience/lazyload-settings.js
 *
 * UserExperience page lazy-load form-save coverage.
 *
 * The `w3tc_userexperience` admin page is the User Experience
 * settings home. It hosts the Lazy Load section (free + Pro) and,
 * on Pro builds, the Delay Scripts / Preload / Remove CSS sub-views.
 * This spec covers the Lazy Load section that ships in every
 * build; the Delay Scripts spec covers the Pro-gated section in a
 * sibling file with a runtime Pro-detection skip.
 *
 * Posture: round-trip every Lazy Load config key through
 * `w3tc.setOptions` and assert read-back. Mirrors the
 * `dbcache/basic.js` shape.
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

describe('UserExperience: Lazy Load settings round-trip', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('save and read back every Lazy Load setting', async() => {
		/**
		 * Enable the master toggle from the General page first;
		 * the section-level inputs are only meaningful when the
		 * feature is active.
		 */
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			lazyload__enabled: true
		});

		// Section-level toggles on w3tc_userexperience itself.
		await w3tc.setOptions(adminPage, 'w3tc_userexperience', {
			lazyload__process_img:        true,
			lazyload__process_background: true,
			lazyload__exclude:            'bg-no-lazy\nno-lazy\nthemehero',
			lazyload__threshold:          '300px',
			lazyload__embed_method:       'inline_footer'
		});

		/**
		 * Reload the page and verify each input's rendered value
		 * matches what we just saved. `w3tc.setOptions` already
		 * performs the round-trip assertion internally, but here
		 * we additionally pull from the rendered DOM so a future
		 * helper refactor cannot hide a regression.
		 *
		 * Use `networkAdminUrl` — the same context `setOptions`
		 * writes to. On multisite with `common.force_master`
		 * (default on), `Root_AdminMenu::generate()` does not
		 * register non-`visible_always` settings pages on the
		 * per-site admin, so `env.adminUrl` would render WP's
		 * "not allowed" page and every `$eval` below would return
		 * null. On single-site the two URLs are identical.
		 */
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_userexperience',
			{waitUntil: 'domcontentloaded'});

		let processImg = await adminPage.$eval('#lazyload__process_img',
			(e) => e.checked).catch(() => null);
		expect(processImg).equals(true);

		let processBg = await adminPage.$eval('#lazyload__process_background',
			(e) => e.checked).catch(() => null);
		expect(processBg).equals(true);

		let excludeVal = await adminPage.$eval('#lazyload__exclude',
			(e) => e.value).catch(() => '');
		expect(excludeVal).contains('no-lazy');
		expect(excludeVal).contains('themehero');

		let thresholdVal = await adminPage.$eval('#lazyload__threshold',
			(e) => e.value).catch(() => '');
		expect(thresholdVal).equals('300px');

		let embedSelect = await adminPage.$eval('#lazyload__embed_method',
			(e) => e.value).catch(() => '');
		expect(embedSelect).equals('inline_footer');

		log.success('All Lazy Load settings persisted and rendered correctly');
	});

	/**
	 * Negative path: disabling the master toggle hides feature
	 * behavior. We just assert the saved-state reflects the new
	 * boolean — the runtime "no <img class=lazy>" assertion is
	 * out of this spec's scope (no fixture pages required).
	 */
	it('disabling the master toggle persists', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			lazyload__enabled: false
		});

		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general',
			{waitUntil: 'domcontentloaded'});
		let enabled = await adminPage.$eval('#lazyload__enabled',
			(e) => e.checked).catch(() => null);
		expect(enabled).equals(false);
		log.success('lazyload.enabled toggle round-trips false');
	});
});
