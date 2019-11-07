function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('cache')) */

describe('', function() {
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

		await sys.copyPhpToRoot('../../plugins/dbcache/basic.php');
	});



	it('check cache works', async() => {
		await setDBCache();
		await getDBCache('Hello world!');
		await changeCache();
		await getDBCache('Change');

		await w3tc.flushAll(adminPage);

		await getDBCache('Hello world!');
	});
});



async function setDBCache(test) {
	log.log('adding DB cache');

	await page.goto(env.blogSiteUrl + '/basic.php?action=add_cache&blog_id=' +
		env.blogId + '&engine=' + env.cacheEngineLabel);
	let added = await page.$eval('#added', (e) => e.textContent);
	expect(added).equals('ok');
}



async function getDBCache(value) {
	log.log('check cache value');

	await page.goto(env.blogSiteUrl + '/basic.php?action=get_cache&blog_id=' +
		env.blogId + '&engine=' + env.cacheEngineLabel);
	let html = await page.content();
	expect(html).contains(value);
}



async function changeCache() {
	log.log('changing cache');

	await page.goto(env.blogSiteUrl + '/basic.php?action=change_cache&blog_id=' +
		env.blogId + '&engine=' + env.cacheEngineLabel);
	log.log(await page.content());
	let changed = await page.$eval('#changed', (e) => e.textContent);
	expect(changed).equals('ok');
}
