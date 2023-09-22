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

let theme;
let imageUrl;
let testPageUrl;

describe('check that media library works when CDN is active', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('copy theme files', async() => {
		theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/cdn-pull-theme/*', `${themePath}/qa`);
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`, '/qa/cdn-pull-sc.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
			browsercache__enabled: false,
		    cdn__engine: 'mirror',
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn__theme__enable: false,
			cdn__includes__enable: false,
			cdn__uploads__enable: false,
			cdn_custom_files:
				'{plugins_dir}/*.jpg\n' +
				'{plugins_dir}/*.js\n' +
				'{wp_content_dir}/themes/*.js\n' +
				'{wp_content_dir}/themes/*.png\n' +
				'{wp_content_dir}/uploads/*\n',
	      cdn_cnames_0: 'for-tests.sandbox'
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
			if (url.indexOf("image.jpg") >= 0) {
				imageUrl = url;
			}
		}
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content [w3tcqa]',
		});
		testPageUrl = testPage.url;
	});



	it('check page', async() => {
		log.log('opening ' + testPageUrl);
		await page.goto(testPageUrl);
		let urlsToReplace = [
			env.blogWpContentUrl + 'themes/' + theme + '/qa/theme-js.js',
			env.blogWpContentUrl + 'themes/' + theme + '/qa-theme-image2.png',
			env.blogPluginsUrl + 'test-plugin/plugin-js.js',
			env.blogPluginsUrl + 'test-plugin/plugin-image1.jpg'
		];

		let urlsToKeep = [
			env.blogWpContentUrl + 'themes/' + theme + '/qa-theme-image1.jpg',
			env.blogWpContentUrl + 'themes/' + theme + '/qa-theme-image3.gif',
			env.blogPluginsUrl + 'test-plugin/plugin-image2.png',
			env.blogPluginsUrl + 'test-plugin/plugin-image3.gif',
		];

		let pageContent = await page.content();

		urlsToReplace.forEach(function(url) {
			log.log('URL to replace host: ' + url);
			let urlReplaced = url.replace(
				'://' + env.blogHost + env.wpMaybeColonPort,
				'://for-tests.sandbox');

			log.log(testPageUrl + ' replaced ' + url);
			expect(url).not.equals(urlReplaced);
			expect(pageContent).not.contains(url);
			expect(pageContent).contains(urlReplaced);
			log.success('ok');
		});

		urlsToKeep.forEach(function(url) {
			log.log(testPageUrl + ' keeps ' + url);
			expect(pageContent).contains(url);
			log.success('ok');
		});
	});
});
