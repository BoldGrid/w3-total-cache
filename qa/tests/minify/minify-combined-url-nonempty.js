function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('cache')) */

let testPageUrl;

describe('Minify combined asset URL returns non-empty body', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('copy theme files', async() => {
		const theme = await wp.getCurrentTheme(adminPage);
		const themePath = env.wpContentPath + 'themes/' + theme;
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
		const testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'minify nonempty',
			content: 'page content [w3tcqa]'
		});
		testPageUrl = testPage.url;
	});

	it('each sampled minify URL returns 200 with non-empty body', async() => {
		await page.goto(testPageUrl, {
			timeout: 300000,
			waitUntil: 'domcontentloaded'
		});

		const urls = await page.evaluate(() => {
			const out = [];
			document.querySelectorAll('script[src], link[rel="stylesheet"][href]').forEach((el) => {
				const u = el.src || el.href;
				if (u && u.indexOf('cache/minify') >= 0) {
					out.push(u);
				}
			});
			return out;
		});

		expect(urls.length).greaterThan(0);

		const sample = urls.slice(0, 5);
		for (const u of sample) {
			log.log('GET ' + u);
			const r = await sys.httpGet(u);
			expect(r.statusCode).equals(200);
			expect(r.body.length).greaterThan(0);
		}
	});
});
