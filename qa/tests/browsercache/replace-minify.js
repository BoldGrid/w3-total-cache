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

/**environments: environments('blog') */

let testPageUrl;
let minifyUrls = [];

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			minify__enabled: true,
			browsercache__enabled: true,
			pgcache__engine: 'file'
		});

		// Enable "Prevent caching of objects after settings change" on browser cache page
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__other__replace: true,
			browsercache__cssjs__replace: true
		});

		await sys.afterRulesChange();
	});



	it('find minify assets generated', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);
		scripts.forEach(function(url) {
			log.log('checking ' + url);
			expect(url).matches(/\?(.+)$/);
		});

		let response = await page.goto(scripts[0], {waitUntil: 'domcontentloaded'});

		expect(response.status() < 400);
	});



	it('enable bc rewrite', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__rewrite: true
		});
		await w3tc.flushAll(adminPage);
	});


	it('check that cached url structure changed', async() => {
		await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);

		let id = scripts[0].match(/.+\.(x[0-9]{5})\.js/);
		log.log('url has unique-id ' + id);
		let urlReg = new RegExp('^https?:\\/\\/.+\\.' + id[1] + '\\.js$');

		let foundScript = '';
		scripts.forEach(function(url) {
			if (url.indexOf('/minify/') > 0) {
				log.log('checking ' + url + ' to have unique-id');
				expect(url).matches(urlReg);
				foundScript = url;
			}
		});

		expect(foundScript).not.empty;
		let response = await page.goto(foundScript, {waitUntil: 'domcontentloaded'});
		expect(response.status() < 400);
	});
});
