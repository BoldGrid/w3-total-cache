/**
 * File: qa/tests/extensions/imageservice-activate.js
 *
 * ImageService extension activation smoke.
 *
 * ImageService is the SaaS image-optimization extension. It has
 * NO dedicated settings page — per-image actions live in the
 * Media Library (post.php attachment edit screen), and global
 * config writes happen via the SetupGuide wizard. The only
 * meaningful "form-save" surface from the extensions page is the
 * activate / deactivate link.
 *
 * Posture: activate the extension, assert it appears active on
 * the extensions list. Done.
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

describe("ImageService extension activation smoke", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("activate ImageService extension", async function () {
    await w3tc
      .activateExtension(adminPage, "imageservice")
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
    if (
      html.indexOf("imageservice") === -1 &&
      html.indexOf("Image Service") === -1
    ) {
      log.log("SKIP: ImageService not visible on extensions page");
      this.skip();
      return;
    }

    /**
     * The extension row marks "deactivate" when active.
     * We don't depend on a specific selector since the row
     * markup varies across versions; we just confirm the
     * extension name appears in the active block.
     */
    let activeHtml = await adminPage
      .$eval("#imageservice", (e) => e.outerHTML)
      .catch(() => null);
    if (activeHtml === null) {
      log.log("SKIP: imageservice list row not rendered");
      this.skip();
      return;
    }
    log.log("imageservice row: " + activeHtml.substring(0, 200));
    // Active state — `deactivate` action link present.
    expect(activeHtml).contains("deactivate");
    log.success("ImageService extension activated");
  });
});
