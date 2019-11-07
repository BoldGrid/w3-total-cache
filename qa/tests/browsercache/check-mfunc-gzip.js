function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const dom = requireRoot('lib/dom');
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
			dbcache__enabled: true,
			objectcache__enabled: true,
			browsercache__enabled: true,
			dbcache__engine: 'file',
			objectcache__engine: 'file',
			pgcache__engine: 'file'
		});

		// enable html compression
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__html__compression: true
		});

		await wp.addWpConfigConstant(adminPage, 'W3TC_DYNAMIC_SECURITY', 'phptest');
		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'test',
			content: '<!-- mfunc phptest --> echo 5678 * 780; <!-- /mfunc phptest -->'
		});
		testPageUrl = testPage.url;
	});



	it('first page load', async() => {
		let response = await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);
		await expectResponseCorrect(response);
	});



	it('2nd page load', async() => {
		let response = await page.reload({waitUntil: 'domcontentloaded'});
		await expectResponseCorrect(response);
	});
});



async function expectResponseCorrect(response) {
	let headers = response.headers();
	expect(headers['content-encoding']).equals('gzip');

	let content = await page.content();
	expect(content).not.contains('echo');
	expect(content).contains('4428840');
	w3tc.expectPageCachingMethod(content, 'disk');
}
