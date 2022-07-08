function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

let testPage;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: 'file_generic'
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'page_1_title',
			content: 'content1'
		});

		log.log(testPage.url);
	});



	it('fill the cache', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPage.url);
		await page.goto(env.homeUrl);
	});

	it('ends with canoncial for slashed', async() => {
		let url = testPage.url.replace(/[\/]+$/g, '') + '/';
		log.log(`trying ${url}`);

		let response = await page.goto(url);
		expect(page.url().toLowerCase()).equals(testPage.url.toLowerCase());
		expectNoPhp(response);
	});

	it('ends with canoncial for naked', async() => {
		let url = testPage.url.replace(/[\/]+$/g, '');
		log.log(`trying ${url}`);

		let response = await page.goto(url);
		expect(page.url().toLowerCase()).equals(testPage.url.toLowerCase());
		expectNoPhp(response);
	});

	it('updating the post title', async() => {
		await wp.postUpdate(adminPage, {
			'post_id'   : testPage.id,
			'post_title': 'page_2_title',
			'post_type' : 'post'
		});

		await w3tc.pageCacheFileGenericChangeFileTimestamp(testPage.url);
	});

	it('ends with canoncial for slashed', async() => {
		await page.goto(testPage.url);   // fill the cache

		let url = testPage.url.replace(/[\/]+$/g, '') + '/';
		log.log(`trying ${url}`);

		let response = await page.goto(url);
		expect(page.url().toLowerCase()).equals(testPage.url.toLowerCase());
		expectNoPhp(response);

		let content = await page.content();
		expect(content).contains('Page Caching using');
		log.success('is cached');
		expect(content).contains('page_2_title');
		log.success('contains new post content');
	});

	it('ends with canoncial for naked', async() => {
		let url = testPage.url.replace(/[\/]+$/g, '');
		log.log(`trying ${url}`);

		let response = await page.goto(url);
		expect(page.url().toLowerCase()).equals(testPage.url.toLowerCase());
		expectNoPhp(response);

		let content = await page.content();
		expect(content).contains('Page Caching using');
		log.success('is cached');
		expect(content).contains('page_2_title');
		log.success('contains new post content');
	});
});



function expectNoPhp(response) {
	log.log('make sure not passed to PHP fallback and handled by rules');
	let headers = response.headers();

	phpResponse = (headers['w3tc_php'] != null);

	if (env.boxName.indexOf('php55') >= 0) {
		log.error('php handled here in apache 2.4.7 - skip it since its apache bug');
	} else {
		expect(phpResponse).is.false;
	}
}
