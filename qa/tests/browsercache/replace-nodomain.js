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

/**environments:environments('blog') */

let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/browsercache/*', targetPath);
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: true,
		});

		// Enable "Prevent caching of objects after settings change" on browser cache page
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__other__replace: true,
			browsercache__cssjs__replace: true
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content',
			template: 'qa/prevent-caching.php'
		});
		testPageUrl = testPage.url;
	});



	it('check content', async() => {
		await page.goto(testPageUrl, {waitUntil: 'domcontentloaded'});

		let img1 = await page.$eval('#image1', (e) => e.src);
		let id1 = img1.match(/[?&](x([0-9]+))/);
		log.log('check image1 link ' + img1 + ' contains version');
		expect(id1 != null && id1[2] != null).is.true;

		let img2 = await page.$eval('#image2', (e) => e.src);
		let id2 = img2.match(/[?&](x([0-9]+))/);
		log.log('check image2 link ' + img2 + ' contains version');
		expect(id2 != null && id2[2] != null).is.true;

		let img3 = await page.$eval('#image3', (e) => e.src);
		let id3 = img3.match(/[?&](x([0-9]+))/);
		log.log('check image3 link ' + img3 + ' doesnt contain version');
		expect(id3 == null || id3[2] == null).is.true;
	});
});
