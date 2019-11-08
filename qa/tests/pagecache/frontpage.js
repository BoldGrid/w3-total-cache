function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('pagecache')) */

let newPostNumber = 0;

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
			browsercache__enabled: true,
			pgcache__engine: env.cacheEngineLabel
		});

		// avoid BC side-effects
		// last-mod is enabled by default at server level and BC switches it off
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__html__etag: false,
			browsercache__html__last_modified: false,
			browsercache__html__compression: false
		});

		await sys.afterRulesChange();
	});



	it('post updates work', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__cache__home: true,
			pgcache_late_init: false
		});

		await expectHomeUrlCached();
		await expectNewPostUpdatesHomeUrl();


		//set Cache front page to 0
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__cache__home: false,
			pgcache_late_init: false
		});

		await expectNewPostUpdatesHomeUrl();
	});
});



async function expectHomeUrlCached() {
	await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

	// trying to write a dummy word into the cached file
	await w3tc.pageCacheEntryChange(page);
	//box.onPageChangedOutside(test);

	// checking if the file was not regenerated again
	log.log('Going to the homepage to check if the file has "test of cache" text...');
	await page.goto(env.homeUrl);
	expect(await page.content()).contains('Test of cache');
}



async function expectNewPostUpdatesHomeUrl() {
	if (env.cacheEngineLabel == 'file_generic') {
		await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);
	}

	newPostNumber++;
	let testPage = await wp.postCreate(adminPage, {
		type: 'post',
		title: 'post_title_' + newPostNumber,
		content: 'test'
	});

	await page.goto(env.homeUrl);
	expect(await page.content()).contains('post_title_' + newPostNumber);
}
