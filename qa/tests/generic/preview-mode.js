function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

// it cant work under wpmu since network admin cant set cookie to each blog
/**environments:
variable_equals('W3D_WP_NETWORK', ['single'],
	environments('blog')
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			pgcache__engine: 'file',
			minify__enabled: true,
			minify__engine: 'file',
			dbcache__enabled: true,
			dbcache__engine: 'file',
			objectcache__enabled: true,
			objectcache__engine: 'file',
			browsercache__enabled: false
		});
	});



	it('enable preview mode', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general');

		let configPreviewEnable = 'input[name=w3tc_config_preview_enable]';
		await Promise.all([
			adminPage.evaluate((configPreviewEnable) => document.querySelector(configPreviewEnable).click(), configPreviewEnable),
			adminPage.waitForNavigation()
		]);

		expect(await adminPage.content()).contains('Preview mode was successfully enabled');
	});



	it('w3tc comments in preview mode', async() => {
		await checkW3tcComments('preview');
	});



	it('"deploy" changes in preview mode to usual', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general');

		let deployButton = 'input[value=Deploy]';
		await Promise.all([
			adminPage.evaluate((deployButton) => document.querySelector(deployButton).click(), deployButton),
			adminPage.waitForNavigation()
		]);
		expect(await adminPage.content()).contains('Preview settings successfully deployed.');
	});



	it('w3tc comments in preview mode', async() => {
		await checkW3tcComments('live');
	});
});



async function checkW3tcComments(mode) {
	await adminPage.goto(env.homeUrl);
	let html = await adminPage.content();

	if (mode == 'preview') {
		expect(html).contains('W3 Total Cache used in preview mode');
	}

	expect(html).matches(new RegExp(
		'Object Caching \\d+\\/\\d+ objects using disk'));
	expect(html).matches(new RegExp('Page Caching using disk'));
	expect(html).matches(new RegExp('Minified using disk'));
	expect(html).matches(new RegExp(
		'Database Caching (\\d+/\\d+ queries in [0-9.]+ seconds )?using disk'));
}
