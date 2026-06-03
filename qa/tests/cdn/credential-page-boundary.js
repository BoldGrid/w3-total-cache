/**
 * File: qa/tests/cdn/credential-page-boundary.js
 *
 * rt9-210 puppeteer regression — cross-page CDN credential mass-assignment.
 *
 * The `cdn.<engine>.*` config keys are page-bound by
 * `Generic_AdminActions_Default::_credential_namespace_page()`. A
 * cdn.s3.* write submitted from any admin save form other than
 * `w3tc_cdn` must be silently dropped before the
 * type-coercion / secret paths run. The strict-allowlist already
 * blocks unknown keys; this regression catches the residual where a
 * known per-engine credential key arrives from the wrong page.
 *
 * Posture: feature side asserts the legitimate CDN save (engine = s3,
 * key/secret/bucket via the CDN page) still works end-to-end and
 * survives a re-render. Regression side asserts that a malicious POST
 * to `w3tc_general` carrying an extra `cdn__s3__key=` field does NOT
 * overwrite the value stored by the legitimate save. The malicious
 * POST is constructed via `fetch()` inside the admin context so the
 * test reuses the live admin session cookie + the General page's own
 * `_wpnonce` — exactly the attack the rt9-210 proof exhibited.
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

/**environments:environments('blog') */

const KNOWN_GOOD_KEY = 'AKIAIOSFODNN7EXAMPLE';
const ATTACK_KEY     = 'AKIAATTACKERPWN00000';

describe('rt9-210 CDN credential page-boundary regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('select S3 as the CDN engine on the General page', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			cdn__engine: 's3'
		});

		await sys.afterRulesChange();
	});

	/**
	 * Feature side: writing cdn.s3.key from the CDN page lands in
	 * config and survives a page reload. The masked-secret input rule
	 * at Generic_AdminActions_Default.php:992 means we cannot read the
	 * secret back via the rendered DOM, so we use the non-secret
	 * `cdn_s3_key` (Access Key ID) — it is the canonical opaque
	 * identifier and is rendered in clear text.
	 */
	it('legitimate save from w3tc_cdn writes cdn.s3.key', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn_s3_key: KNOWN_GOOD_KEY,
			cdn_s3_bucket: 'w3tc-qa-bucket-rt9-210'
		});

		expect(await w3tc.getConfigOption('cdn.s3.key')).equals(KNOWN_GOOD_KEY);
		expect(await w3tc.getConfigOption('cdn.s3.bucket')).equals('w3tc-qa-bucket-rt9-210');
		log.success('cdn.s3.key persisted on legitimate save');
	});

	/**
	 * Regression side: a save submitted from w3tc_general that smuggles
	 * `cdn__s3__key=ATTACK_KEY` must not overwrite the value stored
	 * above. The attack is shaped exactly like the rt9-210 proof:
	 * reuse the General page's own nonce, POST cdn__s3__key alongside
	 * the General page's legitimate POST fields.
	 */
	it('cross-page POST of cdn__s3__key is dropped before config write', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general',
			{waitUntil: 'domcontentloaded'});
		let generalNonce = await adminPage.$eval('input[name=_wpnonce]', (e) => e.value);
		expect(generalNonce).not.empty;

		/**
		 * Build the cross-page malicious POST body. The set of base
		 * fields mirrors what a real General-page save would include,
		 * so the handler treats this as a normal save request and the
		 * only thing being "tested" is the per-key namespace gate.
		 */
		let result = await adminPage.evaluate(async function(networkAdminUrl, nonce, attackKey) {
			let body = new URLSearchParams();
			body.append('_wpnonce', nonce);
			body.append('_wp_http_referer', '/wp-admin/network/admin.php?page=w3tc_general');
			body.append('w3tc_save_options', 'Save all settings');
			/**
			 * The smuggled credential key. The handler resolves this
			 * to the cdn.s3.key config slot via the dotted-name
			 * transform.
			 */
			body.append('cdn__s3__key', attackKey);

			let r = await fetch(networkAdminUrl + 'admin.php?page=w3tc_general', {
				method: 'POST',
				body: body,
				credentials: 'include',
				redirect: 'follow'
			});
			return {status: r.status, finalUrl: r.url};
		}, env.networkAdminUrl, generalNonce, ATTACK_KEY);

		log.log('malicious POST returned ' + JSON.stringify(result));

		/**
		 * Now re-load the CDN page and assert the stored key has NOT
		 * been overwritten. The page-boundary gate skipped the write
		 * because `$this->_page === 'w3tc_general' !== 'w3tc_cdn'`.
		 */
		let v = await w3tc.getConfigOption('cdn.s3.key');

		expect(v).equals(KNOWN_GOOD_KEY);
		expect(v).not.equals(ATTACK_KEY);
		log.success('cross-page POST blocked — cdn.s3.key still ' + KNOWN_GOOD_KEY);
	});
});
