/**
 * File: qa/tests/cdn/engine-cf2.js
 *
 * CloudFront (pull) — cf2 — CDN engine form-save coverage.
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

describe("CDN engine: cf2 (CloudFront pull) form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdn.cf2.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(adminPage, "cdn", "cf2", {
      "cdn.cf2.key": "AKIAIOSFODNN7QATEST",
      "cdn.cf2.secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
      "cdn.cf2.id": "EXAMPLEDIST456",
      "cdn.cf2.ssl": "auto",
    });
  });
});
