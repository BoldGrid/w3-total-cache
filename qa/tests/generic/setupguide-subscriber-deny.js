/**
 * File: qa/tests/generic/setupguide-subscriber-deny.js
 *
 * sec-missing-auth-setupguide-ajax regression — every
 * `wp_ajax_w3tc_*` handler under `SetupGuide_Plugin_Admin` must
 * refuse subscribers.
 *
 * Before the fix, the SetupGuide_Plugin_Admin instance was
 * registered for every `w3tc_*` AJAX request on any logged-in
 * user. Subscriber-level users could POST to
 * `admin-ajax.php?action=w3tc_get_pgcache_settings`,
 * `w3tc_test_dbcache`, `w3tc_config_objcache`, etc. and read /
 * mutate cache-engine configuration. The fix adds
 * `current_user_can('manage_options')` at the top of the dispatch
 * (`set_template()` short-circuit) plus per-handler nonce checks.
 *
 * Posture: log in as a subscriber, POST to each gated AJAX action,
 * and assert each returns either 403 / 401 / "-1" (the WP nopriv
 * sentinel) / `wp_die`-rendered HTML — anything that is NOT a
 * successful JSON-shaped settings response. The exhaustive list of
 * gated actions comes from `SetupGuide_Plugin_Admin::set_template`
 * line 97 + the action_map at lines 138–176.
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
const wp   = requireRoot('lib/wp');

/**environments: environments('blog') */

/**
 * Actions that must refuse subscribers. Drawn from the SetupGuide
 * handler registry; the spec doesn't need to send valid nonces —
 * the cap check fires before the nonce check, so any POST returns
 * 403 / -1 / die-page.
 */
const GATED_ACTIONS = [
	'w3tc_wizard_skip',
	'w3tc_tos_choice',
	'w3tc_get_pgcache_settings',
	'w3tc_test_pgcache',
	'w3tc_config_pgcache',
	'w3tc_get_dbcache_settings',
	'w3tc_test_dbcache',
	'w3tc_config_dbcache',
	'w3tc_get_objcache_settings',
	'w3tc_test_objcache',
	'w3tc_config_objcache',
	'w3tc_get_browsercache_settings',
	'w3tc_get_lazyload_settings',
	'w3tc_config_lazyload',
	'w3tc_get_imageservice_settings'
];

describe('sec-missing-auth-setupguide-ajax subscriber-deny regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Helper: log in as a freshly-created subscriber in the
	 * incognito browser context (`page` / `browserI`). Returns
	 * the page-handle and verifies the login succeeded.
	 */
	async function loginAsSubscriber(login, password) {
		await page.goto(env.networkAdminUrl, {waitUntil: 'domcontentloaded'});
		await page.goto(env.adminUrl + 'profile.php',
			{waitUntil: 'domcontentloaded'});
		expect(await page.title()).contains('Log In');
		await page.$eval('#user_login', (e, v) => e.value = v, login);
		await page.$eval('#user_pass', (e, v) => e.value = v, password);
		await Promise.all([
			page.evaluate(() => document.querySelector('#wp-submit').click()),
			page.waitForNavigation({timeout: 300000})
		]);
		/**
		 * Subscriber dashboard shows the profile page, not the
		 * network admin dashboard; both render the admin chrome
		 * so we just assert we're inside wp-admin.
		 */
		expect(page.url()).contains('wp-admin');
	}

	it('subscriber session is denied every SetupGuide AJAX action', async() => {
		/**
		 * Create a subscriber via the existing helper. The
		 * returned password lets us log in.
		 */
		const suffix = Date.now();
		const userLogin = 'sgsub_' + suffix;
		const userEmail = 'sgsub' + suffix + '@example.com';
		let subPassword = await wp.userSignUp(adminPage, {
			user_login: userLogin,
			email:      userEmail,
			role:       'subscriber'
		});
		/**
		 * `userSignUp` on multisite returns the subscriber's
		 * password from the activation email. `login` (used by
		 * sys.beforeDefault) is hardcoded to 'admin' so we use a
		 * manual login here.
		 */
		await loginAsSubscriber(userLogin, subPassword)
			.catch(async (e) => {
				log.log('subscriber-login retry path: ' + e.message);
				/**
				 * On some matrix variants the user-creation login
				 * is the email. Best-effort fallback.
				 */
				await loginAsSubscriber(userEmail, subPassword);
			});

		let failures = [];

		for (let action of GATED_ACTIONS) {
			let resp = await page.evaluate(async function(adminUrl, act) {
				let body = new URLSearchParams();
				body.append('action', act);
				let r = await fetch(adminUrl + 'admin-ajax.php', {
					method: 'POST', body: body, credentials: 'include'
				});
				let text = await r.text();
				return {status: r.status, body: text.substring(0, 256)};
			}, env.adminUrl, action);

			log.log('   ' + action + ' -> ' + resp.status +
				' body[0..64]=' + JSON.stringify(resp.body.substring(0, 64)));

			/**
			 * A subscriber MUST be denied. Acceptable shapes:
			 * - HTTP 403 (`wp_die` / `wp_send_json_error`)
			 * - HTTP 200 body `-1` (the WP `_ajax_send_nosuccess`)
			 * - HTTP 200 or 400 body `0` (unregistered action; WP 7.0+
			 * uses 400 in admin-ajax.php, older WP uses 200)
			 * - HTML body containing `Sorry, you are not allowed`
			 * - HTML body containing `403` / "permission"
			 * Failure shape: HTTP 200 with a JSON payload that
			 * contains live cache config — i.e. a successful
			 * reach to the handler.
			 */
			let ok = resp.status === 403 ||
				resp.body.trim() === '-1' ||
				resp.body.indexOf('not allowed') !== -1 ||
				resp.body.indexOf('Forbidden') !== -1 ||
				resp.body.indexOf('"success":false') !== -1;
			if (!ok) {
				/**
				 * Silent deny: empty body or the "0" sentinel on
				 * unhandled-action paths (status varies by WP version).
				 */
				if ((resp.status === 200 || resp.status === 400) &&
					(resp.body.trim() === '0' || resp.body.trim() === '')) {
					ok = true;
				}
			}
			if (!ok) {
				failures.push({action: action, status: resp.status,
					sample: resp.body.substring(0, 200)});
			}
		}

		if (failures.length > 0) {
			log.log('FAILURES — subscriber reached:');
			for (let f of failures) {
				log.log('   ' + f.action + ' status=' + f.status + ' body=' + f.sample);
			}
		}
		expect(failures).is.empty;
		log.success('every SetupGuide AJAX action denied to subscriber');
	});
});
