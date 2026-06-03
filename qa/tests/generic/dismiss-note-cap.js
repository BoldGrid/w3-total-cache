/**
 * File: qa/tests/generic/dismiss-note-cap.js
 *
 * Capability gate on admin-note dismiss endpoints
 * (`w3tc_default_hide_note`, `w3tc_default_hide_note_custom`,
 * `w3tc_default_config_state*`).
 *
 * These handlers are gated at two layers: the admin-action
 * dispatcher in `Generic_Plugin_Admin::load()` requires a valid
 * `_wpnonce` (per-action key, with the legacy `'w3tc'` token still
 * accepted for back-compat), and each handler calls
 * `Generic_AdminActions_Default::_require_admin_cap()` which floors
 * at `current_user_can('manage_options')`. Unauthenticated callers
 * fail the cap check; authenticated subscribers fail the cap check
 * even if they somehow had a nonce.
 *
 * Posture: feature side asserts an admin GET to
 * `w3tc_default_hide_note&note=<key>` flips the corresponding
 * `notes.<key>` slot in the main config blob to false (legacy
 * dismiss path still used by a few notices such as CloudFlare).
 * Regression side uses an unauthenticated request (no admin cookies)
 * and asserts the slot stays unset.
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');
const util   = require('util');
const execFile = util.promisify(require('child_process').execFile);

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

/**
 * A note key that is not used by any real notice; we choose
 * something unique enough to verify the option write deterministically
 * via wp-cli read-back. The handler doesn't validate against a
 * known-keys list (it's a generic dismiss endpoint), so this works.
 */
const PROBE_NOTE = 'qa_probe_dismiss_note_' + Date.now();

/**
 * Run PHP in WP context without a shell so `$variables` are not
 * expanded by bash (JSON.stringify + exec() was turning `$d=` into
 * `=json_decode(...)` and triggering wp eval parse errors).
 *
 * @param {string} php PHP snippet for `wp eval`.
 * @return {Promise<string>} Trimmed stdout.
 */
async function wpEval(php) {
	let r = await execFile('sudo', [
		'-u', 'www-data',
		'wp', 'eval', php,
		'--path=' + env.wpPath,
	]);
	return (r.stdout || '').trim();
}

async function readNoteFlag(noteKey) {
	/**
	 * Read through `Dispatcher::config()` so file (`w3tc-config/`) and
	 * DB (`w3tc_config_{blog_id}`) backends both resolve. Return `null`
	 * when the slot has never been written (distinct from `false`).
	 */
	let configKey = 'notes.' + noteKey;
	let php = '$d=json_decode(\\W3TC\\Dispatcher::config()->export(),true);' +
		'$k=' + JSON.stringify(configKey) + ';' +
		'echo json_encode(array_key_exists($k,$d)?$d[$k]:null);';
	try {
		let raw = await wpEval(php);
		return JSON.parse(raw);
	} catch (e) {
		log.log('readNoteFlag failed: ' + (e.stderr || e.message || e));
		return undefined;
	}
}

/**
 * Load the W3TC dashboard and return the legacy `'w3tc'` nonce minted for
 * the logged-in admin session (`Util_Ui::nonce_field( 'w3tc' )`).
 *
 * Do not mint this via `wp eval`: CLI runs outside the browser session and
 * produces a user-0 nonce that fails verification (WP 7+ responds 403 with
 * "The link you followed has expired.").
 *
 * @return {Promise<string>}
 */
async function dashboardLegacyNonce() {
	await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
		{waitUntil: 'domcontentloaded'});

	if (await adminPage.$('#w3tc-wizard-skip') != null) {
		await Promise.all([
			adminPage.evaluate(() => document.querySelector('#w3tc-wizard-skip').click()),
			adminPage.waitForNavigation({timeout: 300000}),
		]);
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
			{waitUntil: 'domcontentloaded'});
	}

	let nonce = await adminPage.$eval('input[name="_wpnonce"]', (e) => e.value);
	expect(nonce).not.empty;
	return nonce;
}

describe('Admin-note dismiss endpoint capability gate', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	/**
	 * Feature side: admin GET to the dismiss endpoint sets the
	 * option to false. We assert by reading the option back via
	 * wp-cli; the response body itself is a redirect, not a
	 * status report.
	 */
	it('admin GET to w3tc_default_hide_note sets notes.<key>=false', async() => {
		let nonce = await dashboardLegacyNonce();

		expect(await readNoteFlag(PROBE_NOTE)).equals(null);

		/**
		 * Match `Util_Ui::button_hide_note()` URL shape; top-level
		 * navigation keeps the admin auth cookies on the request.
		 */
		let dismissUrl = env.adminUrl + 'admin.php?page=w3tc_dashboard' +
			'&w3tc_default_hide_note&note=' + encodeURIComponent(PROBE_NOTE) +
			'&_wpnonce=' + encodeURIComponent(nonce);
		let dismissResponse = await adminPage.goto(dismissUrl,
			{waitUntil: 'domcontentloaded'});
		log.log('admin dismiss navigation status ' + dismissResponse.status());

		let bodyText = await adminPage.evaluate(() => document.body.innerText);
		expect(bodyText.indexOf('The link you followed has expired')).equals(-1);
		expect(bodyText.indexOf('You do not have sufficient permissions')).equals(-1);

		/**
		 * Read back through wp-cli; the handler calls $config->save()
		 * before redirecting, so the config blob is flushed.
		 */
		let val = await readNoteFlag(PROBE_NOTE);
		log.log('after admin dismiss: notes.' + PROBE_NOTE + ' = ' + JSON.stringify(val));
		expect(val).equals(false);
		log.success('admin can dismiss note');
	});

	/**
	 * Regression side: unauthenticated request to the same URL
	 * must NOT flip the flag. `_require_admin_cap()` returns 403
	 * via `wp_die()` before the config write.
	 */
	it('anon request to w3tc_default_hide_note does NOT flip notes.<key>', async() => {
		let probeKey = 'qa_anon_probe_' + Date.now();
		let triggerUrl = env.adminUrl +
			'admin.php?page=w3tc_dashboard&w3tc_default_hide_note&note=' + probeKey;

		expect(await readNoteFlag(probeKey)).equals(null);

		/**
		 * Anonymous GET — `sys.httpGet()` includes no cookies, so
		 * the request is unauthenticated.
		 */
		let r;
		try {
			r = await sys.httpGet(triggerUrl, {followRedirects: true});
		} catch (e) {
			log.log('anon GET error (expected for some configs): ' + e);
		}
		if (r) {
			log.log('anon GET status: ' + r.statusCode);
		}

		expect(await readNoteFlag(probeKey)).equals(null);
		log.success('anon request was rejected — notes.' + probeKey + ' is unset');
	});
});
