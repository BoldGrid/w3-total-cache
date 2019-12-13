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

let testPageUrl;
let testPageAmpUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/pagecache/template-amp.php', targetPath);
		await sys.copyPhpToRoot('../../plugins/cache-entry.php');
	});



	it('set options', async() => {
		await w3tc.activateExtension(adminPage, 'amp');
		await w3tc.setOptionInternal(adminPage, ['amp', 'url_type'], 'querystring');
		await w3tc.setOptionInternal(adminPage, ['amp', 'url_postfix'], 'amp');

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
	        content: 'page content',
	        template: 'qa/template-amp.php'
	    });
	    testPageUrl = testPage.url;
		testPageAmpUrl = testPage.url + '?amp';
		log.log('amp page ' + testPageAmpUrl);
	});



	it('check amp page', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);
		expect(await page.content()).contains('!regular-page!');
		log.log('check ' + testPageAmpUrl);
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageAmpUrl);
		expect(await page.content()).contains('!amp-page!');

		// trying to write a dummy word into the cached file
		await w3tc.pageCacheEntryChange(page, null, null, testPageUrl, '_amp');
		//box.onPageChangedOutside(test);

		// checking if the file was not regenerated again
		log.log('Going to the page to check if the file has "test of cache" text...');
		await page.goto(testPageAmpUrl);
		expect(await page.content()).contains('Test of cache');

		await w3tc.pageCacheEntryChange(page, null, null, testPageUrl, '_amp');

		log.log('Going2 to the homepage to check if the file has "test of cache" text...');
		//box.onPageChangedOutside(test);
		let response = await page.goto(testPageAmpUrl);
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

		await page.goto(testPageUrl);
		expect(await page.content()).contains('!regular-page!');
	});
});
