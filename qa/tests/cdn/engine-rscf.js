/**
 * File: qa/tests/cdn/engine-rscf.js
 *
 * RackSpace CloudFiles (rscf) form-save coverage.
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');

const env  = requireRoot('lib/environment');
const sys  = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: environments('blog') */

describe('CDN engine: rscf (Rackspace CloudFiles) form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('cdn.rscf.* keys round-trip', async() => {
		await w3tc.assertEngineSaveRoundTrip(adminPage, 'cdn', 'rscf', {
			'cdn.rscf.user':      'w3tc-qa-user',
			'cdn.rscf.key':       'rackspace-qa-key-aaaaaaaaaaaaaaaaaaaa',
			'cdn.rscf.location':  'us',
			'cdn.rscf.container': 'w3tc-qa-container',
			'cdn.rscf.ssl':       'auto'
		});
	});
});
