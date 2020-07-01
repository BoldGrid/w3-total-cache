function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const fs = require('fs');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

// doesnt support disk-enhanced
/**environments: multiply(environments('blog'), environments('cache')) */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('add header on cache', async() => {
		let filename = env.wpPluginsPath + 'w3-total-cache/PgCache_ContentGrabber.php';
		let content = fs.readFileSync(filename, {encoding: 'utf8'});
		content = content.replace(
			'$this->process_cached_page_and_exit( $this->_cached_data );',
			'header("w3tc-cache: hit"); $this->process_cached_page_and_exit( $this->_cached_data );');
		fs.writeFileSync(filename, content, {encoding: 'utf8'});
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			pgcache__engine: env.cacheEngineLabel
		});
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__html__etag: false,
			browsercache__html__last_modified: false
		});

		await sys.afterRulesChange();
	});



	it('check cached', async() => {
		// going to homepage to create cache
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		await checkCached(env.homeUrl, true);
	});



	it('set options - qs', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache_accept_qs: 'my_query',
			pgcache__cache__query: true
		});

		await sys.afterRulesChange();
	});



	it('check', async() => {
		await checkCached(env.homeUrl, true);
		await checkCached(env.homeUrl + '?min=1', false);
		await checkCached(env.homeUrl + '?min=1', true);
		await checkCached(env.homeUrl + '?my_query=2', true);
		await checkCached(env.homeUrl + '?my_query=2&min=3', true);
		await checkCached(env.homeUrl + '?min=4&my_query=5', false);
		await checkCached(env.homeUrl + '?min=4&my_query=5', true);
	});
});



async function checkCached(url, isCached) {
	log.log('opening ' + url);
	let response = await page.goto(url);

	let content = await page.content();
	let headers = response.headers();

	let fromCache = (headers['w3tc-cache'] == 'hit');

	expect(fromCache == isCached);
}
