function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const exec = util.promisify(require('child_process').exec);
const puppeteer = require('puppeteer');

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	after(sys.after);



	before(async() => {
		global.adminPage = null;
		global.page = null;
		global.browser = await puppeteer.launch({
			ignoreHTTPSErrors: true,
			args: ['--no-sandbox']
		});

		await sys.restoreStateW3tcInactive();

		global.adminPage = await browser.newPage();
		adminPage.setViewport({width: 1187, height: 1000});
		await wp.login(adminPage);

		const context = await browser.createIncognitoBrowserContext();
		global.page = await context.newPage();
		page.setViewport({width: 1187, height: 1000});
	});



	it('activate w3tc', async() => {
		await wp.networkActivatePlugin(adminPage, 'w3-total-cache/w3-total-cache.php');
	});
	it('set options', setBasicOptions);

	it('check no errors', async() => {
		await w3tc.expectW3tcErrors(adminPage, false);
	});

	it('check page caching works', expectPageCachingWorks);



	//
	// checking with changed rights
	//
	it('restore initial state with locked permissions', async() => {
		await sys.restoreStateW3tcInactive();

		if (process.env['W3D_HTTP_SERVER'] == 'nginx') {
			const r0 = await exec('touch ${W3D_WP_HOME_PATH}nginx.conf')
		}

		const r1 = await exec('chown -R ubuntu:ubuntu /var/www/wp-sandbox');
		const r2 = await exec('chmod -R 0755 /var/www/wp-sandbox');
		const r3 = await exec('ls -l /var/www/wp-sandbox');
		expect(r3.stdout).contain('ubuntu');

		await sys.afterSourceFileContentsChanges();
		await wp.login(adminPage);
	});


	it('activate w3tc', async() => {
		await wp.networkActivatePlugin(adminPage, 'w3-total-cache/w3-total-cache.php');
	});

	it('check errors present', async() => {
		await w3tc.expectW3tcErrors(adminPage, true);
	});

	it('configure via FTP after activation', async() => {
		await fillFtpForm();
		let note = await adminPage.$eval('.w3tc_note', (e) => e.textContent);
		expect(note).contains('Required files and directories have been automatically created');
	});

	it('check errors present', async() => {
		await w3tc.expectW3tcErrors(adminPage, false);
	});

	it('set options', setBasicOptions);

	it('configure via FTP after settings changes', async() => {
		await fillFtpForm();
		let note = await adminPage.$eval('.w3tc_note', (e) => e.textContent);
		expect(note).contains('Required files and directories have been automatically created');
	});

	it('check no errors', async() => {
		await w3tc.expectW3tcErrors(adminPage, false);
	});

	it('check page caching works', expectPageCachingWorks);
});



async function expectPageCachingWorks() {
	// fill the cache (twice to pass through w3tc=repeat)
	log.log('filling cache by requesting homepage');
	await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
	await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});

	// trying to write a dummy word into the cached file
	await w3tc.updateCacheEntry(adminPage, env.homeUrl, false, 'file', 'disk');
	//box.onPageChangedOutside(test);

	log.log('checking content of updated cache entry');
	await page.goto(env.homeUrl, {waitUntil: 'domcontentloaded'});
	const html = await page.content();
	expect(html).contain('Test of cache');
}



async function setBasicOptions() {
	await w3tc.setOptions(adminPage, 'w3tc_general', {
		pgcache__enabled: true,
		pgcache__engine: 'file',
		dbcache__enabled: true,
		dbcache__engine: 'file',
		browsercache__enabled: false
	});
}



async function fillFtpForm() {
	expect(await adminPage.$('.w3tc-show-ftp-form')).not.null;
	await adminPage.click('.w3tc-show-ftp-form');
	await adminPage.waitForSelector('form[name=w3tc_ftp_form]', {
		visible: true,
	});
	await adminPage.$eval('input[name=hostname]',
		(e) => e.value = 'localhost');
	await adminPage.$eval('input[name=username]',
		(e) => e.value = 'ubuntu');
	await adminPage.$eval('input[name=password]',
		(e) => e.value = 'Ilgr5UOoc7s6Gj1htaDDcQ4F6T27e3UC');


	await Promise.all([
		adminPage.click('#upgrade'),
		adminPage.waitForNavigation({timeout:0})
	]);
}
