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

let targetUrl;

describe('check that canonical headers present with pull CDN', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: true,
			cdn__enabled: true,
			cdn__engine: 'mirror'
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
			cdn_cnames_0: 'cdn-host.com',
			cdn__canonical_header: true
		});

		await sys.afterRulesChange();
	});



	it('copy files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme;
		targetUrl = env.blogWpContentUrl + 'themes/' + theme;
		await sys.copyPhpToPath('../../plugins/image.jpg', targetPath);
		await sys.copyPhpToPath('../../plugins/font.woff2', targetPath);
	});



	it('test', async() => {
		log.log('checking ' + targetUrl + '/image.jpg');
		let response = await page.goto(targetUrl + '/image.jpg');
		expect(response.headers()['content-type']).equals('image/jpeg');
		expect(response.status()).equals(200);
		expectCommonHeaders(response.headers());

		log.log('checking ' + targetUrl + '/font.woff2');
		let response2 = await sys.httpGet(targetUrl + '/font.woff2');
		expect(response2.headers['access-control-allow-origin']).not.empty;
		expect(response2.headers['content-type']).contains('application/');
		expectCommonHeaders(response2.headers);
	});
});



function expectCommonHeaders(headers) {
	expect(headers['link'].indexOf('>; rel="canonical"') >= 0).is.true;
}
