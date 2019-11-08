function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments:
variable_equals('W3D_WP_NETWORK', ['subdir', 'subdomain'],
	environments('blog')
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('check that repeats only once', async() => {
		let response = await page.goto(env.homeUrl);
		if (page.url().indexOf("repeat=w3tc") > 0) {
			log.log('repeat=w3tc found, doing one more request');
			let headers = response.headers();
			console.log(headers);
			expect(headers['x-robots-tag']).equals('noindex');

			await page.goto(env.homeUrl);
			expect(page.url()).not.contains('repeat=w3tc');
		}
	});
});
