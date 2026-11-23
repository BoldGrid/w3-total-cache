/**
 * File: qa/tests/cdnfsd/engine-transparentcdn.js
 *
 * CDNFSD (full-site delivery) TransparentCDN engine form-save
 * coverage. Three credential keys:
 *   - cdnfsd.transparentcdn.company_id
 *   - cdnfsd.transparentcdn.client_id
 *   - cdnfsd.transparentcdn.client_secret  (secret)
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

describe("CDNFSD engine: transparentcdn form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdnfsd.transparentcdn.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(
      adminPage,
      "cdnfsd",
      "transparentcdn",
      {
        "cdnfsd.transparentcdn.company_id": "1234",
        "cdnfsd.transparentcdn.client_id": "w3tc-qa-tcdn-client",
        "cdnfsd.transparentcdn.client_secret":
          "qa-tcdn-secret-ffffffffffffffff",
      },
    );
  });
});
