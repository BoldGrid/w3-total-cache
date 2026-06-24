/**
 * File: qa/tests/cdn/bunnycdn-exception-xss.js
 *
 * sec-xss BunnyCDN regression (PR E, commit 62423ea2) — exception
 * messages from `Cdn_BunnyCdn_Api` must be escaped before they
 * land in the configured-popup view.
 *
 * Before the fix, `Cdn_BunnyCdn_Popup_View_Configured.php:25`
 * echoed `$error_messages` directly via `nl2br()` without
 * `esc_html()`. The BunnyCDN SDK throws exceptions whose
 * `getMessage()` string can include attacker-controlled fragments
 * (e.g. the bucket / pull-zone name the admin typed, the API
 * response body). A configuration mistake that round-tripped any
 * `<` character into the exception message would render unescaped
 * in the popup, which itself opens inside the admin context.
 *
 * Fix (PR E + the PR-H double-escape correction): the view sink
 * now calls `nl2br( esc_html( (string) $error_messages ) )`.
 *
 * Posture: there is no truly "feature side" — the popup only
 * renders when a real exception is thrown. The spec sets BunnyCDN
 * credentials to a value that the API will reject (empty API key
 * + a pull-zone name embedding `<script>` markers), triggers the
 * popup via its AJAX action, and asserts the rendered HTML escapes
 * the angle brackets.
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
 * A pull-zone name with HTML markers. The BunnyCDN API rejects
 * any pull-zone name with non-alphanumerics, so the SDK throws
 * and `$ex->getMessage()` includes the offending input. If the
 * escape regressed, this token would land unescaped in the popup.
 */
const XSS_MARKER = '<script>window.__w3tc_bunnycdn_xss_fired=1</script>';

describe('sec-xss BunnyCDN configured-popup exception regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('exception message in BunnyCDN configured popup is escaped on render', async() => {
		/**
		 * Activate the BunnyCDN extension and switch CDN engine to
		 * it. We never expect the actual API to succeed; we just
		 * need the popup to render its error branch.
		 */
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			cdn__engine: 'bunnycdn'
		});

		/**
		 * Write a pull-zone name that embeds the XSS marker.
		 * Using `setOptionInternal` bypasses any sanitization at
		 * the form-save boundary, mirroring an admin who pasted
		 * the value from a contaminated source.
		 */
		await w3tc.setOptionInternal(adminPage, 'cdn.bunnycdn.pull_zone_name', XSS_MARKER);
		// Set an invalid API key so the SDK throws on first contact.
		await w3tc.setOptionInternal(adminPage, 'cdn.bunnycdn.account_api_key', 'invalid_key_for_xss_probe');

		/**
		 * Navigate to the popup-rendering URL. The handler is
		 * registered as a wp_ajax_w3tc_* action; we drive it
		 * through `admin-ajax.php`. The exact action name comes
		 * from `Cdn_BunnyCdn_Popup::w3tc_ajax_extension_bunnycdn_view_configured`.
		 */
		let popupResp = await adminPage.evaluate(async function(adminUrl, marker) {
			let body = new URLSearchParams();
			body.append('action', 'w3tc_ajax_extension_bunnycdn_view_configured');
			let r = await fetch(adminUrl + 'admin-ajax.php', {
				method: 'POST', body: body, credentials: 'include'
			});
			let text = await r.text();
			return {status: r.status, marker: marker, body: text};
		}, env.adminUrl, XSS_MARKER);

		log.log('popup AJAX status: ' + popupResp.status);
		log.log('popup body length: ' + popupResp.body.length);

		/**
		 * The escaped form looks like
		 * `&lt;script&gt;window.__w3tc_bunnycdn_xss_fired=1&lt;/script&gt;`.
		 * A regression would emit it raw, in which case the body
		 * would contain the literal `<script>` opener.
		 */
		expect(popupResp.body).not.contains(XSS_MARKER);
		expect(popupResp.body).not.contains('<script>window.__w3tc_bunnycdn_xss_fired');

		/**
		 * Also assert via the live DOM: rendering the body in the
		 * admin context must NOT set the window flag.
		 */
		await adminPage.evaluate((html) => {
			let d = document.createElement('div');
			d.innerHTML = html;
			document.body.appendChild(d);
		}, popupResp.body);

		let xssFired = await adminPage.evaluate(
			() => typeof window.__w3tc_bunnycdn_xss_fired !== 'undefined');
		expect(xssFired).equals(false);
		log.success('BunnyCDN exception sink does not echo raw HTML');
	});
});
