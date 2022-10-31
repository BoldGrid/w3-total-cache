function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: multiply(environments('blog'), environments('cache')) */

let otherTheme;

log.log('WordPress version number: ' + parseFloat(env.wpVersion));

if (parseFloat(env.wpVersion) < 4.4) {
	otherTheme = 'twentythirteen/twentythirteen';
} else if (parseFloat(env.wpVersion) < 4.7) {
	otherTheme = 'twentyfourteen/twentyfourteen';
} else if (parseFloat(env.wpVersion) < 5.0) {
	otherTheme = 'twentyfifteen/twentyfifteen';
} else if (parseFloat(env.wpVersion) < 5.5) {
	otherTheme = 'twentysixteen/twentysixteen';
} else if (parseFloat(env.wpVersion) < 5.9) {
	otherTheme = 'twentynineteen/twentynineteen';
} else if (parseFloat(env.wpVersion) < 6.1) {
	otherTheme = 'twentytwenty/twentytwenty';
} else {
	// WP 6.1.
	otherTheme = 'twentytwentythree/twentytwentythree';
}

log.log('Switch to theme: ' + otherTheme);

let pluginUrl = env.blogSiteUrl.replace(/(b2\.)?wp\.sandbox/i, 'for-tests.wp.sandbox') +
	'referrer-groups.php?path=' + env.blogSiteUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy files', async() => {
		await sys.copyPhpToRoot('../../plugins/generic/referrer-groups.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			pgcache__engine: env.cacheEngineLabel
		});
	});



	it('add referrer group', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups');
		adminPage._overwriteSystemDialogPrompt = true;
		adminPage.once('dialog', async dialog => {
  			log.log('fill prompt');
  			await dialog.accept('test_group');
			adminPage._overwriteSystemDialogPrompt = false;
		});

		await adminPage.click('#referrer_add');
		log.log('wait button to create elements');
		await adminPage.waitForSelector('#referrer_groups_test_group_redirect');

		await adminPage.$eval('#referrer_groups_test_group_referrers',
			(e) => e.value = 'for-tests\\.wp\\.sandbox(\\/wp\\/)?');
		await adminPage.$eval('#referrer_groups_test_group_theme',
			(e, v) => e.value = v, otherTheme);

		await Promise.all([
			adminPage.click('#w3tc_save_options_referrers'),
			adminPage.waitForNavigation()
		]);

		//checking if the group was created
		expect(await adminPage.content()).contains('Plugin configuration successfully updated');
	});



	it('theme changed', async() => {
		log.log('opening ' + pluginUrl);
		await page.goto(pluginUrl);

		await Promise.all([
			page.click('#hello-world'),
			page.waitForNavigation()
		]);

		let theme = otherTheme.split('/');

		let css;
		if (theme[0] == 'twentythirteen') {
			css = await page.$eval('#twentythirteen-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentyfourteen') {
			css = await page.$eval('#twentyfourteen-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentyfifteen') {
		 	css = await page.$eval('#twentyfifteen-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentysixteen') {
		 	css = await page.$eval('#twentysixteen-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentynineteen') {
			css = await page.$eval('#twentynineteen-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentytwenty') {
			css = await page.$eval('#twentytwenty-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentytwentyone') {
			css = await page.$eval('#twentytwentyone-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentytwentytwo') {
			css = await page.$eval('#twentytwentytwo-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentytwentythree') {
			css = await page.$eval('#wp-webfonts-inline-css',
				(e) => e.innerHTML);
		} else {
			css = await page.$eval('link[type="text/css"]',
				(e) => e.getAttribute('href'));
		}

		if (theme[0] == 'twentytwentythree') {
			expect(css).contains('themes/' + theme[0] + '/assets/');
		} else {
			expect(css).contains('themes/' + theme[0] + '/style.css');
		}
	});
});
