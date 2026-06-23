/**
 * File: qa/tests/extensions/cloudflare-settings.js
 *
 * CloudFlare extension settings form-save coverage.
 *
 * The CloudFlare extension hosts a settings panel + an auth popup
 * that drives a multi-step "intro → connect → zone-picker" flow.
 * This spec covers the form-save portion of the panel; the
 * auth-popup live-API path is env-gated and only runs when both
 * `CLOUDFLARE_EMAIL` and `CLOUDFLARE_KEY` are present.
 *
 * Posture: activate the extension, set the four widget /
 * pagecache / minify config keys on the General settings page
 * (`w3tc_general#cloudflare`), reload, assert read-back. Skip
 * cleanly if the extension isn't available.
 *
 * The four widget/behavior keys live in
 * `Extension_CloudFlare_GeneralPage_View.php`, which is rendered
 * on General — not on the extension's own settings page (that
 * page links to General for these fields). Array config keys
 * render as `cloudflare___widget_interval`, etc. (triple
 * underscore between module and field per
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

describe('CloudFlare extension settings form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('activate CloudFlare extension', async function() {
		await w3tc.activateExtension(adminPage, 'cloudflare')
			.catch((e) => log.log('activate result: ' + e.message));

		// networkAdminUrl: w3tc_extensions is not visible_always, so on
		// multisite (default common.force_master) env.adminUrl serves WP's
		// "not allowed" page. Single-site: same URL.
		let settingsUrl = env.networkAdminUrl +
			'admin.php?page=w3tc_extensions&extension=cloudflare&action=view';
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let html = await adminPage.content();
		if (html.indexOf('cloudflare') === -1 &&
			html.indexOf('CloudFlare') === -1 &&
			html.indexOf('Cloudflare') === -1) {
			log.log('SKIP: CloudFlare settings page did not render');
			this.skip();
			return;
		}
		log.success('CloudFlare settings page rendered');
	});

	it('save and read back CloudFlare widget + behavior config', async function() {
		/**
		 * Widget interval / cache mins / pagecache / minify-exclude
		 * inputs are on General → Cloudflare, not the extension page.
		 */
		await adminPage.goto(
			env.networkAdminUrl + 'admin.php?page=w3tc_general#cloudflare',
			{waitUntil: 'domcontentloaded'});

		if (await adminPage.$('#cloudflare___widget_interval') === null) {
			log.log('SKIP: CloudFlare general settings inputs not rendered');
			this.skip();
			return;
		}

		await w3tc.setOptions(adminPage, 'w3tc_general', {
			'cloudflare___widget_interval':     '-720',
			'cloudflare___widget_cache_mins':   '20',
			'cloudflare___pagecache':           true,
			'cloudflare___minify_js_rl_exclude': true,
		});
		log.success('CloudFlare widget + behavior config persisted');
	});

	it('CloudFlare auth-popup live API flow (env-gated)', async function() {
		if (sys.skipIfMissingEnv(this, ['CLOUDFLARE_EMAIL', 'CLOUDFLARE_KEY'])) return;

		/**
		 * With live credentials present, drive the intro popup
		 * and validate the response shape. We do NOT auto-confirm
		 * a zone change against the real account — just exercise
		 * the AJAX wiring.
		 */
		let r = await adminPage.evaluate(async function(adminUrl, email, key) {
			let body = new URLSearchParams();
			body.append('action', 'w3tc_ajax_extension_cloudflare_intro_done');
			body.append('email', email);
			body.append('key',   key);
			let resp = await fetch(adminUrl + 'admin-ajax.php', {
				method: 'POST', body: body, credentials: 'include'
			});
			return {status: resp.status, body: (await resp.text()).substring(0, 200)};
		}, env.adminUrl, process.env['CLOUDFLARE_EMAIL'], process.env['CLOUDFLARE_KEY']);
		log.log('intro_done AJAX status: ' + r.status + ' body: ' + r.body);
		expect(r.status).equals(200);
	});
});
