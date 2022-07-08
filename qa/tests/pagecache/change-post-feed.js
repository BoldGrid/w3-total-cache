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

let testPageId;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__debug: true,
		});

		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__cache__feed: true,
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'page_1_title',
			content: 'content1'
		});
		testPageId = testPage.id;
	});



	it('check feed', async() => {
		await page.goto(env.homeUrl + 'feed/');
		let content = await page.content();
		expect(content).contains('Page Caching using');
		log.success('feed is cached');

		expect(content).contains('xmlns:content');
		log.success('feed returns xml content');

		expect(content).contains('page_1_title');
		log.success('feed contains post content');
	});



	it('updating the post title', async() => {
		await wp.postUpdate(adminPage, {
			'post_id'   : testPageId,
			'post_title': 'page_2_title',
			'post_type' : 'post'
		});

		if (env.cacheEngineLabel == 'file_generic') {
			await page.goto(env.homeUrl + 'feed/');
			// changing timestamp for index.html.old file in order to flush cache
			await w3tc.pageCacheFileGenericChangeFileTimestamp(
				env.homeUrl + 'feed', 'xml');
		}
	});



	it('check feed was updated', async() => {
		await page.goto(env.homeUrl + 'feed/');
		let content = await page.content();
		expect(content).contains('Page Caching using');
		log.success('feed is cached');

		expect(content).contains('xmlns:content');
		log.success('feed returns xml content');

		expect(content).contains('page_2_title');
		log.success('feed contains post content');
	});
});
