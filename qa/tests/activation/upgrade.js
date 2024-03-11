function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const exec = util.promisify(require('child_process').exec);
const puppeteer = require('puppeteer');
const fs = require('fs');

fs.readFileAsync = util.promisify(fs.readFile);

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



	it('copy qa files', async() => {
		await sys.copyPhpToRoot('../../plugins/upgrade/generic.php');
	});



	it('take old w3tc', async() => {
		log.log('Installing old w3tc...');

		let old = {
			repo: 'https://downloads.wordpress.org/plugin/w3-total-cache.0.9.5.zip',
		 	output: '/share/w3tc-9-5.zip',
			content: "'0.9.5'"
		};

		if (env.phpVersion >= 8 || parseFloat(env.wpVersion) >= 5.7) {
			old = {
				repo: 'https://downloads.wordpress.org/plugin/w3-total-cache.2.1.0.zip',
			 	output: '/share/w3tc-2-1-0.zip',
				content: "'2.1.0'"
			};
		}

		const r1 = await exec('curl --silent ' + old.repo + ' --output ' + old.output);
		const r2 = await exec('/share/scripts/w3tc-umount.sh');
		const r3 = await exec('unzip -q ' + old.output +' -d ' + env.wpPluginsPath);
		console.log(r3);
		let content = await fs.readFileAsync(env.wpPluginsPath + 'w3-total-cache/w3-total-cache-api.php' );
		expect(content.indexOf(old.content) > 0).true;
	});


	it('Fix DbCache_WpdbBase.php for WP >= 6.1', async() => {
		// Prevent deprecated error on older version of W3TC in WP >= 6.1.
		if (parseFloat(env.wpVersion) >= 6.1) {
			log.log('Fixing DbCache_WpdbBase.php for WP >= 6.1 (' + env.wpVersion + ')...');
			await exec('cp -pf /share/w3tc/DbCache_WpdbBase.php ' + env.wpPluginsPath + 'w3-total-cache/');
		}
	});



	it('activate w3tc', async() => {
		await wp.networkActivatePlugin(adminPage, 'w3-total-cache/w3-total-cache.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			'dbcache__enabled': true,
			'dbcache__engine': 'file',
			'objectcache__enabled': true,
			'objectcache__engine': 'file',
			'browsercache__enabled': true,
			'pgcache__enabled': true,
			'pgcache__engine': 'file',
		});

		await sys.afterRulesChange();
	});



	it('check works', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
	});



 	it('upgrade w3tc to actual version', async() => {
		const r1 = await exec('sudo /share/scripts/w3tc-mount.sh');
		await sys.afterSourceFileContentsChanges();
		expect(fs.existsSync(env.wpPluginsPath + 'w3-total-cache/Base_Page_Settings.php'));
	});

	//helpers.httpServerErrorLogTruncate(test);
	//helpers.restartHttpServer(test);


	it('flush', async() => {
		await w3tc.flushAll(adminPage);
	});



	it('check works', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		let content = await page.content();

		expect(content).matches(new RegExp('Object Caching \\d+\\/\\d+ objects using Disk'), 'Object cache is enabled');
		expect(content).matches(new RegExp('Page Caching using Disk'), 'Page caching is enabled');
		expect(content).matches(new RegExp('Database Caching.+?using Disk'), 'Database Caching is enabled');
	});
});
