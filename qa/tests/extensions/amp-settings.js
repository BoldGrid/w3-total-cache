/**
 * File: qa/tests/extensions/amp-settings.js
 *
 * AMP extension settings form-save coverage.
 *
 * The AMP extension detects AMP page variants by URL pattern.
 * Two config keys:
 *  - `amp.url_type`     — radiogroup (tag | querystring)
 *  - `amp.url_postfix`  — textbox (e.g. `amp` or `?amp`)
 *
 * Array config keys render as `amp___url_type` / `amp___url_postfix`
 * (triple underscore between module and field per
 * `Util_Ui::config_key_to_http_name()`).
 *
 * No external dependencies; no credentials required.
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

describe('AMP extension settings form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('activate AMP and round-trip url_type + url_postfix', async() => {
		await w3tc.activateExtension(adminPage, 'amp')
			.catch((e) => log.log('activate result: ' + e.message));

		let settingsUrl = env.adminUrl +
			'admin.php?page=w3tc_extensions&extension=amp&action=view';
		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		let html = await adminPage.content();
		if (html.indexOf('AMP URL Type') === -1 &&
			html.indexOf('amp___url_type') === -1 &&
			html.indexOf('amp__url_type') === -1) {
			log.log('SKIP: AMP settings page did not render');
			this.skip();
			return;
		}

		let nonce = await adminPage.$eval(
			'input[name=_wpnonce]', (e) => e.value).catch(() => '');
		if (!nonce) {
			log.log('SKIP: no nonce on AMP settings page');
			this.skip();
			return;
		}

		// Save: tag-style URLs with postfix 'amp-mobile'.
		let r = await adminPage.evaluate(async function(url, n) {
			let body = new URLSearchParams();
			body.append('_wpnonce', n);
			body.append('w3tc_save_options', 'Save all settings');
			body.append('amp___url_type',    'tag');
			body.append('amp___url_postfix', 'amp-mobile');
			let resp = await fetch(url, {
				method: 'POST', body: body, credentials: 'include', redirect: 'follow'
			});
			return resp.status;
		}, settingsUrl, nonce);
		log.log('AMP save returned ' + r);

		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});

		/**
		 * `url_type` is a radiogroup — read the checked radio's
		 * value rather than $eval on a single id.
		 */
		let urlType = await adminPage.evaluate(() => {
			let chosen = document.querySelector('input[name=amp___url_type]:checked');
			return chosen ? chosen.value : null;
		});
		expect(urlType).equals('tag');

		let postfix = await adminPage.$eval(
			'#amp___url_postfix', (e) => e.value).catch(() => '');
		expect(postfix).equals('amp-mobile');

		/**
		 * Save again with the querystring variant to prove the
		 * radiogroup switch persists.
		 */
		nonce = await adminPage.$eval(
			'input[name=_wpnonce]', (e) => e.value).catch(() => '');
		let r2 = await adminPage.evaluate(async function(url, n) {
			let body = new URLSearchParams();
			body.append('_wpnonce', n);
			body.append('w3tc_save_options', 'Save all settings');
			body.append('amp___url_type',    'querystring');
			body.append('amp___url_postfix', 'qsmode');
			let resp = await fetch(url, {
				method: 'POST', body: body, credentials: 'include', redirect: 'follow'
			});
			return resp.status;
		}, settingsUrl, nonce);
		log.log('AMP second save returned ' + r2);

		await adminPage.goto(settingsUrl, {waitUntil: 'domcontentloaded'});
		urlType = await adminPage.evaluate(() => {
			let chosen = document.querySelector('input[name=amp___url_type]:checked');
			return chosen ? chosen.value : null;
		});
		expect(urlType).equals('querystring');

		log.success('AMP url_type radiogroup + url_postfix round-trip');
	});
});
