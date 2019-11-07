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

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('test', async() => {
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
			cdn__includes__enable: false,
			cdn__theme__enable: false,
			cdn_ftp_host: 'wp.sandbox',
			cdn_ftp_user: 'www-data',
			cdn_ftp_pass: 'sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU',
			cdn_ftp_path: env.cdnFtpExportDir,
			cdn_cnames_0: env.cdnFtpExportHostPort
		});

		await sys.afterRulesChange();


		//
		// check test button
		//
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cdn');
		await adminPage.click('#cdn_test');

		await adminPage.waitForSelector('#cdn_test_status', {visible: true});
		await adminPage.waitFor(function() {
			return document.querySelector('#cdn_test_status').textContent != 'Testing...';
		});

		let text = await adminPage.$eval('#cdn_test_status', (e) => e.textContent);
		expect(text).equals('Test passed');
	});
});
