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
		await adminPage.click('input[value="compatibility check"]');

		await adminPage.waitFor(() => {
			return typeof(document.querySelector('div.lightbox-content')) != 'undefined' &&
				document.querySelector('div.lightbox-loader') == null;
		});

		let html = await adminPage.$eval('#w3tc-self-test', (e) => e.innerHTML);

		expect(html).contains('cURL extension:');
	});
});
