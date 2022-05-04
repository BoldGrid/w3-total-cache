function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

// file_generic doesnt support redirect caching
/**environments:
variable_equals('W3D_WP_NETWORK', ['single'],
	multiply(
		environments('blog'),
		environments('cache')
	)
)
*/

let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/pagecache/redirect-caching-redirect.php', targetPath);
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'test',
			template: 'redirect-caching-redirect.php'
		});

		testPageUrl = testPage.url;
	});



	it('redirect works', async() => {
		await page.goto(testPageUrl);
		expect(page.url()).equals(env.scheme + '://for-tests.sandbox' +
			env.wpMaybeColonPort + '/');
	});



	it('switch off redirect', async() => {
		await wp.addWpConfigConstant(adminPage, 'W3TCQA_NO_REDIRECT', 'no_redirect');
	});



	it('check redirect still happens - since cached', async() => {
		await page.goto(testPageUrl);
		expect(page.url()).equals(env.scheme + '://for-tests.sandbox' +
			env.wpMaybeColonPort + '/');
	});
});
