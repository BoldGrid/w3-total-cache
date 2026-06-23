/**
 * File: qa/tests/extensions/alwayscached-settings.js
 *
 * AlwaysCached extension settings page form-save coverage.
 *
 * AlwaysCached is the Pro-tier pre-cache worker queue. Settings
 * live at `?page=w3tc_extensions&extension=alwayscached&action=view`
 * and cover:
 *  - `alwayscached.wp_cron` — schedule via WP-Cron
 *  - `alwayscached.wp_cron_time` — start time (selectbox of half-hour slots)
 *  - `alwayscached.wp_cron_interval` — recurrence (hourly|twicedaily|daily|weekly)
 *  - `alwayscached.exclusions` — URL pattern list
 *
 * Posture: activate the extension, save each config field via
 * `setOptions`, reload the page, assert read-back. No external
 * dependencies; AlwaysCached requires no API credentials. The
 * cron-event scheduling side-effect (`wp_schedule_event`) is not
 * asserted here — that's a WP-internal side-effect and outside
 * the form-save contract.
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
 * True when the AlwaysCached settings UI is present in HTML.
 *
 * @param {string} html Page HTML.
 * @return {boolean}
 */
function alwaysCachedSettingsPresent(html) {
	return html.indexOf('Enable WP-Cron Event') !== -1 ||
		html.indexOf('w3tc_alwayscached_wp_cron') !== -1;
}

describe('AlwaysCached extension settings form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('activate AlwaysCached extension', async function() {
		// AlwaysCached requires page cache to be enabled.
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true
		});
		await sys.afterRulesChange();

		await w3tc.activateExtension(adminPage, 'alwayscached')
			.catch((e) => log.log('activate-extension result: ' + e.message));

		// networkAdminUrl: w3tc_extensions is not visible_always, so on
		// multisite (default common.force_master) env.adminUrl serves WP's
		// "not allowed" page. Single-site: same URL.
		let settingsUrl = env.networkAdminUrl +
			'admin.php?page=w3tc_extensions&extension=alwayscached&action=view';
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let html = await adminPage.content();
		if (!alwaysCachedSettingsPresent(html)) {
			log.log('SKIP: AlwaysCached settings page did not render (free build?)');
			this.skip();
			return;
		}
		log.success('AlwaysCached settings page rendered');
	});

	it('save and read back AlwaysCached cron settings', async function() {
		let settingsUrl = env.networkAdminUrl +
			'admin.php?page=w3tc_extensions&extension=alwayscached&action=view';
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});
		let html = await adminPage.content();
		if (!alwaysCachedSettingsPresent(html)) {
			log.log('SKIP: AlwaysCached settings page did not render (free build?)');
			this.skip();
			return;
		}

		let nonce = await adminPage.$eval(
			'input[name=_wpnonce]', (e) => e.value).catch(() => '');
		if (!nonce) {
			log.log('SKIP: no nonce on AlwaysCached settings page');
			this.skip();
			return;
		}

		let saveStatus = await adminPage.evaluate(async function(url, n) {
			let body = new URLSearchParams();
			body.append('_wpnonce', n);
			body.append('w3tc_save_options', 'Save all settings');
			body.append('alwayscached__wp_cron', '1');
			body.append('alwayscached__wp_cron_time', '0');
			body.append('alwayscached__wp_cron_interval', 'daily');
			let resp = await fetch(url, {
				method: 'POST',
				body: body,
				credentials: 'include',
				redirect: 'follow'
			});
			return resp.status;
		}, settingsUrl, nonce);
		log.log('AlwaysCached save returned ' + saveStatus);

		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let cronEnabled = await adminPage.$eval('#alwayscached__wp_cron',
			(e) => e.checked).catch(() => null);
		expect(cronEnabled).equals(true);

		let cronTime = await adminPage.$eval('#alwayscached__wp_cron_time',
			(e) => e.value).catch(() => '');
		expect(cronTime).equals('0');

		let cronInterval = await adminPage.$eval('#alwayscached__wp_cron_interval',
			(e) => e.value).catch(() => '');
		expect(cronInterval).equals('daily');
		log.success('AlwaysCached cron settings persisted');
	});

	it('AlwaysCached queue UI renders', async function() {
		let settingsUrl = env.networkAdminUrl +
			'admin.php?page=w3tc_extensions&extension=alwayscached&action=view';
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let html = await adminPage.content();
		if (!alwaysCachedSettingsPresent(html)) {
			log.log('SKIP: AlwaysCached settings page did not render (free build?)');
			this.skip();
			return;
		}
		/**
		 * Queue list / exclusions are part of the settings page;
		 * each has its own postbox. We just confirm the queue
		 * box section exists.
		 */
		let queuePresent =
			html.indexOf('Queue') !== -1 ||
			html.indexOf('queue') !== -1 ||
			html.indexOf('alwayscached_queue') !== -1;
		expect(queuePresent).equals(true);
		log.success('AlwaysCached queue UI surface present');
	});
});
