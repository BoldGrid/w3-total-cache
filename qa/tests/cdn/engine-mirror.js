/**
 * File: qa/tests/cdn/engine-mirror.js
 *
 * Generic mirror CDN engine form-save coverage. No credentials.
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

describe("CDN engine: mirror form-save", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("cdn.mirror.* keys round-trip", async () => {
    await w3tc.assertEngineSaveRoundTrip(adminPage, "cdn", "mirror", {
      "cdn.mirror.ssl": "enabled",
    });
    /**
     * Mirror domain is an array — set/read via setOptionInternal
     * directly to verify array round-trip.
     */
    await w3tc.setOptionInternal(adminPage, "cdn.mirror.domain", [
      "cdn.example.com",
    ]);

    let domains = await w3tc.getConfigOption("cdn.mirror.domain", "json");
    log.log("cdn.mirror.domain = " + JSON.stringify(domains));
    expect(domains).is.an("array");
    expect(domains).contains("cdn.example.com");
  });
});
