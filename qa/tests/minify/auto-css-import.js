function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');

fs.readFileAsync = util.promisify(fs.readFile);

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-auto-css-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`,
			'/qa/minify-css-import-sc.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__engine: 'file'
		});
		await w3tc.setOptions(adminPage, 'w3tc_minify', {
			minify_css_import: 'process'
		});
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content [w3tcqa]',
		});
		testPageUrl = testPage.url;
	});



	it('check css minified', async() => {
		await page.goto(testPageUrl);
		let linkHrefs = await dom.listLinkCssHref(page);
		let minifiedHrefs = [];

		for (let href of linkHrefs) {
			if (href.indexOf('cache/minify/') >= 0) {
				minifiedHrefs.push(href);
			}
		}

		expect(minifiedHrefs.length > 0);


		let content = '';
		for (let cssUrl of minifiedHrefs) {
			log.log('opening minified css ' + cssUrl);
			let m = cssUrl.match(/sandbox(:\d+)?(.*)$/i);
			let filename = '/var/www/wp-sandbox' + m[2].replace("/b2/", "/");
			log.log('reading ' + filename);

			content += await fs.readFileAsync(filename);
		}

		expect(content).not.contains('@import');
		log.success('import directive not found in css');
		expect(content).contains('#127844');
		log.success('#127844 color found in css (part of imported)');
		expect(content).contains('#534136');
		log.success('#534136 color found in css (part of relative-path css)');
	});
});
