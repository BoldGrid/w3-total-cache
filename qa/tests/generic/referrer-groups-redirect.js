function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: multiply(environments('blog'), environments('cache')) */

let redirectedUrl = env.scheme + '://for-tests.sandbox' +
	(env.scheme == 'http' && env.httpServerPort != 80 ? ':' + env.httpServerPort : '') +
	(env.scheme == 'https' && env.httpServerPort != 443 ? ':' + env.httpServerPort : '') +
	'/';
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



	it('not redirected to http://for-tests.sandbox', async() => {
		log.log('opening ' + pluginUrl);
		await page.goto(pluginUrl);

		await Promise.all([
			page.click('#hello-world'),
			page.waitForNavigation()
		]);

		expect(page.url()).not.equals(redirectedUrl);
	});



	it('add referrer group', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_referrer');
		adminPage.on('dialog', async dialog => {
  			log.log('fill prompt');
  			await dialog.accept('test_group');
		});

		await adminPage.click('#referrer_add');
		log.log('wait button to create elements');
		await adminPage.waitForSelector('#referrer_groups_test_group_redirect');

		await adminPage.$eval('#referrer_groups_test_group_referrers',
			(e) => e.value = 'for-tests\\.wp\\.sandbox(\\/wp\\/)?');
		await adminPage.$eval('#referrer_groups_test_group_redirect',
			(e, v) => e.value = v, redirectedUrl);

		await Promise.all([
			adminPage.click('#w3tc_save_options_referrers'),
			adminPage.waitForNavigation()
		]);

		//checking if the group was created
		expect(await adminPage.content()).contains('Plugin configuration successfully updated');
	});



	it('redirected to http://for-tests.sandbox', async() => {
		log.log('opening ' + pluginUrl);
		await page.goto(pluginUrl);

		await Promise.all([
			page.click('#hello-world'),
			page.waitForNavigation()
		]);

		expect(page.url()).equals(redirectedUrl);
	});
});
