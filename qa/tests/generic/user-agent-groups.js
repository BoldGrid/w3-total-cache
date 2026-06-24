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

log.log('WordPress version number: ' + parseFloat(env.wpVersion));

if (parseFloat(env.wpVersion) < 6.1) {
	otherTheme = 'twentytwenty/twentytwenty';
} else if (parseFloat(env.wpVersion) < 6.4) {
	otherTheme = 'twentytwentythree/twentytwentythree';
} else if (parseFloat(env.wpVersion) < 6.7) {
	otherTheme = 'twentytwentyfour/twentytwentyfour';
} else {
	otherTheme = 'twentytwentyfive/twentytwentyfive';
}

log.log('Switch to theme: ' + otherTheme);

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

		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__cache__home: true
		});

		// Plain page-cache keys (no _gzip segment) so backend probes match runtime writes.
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__html__etag: false,
			browsercache__html__last_modified: false,
			browsercache__html__compression: false
		});
	});



	it('add user agent group', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_cachegroups');
		await adminPage.waitForSelector('#mobile_add');
		adminPage._overwriteSystemDialogPrompt = true;

		const dialogPromise = new Promise((resolve, reject) => {
			const timeout = setTimeout(() => {
				reject(new Error('Dialog did not appear within timeout'));
			}, 10000);

			adminPage.once('dialog', async dialog => {
				clearTimeout(timeout);
				log.log('fill prompt');
				await dialog.accept('test1');
				adminPage._overwriteSystemDialogPrompt = false;
				resolve();
			});
		});

		await Promise.all([
			adminPage.click('#mobile_add'),
			dialogPromise
		]);

		log.log('wait button to create elements');
		await adminPage.waitForSelector('#mobile_groups_test1_redirect');

		await adminPage.$eval('#mobile_groups_test1_agents',
			(e) => e.value = 'safari');
		await adminPage.$eval('#mobile_groups_test1_theme',
			(e, v) => e.value = v, otherTheme);

		let saveSelector = 'input[name="w3tc_save_options"]';
		await Promise.all([
			adminPage.evaluate((saveSelector) => document.querySelector(saveSelector).click(), saveSelector),
			adminPage.waitForNavigation()
		]);

		//checking if the group was created
		expect(await adminPage.content()).contains('Plugin configuration successfully updated');
	});



	it('create 2 copies of page', async() => {
		await page.setUserAgent(sys.qaPageCacheUserAgent);
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		w3tc.expectPageCachingMethod(await page.content(), env.cacheEngineName);

		await page.setUserAgent(sys.qaPageCacheSafariUserAgent);
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		w3tc.expectPageCachingMethod(await page.content(), env.cacheEngineName);

		let theme = otherTheme.split('/');

		let css;
		if (theme[0] == 'twentytwenty') {
			css = await page.$eval('#twentytwenty-style-css',
				(e) => e.getAttribute('href'));
		} else if (theme[0] == 'twentytwentythree') {
			css = await page.$eval('#wp-webfonts-inline-css',
				(e) => e.innerHTML);
		} else if (['twentytwentyfour', 'twentytwentyfive'].includes(theme[0])) {
			css = await page.$eval(
				parseFloat(env.wpVersion) >= 6.7 ? '.wp-fonts-local': '#wp-fonts-local',
				(e) => e.innerHTML
			);
		} else {
			css = await page.$eval('link[type="text/css"]',
				(e) => e.getAttribute('href'));
		}

		if (['twentytwentythree', 'twentytwentyfour', 'twentytwentyfive'].includes(theme[0])) {
			expect(css).contains('themes/' + theme[0] + '/assets/');
		} else {
			expect(css).contains('themes/' + theme[0] + '/style.css');
		}
	});



	it('check 2 copies exists in a cache', async() => {
		let checkUrl = env.blogSiteUrl + 'user-agent-groups.php?engine=' + env.cacheEngineLabel +
			'&url=' + env.homeUrl + '&blog_id=' + env.blogId;

		log.log(`opening ${checkUrl}`);

		await page.goto(checkUrl);
		let content = await page.content();

		if (content.indexOf('ok') < 0) {
			log.error('probe diagnostics: ' + content);
		}

		expect(content).contains('ok');
	});
});
