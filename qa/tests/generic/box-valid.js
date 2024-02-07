function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

describe('Check if box is valid', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('Dashboard page shown fine', async() => {
		log.success('logger module available');

		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_dashboard');
		let html = await adminPage.content();

		expect(html).contains('Setup Guide');

		let wizardSkip = '#w3tc-wizard-skip';
		await Promise.all([
			adminPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
			adminPage.waitForNavigation()
		]);

		expect(await adminPage.content()).contains('Setup Guides from Partner Hosts');
	})



	it('Rewrite works', async() => {
		await page.goto(env.homeUrl + 'feed/');
		let html = await page.content();

		expect(html).contains('xmlns:content');
	})
});
