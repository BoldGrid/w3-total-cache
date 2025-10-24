function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: environments('blog') */

let cacheQuery;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: false,
			browsercache__enabled: true,
			cdn__engine: 'ftp',
			cdn__enabled: true,
			pgcache__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache_replace: true,
			browsercache__other__replace: true,
			browsercache__cssjs__replace: true
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn_ftp_host: 'ftp.netdna.com'
		});

		// set CNAME
		await adminPage.$eval('#cdn_cnames_0',
			(e) => e.value = 'ftp.netdna.com');

		let saveSelector = 'input[name="w3tc_save_options"]';
		await Promise.all([
			adminPage.evaluate((saveSelector) => document.querySelector(saveSelector).click(), saveSelector),
			adminPage.waitForNavigation({timeout: 300000}),
		]);

		await sys.afterRulesChange();
	});



	it('check cdn urls', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);

		let queryString = '';
		scripts.forEach(function(url) {
			expect(url).contains('ftp.netdna.com');
			let m = url.match(/\?(x([0-9]+))/);
			cacheQuery = m[1];
			expect(cacheQuery).not.empty;
		});
	});



	it('set options - rewrite', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__rewrite: true,
		});
	});



	it('checking cache query string didnt change', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);

		scripts.forEach(function(url) {
			expect(url).contains(cacheQuery);
		});
	});



	it('set options - switch to pull cdn', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__engine: 'cf2',
			pgcache__engine: 'file'
		});

		// inserting hostname
		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn_cf2_id: 'netdna'
		});

		await w3tc.followNoteFlushStatics(adminPage);
	});



	it('checking urls of pull cdn', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);

		var urlReg = new RegExp('^https?:\\/\\/netdna\\.cloudfront\\.net\\/.+\\.x([0-9]+)\\.js');
		scripts.forEach(function(url) {
			expect(url).matches(urlReg);
		});
	});
});
