function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: environments('blog') */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('activate extra plugin', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/minify-test-plugin/*',
			env.wpPluginsPath + 'minify-test-plugin');
		await wp.networkActivatePlugin(adminPage, 'minify-test-plugin/minify-test-plugin.php');
	});



	it('there are plugin assets on page', async() => {
		await page.goto(env.homeUrl);
		let html = await page.content();
		expect(html).contains("test-js.js");
		expect(html).contains("test-css.css");
	});



	it('set options - activate minify', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			minify__enabled: true,
			minify__engine: 'file'
		});
	});



	it('there are no plugin assets on page', async() => {
		await page.goto(env.homeUrl);
		let html = await page.content();
		expect(html).not.contains("test-js.js");
		expect(html).not.contains("test-css.css");
	});
});
