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

/**environments:environments('blog') */

let cacheQuery = '';

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: true,
		});

		// Enable "Prevent caching of objects after settings change" on browser cache page
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__other__replace: true,
			browsercache__cssjs__replace: true
		});

		await sys.afterRulesChange();
	});



	it('check content', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});

		// checking that bla.css file got our bla.css?fjfjg and url opens
		let scripts = await dom.listScriptSrc(page);

		scripts.forEach(function(url) {
			expect(url).matches(/\?(x[0-9]+)/);
			let m = url.match(/\?([x0-9]+)/);
			cacheQuery = m[1];
			expect(cacheQuery).not.empty;
		});


		// opening first javascript
		let response = await page.goto(scripts[0], {waitUntil: 'domcontentloaded'});
		expect(response.status() < 400);
	});



	it('enable Rewrite URL structure', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__rewrite: true
		});

		await sys.afterRulesChange();
		await w3tc.followNoteFlushStatics(adminPage);
	});



	it('check content', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});

		let scripts = await dom.listScriptSrc(page);
		let m = scripts[0].match(/.+\.(x([0-9]+))\.js/);
		cacheQuery = m[1];
		expect(cacheQuery).not.empty;

		//expect(id[1]).equals(cacheQuery);
		let urlReg = new RegExp('^https?:\\/\\/.+\\.' + cacheQuery + '\\.js');
		scripts.forEach(function(url) {
			log.log('checking url ' + url);
			expect(url).matches(urlReg);
		});

		let response = await page.goto(scripts[0], {waitUntil: 'domcontentloaded'});
		expect(response.status() < 400);
	});



	// flush caches
	it('flush', async() => {
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
			{waitUntil: 'domcontentloaded'});
		await Promise.all([
			adminPage.click('input[name=w3tc_flush_browser_cache]'),
			adminPage.waitForNavigation({timeout:0}),
		]);
	});



	it('check querystring changed after flush', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});

		let scripts = await dom.listScriptSrc(page);
		scripts.forEach(function(url) {
			log.log('check url ' + url);
			let m = url.match(/.+\.(x([0-9])+)\.js/);
			let queryString = m[1];
			expect(queryString).not.empty;
			expect(queryString).not.equals(cacheQuery);
		});
	});
})
