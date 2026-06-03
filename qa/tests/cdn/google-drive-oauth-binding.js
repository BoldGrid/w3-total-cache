/**
 * File: qa/tests/cdn/google-drive-oauth-binding.js
 *
 * rt9-233 puppeteer regression — Google Drive OAuth callback handler
 * is session-bound.
 *
 * Before rt9-233, an admin who loaded
 * `?page=w3tc_cdn&oa_client_id=ATK&oa_access_token=ATK&oa_refresh_token=ATK`
 * via a crafted URL would have the auth-return popup auto-opened
 * against the attacker's Google Drive account; submitting the popup
 * would then write attacker credentials into `cdn.google_drive.*`
 * config — and the site would start uploading CDN content to the
 * attacker's Drive.
 *
 * The fix mints a session-bound state token on the CDN page render
 * (`Cdn_GoogleDrive_OAuthState::issue()`) and embeds it in the
 * `return_url` query string. On callback, the popup auto-open is
 * gated by `Cdn_GoogleDrive_OAuthState::verify()`, the auth-return
 * handler re-validates independently, and the auth-set handler
 * re-validates a third time before `consume()`-ing the token.
 *
 * Posture: feature side asserts that loading `?page=w3tc_cdn`
 * normally embeds a state token in the localized OAuth URL.
 * Regression side asserts that loading the same page with crafted
 * `oa_*` params but NO `w3tc_gdrive_state` param does NOT emit the
 * popup-auto-open `w3tc_cdn_google_drive_popup_url` localized
 * script — meaning the attacker's tokens never reach the folder-
 * picker UI. The auth-return handler is also exercised directly via
 * a GET without state, which must return 403.
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

const ATTACKER_CLIENT_ID    = 'ATTACKER_CLIENT_ID_RT9_233';
const ATTACKER_ACCESS_TOKEN = 'ATTACKER_ACCESS_TOKEN_RT9_233';
const ATTACKER_REFRESH      = 'ATTACKER_REFRESH_TOKEN_RT9_233';

/**
 * Inline script bodies only (excludes WordPress `_wp_http_referer` etc. that
 * mirror the current admin URL query string).
 *
 * @param {object} pPage Puppeteer page.
 * @return {Promise<string>}
 */
async function inlineScriptText(pPage) {
	return pPage.$$eval(
		'script:not([src])',
		(els) => els.map((e) => e.textContent || '').join('\n')
	);
}

describe('rt9-233 Google Drive OAuth state-binding regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('select Google Drive as the CDN engine', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			cdn__engine: 'google_drive'
		});

		await sys.afterRulesChange();
	});

	/**
	 * Feature side: the CDN page render localizes a Google OAuth authorize URL
	 * whose `return_url` query string contains `w3tc_gdrive_state` (32-char
	 * alphanumeric from `wp_generate_password`). The authorize URL is
	 * `rawurlencode()`'d in the localized script, so the page HTML carries
	 * `%3D` / `%26` rather than literal `=` / `&`.
	 */
	it('CDN page render embeds a state token in the OAuth return_url', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cdn',
			{waitUntil: 'domcontentloaded'});

		let html = await adminPage.content();
		expect(html).contains('w3tc_cdn_google_drive_url');
		let m = html.match(
			/w3tc_gdrive_state(?:=|%3D)([a-zA-Z0-9]{32})/
		);
		expect(m).is.not.null;
		log.success('state token issued: ' + m[1].substr(0, 8) + '…');
	});

	/**
	 * Regression side: crafted `oa_*` params without `w3tc_gdrive_state` must
	 * not enqueue `w3tc_cdn_google_drive_popup_url` (see Cdn_GoogleDrive_Page).
	 * Attacker strings may still appear in `_wp_http_referer` (current URL);
	 * the kill-chain requires them in localized OAuth / popup script payloads.
	 */
	it('crafted oa_* params without state do NOT enqueue auth popup', async() => {
		let craftedUrl = env.networkAdminUrl + 'admin.php?page=w3tc_cdn' +
			'&oa_client_id='     + encodeURIComponent(ATTACKER_CLIENT_ID) +
			'&oa_access_token='  + encodeURIComponent(ATTACKER_ACCESS_TOKEN) +
			'&oa_refresh_token=' + encodeURIComponent(ATTACKER_REFRESH);

		await adminPage.goto(craftedUrl, {waitUntil: 'domcontentloaded'});
		let html = await adminPage.content();
		let scripts = await inlineScriptText(adminPage);

		expect(html).not.contains('w3tc_cdn_google_drive_popup_url');
		expect(scripts).not.contains('w3tc_cdn_google_drive_popup_url');
		expect(scripts).not.contains(ATTACKER_CLIENT_ID);
		expect(scripts).not.contains(ATTACKER_ACCESS_TOKEN);
		expect(scripts).not.contains(ATTACKER_REFRESH);

		let authorizeMatch = html.match(
			/w3tc_cdn_google_drive_url\s*=\s*\["([^"]+)"\]/
		);
		if (authorizeMatch) {
			let authorizeUrl = authorizeMatch[1].replace(/\\\//g, '/');
			expect(authorizeUrl).not.contains(ATTACKER_CLIENT_ID);
			expect(authorizeUrl).not.contains(ATTACKER_ACCESS_TOKEN);
			expect(authorizeUrl).not.contains(ATTACKER_REFRESH);
		}

		log.success('popup-auto-open suppressed; attacker tokens not echoed');
	});

	/**
	 * Direct GET to the auth-return action without state must return 403
	 * (Cdn_GoogleDrive_AdminActions defense-in-depth).
	 */
	it('direct GET to auth-return handler without state returns 403', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cdn',
			{waitUntil: 'domcontentloaded'});
		let nonce = await adminPage.$eval('input[name=_wpnonce]', (e) => e.value);
		expect(nonce).not.empty;

		let directUrl = env.networkAdminUrl + 'admin.php?_wpnonce=' + nonce +
			'&page=w3tc_cdn&w3tc_cdn_google_drive_auth_return' +
			'&oa_client_id='     + encodeURIComponent(ATTACKER_CLIENT_ID) +
			'&oa_access_token='  + encodeURIComponent(ATTACKER_ACCESS_TOKEN) +
			'&oa_refresh_token=' + encodeURIComponent(ATTACKER_REFRESH);

		let response = await adminPage.goto(directUrl, {waitUntil: 'domcontentloaded'});
		let body     = await adminPage.content();

		log.log('auth_return direct GET returned ' + response.status());
		expect(response.status()).equals(403);
		expect(body).not.contains(ATTACKER_ACCESS_TOKEN);
		expect(body).not.contains(ATTACKER_REFRESH);
	});

	it('cdn.google_drive.* config unchanged by the crafted attempts', async() => {
		expect(await w3tc.getConfigOption('cdn.google_drive.client_id')).equals('');
		expect(await w3tc.getConfigOption('cdn.google_drive.refresh_token')).equals('');
		log.success('cdn.google_drive credentials not written by crafted GETs');
	});
});
