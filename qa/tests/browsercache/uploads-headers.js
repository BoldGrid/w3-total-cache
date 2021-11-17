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

let imageUrl;
let etagSupport = true;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);


	it('os-specifics', async() => {
		if (process.env['W3D_HTTP_SERVER'] == "nginx" && process.env['W3D_OS'] == "precise") {
			// nginx on preciese is below v1.3.3 - doesnt support ETag
			etagSupport = false;
		}
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: true
		});

		// Enable "Prevent caching of objects after settings change" on browser cache page
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__other__etag: false,
			browsercache__other__expires: false
		});

		await sys.afterRulesChange();
	});



	it('upload image', async() => {
		log.log(env.adminUrl + 'media-new.php?browser-uploader');
		await adminPage.goto(env.adminUrl + 'media-new.php?browser-uploader',
			{waitUntil: 'domcontentloaded'});

		let fileInput = await adminPage.$('input[name=async-upload]');
		await fileInput.uploadFile('../../plugins/image.jpg');
		await Promise.all([
			adminPage.click('#html-upload'),
			adminPage.waitForNavigation({timeout:0})
		]);
	});



	it('find image url', async() => {
		await adminPage.waitForSelector('.attachment-preview');
		let imgs = await dom.listTagAttributes(adminPage, 'img', 'src');
		for (let url of imgs) {
			if (url.indexOf("image.jpg") >= 0) {
				imageUrl = url;
			}
		}
	});



	it('check image without expiration', async() => {
		let response = await page.goto(imageUrl);
		expect(response.headers()['content-type']).matches(/image\/(jpeg|webp)/);
		expect(response.status()).equals(200);
		expect(response.headers()['expires']).is.undefined;
	});



	it('enable etag', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__other__etag: true,
			browsercache__other__expires: true
		});

		await sys.afterRulesChange();
		//box.onPageChangedOutside();   // todo: instead of that note should appear
	});



	it('check image without expiration', async() => {
		let response = await page.goto(imageUrl);
		expect(response.headers()['content-type']).matches(/image\/(jpeg|webp)/);
		expect(response.status()).equals(200);
		expect(response.headers()['expires']).not.null;

		if (etagSupport) {
			expect(response.headers()['etag']).not.null;
		}
	});



	it('enable rewrite', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
			browsercache__other__replace: true,
			browsercache__cssjs__replace: true,
			browsercache__rewrite: true
		});

		await sys.afterRulesChange();
		//box.onPageChangedOutside();   // todo: instead of that note should appear
	});



	it('check image without expiration', async() => {
		let url = imageUrl.replace(".jpg", ".x12345.jpg");
		log.log(url);
		let response = await page.goto(url);

		expect(response.headers()['content-type']).matches(/image\/(jpeg|webp)/);
		expect(response.status()).equals(200);
		expect(response.headers()['expires']).not.null;

		if (etagSupport) {
			expect(response.headers()['etag']).not.null;
		}
	});
});
