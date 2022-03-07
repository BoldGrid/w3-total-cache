function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');
fs.unlinkAsync = util.promisify(fs.unlink);

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
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/minify-auto-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`, '/qa/minify-auto-js-sc.php');
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
			browsercache__cssjs__last_modified: false,
			browsercache__cssjs__replace: true,
			browsercache__rewrite: true
		});

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



	it('find minify assets generated', async() => {
		await page.goto(testPageUrl, {waitUntil: 'domcontentloaded'});
		let scripts = await dom.listScriptSrc(page);
		scripts.forEach(function(url) {
			if (url.indexOf('/cache/minify/') > 0) {
				expect(url).contains('.x');
				minifyUrls.push(url);
			}
		});
		expect(minifyUrls).not.empty;

		let linkHrefs = await dom.listLinkCssHref(page);
		let cssPresent = false;
		linkHrefs.forEach(function(url) {
			if (url.indexOf('/cache/minify/') > 0) {
				expect(url).contains('.x');
				minifyUrls.push(url);
				cssPresent = true;
			}
		});
		expect(cssPresent).is.true;
	});



	it('check headers no check', async() => {
		// can have both PHP and non-PHP processing depending on how
		// fast page load was
		await testUrlHeaders(false, null);
	});


	it('check headers no expiration', async() => {
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



	it('remove gzip version and check that php loaded', async() => {
		let path = env.wpContentPath + 'cache/minify/';
		if (env.blogId > 0) {
			path += env.blogId + '/';
		}
		let filenames = fs.readdirSync(path);
		for (let filename of filenames) {
			if (filename.substr(-5) == '_gzip') {
				console.log('removing ' + filename);
				await fs.unlinkAsync(path + filename);
			}
		}


		// rules should stop working when gzip version of cache removed
		// and request will be handed by php.
		// that gives a signal that rules work well.
		await testUrlHeaders(true, true);
	});
});




async function testUrlHeaders(expectExpires, expectPhp) {
	for (let url of minifyUrls) {
		log.log('checking ' + url);
		let response = await page.goto(url, {waitUntil: 'domcontentloaded'});
		let headers = response.headers();
		expect(response.status()).eq(200);

		let phpFound = (headers['w3tc_php'] != null);
		if (expectPhp == null) {
			// dont check
		} else if (expectPhp) {
			expect(phpFound).true;
		} else {
			expect(phpFound).false;
		}

		let expiresFound = (headers['expires'] != null);

		if (expectExpires) {
			expect(expiresFound).true;
		} else {
			expect(expiresFound).false;
		}
	}
}
