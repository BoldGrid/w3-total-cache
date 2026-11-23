/**
 * File: qa/tests/cdn/engine-s3.js
 *
 * S3 CDN engine form-save coverage.
 *
 * Asserts the legitimate same-page CDN credential save lands all
 * declared cdn.s3.* keys and the engine appears as the active CDN
 * in the General page. The rt9-210 cross-page mass-assignment is
 * already regression-tested by credential-page-boundary.js; this
 * spec is the positive control for the same code path.
 *
 * Live AWS uploads are NOT exercised — the spec only proves the
 * settings form persists. AWS_S3_KEY / AWS_S3_SECRET env-gating
 * is reserved for a follow-up live-upload spec.
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

describe("CDN engine: s3 form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdn.s3.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(adminPage, "cdn", "s3", {
      "cdn.s3.key": "AKIAIOSFODNN7QATEST",
      "cdn.s3.secret": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY",
      "cdn.s3.bucket": "w3tc-qa-bucket",
      "cdn.s3.bucket.location": "us-west-2",
      "cdn.s3.ssl": "auto",
      "cdn.s3.public_objects": "enabled",
    });
  });
});
