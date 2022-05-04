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

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('test', async() => {
		//
		// copy theme files
		//
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/minify-auto-theme/*', targetPath);

		await sys.copyPhpToRoot('../../plugins/cdn/generic.php');


		//
		// set options
		//
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			browsercache__enabled: false,
			cdn__engine: 'ftp',
			minify__enabled: true,
			minify__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn_ftp_host: 'wp.sandbox',
			cdn_ftp_user: 'www-data',
			cdn_ftp_pass: 'sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU',
			cdn_ftp_path: env.cdnFtpExportDir,
			cdn_cnames_0: env.cdnFtpExportHostPort
		});

		await sys.afterRulesChange();


		//
		// create test page
		//
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content',
			template: 'qa/minify-auto-js.php'
		});
		testPageUrl = testPage.url;


		//
		// load test page
		//
		log.log('opening ' + testPageUrl);
		await page.goto(testPageUrl, {waitUntil: 'networkidle0'});

		await page.goto(env.blogSiteUrl + 'generic.php?action=cron_queue_process',
			{waitUntil: 'domcontentloaded'});
		let html = await page.content();
		expect(html).contains('cron_queue_process');

		await page.goto(testPageUrl, {waitUntil: 'domcontentloaded'});

		let scripts = await dom.listScriptSrcSync(page);
		scripts.forEach(function(url) {
			log.log('testing ' + url);
			expect(url).contains('cache/minify');
			expect(url).contains('for-tests.sandbox');
		});

		log.log('Checking if files physically exist on the server...');
		for (let url of scripts) {
			var filePath = url.replace(
				/https?\:\/\/for\-tests\.sandbox(:\d+)?\//,
				'/var/www/for-tests-sandbox/');
			expect(fs.existsSync(filePath));

			let response = await page.goto(url,	{waitUntil: 'domcontentloaded'});
			expect(response.status == 200 || response.status == 304);
		}
	});
});
