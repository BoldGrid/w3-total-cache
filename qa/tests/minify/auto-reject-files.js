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

let theme;
let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-auto-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`, '/qa/minify-auto-js-sc.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__engine: 'file'
		});

		let themePath =  env.blogWpContentUri.replace('/b2', '') + 'themes/' + theme;

		await w3tc.setOptions(adminPage, 'w3tc_minify', {
			minify_reject_files_js: themePath + '/qa/minify-auto-js3.js'
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content [w3tcqa]'
		});
		testPageUrl = testPage.url;
	});



	it('scripts minified', async() => {
		await page.goto(testPageUrl);
		let scripts = await dom.listScriptSrc(page);
		let rejectedFound = false;
		for (let url of scripts) {
			if (url.indexOf('/qa/minify-auto-js3.js')) {
				rejectedFound = true;
			} else {
				// WordPress script modules are intentionally excluded from minification
				if (url.indexOf('wp-includes/js/dist/script-modules/') >= 0) {
					log.log('skipping WordPress script module: ' + url);
					continue;
				}
				log.log('Minify presence expected in ' + url);
				expect(url).contains('cache/minify');
			}
		}

		expect(rejectedFound).true;
	});



	it('all js works well', async() => {
		await page.waitForFunction(() => {
			return document.querySelector('#js1').textContent == 'passed'
		});
		await page.waitForFunction(() => {
			return document.querySelector('#js2').textContent == 'passed'
		});
		await page.waitForFunction(() => {
			return document.querySelector('#js3').textContent == 'passed'
		});
		await page.waitForFunction(() => {
			return document.querySelector('#js4').textContent == 'passed'
		});
		await page.waitForFunction(() => {
			return document.querySelector('#js5').textContent == 'passed'
		});
		await page.waitForFunction(() => {
			return document.querySelector('#js6').textContent == 'passed'
		});
	});
});
