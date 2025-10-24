function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const fs = require('fs');
const log = require('mocha-logger');

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments:environments('blog') */

let theme;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('copy theme files', async() => {
		theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-manual-theme/*', `${themePath}/qa`);
		await sys.copyPhpToRoot('../../plugins/cdn/generic.php');
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`, '/qa/minify-js-sc.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			browsercache__enabled: false,
			cdn__engine: 'ftp',
			minify__enabled: true,
			minify__engine: 'file',
			minify__auto__0: true,
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn__includes__enable: false,
			cdn__theme__enable: false,
			cdn_ftp_host: 'wp.sandbox',
			cdn_ftp_user: 'www-data',
			cdn_ftp_pass: 'sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU',
			cdn_ftp_path: env.cdnFtpExportDir,
			cdn_cnames_0: env.cdnFtpExportHostPort
		});

		await sys.afterRulesChange();
	});


	it('test', async() => {
		//
		// configure minify
		//
		await configureMinify(theme);
		await w3tc.cdnPushExportFiles(adminPage, 'minify');


		//
		// create page
		//
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content [w3tcqa]'
		});
		testPageUrl = testPage.url;


		//
		// check test page
		//
		log.log('opening ' + testPageUrl);
		await page.goto(testPageUrl, {waitUntil: 'networkidle0'});

		let scripts = await dom.listScriptSrc(page);
		//let css = page.$eval('link[type="text/css"]', (e) => e.href);
		//scripts.push(css);

		log.log('Checking minified presence on ' + testPageUrl);
		let minifiedCount = 0;
		for (url of scripts) {
			log.log('testing ' + url);
			if (url.indexOf('cache/minify') >= 0) {
				minifiedCount++;
				expect(url).contains('cache/minify');
				expect(url).contains('for-tests.sandbox');

				let filePath = url.replace(
					/https?\:\/\/for\-tests\.sandbox(:\d+)?\//,
					'/var/www/for-tests-sandbox/');
				expect(fs.existsSync(filePath));

				let response = await page.goto(url,	{waitUntil: 'domcontentloaded'});
				expect(response.status == 200 || response.status == 304);
			}
		}

		expect(minifiedCount > 0).true;
	});
});



async function configureMinify(theme) {
	let themesPath =  env.wpContentPath.replace(/\/var\/www\/wp-sandbox/g, '') +
		'themes/' + theme;

	await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_minify');

	await w3tc.setOptionsMinifyAddJsEntry(adminPage, 1,
		themesPath + '/qa/minify-js1.js', 'include');
	await w3tc.setOptionsMinifyAddJsEntry(adminPage, 2,
		themesPath + '/qa/minify-js2.js', 'include');
	await w3tc.setOptionsMinifyAddJsEntry(adminPage, 3,
		themesPath + '/qa/minify-js3.js', 'include');
	await w3tc.setOptionsMinifyAddJsEntry(adminPage, 4,
		themesPath + '/qa/minify-js4.js', 'include-body');
	await w3tc.setOptionsMinifyAddJsEntry(adminPage, 5,
		themesPath + '/qa/minify-js5.js', 'include-footer');
	await w3tc.setOptionsMinifyAddJsEntry(adminPage, 6,
		themesPath + '/qa/minify-js6.js', 'include-footer');

	log.log('click save');
	let saveSelector = 'input[name="w3tc_save_options"]';
	await Promise.all([
		adminPage.evaluate((saveSelector) => document.querySelector(saveSelector).click(), saveSelector),
		adminPage.waitForNavigation({timeout: 300000})
	]);

	await sys.afterRulesChange();
}
