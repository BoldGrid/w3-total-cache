function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

let theme;
let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-manual-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`,
			'/qa/minify-url-deformation-sc.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__auto__0: true,
			minify__engine: 'file'
		});
	});



	it('configure minify', async() => {
		let themesPath =  env.wpContentPath.replace(/\/var\/www\/wp-sandbox/g, '') +
			'themes/' + theme;
		let wpIncludesPath = env.wpSiteUri.replace(/^\//, '') + 'wp-includes';

		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_minify');

		let valueOriginal = '//fonts.googleapis.com/css?family=Ubuntu%3A400%2C700%26subset%3Dlatin%2Clatin-ex';
		await w3tc.setOptionsMinifyAddCssEntry(adminPage, 1, valueOriginal,	'include');

		log.log('click save');
		await Promise.all([
			adminPage.click('#w3tc_save_options_minify_css'),
			adminPage.waitForNavigation({timeout: 0})
		]);

		let valueAfterSave = await adminPage.$eval(
			'#css_files input[type=text].css_enabled', (e) => e.value);
		expect(valueAfterSave).equals(valueOriginal);

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content [w3tcqa]'
		});
		testPageUrl = testPage.url;
	});



	it('font was minified', async() => {
		await page.goto(testPageUrl);
		let html = await page.content();

		let fontTag = await page.$('#font_test');
		expect(fontTag).null;

		log.log('Checking css minified presence...');

		var css = expect(html).matches(/https?:\/\/.+?\/cache\/minify\/(\d+\/)?.+?default\.include\..+?\.css/g);
	});
});
