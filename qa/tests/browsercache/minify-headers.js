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

let testPageUrl;
let minifyUrls = [];

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
			browsercache__enabled: true,
			minify__enabled: true,
			//minify_auto: true,
			minify__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__cssjs__etag: false,
			browsercache__cssjs__expires: false,
			browsercache__cssjs__last_modified: false
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content',
			template: 'qa/minify-auto-js.php'
		});
		testPageUrl = testPage.url;
	});



	it('find minify assets generated', async() => {
		await page.goto(testPageUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);
		scripts.forEach(function(url) {
			if (url.indexOf('/cache/minify/') > 0) {
				minifyUrls.push(url);
			}
		});
		expect(minifyUrls).not.empty;

		let linkHrefs = await dom.listLinkCssHref(page);
		let cssPresent = false;
		linkHrefs.forEach(function(url) {
			if (url.indexOf('/cache/minify/') > 0) {
				minifyUrls.push(url);
				cssPresent = true;
			}
		});
		expect(cssPresent).is.true;
	});



	it('check headers', async() => {
		await testUrlHeaders(false);
	});


	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__cssjs__expires: true
		});

		await sys.afterRulesChange();
		//box.onPageChangedOutside(test);   // todo: instead of that note should appear
	});



	it('check headers', async() => {
		await testUrlHeaders(true);
	});
});




async function testUrlHeaders(expectExpires) {
	for (let url of minifyUrls) {
		log.log('checking ' + url);
		let response = await page.goto(url, {waitUntil: 'domcontentloaded'});
		let headers = response.headers();
		expect(response.status()).eq(200);

		let expiresFound = (headers['expires'] != null);

		if (expectExpires) {
			expect(expiresFound).true;
		} else {
			expect(expiresFound).false;
		}
	}
}
