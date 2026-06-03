/**
 * File: qa/tests/cdnfsd/engine-cloudfront.js
 *
 * CDNFSD (full-site delivery) CloudFront engine form-save coverage.
 *
 * Three config keys:
 *   - cdnfsd.cloudfront.access_key      (secret)
 *   - cdnfsd.cloudfront.secret_key      (secret)
 *   - cdnfsd.cloudfront.distribution_id (string)
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

describe('CDNFSD engine: cloudfront form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('cdnfsd.cloudfront.* keys round-trip', async() => {
		await w3tc.assertEngineSaveRoundTrip(adminPage, 'cdnfsd', 'cloudfront', {
			'cdnfsd.cloudfront.access_key':      'AKIAIOSFODNN7QATEST',
			'cdnfsd.cloudfront.secret_key':      'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			'cdnfsd.cloudfront.distribution_id': 'EXAMPLEFSD123'
		});
	});
});
