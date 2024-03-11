function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: environments('blog') */

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
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__html__etag: false,
			browsercache__html__last_modified: false
		});

		await sys.afterRulesChange();
	});



	it('feed without caching', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		let response = await page.goto(env.homeUrl + 'feed/');

		let content = await page.content();
		expect(content).contains('Page Caching using Disk: Enhanced (Page is feed)');
		expect(content).contains('xmlns:content');

		let headers = response.headers();
		log.log('check if content-type is ok - ' + headers['content-type']);
		expect(headers['content-type']).contains('rss');
	});



	it('enable caching', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__cache__feed: true
		});

		await sys.afterRulesChange();
	});



	it('feed 1st load - uncached', async() => {
		let response = await page.goto(env.homeUrl + 'feed/');

		expect(response.status()).equals(200);

		let content = await page.content();
		expect(content).matches(/Page Caching using Disk: Enhanced\s*[\r\n]/);
		expect(content).contains('xmlns:content');

		let headers = response.headers();
		log.log('check if content-type is ok - ' + headers['content-type']);
		expect(headers['content-type']).contains('rss');
	});



	it('feed 2nd load - cached', async() => {
		console.log(env.homeUrl + 'feed/');
		let response = await page.goto(env.homeUrl + 'feed/');

		expect(response.status()).equals(200);

		let content = await page.content();
		expect(content).matches(/Page Caching using Disk: Enhanced\s*[\r\n]/);
		expect(content).contains('xmlns:content');

		let headers = response.headers();
		log.log('check if content-type - ' + headers['content-type']);
		expect(headers['content-type']).contains('xml');

		phpResponse = (headers['w3tc_php'] != null);
		expect(phpResponse).is.false;
	});
});
