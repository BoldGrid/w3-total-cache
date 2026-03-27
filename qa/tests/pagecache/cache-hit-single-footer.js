function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: multiply(environments('blog'), environments('pagecache')) */

/**
 * Count W3TC "Served from" footer lines (duplicate ob processing adds a second).
 *
 * @param {string} html Page HTML.
 * @returns {number}
 */
function countServedFromFooters(html) {
	const m = html.match(/Served from:[^\n]*by W3 Total Cache/g);
	return m ? m.length : 0;
}

describe('Page cache footer once on cache hit (double ob_callback regression)', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('install QA mu-plugin (nginx X-Accel-Buffering: no)', async() => {
		await sys.installQaNginxStreamMuPlugin();
	});

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await sys.afterRulesChange();
	});

	it('warm cache then assert single Served from footer on hit', async() => {
		await page.setExtraHTTPHeaders(sys.qaNginxStreamRequestHeaders);

		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
		log.log('Second request (expect cache hit where supported)');
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);

		const html = await page.content();
		const n = countServedFromFooters(html);
		log.log('Served from footer count: ' + n);
		expect(n).equals(1);
	});
});
