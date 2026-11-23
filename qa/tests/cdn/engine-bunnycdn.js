/**
 * File: qa/tests/cdn/engine-bunnycdn.js
 *
 * BunnyCDN CDN engine form-save coverage. The XSS regression for
 * the configured-popup exception sink is covered separately by
 * `bunnycdn-exception-xss.js`; this spec is the positive control
 * for the same configuration namespace.
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
  return require("../../" + p);
}

const expect = require("chai").expect;
const log = require("mocha-logger");

const env = requireRoot("lib/environment");
const sys = requireRoot("lib/sys");
const w3tc = requireRoot("lib/w3tc");

/**environments: environments('blog') */

describe("CDN engine: bunnycdn form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdn.bunnycdn.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(adminPage, "cdn", "bunnycdn", {
      "cdn.bunnycdn.account_api_key": "qa-bunny-account-key-dddddddddddd",
      "cdn.bunnycdn.cdn_hostname": "w3tcqa.b-cdn.net",
      "cdn.bunnycdn.pull_zone_id": "12345",
      "cdn.bunnycdn.verify_tls_certificates": true,
    });
  });

  it("cdn.bunnycdn.verify_tls_certificates round-trips from the CDN page", async () => {
    await w3tc.setOptions(adminPage, "w3tc_cdn", {
      cdn__bunnycdn__verify_tls_certificates: false,
    });
    expect(
      await w3tc.getConfigOption(
        "cdn.bunnycdn.verify_tls_certificates",
        "boolean",
      ),
    ).equals(false);
  });
});
