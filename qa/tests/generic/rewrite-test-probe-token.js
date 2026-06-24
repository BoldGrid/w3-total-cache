/**
 * File: qa/tests/generic/rewrite-test-probe-token.js
 *
 * rt9-178 puppeteer regression — anonymous reachability of the
 * `?w3tc_rewrite_test=1` echo.
 *
 * Before rt9-178, an anonymous GET to `<homeUrl>?w3tc_rewrite_test=1`
 * returned the bare body `OK`. That was a two-byte plugin-presence
 * fingerprint (any 200 from this URL proves W3TC is installed) and a
 * cache-bypass side channel (the response always bypasses page cache
 * by design, so probing forces uncached processing). The fix moves
 * the echo behind a one-shot probe token issued by
 * `PgCache_Environment::test_rewrite()` and forwarded in the
 * `X-W3TC-PgCache-Probe` header. Anonymous requests without the
 * header receive 404 from `Util_ProbeToken::reject()`.
 *
 * Posture: feature side asserts the in-process probe still works —
 * the admin "verify rewrites" path issues the token, fires the
 * self-request, and reports success. Regression side asserts that
 * an anonymous browser GET to `?w3tc_rewrite_test=1` returns 404 with
 * no `OK` body, regardless of whether a random non-token header is
 * supplied.
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

describe('rt9-178 rewrite-test probe-token regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Feature side: enabling Disk Enhanced page caching triggers
	 * `PgCache_Environment::test_rewrite()` server-side. If the
	 * in-process probe fails, the save reports a W3TC error banner
	 * — the feature is exactly the rewrite-test self-call, and
	 * `expectW3tcErrors(adminPage, false)` is the existing assertion
	 * surface for that.
	 */
	it('admin save with Disk Enhanced succeeds (probe self-test passes)', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			pgcache__engine: 'file_generic',
			browsercache__enabled: false
		});

		await sys.afterRulesChange();
		await w3tc.expectW3tcErrors(adminPage, false);
		log.success('Disk Enhanced save completed without W3TC errors');
	});

	/**
	 * Regression side: an anonymous GET without the probe-token
	 * header must NOT echo `OK`. We use `sys.httpGet()` rather than a
	 * puppeteer goto so we observe the raw HTTP-layer response (the
	 * 404 status and the absence of `OK`), not a possibly-replaced
	 * error page rendered by the browser.
	 */
	it('anonymous GET ?w3tc_rewrite_test=1 returns 404 with no OK body', async() => {
		let r = await sys.httpGet(env.homeUrl.replace(/\/$/, '') +
			'/?w3tc_rewrite_test=1');

		log.log('anonymous probe returned status ' + r.statusCode);
		expect(r.statusCode).equals(404);
		expect(r.body).not.equals('OK');
		expect(r.body.trim()).not.equals('OK');
	});

	/**
	 * And a GET with a syntactically valid-looking but unissued
	 * probe-token header must still 404 — the consume side checks
	 * the token against the live site_transient store, not just the
	 * `/^[a-f0-9]{32}$/` shape.
	 */
	it('anonymous GET with forged 32-hex token still returns 404', async() => {
		let forgedToken = 'deadbeefcafef00d0123456789abcdef';
		let r = await sys.httpGet(env.homeUrl.replace(/\/$/, '') +
			'/?w3tc_rewrite_test=1', {
				headers: {
					'X-W3TC-PgCache-Probe': forgedToken
				}
			});

		log.log('forged-token probe returned status ' + r.statusCode);
		expect(r.statusCode).equals(404);
		expect(r.body).not.equals('OK');
		expect(r.body.trim()).not.equals('OK');
	});
});
