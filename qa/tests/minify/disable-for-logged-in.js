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

/**environments: multiply(environments('blog'), environments('cache')) */

let testPageUrl;

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
			browsercache__enabled: false,
			minify__enabled: true,
			minify__engine: env.cacheEngineLabel
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



	it('scripts minified', async() => {
		await adminPage.goto(testPageUrl);
		let scripts = await dom.listScriptSrcSync(adminPage);
		for (let url of scripts) {
			// WordPress script modules are intentionally excluded from minification
			if (url.indexOf('wp-includes/js/dist/script-modules/') >= 0) {
				log.log('skipping WordPress script module: ' + url);
				continue;
			}
			log.log('Minify presence expected in ' + url);
			expect(url).contains('cache/minify');
		}
	});



	it('set options - exclude minification', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_minify', {
			minify__reject__logged: true
		});
	});



	it('scripts not minified', async() => {
		await adminPage.goto(testPageUrl);
		let scripts = await dom.listScriptSrcSync(adminPage);
		for (let url of scripts) {
			log.log('Minify presence not expected in ' + url);
			expect(url).not.contains('cache/minify');
		}
	});
});
