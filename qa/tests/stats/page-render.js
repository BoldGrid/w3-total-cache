/**
 * File: qa/tests/stats/page-render.js
 *
 * UsageStatistics admin page render coverage.
 *
 * `?page=w3tc_stats` switches between three views based on
 * `stats.enabled` + Pro status:
 *  - `UsageStatistics_Page_View.php`         — Pro + enabled
 *  - `UsageStatistics_Page_View_Free.php`    — free build
 *  - `UsageStatistics_Page_View_Disabled.php` — Pro + disabled
 *
 * There are no form inputs to round-trip — the page hosts JS
 * charts and a refresh link. The smoke check is that each path
 * renders without a fatal PHP error and presents the expected
 * top-level marker for its variant.
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

describe('UsageStatistics page render variants', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('?page=w3tc_stats renders without fatal error', async() => {
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_stats',
			{waitUntil: 'domcontentloaded'});

		// Wizard skip if present.
		if (await adminPage.$('#w3tc-wizard-skip') != null) {
			await Promise.all([
				adminPage.evaluate(() => document.querySelector('#w3tc-wizard-skip').click()),
				adminPage.waitForNavigation({timeout: 300000})
			]);
			await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_stats',
				{waitUntil: 'domcontentloaded'});
		}

		/**
		 * Whichever variant rendered, the page MUST NOT contain
		 * PHP error markers or a WSOD signature.
		 */
		let pageHtml = await adminPage.content();
		expect(pageHtml).not.contains('Fatal error');
		expect(pageHtml).not.contains('Parse error');
		expect(pageHtml).not.contains('Uncaught');
		expect(pageHtml).not.contains('Notice:');
		expect(pageHtml).not.contains('Warning:');
		log.success('w3tc_stats renders without PHP error markers');

		/**
		 * At least one of the three variant-marker strings must
		 * be present so we know we landed on the page at all,
		 * not a redirect.
		 */
		let variantMarker =
			pageHtml.indexOf('w3tcus') !== -1 ||              // Pro view
			pageHtml.indexOf('Usage Statistics') !== -1 ||    // common header
			pageHtml.indexOf('not currently enabled') !== -1; // disabled banner
		expect(variantMarker).equals(true);
		log.success('w3tc_stats view variant marker present');
	});

	/**
	 * Re-render the page with stats.enabled toggled to false to
	 * exercise the disabled variant. If the matrix is a free
	 * build, the Free view renders unconditionally; the Pro +
	 * disabled variant only shows on Pro builds.
	 */
	it('toggling stats.enabled re-renders without crash', async() => {
		/**
		 * Use setOptionInternal so we don't depend on a stats
		 * form input existing on the page (it doesn't on
		 * non-Pro builds).
		 */
		await w3tc.setOptionInternal(adminPage, 'stats.enabled', false);

		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_stats',
			{waitUntil: 'domcontentloaded'});

		let pageHtml = await adminPage.content();
		expect(pageHtml).not.contains('Fatal error');
		expect(pageHtml).not.contains('Parse error');
		log.success('w3tc_stats with stats.enabled=false renders cleanly');

		// Restore.
		await w3tc.setOptionInternal(adminPage, 'stats.enabled', true);
	});
});
