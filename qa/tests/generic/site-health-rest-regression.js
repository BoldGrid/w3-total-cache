function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

describe('Site Health REST signals (PR output-buffering regressions)', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('install QA mu-plugin (nginx X-Accel-Buffering: no)', async() => {
		await sys.installQaNginxStreamMuPlugin();
	});

	it('Tools > Site Health does not show REST API failure strings', async() => {
		await adminPage.setExtraHTTPHeaders(sys.qaNginxStreamRequestHeaders);

		const url = env.adminUrl + 'tools.php?page=site-health';
		log.log('Opening ' + url);
		await adminPage.goto(url, {
			waitUntil: 'domcontentloaded',
			timeout: 300000
		});

		// Site Health runs asynchronous checks in the background.
		await new Promise((resolve) => setTimeout(resolve, 10000));

		const html = await adminPage.content();
		expect(html).not.contains('The REST API did not behave correctly');
		expect(html).not.contains('context query parameter');
	});
});
