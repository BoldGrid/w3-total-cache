function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');

fs.readFileAsync = util.promisify(fs.readFile);

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('cache')) */

let theme;
let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-manual-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`,
			'/qa/minify-js-sc.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__auto__0: true,
			minify__engine: env.cacheEngineLabel
		});
	});



	it('configure minify', async() => {
		let themesPath =  env.wpContentPath.replace(/\/var\/www\/wp-sandbox/g, '') +
			'themes/' + theme;
		let wpIncludesPath = env.wpSiteUri.replace(/^\//, '') + 'wp-includes';

		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_minify');

		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 1,
			wpIncludesPath + '/js/jquery/jquery.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 2,
			wpIncludesPath + '/js/jquery/jquery-migrate.min.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 3,
			themesPath + '/qa/minify-js1.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 4,
			themesPath + '/qa/minify-js2.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 5,
			themesPath + '/qa/minify-js3.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 6,
			themesPath + '/qa/minify-js4.js', 'include-body');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 7,
			themesPath + '/qa/minify-js5.js', 'include-body');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 8,
			themesPath + '/qa/minify-js6.js', 'include-footer');

		await adminPage.select('#js_use_type_header', 'blocking');
		await adminPage.select('#js_use_type_body', 'nb-js');
		await adminPage.select('#js_use_type_footer', 'nb-async');

		log.log('click save');
		let saveSelector = 'input[name="w3tc_save_options"]';
		await Promise.all([
			adminPage.evaluate((saveSelector) => document.querySelector(saveSelector).click(), saveSelector),
			adminPage.waitForNavigation({timeout: 0})
		]);

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

		for (let url of scripts) {
			log.log('All origin JS should be minified ' + url);
			expect(url).not.contains('minify-js');
		}
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
