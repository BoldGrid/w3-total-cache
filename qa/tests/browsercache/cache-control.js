function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const exec = util.promisify(require('child_process').exec);

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

let jsUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
	      pgcache__enabled: true,
	      browsercache__enabled: true
	    });

	    await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
	      browsercache__cssjs__expires: true,
	      browsercache_cssjs_lifetime: '100',
		  browsercache__cssjs__cache__control: true,
		  browsercache_cssjs_cache_policy: 'no_cache'
	    });

	    await sys.afterRulesChange();
	});



	it('find script on page', async() => {
		let response = await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});

		// find some js
		let scripts = await dom.listScriptSrc(page);
		jsUrl = scripts[0];
		log.log(`found ${jsUrl}`);
	});



	it('expires is present', async() => {
		let response = await page.goto(jsUrl, {waitUntil: 'domcontentloaded'});
		let headers = response.headers();
		console.log(headers);
		expect(headers['cache-control']).contains('max-age=0, private, no-store, no-cache, must-revalidate');

	});



	if (process.env['W3D_HTTP_SERVER'] == 'nginx') {
		// no maxage support in nginx
	} else {
		it('set options - maxage', async() => {
			await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			  browsercache_cssjs_cache_policy: 'cache_public_maxage'
		    });

		    await sys.afterRulesChange();
		});



		it('maxage is present', async() => {
			let response = await page.goto(jsUrl, {waitUntil: 'domcontentloaded'});
			let headers = response.headers();
			console.log(headers);
			expect(headers['cache-control']).contains('max-age=100');
		});
	}
});
