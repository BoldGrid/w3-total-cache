/**
 * File: qa/tests/support/page-render.js
 *
 * Support page render + sec-info-leak regression.
 *
 * `?page=w3tc_support` historically had two failure modes:
 *  1. The Wufoo iframe embed assumed the third-party script loaded
 *     and would white-screen on failure.
 *  2. The "system info" / "wp-config" / "phpinfo" handlers leaked
 *     wp-config.php contents and the full phpinfo() output to the
 *     admin via the support-ticket flow — exfil paths even an
 *     authenticated admin should not have a one-click button for.
 *
 * After the sec-info-leak fix, the page renders the Wufoo embed
 * but does NOT inline wp-config.php content or full phpinfo()
 * markers into the response. The Support page is also the only
 * place where a "view diagnostic" link could legitimately reach
 * those primitives; that link was removed.
 *
 * Posture: load the page and assert it renders without fatal
 * error AND the response body contains none of the diagnostic-
 * marker strings that would indicate wp-config / phpinfo content
 * leaked through.
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

/**environments: environments('blog') */

/**
 * Markers whose presence in the admin response body would prove
 * wp-config / phpinfo leaked. These are deliberately distinctive:
 * - `DB_PASSWORD`         — only in wp-config.php
 * - `phpinfo()`           — phpinfo() output's own header
 * - `Loaded Configuration File` — phpinfo() section
 * - `WordPress database error` — typically in debug-traces
 * - `AUTH_KEY`            — wp-config salt block
 */
const LEAK_MARKERS = [
  "DB_PASSWORD",
  "AUTH_KEY",
  "SECURE_AUTH_KEY",
  "LOGGED_IN_KEY",
  "NONCE_KEY",
  "AUTH_SALT",
  "phpinfo()",
  "Loaded Configuration File",
  "allow_url_fopen",
  '_SERVER["HTTP_HOST"]',
];

describe("Support page render + sec-info-leak regression", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("?page=w3tc_support renders without fatal error", async () => {
    await adminPage.goto(env.adminUrl + "admin.php?page=w3tc_support", {
      waitUntil: "domcontentloaded",
    });

    // Skip wizard if shown.
    if ((await adminPage.$("#w3tc-wizard-skip")) != null) {
      await Promise.all([
        adminPage.evaluate(() =>
          document.querySelector("#w3tc-wizard-skip").click(),
        ),
        adminPage.waitForNavigation({ timeout: 300000 }),
      ]);
      await adminPage.goto(env.adminUrl + "admin.php?page=w3tc_support", {
        waitUntil: "domcontentloaded",
      });
    }

    let pageHtml = await adminPage.content();
    expect(pageHtml).not.contains("Fatal error");
    expect(pageHtml).not.contains("Parse error");
    expect(pageHtml).not.contains("Uncaught");

    /**
     * Something support-page-shaped must be in the response.
     * The Wufoo embed loads cross-origin so its iframe shell
     * is what we can assert from this side of the network.
     */
    let hasSupportMarker =
      pageHtml.indexOf("wufoo") !== -1 ||
      pageHtml.indexOf("support") !== -1 ||
      pageHtml.indexOf("Support") !== -1;
    expect(hasSupportMarker).equals(true);
    log.success("Support page rendered without fatal error");
  });

  /**
   * Regression: load the page and assert NONE of the wp-config /
   * phpinfo marker strings appear. The sec-info-leak fix removed
   * the one-click diagnostic exfil and the marker strings are
   * the proof that the leak vector is closed.
   */
  it("Support page response carries no wp-config / phpinfo markers", async () => {
    await adminPage.goto(env.adminUrl + "admin.php?page=w3tc_support", {
      waitUntil: "domcontentloaded",
    });

    let pageHtml = await adminPage.content();
    let leaks = LEAK_MARKERS.filter((m) => pageHtml.indexOf(m) !== -1);
    if (leaks.length > 0) {
      log.log("LEAKED markers: " + leaks.join(", "));
    }
    expect(leaks).is.empty;
    log.success("Support page does not echo wp-config / phpinfo markers");
  });
});
