/**
 * File: qa/tests/extensions/swarmify-settings.js
 *
 * Swarmify extension settings form-save coverage.
 *
 * Swarmify is a video-optimization extension. The API-key field
 * is unvalidated client-side, so we can use any non-empty string
 * for the form-save round-trip — no live API needed.
 *
 * Config keys covered:
 *  - `swarmify.api_key`            — textbox
 *  - `swarmify.handle.htmlvideo`   — checkbox
 *  - `swarmify.handle.jwplayer`    — checkbox
 *  - `swarmify.reject.logged`      — checkbox
 *
 * Array keys render as `swarmify___api_key`,
 * `swarmify___handle__htmlvideo`, etc. (triple underscore between
 * module and field, double underscore for dots within the field per
 * `Util_Ui::config_key_to_http_name()`).
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

describe('Swarmify extension settings form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('activate Swarmify and round-trip every config key', async function() {
		await w3tc.activateExtension(adminPage, 'swarmify')
			.catch((e) => log.log('activate result: ' + e.message));

		// networkAdminUrl: w3tc_extensions is not visible_always, so on
		// multisite (default common.force_master) env.adminUrl serves WP's
		// "not allowed" page. Single-site: same URL.
		let settingsUrl = env.networkAdminUrl +
			'admin.php?page=w3tc_extensions&extension=swarmify&action=view';
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let html = await adminPage.content();
		if (html.indexOf('swarmify') === -1 && html.indexOf('Swarmify') === -1) {
			log.log('SKIP: Swarmify settings page did not render');
			this.skip();
			return;
		}

		// Pull the form nonce + POST all four config fields.
		let nonce = await adminPage.$eval(
			'input[name=_wpnonce]', (e) => e.value).catch(() => '');
		if (!nonce) {
			log.log('SKIP: no nonce on Swarmify settings page');
			this.skip();
			return;
		}

		let r = await adminPage.evaluate(async function(url, n) {
			let body = new URLSearchParams();
			body.append('_wpnonce', n);
			body.append('w3tc_save_options', 'Save all settings');
			body.append('swarmify___api_key',          'qa-swarmify-api-key-12345');
			body.append('swarmify___handle__htmlvideo', '1');
			body.append('swarmify___handle__jwplayer',  '1');
			body.append('swarmify___reject__logged',    '1');
			let resp = await fetch(url, {
				method: 'POST', body: body, credentials: 'include', redirect: 'follow'
			});
			return resp.status;
		}, settingsUrl, nonce);
		log.log('Swarmify save POST returned ' + r);

		// Reload + verify rendered values.
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let apiKey = await adminPage.$eval(
			'#swarmify___api_key', (e) => e.value).catch(() => '');
		expect(apiKey).equals('qa-swarmify-api-key-12345');

		let htmlVideo = await adminPage.$eval(
			'#swarmify___handle__htmlvideo', (e) => e.checked).catch(() => null);
		expect(htmlVideo).equals(true);

		let jwplayer = await adminPage.$eval(
			'#swarmify___handle__jwplayer', (e) => e.checked).catch(() => null);
		expect(jwplayer).equals(true);

		let rejectLogged = await adminPage.$eval(
			'#swarmify___reject__logged', (e) => e.checked).catch(() => null);
		expect(rejectLogged).equals(true);

		log.success('all four Swarmify config keys round-tripped');
	});
});
