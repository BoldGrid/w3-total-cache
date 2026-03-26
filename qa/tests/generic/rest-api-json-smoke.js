function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

/**
 * Build an absolute URL under the blog home URL.
 *
 * @param {string} path Path after home, with or without a leading slash (e.g. wp-json/).
 * @returns {string}
 */
function blogHomePath(path) {
	const home = env.homeUrl.endsWith('/') ? env.homeUrl : env.homeUrl + '/';
	const p = path.startsWith('/') ? path.slice(1) : path;
	return home + p;
}

describe('REST API JSON smoke (output buffering / empty body regressions)', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('wp-json route index returns non-empty JSON', async() => {
		const url = blogHomePath('wp-json/');
		log.log('GET ' + url);
		const r = await sys.httpGet(url);
		expect(r.statusCode).equals(200);
		expect(r.body.length).greaterThan(50);
		const data = JSON.parse(r.body);
		expect(data).property('namespaces');
	});

	it('wp/v2/posts returns a JSON array', async() => {
		const url = blogHomePath('wp-json/wp/v2/posts');
		log.log('GET ' + url);
		const r = await sys.httpGet(url);
		expect(r.statusCode).equals(200);
		expect(r.body.length).greaterThan(2);
		const data = JSON.parse(r.body);
		expect(Array.isArray(data)).true;
	});
});
