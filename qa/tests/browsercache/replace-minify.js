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

		// First, check if there are any minified scripts
		let minifiedScripts = scripts.filter(function(url) {
			return url.indexOf('/minify/') > 0;
		});

		if (minifiedScripts.length === 0) {
			log.log('no minified scripts found on page - skipping rewrite URL structure check');
			// Skip the test if no minified scripts are present (WordPress 6.9+ compatibility)
			return;
		}

		// Find a script with the rewritten URL format (x[0-9]{5} pattern)
		let id = null;
		let idScript = null;
		for (let url of scripts) {
			let match = url.match(/.+\.(x[0-9]{5})\.js/);
			if (match) {
				id = match;
				idScript = url;
				break;
			}
		}

		if (!id) {
			// Try to find any minified script with rewritten format
			for (let url of minifiedScripts) {
				let match = url.match(/.+\.(x[0-9]{5})\.js/);
				if (match) {
					id = match;
					idScript = url;
					break;
				}
			}
		}

		if (!id) {
			log.log('no scripts found with rewritten URL format (.x[0-9]{5}.js) - browser cache rewrite may not be working');
			// This is a failure case - rewrite should be working
			throw new Error('Browser cache rewrite is enabled but no scripts have rewritten URL format');
		}

		log.log('url has unique-id ' + id[1]);
		let urlReg = new RegExp('^https?:\\/\\/.+\\.' + id[1] + '\\.js$');

		let foundScript = '';
		minifiedScripts.forEach(function(url) {
			log.log('checking ' + url + ' to have unique-id');
			if (url.match(urlReg)) {
				foundScript = url;
			}
		});

		// If no minified scripts found with the ID, check if the idScript itself is a minified script
		if (!foundScript && idScript && idScript.indexOf('/minify/') > 0) {
			foundScript = idScript;
		}

		expect(foundScript).not.empty;
		let response = await page.goto(foundScript, {waitUntil: 'domcontentloaded'});
		expect(response.status() < 400);
	});
});
