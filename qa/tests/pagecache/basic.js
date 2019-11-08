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

		await w3tc.pageCacheEntryChange(page);

		log.log('Going2 to the homepage to check if the file has "test of cache" text...');
		//box.onPageChangedOutside(test);
		let response = await page.goto(env.homeUrl);
		let html = await page.content();
		expect(html.match(/Test of cache/g).length).equals(2);

		if (env.cacheEngineLabel == 'file_generic') {
			log.log('make sure not passed to PHP fallback and handled by rules');
			let headers = response.headers();

			console.log(headers);
			phpResponse = (headers['w3tc_php'] != null);

			if (env.boxName.indexOf('php55') >= 0) {
				log.error('php handled here in apache 2.4.7 - skip it since its apache bug');
			} else {
				expect(phpResponse).is.false;
			}
		}
	});
});
