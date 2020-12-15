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

let minifiedLink;
let minifiedLinkWithFonts;
let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/minify-auto-theme/*', targetPath);
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__engine: 'file'
		});
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content',
			template: 'qa/external-css.php'
		});
		testPageUrl = testPage.url;
	});



	it('we minified css', async() => {
		log.log('checking ' + testPageUrl);
		await page.goto(testPageUrl);
		let links = await dom.listLinkCssHrefAll(page);

		let minifiedExists = false;
		let fontExists = false;
		for (let link of links) {
			if (link.indexOf('cache/minify/') >= 0) {
				minifiedExists = true;
				minifiedLink = link;
			}
			if (link.indexOf('fonts.googleapis.com/css') >= 0) {
				fontExists = true;
			}
		}

		expect(minifiedExists).true;
		log.success('minified css found');
		expect(fontExists).true;
		log.success('unminified fonts css found');
	});



	it('set options - include fonts', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_minify', {
			minify_cache_files: 'fonts.googleapis.com'
		});
	});




	it('minify url changed', async() => {
		await page.goto(testPageUrl);
		let links = await dom.listLinkCssHrefAll(page);

		let minifiedExists = false;
		let fontExists = false;
		for (let link of links) {
			if (link.indexOf('cache/minify/') >= 0) {
				minifiedExists = true;
				minifiedLinkWithFonts = link;
			}
			if (link.indexOf('fonts.googleapis.com/css') >= 0) {
				fontExists = true;
			}
		}

		expect(minifiedExists).true;
		log.success('minified css found');
		expect(minifiedLink != minifiedLinkWithFonts).true;
		log.success('minified css changed');
		expect(fontExists).false;
		log.success('unminified fonts not found');
	});



	it('it contains fonts.googleapis.com or fonts.gstatic.com content', async() => {
		log.log('opening minified css ' + minifiedLinkWithFonts);
		let m = minifiedLinkWithFonts.match(/sandbox(:\d+)?(.*)$/i);
		let filename = '/var/www/wp-sandbox' + m[2].replace("/b2/", "/");
		log.log('reading ' + filename);

		let content = await fs.readFileAsync(filename);
		expect(
			content.indexOf('fonts-googleapis') >= 0 ||
			content.indexOf('//fonts.gstatic.com/s/') >= 0
		).true;
		log.success('css contains fonts content');
	});
});
