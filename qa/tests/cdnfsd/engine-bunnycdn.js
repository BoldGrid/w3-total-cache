/**
 * File: qa/tests/cdnfsd/engine-bunnycdn.js
 *
 * CDNFSD (full-site delivery) BunnyCDN engine form-save coverage.
 *
 * Most BunnyCDN FSD config keys are populated via the popup-based
 * Authorize flow that calls into the BunnyCDN API and writes the
 * pull-zone id, hostname, and origin URL back to config. This
 * spec instead writes the same target keys directly via
 * setOptionInternal so the form-save / config-loader path is
 * covered without needing a live BunnyCDN account.
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

describe('CDNFSD engine: bunnycdn form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('cdnfsd.bunnycdn.* keys round-trip', async() => {
		await w3tc.assertEngineSaveRoundTrip(adminPage, 'cdnfsd', 'bunnycdn', {
			'cdnfsd.bunnycdn.account_api_key': 'qa-bunny-fsd-key-eeeeeeeeeeeeeee',
			'cdnfsd.bunnycdn.pull_zone_id':    9876,
			'cdnfsd.bunnycdn.name':            'w3tc-qa-fsd',
			'cdnfsd.bunnycdn.origin_url':      env.homeUrl,
			'cdnfsd.bunnycdn.cdn_hostname':    'w3tcqa-fsd.b-cdn.net'
		});
	});
});
