/**
 * File: qa/tests/cdn/engine-azuremi.js
 *
 * Azure Blob Storage with Managed Identity (azuremi) CDN engine
 * form-save coverage.
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

describe('CDN engine: azuremi form-save', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('cdn.azuremi.* keys round-trip', async() => {
		await w3tc.assertEngineSaveRoundTrip(adminPage, 'cdn', 'azuremi', {
			'cdn.azuremi.user':      'w3tcqaaccount',
			'cdn.azuremi.clientid':  '11111111-2222-3333-4444-555555555555',
			'cdn.azuremi.container': 'w3tc-qa-mi-container',
			'cdn.azuremi.ssl':       'auto'
		});
	});
});
