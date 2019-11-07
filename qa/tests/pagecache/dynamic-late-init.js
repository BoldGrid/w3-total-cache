function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/* dont run under varnish - mfunc cant work under varnish of course */
/**environments:
variable_not_equals('W3D_VARNISH', ['varnish'],	environments('blog'))
*/

let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await sys.copyPhpToPath('../../plugins/pagecache/dynamic-late-init.php',
			env.wpContentPath + 'mu-plugins');

		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: true,
			pgcache__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache_late_init: true
		});

		await wp.addWpConfigConstant(adminPage, 'W3TC_DYNAMIC_SECURITY', 'phptest');
		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'post_1_title',
			content: "test"
		});

		testPageUrl = testPage.url;
	});



	it('check mfunc works in late-init mode', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);

		let content = await page.content();
		expect(content).not.contains('echo');
		expect(content).contains('969526');
	});



	it('disable late-init', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache_late_init: false
		});
	});



	it('check mfunc works in regular mode', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);

		let content = await page.content();
		expect(content).not.contains('echo');
		expect(content).not.contains('969526');
	});
});
