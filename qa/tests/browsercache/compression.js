function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('pagecache')) */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: true,
			pgcache__engine: env.cacheEngineLabel
		});

		// disable compression
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__cssjs__compression: false,
			browsercache__html__compression: false,
			browsercache__other__compression: false,
			browsercache__html__etag: false,
			browsercache__html__last_modified: false
		});

		await sys.copyPhpToRoot('../../plugins/browsercache/compression.php');
	});



	it('first page load - fill the cache', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

		// check gzip version wasnt created
		let controlUrl = env.blogSiteUrl + 'compression.php?' +
			'blog_id=' + env.blogId +
			'&wp_content_path=' + env.wpContentPath + '&url=' + env.homeUrl +
			'&engine=' + env.cacheEngineLabel;

		await page.goto(controlUrl, {waitUntil: 'domcontentloaded'});
		let html = await page.content();
		expect(html).contains('plain found');
		expect(html).contains('gzip not found');
	});



	it('enable compression', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__cssjs__compression: true,
			browsercache__html__compression: true,
			browsercache__other__compression: true
		});
	});



	it('page loads fine when gzip enabled and no gzip cache exists', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		log.log('Checking that page displays normally...');
		let html = await page.content();
		expect(html).contains('Hello world');
	});



	it('flush cache', async() => {
		await w3tc.flushAll(adminPage);
		if (env.cacheEngineLabel == 'file_generic') {
			await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);
		}
	});



	it('create gzip cache entry', async() => {
		await page.reload({waitUntil: 'domcontentloaded'});
		let html = await page.content();
		expect(html).contains('Hello world');

		w3tc.expectPageCachingMethod(html, env.cacheEngineName);

		// check gzip version wasnt created
		let controlUrl = env.blogSiteUrl + 'compression.php?' +
		'blog_id=' + env.blogId +
		'&wp_content_path=' + env.wpContentPath + '&url=' + env.homeUrl +
		'&engine=' + env.cacheEngineLabel;

		await page.goto(controlUrl, {waitUntil: 'domcontentloaded'});
		let html2 = await page.content();
		expect(html2).contains('plain found');
		expect(html2).contains('gzip found');
	});
});
