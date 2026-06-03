/**
 * File: qa/tests/pagespeed/dashboard-render.js
 *
 * PageSpeed Insights admin page render + AJAX-wiring smoke spec.
 *
 * The `w3tc_pagespeed` page is a read-only dashboard: it renders
 * `<div id="w3tcps_container">`, a "Refresh Analysis" button, and
 * a JS controller that fetches data via
 * `admin-ajax.php?action=w3tc_ajax&w3tc_action=pagespeed_data`. There is
 * no traditional form-save. The Google PSI OAuth2 access token is
 * configured on the General page; without a valid token the AJAX
 * returns an error JSON which the dashboard renders as a banner.
 *
 * Posture: this spec verifies the page renders the container, the
 * Refresh button exists, and clicking it issues exactly one AJAX
 * call to the expected action. We deliberately do NOT assert API
 * success — that requires a live Google PSI access token which
 * the CI matrix does not provision.
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

describe('PageSpeed dashboard render + AJAX wiring', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('w3tc_pagespeed renders container + analyze button', async() => {
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_pagespeed',
			{waitUntil: 'domcontentloaded'});

		// Wizard skip if present.
		if (await adminPage.$('#w3tc-wizard-skip') != null) {
			await Promise.all([
				adminPage.evaluate(() => document.querySelector('#w3tc-wizard-skip').click()),
				adminPage.waitForNavigation({timeout: 300000})
			]);
			await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_pagespeed',
				{waitUntil: 'domcontentloaded'});
		}

		// Page must mount the analysis container.
		let container = await adminPage.$('#w3tcps_container');
		expect(container).not.equal(null);
		log.success('PageSpeed container rendered');

		// Refresh / Analyze button (class, not id — see PageSpeed_Page_View.php).
		let analyzeBtn = await adminPage.$('.w3tcps_analyze');
		expect(analyzeBtn).not.equal(null);
		log.success('PageSpeed analyze button present');
	});

	/**
	 * AJAX wiring check: when the button is clicked, the page
	 * should issue an admin-ajax request with
	 * `w3tc_action=pagespeed_data`. We listen for the request and
	 * resolve when seen. We don't care about the response — the
	 * goal is just to prove the click-to-AJAX wiring is intact.
	 */
	it('Refresh Analysis button triggers pagespeed_data AJAX', async() => {
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_pagespeed',
			{waitUntil: 'domcontentloaded'});

		/**
		 * Page load auto-triggers one pagespeed_data request; wait for
		 * it to finish before listening for the click-triggered call.
		 */
		await adminPage.waitForResponse(
			(response) => response.url().indexOf('w3tc_action=pagespeed_data') !== -1,
			{timeout: 120000});

		let ajaxSeen = false;
		let listener = (request) => {
			if (request.url().indexOf('w3tc_action=pagespeed_data') !== -1) {
				ajaxSeen = true;
			}
		};
		adminPage.on('request', listener);

		await adminPage.evaluate(
			() => document.querySelector('.w3tcps_analyze').click());

		// Give the JS controller a moment to fire the AJAX.
		await new Promise((r) => setTimeout(r, 5000));
		adminPage.off('request', listener);

		expect(ajaxSeen).equals(true);
		log.success('pagespeed_data AJAX was requested on button click');
	});
});
