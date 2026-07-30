/**
 * File: qa/tests/cdn/engine-cf.js
 *
 * CloudFront (push) CDN engine form-save coverage.
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

describe("CDN engine: cf (CloudFront push) form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdn.cf.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(adminPage, "cdn", "cf", {
      "cdn.cf.key": "AKIAIOSFODNN7QATEST",
      "cdn.cf.secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
      "cdn.cf.bucket": "w3tc-qa-cf-bucket",
      "cdn.cf.bucket.location": "us-east-1",
      "cdn.cf.id": "EXAMPLEDIST123",
      "cdn.cf.ssl": "auto",
      "cdn.cf.public_objects": "enabled",
    });
  });
});
