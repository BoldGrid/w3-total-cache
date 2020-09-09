function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: multiply(environments('blog'), environments('pagecache')) */

let otherTheme;
if (parseFloat(env.wpVersion) < 4.4)
	otherTheme = 'twentythirteen/twentythirteen';
else if (parseFloat(env.wpVersion) < 4.7)
	otherTheme = 'twentyfourteen/twentyfourteen';
else if (parseFloat(env.wpVersion) < 5.0)
	otherTheme = 'twentyfifteen/twentyfifteen';
else if (parseFloat(env.wpVersion) < 5.0)
	otherTheme = 'twentyfifteen/twentyfifteen';
else if (parseFloat(env.wpVersion) < 5.5)
	otherTheme = 'twentysixteen/twentysixteen';
else
	otherTheme = 'twentynineteen/twentynineteen';

let pluginUrl = env.blogSiteUrl.replace(/(b2\.)?wp\.sandbox/, 'for-tests.wp.sandbox') +
	'user-agent-groups.php?path=' + env.blogSiteUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy files', async() => {
		await sys.copyPhpToRoot('../../plugins/generic/user-agent-groups.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			pgcache__engine: env.cacheEngineLabel
		});
	});



	it('add user agent group', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_mobile');
		adminPage.on('dialog', async dialog => {
  			log.log('fill prompt');
  			await dialog.accept('test1');
		});

		await adminPage.click('#mobile_add');
		log.log('wait button to create elements');
		await adminPage.waitForSelector('#mobile_groups_test1_redirect');

		await adminPage.$eval('#mobile_groups_test1_agents',
			(e) => e.value = 'safari');
		await adminPage.$eval('#mobile_groups_test1_theme',
			(e, v) => e.value = v, otherTheme);

		await Promise.all([
			adminPage.click('#w3tc_save_options_mobile'),
			adminPage.waitForNavigation()
		]);

		//checking if the group was created
		expect(await adminPage.content()).contains('Plugin configuration successfully updated');
	});



	it('create 2 copies of page', async() => {
		await page.setUserAgent(
			'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) ' +
			'Chrome/40.0.2214.111');
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

		await page.setUserAgent(
			'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) ' +
			'Safari/537.36');
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

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
		} else {
			css = await page.$eval('link[type="text/css"]',
				(e) => e.getAttribute('href'));
		}

		expect(css).contains('themes/' + theme[0] + '/style.css');
	});



	it('check 2 copies exists in a cache', async() => {
		await page.goto(env.blogSiteUrl + 'user-agent-groups.php?engine=' + env.cacheEngineLabel +
			'&url=' + env.homeUrl + '&blog_id=' + env.blogId);
		expect(await page.content()).contains('ok');
	});
});
