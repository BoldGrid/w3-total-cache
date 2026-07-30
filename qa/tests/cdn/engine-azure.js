/**
 * File: qa/tests/cdn/engine-azure.js
 *
 * Azure Blob Storage CDN engine form-save coverage.
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

describe("CDN engine: azure form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdn.azure.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(adminPage, "cdn", "azure", {
      "cdn.azure.user": "w3tcqaaccount",
      "cdn.azure.key": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa==",
      "cdn.azure.container": "w3tc-qa-container",
      "cdn.azure.ssl": "auto",
    });
  });
});
