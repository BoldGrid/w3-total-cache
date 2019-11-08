function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/* dont run under varnish - not related to it by any means */
/**environments:
variable_not_equals('W3D_VARNISH', ['varnish'],
	multiply(
		environments('blog'),
		environments('cache')
	)
)
*/

describe('"Dont cache for logged in" works', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			dbcache__enabled: true,
			browsercache__enabled: false,
			dbcache__engine: env.cacheEngineLabel
		});

		// prevent cron in-process operations causing dbcache flush
		await wp.addWpConfigConstant(adminPage, 'DISABLE_WP_CRON', true);

		await sys.copyPhpToRoot('../../plugins/dbcache/dont-cache-for-logged-in.php');
	});



	it('checking that is not cached for logged in', async() => {
		await getCache(adminPage, 'Hello world!', 'Cache created');
		await updateOutOfWP('test_update');
		await getCache(adminPage, 'test_update', 'Title updated, the page is not cached');
	});



	it('check that cache is used when switched off', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_dbcache', {
			dbcache__reject__logged: false
		});

		await getCache(adminPage, 'test_update', 'The cache was potentially created');
		await updateOutOfWP('Hello');
		await getCache(adminPage, 'test_update', 'Title was not updated, the cache retrieved');
	});
});



async function getCache(pPage, title, text) {
	await pPage.goto(env.blogSiteUrl + 'dont-cache-for-logged-in.php?action=get_cache&title=' + title +
		'&engine=' + env.cacheEngineLabel);
	let html = await pPage.content();
	expect(html).contains('1');
}



async function updateOutOfWP(title) {
	await page.goto(env.blogSiteUrl + 'dont-cache-for-logged-in.php?action=update_record&title=' +
		title + '&engine=' + env.cacheEngineLabel);
	let html = await page.content();
	expect(html).contains('ok');
}
