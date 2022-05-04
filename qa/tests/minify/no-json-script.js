function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');

fs.readFileAsync = util.promisify(fs.readFile);

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
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/minify-no-json-script/*', targetPath);
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_minify', {
	      minify__html__enable: true,
	      minify__html__inline__js: true,
	      minify__html__inline__css: true
	    });

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content',
			template: 'qa/minify-json-script.php'
		});
		testPageUrl = testPage.url;
	});



	it('check html', async() => {
		await page.goto(testPageUrl);
		let html = await page.content();

		let script1 = '<script>var js4="#js4";console.log("hello"+"   world");</script>';
		let script2 = '<script>console.log("hello2"+" world2");</script>';
		let script3 = '<script type="application/json">{"a":["b","c"]}</script>';

		// check all scripts are correctly minified
		expect(html).contains(script1);
		expect(html).contains(script2);
		expect(html).contains(script3);
	});
});
