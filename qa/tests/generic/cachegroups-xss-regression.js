/**
 * File: qa/tests/generic/cachegroups-xss-regression.js
 *
 * sec-xss CacheGroups regression — stored XSS in the Cache Groups
 * "User Agent" group editor.
 *
 * Before the fix, the User Agents textarea content was echoed back
 * into the admin form on re-render without escaping, and the group
 * name was rendered inside `<span class="mobile_group">` without
 * `esc_html`. A stored value of `<script>...</script>` then executed
 * in the admin context on the next page load, hijacking `_wpnonce`
 * values for every admin action — the entry hop in the
 * chain-critical-stored-xss-cachegroups-filter chain.
 *
 * After the fix:
 *  - `sanitize_text_field( wp_unslash( $_POST['mobile_groups'][..][agents] ) )`
 *    is applied at the $_POST boundary in
 *    `CacheGroups_Plugin_Admin::w3tc_config_ui_save_w3tc_cachegroups`,
 *    stripping tags before persistence.
 *  - The textarea re-render at
 *    `CacheGroups_Plugin_Admin_View.php:115` uses `esc_textarea()`.
 *  - The group label at line 58 uses `esc_html()`.
 *
 * Posture: feature side asserts a normal user-agent group (`mozilla`,
 * `iphone`) round-trips correctly through save + reload (clean_values
 * lowercases on persist). Regression
 * side asserts a `<script>` payload submitted as the agent body is
 * stored stripped and rendered escaped — and that the XSS marker
 * never fires in the page context.
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
 * The payload sets a window flag the regression block then asserts
 * is undefined. A literal `<script>` token (without the closing
 * `</script>`) is enough to detect raw echo, but using a full,
 * fireable script lets `page.evaluate()` confirm the script never
 * ran in the live page context.
 */
const XSS_AGENT_PAYLOAD = '<script>window.__w3tc_cachegroups_xss_fired=1</script>iPhone';
const XSS_GROUP_NAME    = 'xssprobe';

describe('sec-xss CacheGroups UserAgent regression', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Feature side: a benign user-agent group save round-trips
	 * through the same form path; this is the positive control
	 * that establishes the form-save selectors work at all on the
	 * current WP / W3TC build.
	 */
	it('benign user-agent group saves and re-renders', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups',
			{waitUntil: 'domcontentloaded'});

		// Skip the wizard if present.
		if (await adminPage.$('#w3tc-wizard-skip') != null) {
			await Promise.all([
				adminPage.evaluate(() => document.querySelector('#w3tc-wizard-skip').click()),
				adminPage.waitForNavigation({timeout: 300000})
			]);
			await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups',
				{waitUntil: 'domcontentloaded'});
		}

		/**
		 * `mobile_add` button calls jquery code that injects a new
		 * group block; rather than drive the DOM-add flow, POST the
		 * `mobile_groups[...]` array directly. The handler accepts
		 * any group key not already present.
		 */
		let nonce = await adminPage.$eval('input[name=_wpnonce]', (e) => e.value);
		expect(nonce).not.empty;

		let r1 = await adminPage.evaluate(async function(adminUrl, n) {
			let body = new URLSearchParams();
			body.append('_wpnonce', n);
			body.append('w3tc_save_options', 'Save all settings');
			body.append('mobile_groups[benign][enabled]', '1');
			body.append('mobile_groups[benign][theme]', '');
			body.append('mobile_groups[benign][redirect]', '');
			body.append('mobile_groups[benign][agents]', 'Mozilla\niPhone\nAndroid');
			let r = await fetch(adminUrl + 'admin.php?page=w3tc_cachegroups', {
				method: 'POST', body: body, credentials: 'include', redirect: 'follow'
			});
			return r.status;
		}, env.networkAdminUrl, nonce);
		log.log('benign save returned ' + r1);

		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups',
			{waitUntil: 'domcontentloaded'});

		let benignAgents = await adminPage.$eval('#mobile_groups_benign_agents',
			(e) => e.value).catch(() => '');
		expect(benignAgents).contains('iphone');
		log.success('benign user-agent group persisted');
	});

	/**
	 * Regression side: a `<script>` payload submitted as the agent
	 * body must be stripped by `sanitize_text_field`, and the
	 * re-rendered textarea must show the escaped form. The window-
	 * flag check is the live-fire proof that no raw script tag
	 * reached the DOM.
	 */
	it('<script> payload in agent body is stripped + escaped on render', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups',
			{waitUntil: 'domcontentloaded'});
		let nonce = await adminPage.$eval('input[name=_wpnonce]', (e) => e.value);
		expect(nonce).not.empty;

		let r1 = await adminPage.evaluate(async function(adminUrl, n, payload, groupName) {
			let body = new URLSearchParams();
			body.append('_wpnonce', n);
			body.append('w3tc_save_options', 'Save all settings');
			body.append('mobile_groups[' + groupName + '][enabled]', '1');
			body.append('mobile_groups[' + groupName + '][theme]', '');
			body.append('mobile_groups[' + groupName + '][redirect]', '');
			body.append('mobile_groups[' + groupName + '][agents]', payload);
			let r = await fetch(adminUrl + 'admin.php?page=w3tc_cachegroups', {
				method: 'POST', body: body, credentials: 'include', redirect: 'follow'
			});
			return r.status;
		}, env.networkAdminUrl, nonce, XSS_AGENT_PAYLOAD, XSS_GROUP_NAME);
		log.log('xss-payload save returned ' + r1);

		/**
		 * Reload the page in a fresh navigation; if the value were
		 * echoed unescaped, the inline <script> would execute here
		 * and set window.__w3tc_cachegroups_xss_fired.
		 */
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups',
			{waitUntil: 'domcontentloaded'});

		let xssFired = await adminPage.evaluate(
			() => typeof window.__w3tc_cachegroups_xss_fired !== 'undefined');
		expect(xssFired).equals(false);
		log.success('window.__w3tc_cachegroups_xss_fired did not fire');

		/**
		 * Pull the textarea value as well; sanitize_text_field
		 * strips the entire `<script>...</script>` block including
		 * its body, so the stored value should be just the trailing
		 * `iphone` text from the payload (clean_values lowercases).
		 */
		let agentVal = await adminPage.$eval(
			'#mobile_groups_' + XSS_GROUP_NAME + '_agents',
			(e) => e.value).catch(() => '');
		log.log('stored agent value: ' + JSON.stringify(agentVal));
		expect(agentVal).not.contains('<script>');
		expect(agentVal).not.contains('</script>');

		/**
		 * Pull the raw rendered HTML for the textarea; the value
		 * content must appear escaped (`&lt;script&gt;` would only
		 * be present if the sanitizer let HTML through and esc_textarea
		 * escaped it on render).
		 */
		let pageHtml = await adminPage.content();
		expect(pageHtml).not.contains('<script>window.__w3tc_cachegroups_xss_fired');
		log.success('no raw <script> payload in rendered admin page');
	});
});
