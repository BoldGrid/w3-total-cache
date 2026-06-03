/**
 * File: qa/tests/objectcache/test-button-method.js
 *
 * rt9-12 puppeteer regression — entry-method gating on the
 * memcached / redis test handlers.
 *
 * Before rt9-12, `w3tc_test_memcached` and `w3tc_test_redis` read
 * the cleartext cache-server password from
 * `Util_Request::get_string('password')`, which resolves against
 * `$_REQUEST = $_GET + $_POST + $_COOKIE`. A misconfigured admin
 * form or a CSRF-style `<img>` tag could land the password on the
 * URL, where it ends up in the access log and every reverse-proxy
 * log between the browser and the server. The fix:
 *   1. `require_post_request()` returns 405 + `Allow: POST` for any
 *      non-POST request to either handler.
 *   2. The handler reads `password` directly from `$_POST` (not
 *      `$_REQUEST`) so the gate stays correct even if the entry-
 *      method check is ever relaxed.
 *
 * Posture: feature side asserts the JS test button (which posts via
 * `jQuery.post`) still gets a response from the dashboard handler.
 * Regression side asserts that a manual GET with `password=PEEK_ME`
 * on the URL returns 405 with `Allow: POST` and does NOT leak the
 * password in the response body.
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

const PEEK_PASSWORD = 'PEEK_ME_THIS_MUST_NOT_LAND_IN_RESPONSE';

/**
 * Load the W3TC dashboard and return the legacy `'w3tc'` nonce minted for
 * the logged-in admin session (`Util_Ui::nonce_field( 'w3tc' )`).
 *
 * Fresh installs redirect to the Setup Guide wizard, whose
 * `w3tc_wizard` nonce is the first `input[name=_wpnonce]` on that page
 * and fails admin-action verification (403). Skip the wizard first.
 *
 * @return {Promise<string>}
 */
async function dashboardLegacyNonce() {
	await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_dashboard',
		{waitUntil: 'domcontentloaded'});

	if (await adminPage.$('#w3tc-wizard-skip') != null) {
		await Promise.all([
			adminPage.evaluate(() => document.querySelector('#w3tc-wizard-skip').click()),
			adminPage.waitForNavigation({timeout: 300000}),
		]);
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_dashboard',
			{waitUntil: 'domcontentloaded'});
	}

	let nonce = await adminPage.$eval('input[name="_wpnonce"]', (e) => e.value);
	expect(nonce).not.empty;
	return nonce;
}

describe('rt9-12 memcached/redis test-handler entry-method regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Feature side: the dashboard handler is reachable to an
	 * authenticated admin via the JS test buttons' jQuery.post call.
	 * We replicate that POST in the admin context (not click the
	 * button, because the button is rendered conditionally on the
	 * active cache engine being memcached/redis — we want this spec
	 * to run on every cache engine matrix entry).
	 */
	it('admin POST to w3tc_test_memcached returns JSON test result', async() => {
		let nonce = await dashboardLegacyNonce();

		let result = await adminPage.evaluate(async function(networkAdminUrl, nonce) {
			let body = new URLSearchParams();
			body.append('w3tc_test_memcached', '1');
			body.append('servers', '127.0.0.1:11211');
			body.append('_wpnonce', nonce);
			let r = await fetch(networkAdminUrl + 'admin.php?page=w3tc_dashboard', {
				method: 'POST',
				body: body,
				credentials: 'include'
			});
			return {status: r.status, text: await r.text()};
		}, env.networkAdminUrl, nonce);

		log.log('POST status ' + result.status);
		/**
		 * Either 200 with a JSON result body (memcached available
		 * or not) or a JSON error — both signal the handler ran. We
		 * only care that the entry method (POST) was accepted.
		 */
		expect(result.status).is.oneOf([200, 400]);
	});

	/**
	 * Regression side: a manual GET with `password=PEEK_ME` on the
	 * URL must be rejected with 405 before the handler executes. The
	 * `Allow: POST` header must be present, and the response body
	 * must NOT contain the password string.
	 */
	it('GET ?w3tc_test_memcached=1&password=... returns 405 with Allow: POST and no password echo', async() => {
		let nonce = await dashboardLegacyNonce();

		let probeUrl = env.networkAdminUrl + 'admin.php?page=w3tc_dashboard' +
			'&w3tc_test_memcached=1' +
			'&servers=127.0.0.1%3A11211' +
			'&password=' + encodeURIComponent(PEEK_PASSWORD) +
			'&_wpnonce=' + encodeURIComponent(nonce);

		let response = await adminPage.goto(probeUrl, {waitUntil: 'domcontentloaded'});
		let body = await adminPage.content();

		log.log('GET probe returned status ' + response.status());
		expect(response.status()).equals(405);
		expect(response.headers()['allow'] || '').to.match(/POST/i);
		expect(body).not.contains(PEEK_PASSWORD);
	});

	it('GET ?w3tc_test_redis=1&password=... returns 405 with Allow: POST and no password echo', async() => {
		let nonce = await dashboardLegacyNonce();

		let probeUrl = env.networkAdminUrl + 'admin.php?page=w3tc_dashboard' +
			'&w3tc_test_redis=1' +
			'&servers=127.0.0.1%3A6379' +
			'&password=' + encodeURIComponent(PEEK_PASSWORD) +
			'&_wpnonce=' + encodeURIComponent(nonce);

		let response = await adminPage.goto(probeUrl, {waitUntil: 'domcontentloaded'});
		let body = await adminPage.content();

		log.log('GET probe returned status ' + response.status());
		expect(response.status()).equals(405);
		expect(response.headers()['allow'] || '').to.match(/POST/i);
		expect(body).not.contains(PEEK_PASSWORD);
	});
});
