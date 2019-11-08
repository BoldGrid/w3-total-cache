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

describe('check that media library works when CDN is active', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('test', async() => {
		//
		// set options
		//
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
		    cdn__engine: 'ftp',
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
		// export files to CDN
		//
		await w3tc.cdnPushExportFiles(adminPage, 'includes');
		await w3tc.cdnPushExportFiles(adminPage, 'theme');
		await w3tc.cdnPushExportFiles(adminPage, 'custom');


		//
		// check CDN marked as working
		//
		await page.goto(env.homeUrl);
		let html = await page.content();
		expect(html).contains('Content Delivery Network via for-tests.sandbox');

		log.log('Checking cdnized presence');
		let scripts = await dom.listScriptSrc(page);
		expect(scripts).not.empty;
		let linkHrefs = await dom.listLinkCssHref(page);
		expect(linkHrefs).not.empty;

		for (url of scripts) {
			log.log('testing ' + url);
			expect(url).contains('for-tests.sandbox');

			let response = await page.goto(url,	{waitUntil: 'domcontentloaded'});
			expect(response.status == 200 || response.status == 304);
		}


		for (url of linkHrefs) {
			log.log('testing ' + url);
			expect(url).contains('for-tests.sandbox');

			let response = await page.goto(url,	{waitUntil: 'domcontentloaded'});
			expect(response.status == 200 || response.status == 304);
		}
	});
});
