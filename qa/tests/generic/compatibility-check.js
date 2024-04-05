function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('compatibility page', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_dashboard');

		// Skip the Setup Guide wizard.
		if (await adminPage.$('#w3tc-wizard-skip') != null) {
			log.log('Encountered the Setup Guide wizard; skipping...');

			let wizardSkip = '#w3tc-wizard-skip';
			let skipped = await Promise.all([
				adminPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
				adminPage.waitForNavigation({timeout:0}),
			]);

			expect(skipped).is.not.null;
		}

		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_dashboard');

		let compatibilityCheck = '#w3tc-top-nav-info-menu a.compatiblity-test';
		await adminPage.evaluate((compatibilityCheck) => document.querySelector(compatibilityCheck).click(), compatibilityCheck);

		await adminPage.waitForFunction(() => {
			return typeof(document.querySelector('div.lightbox-content')) != 'undefined' &&
				document.querySelector('div.lightbox-loader') == null;
		});

		let html = await adminPage.$eval('#w3tc-self-test', (e) => e.innerHTML);

		expect(html).contains('cURL extension:');
	});
});
