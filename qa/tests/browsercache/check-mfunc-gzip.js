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
		/**
		 * The eval()/include primitives behind mfunc/mclude were removed; the
		 * dispatcher now only honours `call:<slug>` payloads against callbacks
		 * registered via the `w3tc_dynamic_callbacks` filter. This mu-plugin
		 * registers `qa_multiply` which returns `a * b` so the test below can
		 * assert on the same `4428840` (= 5678 * 780) hit indicator.
		 */
		await sys.copyPhpToPath('../../plugins/pagecache/dynamic-mfunc-callback.php',
			env.wpContentPath + 'mu-plugins');

		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			dbcache__enabled: true,
			objectcache__enabled: true,
			browsercache__enabled: true,
			dbcache__engine: 'file',
			objectcache__engine: 'file',
			pgcache__engine: 'file'
		});

		/**
		 * Late init defers serving a cache HIT until after MU-plugins
		 * register `w3tc_dynamic_callbacks` (advanced-cache runs before
		 * plugins load; without this the 2nd request dispatches with an
		 * empty registry). Same requirement as dynamic-late-init.js.
		 */
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache_late_init: true
		});

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
			content: '<!-- mfunc phptest call:qa_multiply {"a":5678,"b":780} --><!-- /mfunc phptest -->'
		});
		testPageUrl = testPage.url;
	});



	it('first page load', async() => {
		let response = await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);
		await expectResponseCorrect(response);
	});



	it('2nd page load', async() => {
		let response = await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);
		await expectResponseCorrect(response);
	});
});



async function expectResponseCorrect(response) {
	let headers = response.headers();
	expect(headers['content-encoding']).equals('gzip');

	let content = await page.content();
	expect(content).not.contains('call:qa_multiply');
	expect(content).contains('4428840');
	w3tc.expectPageCachingMethod(content, 'Disk');
}
