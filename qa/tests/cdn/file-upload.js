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
let imageUrl;

describe('check that media library works when CDN is active', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
		    cdn__engine: 'ftp',
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
	      cdn_ftp_host: 'wp.sandbox',
	      cdn_ftp_user: 'www-data',
	      cdn_ftp_pass: 'sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU',
	      cdn_ftp_path: env.cdnFtpExportDir,
	      cdn_cnames_0: env.cdnFtpExportHostPort
	    });

		await sys.afterRulesChange();
	});



	it('upload image', async() => {
		await adminPage.goto(env.adminUrl + 'media-new.php?browser-uploader',
			{waitUntil: 'domcontentloaded'});

		let fileInput = await adminPage.$('input[name=async-upload]');
		await fileInput.uploadFile('../../plugins/image.jpg');
		let htmlUpload = '#html-upload';
		await Promise.all([
			adminPage.evaluate((htmlUpload) => document.querySelector(htmlUpload).click(), htmlUpload),
			adminPage.waitForNavigation({timeout:0})
		]);
	});


	it('find image url', async() => {
		await adminPage.waitForSelector('.attachment-preview');
		let imgs = await dom.listTagAttributes(adminPage, 'img', 'src');
		for (let url of imgs) {
			log.log('Found image URL: ' + url);
			if (url.search(/image(.*?).jpg/) >= 0) {
				imageUrl = url;
				log.log('Using URL: ' + url);
			}
		}
	});


	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'test',
			content: '<img src="' + imageUrl + '" id="w3tctest_img" />'
		});
		testPageUrl = testPage.url;
	});


	it('check cdn-ized image loads well', async() => {
		await page.goto(testPageUrl);
		let imageUrlOnPage = await page.$eval('#w3tctest_img', (e) => e.src);
		log.log('found image ' + imageUrlOnPage + ' on page ' + testPageUrl);

		let response = await page.goto(imageUrlOnPage);
		expect(response.headers()['content-type']).equals('image/jpeg');
		expect(response.status()).equals(200);
	});
});
