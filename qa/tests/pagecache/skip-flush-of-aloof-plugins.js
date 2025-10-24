function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: multiply(environments('blog'), environments('pagecache')) */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/cache-entry.php');
		await sys.copyPhpToRoot('../../plugins/pagecache/skip-flush-of-aloof-plugins-action.php');

		await sys.copyPhpToPath('../../plugins/pagecache/skip-flush-of-aloof-plugins.php',
			env.wpContentPath + 'mu-plugins');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await sys.afterRulesChange();
	});



	it('check', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

		// trying to write a dummy word into the cached file
		await w3tc.pageCacheEntryChange(page);
		//box.onPageChangedOutside(test);

		// checking if the file was not regenerated again
		log.log('Going to the homepage to check if the file has "test of cache" text...');
		await page.goto(env.homeUrl);
		expect(await page.content()).contains('Test of cache');
	});


	it('flush via button', async() => {
		if (env.isWpmu) {
			await w3tc.flushAll(adminPage);
		} else {
			await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_general');

			let pgcacheFlushButton = 'input[name="w3tc_flush_pgcache"]';
			await Promise.all([
				adminPage.waitForNavigation({timeout: 300000}),
				adminPage.evaluate((pgcacheFlushButton) => document.querySelector(pgcacheFlushButton).click(), pgcacheFlushButton)
			]);

			expect(await adminPage.content()).contains('Page cache successfully emptied.');
		}

		if (env.cacheEngineLabel == 'file_generic') {
			// changing timestamp for index.html.old file in order to flush cache
			await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);
		}
	});



	it('check after flush', async() => {
		await page.goto(env.homeUrl);
		expect(await page.content()).not.contains('Test of cache');
	});



	it('reset 1', async() => {
		// trying to write a dummy word into the cached file
		await w3tc.pageCacheEntryChange(page);
		//box.onPageChangedOutside(test);

		// checking if the file was not regenerated again
		log.log('Going to the homepage to check if the file has "test of cache" text...');
		await page.goto(env.homeUrl);
		expect(await page.content()).contains('Test of cache');
	});



	it('flush via w3tc_flush_all', async() => {
		let controlUrl = env.blogSiteUrl + 'skip-flush-of-aloof-plugins-action.php?action=w3tc_flush_all';
		await adminPage.goto(controlUrl, {waitUntil: 'domcontentloaded'});
		let html = await adminPage.content();
		expect(html).contains('ok');

		if (env.cacheEngineLabel == 'file_generic') {
			// changing timestamp for index.html.old file in order to flush cache
			await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);
		}
	});



	it('check after flush w3tc_flush_all', async() => {
		await page.goto(env.homeUrl);
		expect(await page.content()).contains('Test of cache');
	});



	it('flush via w3tc_flush_posts', async() => {
		let controlUrl = env.blogSiteUrl + 'skip-flush-of-aloof-plugins-action.php?action=w3tc_flush_posts';
		await adminPage.goto(controlUrl, {waitUntil: 'domcontentloaded'});
		let html = await adminPage.content();
		expect(html).contains('ok');

		if (env.cacheEngineLabel == 'file_generic') {
			// changing timestamp for index.html.old file in order to flush cache
			await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);
		}
	});



	it('check after flush w3tc_flush_posts', async() => {
		await page.goto(env.homeUrl);
		expect(await page.content()).contains('Test of cache');
	});
});
