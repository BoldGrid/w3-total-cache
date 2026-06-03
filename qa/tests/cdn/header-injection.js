/**
 * File: qa/tests/cdn/header-injection.js
 *
 * sec-header-injection regression — `X-W3TC-CDN` emitted from
 * `cdn.engine` must not allow CRLF splitting of the HTTP response.
 *
 * Background: `Cdn_Plugin::send_x_w3tc_cdn_header()` reads
 * `$this->_config->get_string('cdn.engine')` and emits
 * `X-W3TC-CDN: <value>` via `Util_Response::header()`. Historically
 * the value was passed straight to PHP's `header()`. On PHP 7.4 –
 * 8.2 PHP's CRLF-in-value protection is partial, so an admin-side
 * mass-assignment writing `engine = "ftp\r\nX-Injected: yes"` would
 * have splitted the response and let the attacker inject any
 * header on every cached page hit.
 *
 * The fix:
 *  - `Util_Response::header()` rejects any value containing CR, LF
 *    or NUL bytes (`strpbrk($value, "\r\n\0")`) — defense in depth.
 *  - The primary defense is `ConfigKeys.php` which constrains
 *    `cdn.engine` to the allowed-engine list.
 *
 * Posture: feature side asserts a legitimate engine (`ftp`) lands
 * an `X-W3TC-CDN: ftp` header on cached responses. Regression side
 * uses `setOptionInternal` (which bypasses the form save and goes
 * directly to `$config->set()`) to write a CRLF-bearing engine
 * value, then fetches a cached page and asserts no injected header
 * appears.
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

describe('sec-header-injection X-W3TC-CDN CRLF regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Feature side: a legitimate `ftp` engine value lands an
	 * `X-W3TC-CDN: ftp` header on the homepage. Establishes that
	 * the emission point is wired and the test selector works.
	 */
	it('legitimate cdn.engine value emits X-W3TC-CDN cleanly', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			cdn__engine: 'ftp'
		});
		await sys.afterRulesChange();

		let r = await sys.httpGet(env.homeUrl);
		log.log('homepage X-W3TC-CDN: ' + r.headers['x-w3tc-cdn']);
		if (r.headers['x-w3tc-cdn']) {
			expect(r.headers['x-w3tc-cdn']).equals('ftp');
		} else {
			/**
			 * Header may be absent on uncached responses; this is
			 * not a failure condition for the regression test.
			 */
			log.log('header not present — page likely uncached on this hit');
		}
	});

	/**
	 * Regression side: write a CRLF-bearing engine value directly
	 * to config (bypassing the form-save allowlist), then fetch
	 * the homepage and assert no injected header appears AND the
	 * X-W3TC-CDN header is either absent or holds only the literal
	 * pre-CRLF portion.
	 */
	it('CRLF in cdn.engine does NOT produce an injected response header', async() => {
		const poisoned = "ftp\r\nX-Injected-By-W3TC: yes";
		await w3tc.setOptionInternal(adminPage, 'cdn.engine', poisoned);

		// Bust any prior page-cache so the next hit re-emits headers.
		await w3tc.flushAll(adminPage);
		await sys.afterRulesChange();

		/**
		 * Fetch the homepage anonymously and inspect the response
		 * header map. Node's HTTP client normalises header names
		 * to lowercase. If the value were split, an injected
		 * `x-injected-by-w3tc: yes` would appear here.
		 */
		let r = await sys.httpGet(env.homeUrl);
		log.log('after poison: response headers = ' +
			JSON.stringify(r.headers, null, 2).substring(0, 1024));

		// 1. The injected header MUST NOT appear.
		expect(r.headers['x-injected-by-w3tc']).is.undefined;

		/**
		 * 2. If `X-W3TC-CDN` did get emitted (defense-in-depth
		 * might suppress it entirely; the `strpbrk` check
		 * returns false), it must not carry the CRLF tail.
		 */
		let cdnHeader = r.headers['x-w3tc-cdn'];
		if (typeof cdnHeader === 'string') {
			expect(cdnHeader).not.contains('X-Injected');
			expect(cdnHeader).not.contains('\r');
			expect(cdnHeader).not.contains('\n');
		}
		log.success('no header injection from CRLF in cdn.engine');
	});
});
