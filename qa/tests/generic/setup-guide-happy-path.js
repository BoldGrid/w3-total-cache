/**
 * File: qa/tests/generic/setup-guide-happy-path.js
 *
 * Setup Guide wizard happy-path coverage.
 *
 * The Setup Guide is an AJAX-driven multi-step wizard rendered by
 * `inc/wizard/template.php` and controlled by `pub/js/setup-guide.js`.
 * Each step posts to `admin-ajax.php?action=w3tc_*` with a
 * per-action nonce drawn from a wp_localize_script map. The
 * wizard is registered ONLY for `current_user_can('manage_options')`
 * (the sec-missing-auth-setupguide-ajax fix), so this spec
 * implicitly exercises that gate as the positive control.
 *
 * Posture: load the wizard, click "Next" through each visible
 * step, and assert the wizard advances to the final "Dashboard"
 * button without a JS error. The wizard's own JS handles each
 * step's AJAX calls; if any AJAX fails (broken nonce, missing
 * cap, server-side error), the wizard sticks on that slide and
 * the spec fails on a selector timeout.
 *
 * The spec does NOT assert specific cache-engine configuration
 * outcomes — those are covered by the per-engine specs. The
 * happy-path goal is the wizard mechanism + the wp_localize_script
 * nonce map + the 15 admin-AJAX handlers all wiring up correctly
 * end to end.
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

/**
 * Upper bound on the number of "Next" clicks. The wizard has
 * ~7 slides today (welcome, TOS, pgcache, dbcache, objcache,
 * lazyload, imageservice, done). 12 leaves headroom for future
 * additions without unbounded looping if something hangs.
 */
const MAX_STEPS = 12;

async function isDashboardVisible(page) {
	return page.$eval(
		'#w3tc-wizard-dashboard',
		(e) => e.offsetParent !== null
	).catch(() => false);
}

async function isNextEnabled(page) {
	return page.$eval(
		'#w3tc-wizard-next',
		(e) => !e.disabled && e.offsetParent !== null
	).catch(() => false);
}

async function waitForWizardReady(page) {
	await page.waitForFunction(
		() => {
			let dash = document.querySelector('#w3tc-wizard-dashboard');
			let next = document.querySelector('#w3tc-wizard-next');
			if (dash && dash.offsetParent !== null) {
				return true;
			}
			if (next && !next.disabled && next.offsetParent !== null) {
				return true;
			}
			return false;
		},
		{timeout: 30000}
	);
}

describe('Setup Guide wizard happy path', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('admin can walk the wizard from welcome to dashboard', async() => {
		/**
		 * The wizard loads when the user hits the setup-guide
		 * page. Some matrix variants auto-redirect from the
		 * dashboard on first visit; we go direct.
		 *
		 * Use `networkAdminUrl`: `w3tc_setup_guide` is not
		 * `visible_always`, so on multisite with the default
		 * `common.force_master` it is not registered on the
		 * per-site admin (`env.adminUrl`) — that URL would serve
		 * WP's "not allowed" page and `#w3tc-wizard-container`
		 * below would never mount. Single-site: same URL.
		 */
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_setup_guide',
			{waitUntil: 'networkidle0', timeout: 60000});

		// First slide must mount.
		await adminPage.waitForSelector('#w3tc-wizard-container', {timeout: 30000});
		log.success('wizard container rendered');

		/**
		 * Community edition shows a TOS notice on first visit; setup-guide.js
		 * keeps Next disabled until Accept/Decline AJAX succeeds.
		 */
		let tosPresent = await adminPage.$('#w3tc-licensing-terms');
		if (tosPresent) {
			log.log('accepting TOS');
			await adminPage.evaluate(() => {
				document.querySelector(
					'#w3tc-licensing-terms .button[data-choice="accept"]'
				).click();
			});
			await adminPage.waitForFunction(
				() => {
					let btn = document.querySelector('#w3tc-wizard-next');
					return btn && !btn.disabled && btn.offsetParent !== null;
				},
				{timeout: 30000}
			);
			log.success('TOS accepted; Next enabled');
		}

		/**
		 * Click "Next" up to MAX_STEPS times. Each click triggers
		 * the wizard JS to call the appropriate per-step AJAX
		 * (get_*_settings, test_*, config_*) before advancing.
		 */
		let reachedDashboard = false;
		for (let step = 0; step < MAX_STEPS; step++) {
			if (await isDashboardVisible(adminPage)) {
				reachedDashboard = true;
				break;
			}

			if (!(await isNextEnabled(adminPage))) {
				log.log('Next button not interactable at step ' + step +
					'; checking for completion state.');
				try {
					await waitForWizardReady(adminPage);
				} catch (e) {
					let slideId = await adminPage.evaluate(() => {
						let slides = document.querySelectorAll('.w3tc-wizard-slides');
						for (let i = 0; i < slides.length; i++) {
							if (slides[i].offsetParent !== null) {
								return slides[i].id;
							}
						}
						return 'unknown';
					});
					log.log('wizard stuck on slide ' + slideId);
				}
				if (await isDashboardVisible(adminPage)) {
					reachedDashboard = true;
					break;
				}
				log.log('No Next, no Dashboard — wizard appears stuck');
				break;
			}

			log.log('clicking Next (step ' + (step + 1) + ')');
			await adminPage.evaluate(
				() => document.querySelector('#w3tc-wizard-next').click());
			await waitForWizardReady(adminPage);
		}

		if (!reachedDashboard && await isDashboardVisible(adminPage)) {
			reachedDashboard = true;
		}

		expect(reachedDashboard).equals(true);
		log.success('reached Dashboard button — wizard happy-path complete');

		/**
		 * Click Dashboard; assert we land on the W3TC dashboard
		 * (not a 500 / error page).
		 */
		await Promise.all([
			adminPage.evaluate(
				() => document.querySelector('#w3tc-wizard-dashboard').click()),
			adminPage.waitForNavigation({timeout: 60000})
		]);
		expect(adminPage.url()).contains('w3tc_dashboard');
		log.success('Dashboard button navigates to w3tc_dashboard');
	});
});
