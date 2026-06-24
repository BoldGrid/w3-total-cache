/**
 * File: qa/tests/cdn/engine-s3-compatible.js
 *
 * S3-compatible CDN engine form-save coverage. Extends the S3
 * shape with the `cdn.s3_compatible.api_host` selector that
 * routes uploads to MinIO / Wasabi / Backblaze B2 / etc.
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

describe('CDN engine: s3_compatible form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('cdn.s3_compatible.* keys round-trip', async() => {
		await w3tc.assertEngineSaveRoundTrip(adminPage, 'cdn', 's3_compatible', {
			'cdn.s3.key':                    'AKIAIOSFODNN7QATEST',
			'cdn.s3.secret':                 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			'cdn.s3.bucket':                 'w3tc-qa-bucket',
			'cdn.s3_compatible.api_host':    's3.us-west-002.backblazeb2.com',
			'cdn.s3.ssl':                    'enabled'
		});
	});
});
