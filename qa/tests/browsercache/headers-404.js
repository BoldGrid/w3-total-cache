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

		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			'browsercache__cssjs__expires': true,
			'browsercache__html__expires': true,
			'browsercache__other__expires': true,
			'browsercache__cssjs__cache__control': true,
			'browsercache__html__cache__control': true,
			'browsercache__other__cache__control': true,
			'browsercache__cssjs__w3tc': true,
			'browsercache__html__w3tc': true,
			'browsercache__other__w3tc': true,
			'browsercache__no404wp': true,
			'browsercache_no404wp_exceptions':
				w3tc.regExpForOption(env.blogHomeUri + '404test.jpg'),
		});

		await sys.afterRulesChange();
	});



	it('check headers', async() => {
		let response = await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		let headers = response.headers();

		let setHeaders = ['x-powered-by', 'last-modified', 'expires', 'cache-control', 'etag'];
		for (h of setHeaders) {
			log.log('checking header ' + h);
			expect(headers[h]).is.not.null;
		}
	});



	it('check headers for cached page', async() => {
		let response = await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		let headers = response.headers();

		let setHeaders = ['x-powered-by', 'last-modified', 'expires', 'cache-control', 'etag'];
		for (h of setHeaders) {
			log.log('checking header ' + h);
			expect(headers[h]).is.not.null;
		}
	});



	it('check 404 pages', async() => {
		await page.goto(env.homeUrl + '404.jpg', {waitUntil: 'domcontentloaded'});

		log.log('check that 404 is returned by http server (not wp)');
		let title = await page.title();
		expect(title).contains('404 Not Found');

		log.log('check that 404 is returned by wp for exception url (not http server)');
		await page.goto(env.homeUrl + '404test.jpg', {waitUntil: 'domcontentloaded'});
		let title2 = await page.title();
		expect(title2).contains('Page not found');
	});
});
