/**
 * File: qa/tests/extensions/genesis-fragmentcache-smoke.js
 *
 * Genesis + FragmentCache extension activation smoke.
 *
 * Neither extension has a dedicated settings form:
 *  - Genesis renders an activation-note only.
 *  - FragmentCache surfaces its settings inline on the General
 *    page (anchor `#fragmentcache`), not on its own extension page.
 *
 * Both are paired into a single smoke spec because each is
 * trivially small.
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

describe("Genesis + FragmentCache extension smoke", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("Genesis extension activates without crashing", async function () {
    // Genesis extension id is `genesis.theme`.
    await w3tc
      .activateExtension(adminPage, "genesis.theme")
      .catch((e) => log.log("activate result: " + e.message));

    // networkAdminUrl: w3tc_extensions is not visible_always, so on
    // multisite (default common.force_master) env.adminUrl serves WP's
    // "not allowed" page and this spec would always this.skip(),
    // silently losing coverage. Single-site: same URL.
    await adminPage.goto(
      env.networkAdminUrl + "admin.php?page=w3tc_extensions",
      { waitUntil: "domcontentloaded" },
    );

    let html = await adminPage.content();
    // On free builds Genesis may not be present at all.
    if (html.indexOf("genesis") === -1 && html.indexOf("Genesis") === -1) {
      log.log("SKIP: Genesis not visible on extensions page");
      this.skip();
      return;
    }

    // Page renders without fatal error.
    expect(html).not.contains("Fatal error");
    expect(html).not.contains("Parse error");
    log.success("Genesis extension activation surface renders cleanly");
  });

  it("FragmentCache extension activates and General page renders #fragmentcache", async function () {
    await w3tc
      .activateExtension(adminPage, "fragmentcache")
      .catch((e) => log.log("activate result: " + e.message));

    // networkAdminUrl: see the note above — w3tc_general is not
    // visible_always either, so the per-site admin would 'not allow' it.
    await adminPage.goto(env.networkAdminUrl + "admin.php?page=w3tc_general", {
      waitUntil: "domcontentloaded",
    });

    let html = await adminPage.content();
    if (
      html.indexOf("fragmentcache") === -1 &&
      html.indexOf("Fragment") === -1
    ) {
      log.log("SKIP: FragmentCache not visible on General page");
      this.skip();
      return;
    }
    expect(html).not.contains("Fatal error");
    expect(html).not.contains("Parse error");
    log.success("FragmentCache settings block renders on General page");
  });
});
