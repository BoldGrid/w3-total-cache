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

/**
 * Parse JSON from an HTTP body, tolerating leading non-JSON noise if output buffering
 * prepends HTML or whitespace ahead of the payload (possible with direct ob_callback).
 *
 * @param {string} body Response body.
 * @returns {object|Array}
 */
function parseJsonResponseBody(body) {
	const s = String(body).trim();
	try {
		return JSON.parse(s);
	} catch (first) {
		const openObj = s.indexOf('{');
		const openArr = s.indexOf('[');
		let start = -1;
		if (openObj === -1) {
			start = openArr;
		} else if (openArr === -1) {
			start = openObj;
		} else {
			start = Math.min(openObj, openArr);
		}
		if (start < 0) {
			throw first;
		}
		return JSON.parse(s.slice(start));
	}
}

describe('REST API JSON smoke (output buffering / empty body regressions)', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('install QA mu-plugin (nginx X-Accel-Buffering: no)', async() => {
		await sys.installQaNginxStreamMuPlugin();
	});

	it('wp-json route index returns non-empty JSON', async() => {
		const url = blogHomePath('wp-json/');
		log.log('GET ' + url);
		const r = await sys.httpGet(url, {
			headers: Object.assign({}, sys.qaNginxStreamRequestHeaders, {
				'Accept': 'application/json'
			})
		});
		expect(r.statusCode).equals(200);
		expect(r.body.length).greaterThan(50);
		const data = parseJsonResponseBody(r.body);
		expect(data).property('namespaces');
	});

	it('wp/v2/posts returns a JSON array', async() => {
		const url = blogHomePath('wp-json/wp/v2/posts');
		log.log('GET ' + url);
		const r = await sys.httpGet(url, {
			headers: Object.assign({}, sys.qaNginxStreamRequestHeaders, {
				'Accept': 'application/json'
			})
		});
		expect(r.statusCode).equals(200);
		expect(r.body.length).greaterThan(2);
		const data = parseJsonResponseBody(r.body);
		expect(Array.isArray(data)).true;
	});
});
