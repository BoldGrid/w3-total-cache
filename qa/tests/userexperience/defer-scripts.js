/**
 * File: qa/tests/userexperience/defer-scripts.js
 *
 * UserExperience Delay Scripts form-save coverage (Pro-gated).
 *
 * Delay Scripts inputs are rendered via `Util_Ui::config_item_pro`
 * which on free builds emits a disabled "go pro" placeholder. The
 * spec detects this at runtime by checking whether the textbox is
 * present and writable; if absent or disabled it short-circuits
 * with `this.skip()` rather than failing on the matrix that
 * happens to run a free build.
 *
 * Posture: round-trip the three Pro keys
 * (`user-experience-defer-scripts.timeout`,
 * `user-experience-defer-scripts.includes`,
 * `user-experience-defer-scripts.excludes`) through `setOptions`
 * and assert read-back.
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

describe('UserExperience: Delay Scripts (Pro) form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('Pro-only Delay Scripts inputs round-trip when Pro is active', async function() {
		/**
		 * Activate the DeferScripts extension via the master
		 * extensions page. On free builds this won't activate
		 * (the extension class is gated on is_w3tc_pro); we
		 * detect the absence of the timeout input on the rendered
		 * page and skip.
		 */
		await w3tc.activateExtension(adminPage, 'user-experience-defer-scripts')
			.catch((e) => log.log('activate-extension warning: ' + e.message));

		// Navigate to the page and probe for the Pro-only input.
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_userexperience',
			{waitUntil: 'domcontentloaded'});

		/**
		 * `config_item_pro` emits the input under the dotted-key
		 * translation: array key ['user-experience-defer-scripts',
		 * 'timeout'] becomes id="user-experience-defer-scripts___timeout".
		 */
		let inputEl = await adminPage.$('#user-experience-defer-scripts___timeout');
		if (inputEl === null) {
			log.log('SKIP: Delay Scripts input not rendered (free build or extension off)');
			this.skip();
			return;
		}

		/**
		 * Check whether the input is disabled — config_item_pro
		 * returns a disabled stub on free builds even if the
		 * element renders.
		 */
		let isDisabled = await adminPage.$eval(
			'#user-experience-defer-scripts___timeout',
			(e) => e.disabled);
		if (isDisabled) {
			log.log('SKIP: Delay Scripts input rendered but disabled (free build)');
			this.skip();
			return;
		}

		await w3tc.setOptions(adminPage, 'w3tc_userexperience', {
			'user-experience-defer-scripts___timeout':  '7500',
			'user-experience-defer-scripts___includes': 'googletagmanager.com\ngtag/js',
			'user-experience-defer-scripts___excludes': 'jquery.js\nlogged-in-only.js'
		});

		// Reload + verify rendered values.
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_userexperience',
			{waitUntil: 'domcontentloaded'});

		let timeoutVal = await adminPage.$eval(
			'#user-experience-defer-scripts___timeout', (e) => e.value);
		expect(timeoutVal).equals('7500');

		let includesVal = await adminPage.$eval(
			'#user-experience-defer-scripts___includes', (e) => e.value);
		expect(includesVal).contains('googletagmanager.com');

		let excludesVal = await adminPage.$eval(
			'#user-experience-defer-scripts___excludes', (e) => e.value);
		expect(excludesVal).contains('jquery.js');

		log.success('Delay Scripts Pro inputs round-trip');
	});
});
