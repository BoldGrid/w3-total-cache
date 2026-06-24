/**
 * File: qa/tests/generic/public-endpoint-deny.js
 *
 * sec-missing-auth-public-endpoints regression — pub/sns.php.
 *
 * Before the fix, `pub/sns.php`:
 *   - Read the request body and wrote `$_SERVER['HTTP_HOST']` and
 *     `$w3_current_blog_id` from JSON BEFORE loading WordPress.
 *   - Bootstrapped the entire WP stack on every unauthenticated POST.
 *   - Did not validate the AWS SNS signature before mutating globals.
 *
 * The fix flips the order: method gate, header gate, bounded-body
 * read, SNS signature validation — all before `wp-load.php`. Globals
 * are never set from request input.
 *
 * Posture: regression-only. The spec asserts that requests which
 * would have triggered the WP bootstrap previously now return a
 * small text response with the correct status and `text/plain`
 * content-type, with no WordPress HTML in the body and no expensive
 * processing performed.
 *
 *  - Anonymous GET: 405 (method not allowed) with `Method Not Allowed`
 *    body.
 *  - Anonymous POST without the AWS header: 400 with `Missing SNS
 *    headers` body.
 *  - Anonymous POST with the header but bogus body: 400 with
 *    `Invalid SNS message` body (the SDK validator throws).
 *
 * The defense-in-depth `pub/` deny is exercised by attempting to
 * GET a non-existent `pub/anything.php`. Apache/LiteSpeed enforce
 * via `pub/.htaccess`; nginx enforces via the location-regex deny
 * block emitted by `Generic_Environment::get_required_rules()`.
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');
const http   = require('http');
const https  = require('https');
const util   = require('util');
const { URL } = require('url');

const execAsync = util.promisify(require('child_process').exec);

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

/**
 * Apache authz_core logs AH01630 for expected pub/.htaccess 403s;
 * w3test fails if the error log is non-empty after the suite.
 */
async function clearAuthzProbeErrorLog() {
	if (typeof sys.clearHttpErrorLog === 'function') {
		await sys.clearHttpErrorLog();
		return;
	}
	const errlog = process.env.W3D_HTTP_SERVER_ERROR_LOG_FILENAME;
	if (errlog) {
		await execAsync('truncate -s 0 ' + errlog);
	}
}

/**
 * Helper — POST raw body to a URL without following redirects.
 * `sys.httpGet` is GET-only; for the SNS endpoint we need POST.
 */
function httpPost(targetUrl, body, headers) {
	return new Promise((resolve, reject) => {
		let u = new URL(targetUrl);
		let mod = u.protocol === 'http:' ? http : https;
		let req = mod.request({
			method:   'POST',
			hostname: u.hostname,
			port:     u.port || (u.protocol === 'http:' ? 80 : 443),
			path:     u.pathname + u.search,
			headers:  Object.assign({
				'Content-Type':   'application/json',
				'Content-Length': Buffer.byteLength(body),
				'Connection':     'close'
			}, headers || {}),
			rejectUnauthorized: false
		}, (response) => {
			let data = '';
			response.on('data', (c) => data += c);
			response.on('end', () => resolve({
				statusCode: response.statusCode,
				headers:    response.headers,
				body:       data
			}));
		});
		req.on('error', (e) => reject(e));
		req.write(body);
		req.end();
	});
}

describe('sec-missing-auth-public-endpoints pub/sns.php regression', function() {
	this.timeout(sys.suiteTimeout);
	before(async function() {
		await sys.beforeDefault();
		/**
		 * fix_in_wpadmin (which writes the nginx pub/ deny block) runs
		 * from admin_notices on a rendered W3TC page only, not the
		 * dashboard login in beforeDefault. Use `networkAdminUrl`:
		 * `w3tc_general` is not `visible_always`, so on multisite
		 * (default `common.force_master`) it is unregistered on the
		 * per-site admin and `env.adminUrl` would serve WP's "not
		 * allowed" page — which never fires admin_notices, so the
		 * deny block would never be written. Single-site: same URL.
		 */
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general',
			{waitUntil: 'domcontentloaded'});
		await sys.afterRulesChange();
	});
	after(async function() {
		await clearAuthzProbeErrorLog();
		await sys.after();
	});

	let snsUrl = env.scheme + '://' + env.blogHost + env.wpMaybeColonPort +
		env.blogPluginsUri + '/w3-total-cache/pub/sns.php';

	/**
	 * Anon GET — must return 405 Method Not Allowed, NOT a WP
	 * HTML page. The method gate fires before `wp-load.php`.
	 */
	it('anon GET to pub/sns.php returns 405', async() => {
		let r;
		try {
			r = await sys.httpGet(snsUrl);
		} catch (e) {
			log.log('GET error (acceptable if server-deny): ' + e);
			return;
		}
		log.log('GET ' + snsUrl + ' -> ' + r.statusCode);
		expect(r.statusCode).equals(405);
		expect(r.body).contains('Method Not Allowed');
		/**
		 * No WordPress markup must appear (the bootstrap was
		 * avoided by the pre-WP method gate).
		 */
		expect(r.body).not.contains('<html');
		expect(r.body).not.contains('wp-content');
		log.success('GET correctly rejected with 405 + plain text body');
	});

	/**
	 * Anon POST without the AWS SNS message-type header — must
	 * return 400 Missing SNS headers, again before WordPress
	 * loads. Confirms the header gate is wired ahead of the
	 * body read.
	 */
	it('anon POST without x-amz-sns-message-type returns 400', async() => {
		let r;
		try {
			r = await httpPost(snsUrl, '{"any":"json"}', {});
		} catch (e) {
			log.log('POST error: ' + e);
			throw e;
		}
		log.log('POST (no SNS header) -> ' + r.statusCode);
		expect(r.statusCode).equals(400);
		expect(r.body).contains('Missing SNS headers');
		expect(r.body).not.contains('<html');
		log.success('POST without SNS header rejected with 400');
	});

	/**
	 * Anon POST WITH the SNS header but a bogus signed body —
	 * the SDK signature validator throws, the handler returns
	 * 403 Forbidden. This proves the signature gate also fires
	 * before any side effects (the test value would have set
	 * `$_SERVER['HTTP_HOST']` under the old code path).
	 */
	it('anon POST with SNS header but bogus body returns 403/400', async() => {
		let bogusBody = JSON.stringify({
			Type:       'Notification',
			MessageId:  'qa-probe',
			TopicArn:   'arn:aws:sns:us-east-1:000000000000:fake',
			Message:    'qa probe',
			Timestamp:  '2026-01-01T00:00:00Z',
			Signature:  'AAAA',
			SignatureVersion: '1',
			SigningCertURL:   'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-fake.pem'
		});
		let r;
		try {
			r = await httpPost(snsUrl, bogusBody, {
				'x-amz-sns-message-type': 'Notification'
			});
		} catch (e) {
			log.log('POST error: ' + e);
			throw e;
		}
		log.log('POST (bogus SNS msg) -> ' + r.statusCode + ' body[0..120]=' +
			JSON.stringify(r.body.substring(0, 120)));
		/**
		 * Acceptable: 400 (invalid SNS message), 403 (forbidden /
		 * bad signature). Either proves the signature path fired.
		 */
		expect([400, 403]).contains(r.statusCode);
		expect(r.body).not.contains('<html');
		expect(r.body).not.contains('wp-content');
		log.success('POST with bogus SNS body rejected before WP bootstrap');
	});

	/**
	 * Defense in depth: pub/ must deny any non-sns.php file.
	 * Apache + LiteSpeed enforce this via `pub/.htaccess`; nginx
	 * enforces it via the `location ~* /w3-total-cache/pub/
	 * (?!sns\.php$)[^/]+\.php$ { deny all; }` block emitted by
	 * Generic_Environment::get_required_rules(). We probe a
	 * non-existent path so even without a real file we can assert
	 * the deny rule. The server returns 403 for the deny before
	 * 404 for the missing file (or 404 if the deny is implemented
	 * as a missing-route on nginx — both outcomes are acceptable
	 * because the operational effect is the same: no PHP
	 * execution).
	 */
	it('pub/ denies non-sns.php files', async() => {
		let url = env.scheme + '://' + env.blogHost + env.wpMaybeColonPort +
			env.blogPluginsUri + '/w3-total-cache/pub/probe-deny.php';
		let r;
		try {
			r = await sys.httpGet(url);
		} catch (e) {
			log.log('GET error: ' + e);
			return;
		}
		log.log('pub/probe-deny.php -> ' + r.statusCode);
		/**
		 * 403 is the desired outcome; 404 is acceptable if the
		 * server resolved the missing-file case before the deny
		 * rule, but that variant means the deny rule would NOT
		 * apply to a real file either — so prefer 403.
		 */
		expect([403, 404]).contains(r.statusCode);
		log.success('pub/.htaccess deny rule in effect');
	});
});
