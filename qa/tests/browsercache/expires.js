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
	      browsercache_cssjs_lifetime: '100'
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
		expect(headers.expires).is.not.empty;
		// same date to check is small enough similar to 100 seconds set above,
		// not default week or what is there
		expect(headers.expires.substr(0, 17)).equals(headers.date.substr(0, 17));

	});



	it('set options - no expires', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
	      pgcache__enabled: true,
	      browsercache__enabled: true
	    });

	    await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
	      browsercache__cssjs__expires: false
	    });

	    await sys.afterRulesChange();
	});



	it('expires is not present', async() => {
		let response = await page.goto(jsUrl, {waitUntil: 'domcontentloaded'});
		let headers = response.headers();
		console.log(headers);
		expect(headers.expires).is.undefined;
	});
});
