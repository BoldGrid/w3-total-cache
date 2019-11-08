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



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await sys.afterRulesChange();
	});



	it('check logged-in not cached', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(adminPage, env.homeUrl);

		expect(await adminPage.content()).contains('(User is logged in)');
		log.success('The page is not cached for logged in users.');

		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

		expect(await page.content()).not.contains('(User is logged in)');
	});



	it('set option', async() => {
		//setting up "cache for logged in"
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__reject__logged: false
		});
	});



	it('check logged-in cached', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(adminPage, env.homeUrl);

		expect(await adminPage.content()).not.contains('(User is logged in)');
		log.success('The page is cached for logged in users.');
	});
});
