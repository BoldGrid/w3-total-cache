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

let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: 'file'
		});

		await wp.addWpConfigConstant(adminPage, 'W3TC_DYNAMIC_SECURITY', 'phptest');
		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'post_1_title',
			content: '<!-- mfunc phptest --> echo 5678 * 780; <!-- /mfunc phptest -->'
		});

		testPageUrl = testPage.url;
	});



	it('check mfunc works', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);

		let content = await page.content();
		expect(content).not.contains('echo');
		expect(content).contains('4428840');
	});

	it('check mfunc works for 2nd request', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);
		let response = await page.goto(testPageUrl);

		let content = await page.content();
		expect(content).not.contains('echo');
		expect(content).contains('4428840');
	});
});
