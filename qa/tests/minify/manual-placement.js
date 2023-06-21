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

/**environments: environments('blog') */

let theme;
let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-manual-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`,
			'/qa/minify-placement-sc.php');
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

		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 1,
			wpIncludesPath + '/js/jquery/jquery.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 2,
			wpIncludesPath + '/js/jquery/jquery-migrate.min.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 3,
			themesPath + '/qa/minify-js1.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 4,
			themesPath + '/qa/minify-js2.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 5,
			themesPath + '/qa/minify-non-blocking-js3.js', 'include');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 6,
			themesPath + '/qa/minify-js4.js', 'include-body');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 7,
			themesPath + '/qa/minify-js5.js', 'include-body');
		await w3tc.setOptionsMinifyAddJsEntry(adminPage, 8,
			themesPath + '/qa/minify-js6.js', 'include-footer');

		await w3tc.setOptionsMinifyAddCssEntry(adminPage, 1,
			themesPath + '/qa/minify-style.css', 'include');

		await adminPage.select('#js_use_type_header', 'nb-defer');
		await adminPage.select('#js_use_type_body', 'blocking');
		await adminPage.select('#js_use_type_footer', 'blocking');

		log.log('click save');
		await Promise.all([
			adminPage.click('input[name="w3tc_save_options"]'),
			adminPage.waitForNavigation({timeout: 0})
		]);

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



	it('placement', async() => {
		log.log(testPageUrl);
		await page.goto(testPageUrl);
		// checking if <\!-- W3TC-include-css --> was replaced
		await checkPlacement('W3TC-include-css', 'test css placement', 'css');

		//checking js placement
		let scripts = await dom.listScriptSrc(page);
		console.log(scripts);
		expect(JSON.stringify(scripts).match(/cache\/minify/g).length).equals(3);

		// checking if <\!-- W3TC-include-js --> was replaced
		await checkPlacement('W3TC-include-js', 'test js placement', 'js');

		//checking if <\!-- W3TC-include-js-body-start --> was replaced
		await checkPlacement('W3TC-include-js-body-start', 'test minify body start', 'js');

		// checking if <\!-- W3TC-include-js-body-start --> was replaced
		await checkPlacement('W3TC-include-js-body-end', 'test minify body end', 'js');
	});
});




async function checkPlacement(comments, helperComments, cssOrJs) {
	log.log(`checking placement of ${cssOrJs} ${comments}`);
	let html = await page.content();
	expect(html).not.contains('<!-- ' + comments + ' -->');
	let reg;
	if (cssOrJs == 'css') {
		reg = new RegExp('<\\!-- test css placement --><link rel="stylesheet"' +
			'[^>]+href="[^"]+\\/cache\\/minify.+\\.css"', 'gm');
	} else {
		reg = new RegExp('<\\!-- ' + helperComments + ' --><script[^>]+src="[^"]+\\/cache\\/minify.+\\.js"', 'gm')
	}

	let minifiedComments = html.match(reg);
	expect(minifiedComments.length).equals(1);
}
