function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments:
variable_equals('W3D_WP_NETWORK', ['single'],
	multiply(
		environments('blog'),
		environments('pagecache')
	)
)
*/

let mirrorUrl = env.scheme + '://b2.wp.sandbox' + env.wpMaybeColonPort +
	env.blogHomeUri;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/cache-entry.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await sys.afterRulesChange();
	});



	it('mirror redirects to home', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

		await page.goto(mirrorUrl);
		expect(page.url() == env.homeUrl);
	});



	it('enable mirror', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__mirrors__enabled: true
		});

		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__mirrors__home_urls: mirrorUrl
		});
	});



	it('mirror works separately', async() => {
		await page.goto(env.homeUrl);
		expect(page.url() == env.homeUrl);

		await page.goto(mirrorUrl);
		expect(page.url() == mirrorUrl);

		if (env.cacheEngineLabel == 'file_generic') {
			await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);
			await w3tc.pageCacheFileGenericChangeFileTimestamp(mirrorUrl);
		}

		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'post_title_1',
			content: 'test'
		});

		await page.goto(env.homeUrl);
		expect(await page.content()).contains('post_title_1');

		await page.goto(mirrorUrl);
		expect(page.url() == mirrorUrl);
		expect(await page.content()).contains('post_title_1');
	});
});
