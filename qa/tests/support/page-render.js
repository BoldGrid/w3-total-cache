/**
 * File: qa/tests/support/page-render.js
 *
 * Support page render + client-data regression.
 *
 * `?page=w3tc_support` historically had two failure modes:
 *  1. The third-party form embed assumed the script loaded
 *     and would white-screen on failure.
 *  2. Diagnostic handlers leaked wp-config.php contents and
 *     phpinfo() output through the support-ticket flow.
 *
 * The Support page now mounts an empty shell. The third-party
 * form script is injected only after consent and a click.
 * Contact fields are not localized into the page.
 *
 * Posture: load the page and assert it renders without fatal
 * error, the consent gate is present, the third-party script
 * is not injected until Open is clicked, and the response body
 * contains none of the diagnostic-marker strings.
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

const SUPPORT_URL = () =>
  env.networkAdminUrl + "admin.php?page=w3tc_support";

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

async function gotoSupportPage() {
  await adminPage.goto(SUPPORT_URL(), {
    waitUntil: "domcontentloaded",
  });

  if ((await adminPage.$("#w3tc-wizard-skip")) != null) {
    await Promise.all([
      adminPage.evaluate(() =>
        document.querySelector("#w3tc-wizard-skip").click(),
      ),
      adminPage.waitForNavigation({ timeout: 300000 }),
    ]);
    await adminPage.goto(SUPPORT_URL(), {
      waitUntil: "domcontentloaded",
    });
  }
}

describe("Support page render + sec-info-leak regression", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("?page=w3tc_support renders the consent-gated shell", async () => {
    await gotoSupportPage();

    let pageHtml = await adminPage.content();
    expect(pageHtml).not.contains("Fatal error");
    expect(pageHtml).not.contains("Parse error");
    expect(pageHtml).not.contains("Uncaught");

    await adminPage.waitForSelector("#w3tc-support-form-shell", {
      timeout: 30000,
    });
    await adminPage.waitForSelector("#w3tc-support-consent", {
      timeout: 10000,
    });
    await adminPage.waitForSelector("#w3tc-support-load", {
      timeout: 10000,
    });

    let loadDisabled = await adminPage.$eval(
      "#w3tc-support-load",
      (e) => e.disabled,
    );
    expect(loadDisabled).equals(true);

    let wufooScriptCount = await adminPage.evaluate(() =>
      Array.from(document.querySelectorAll("script")).filter((s) =>
        (s.src || "").includes("wufoo.com/scripts/embed/form.js"),
      ).length,
    );
    expect(wufooScriptCount).equals(0);

    log.success("Support page rendered consent-gated shell without form embed");
  });

  it("Open support form stays disabled until consent, then injects the allowlisted script", async () => {
    await gotoSupportPage();

    await adminPage.waitForSelector("#w3tc-support-load", { timeout: 10000 });

    await adminPage.evaluate(() => {
      document.querySelector("#w3tc-support-load").click();
    });
    let stillDisabled = await adminPage.$eval(
      "#w3tc-support-load",
      (e) => e.disabled,
    );
    expect(stillDisabled).equals(true);
    let wufooBeforeConsent = await adminPage.evaluate(() =>
      Array.from(document.querySelectorAll("script")).filter((s) =>
        (s.src || "").includes("wufoo.com/scripts/embed/form.js"),
      ).length,
    );
    expect(wufooBeforeConsent).equals(0);

    await adminPage.evaluate(() => {
      let consent = document.querySelector("#w3tc-support-consent");
      consent.checked = true;
      consent.dispatchEvent(new Event("change", { bubbles: true }));
    });
    let enabledAfterConsent = await adminPage.$eval(
      "#w3tc-support-load",
      (e) => !e.disabled,
    );
    expect(enabledAfterConsent).equals(true);

    await adminPage.click("#w3tc-support-load");
    await adminPage.waitForFunction(
      () =>
        Array.from(document.querySelectorAll("script")).some((s) =>
          (s.src || "").includes("wufoo.com/scripts/embed/form.js"),
        ),
      { timeout: 15000 },
    );

    log.success("Support form script injects only after consent + click");
  });

  /**
   * Regression: load the page and assert NONE of the wp-config /
   * phpinfo marker strings appear.
   */
  it("Support page response carries no wp-config / phpinfo markers", async () => {
    await gotoSupportPage();

    let pageHtml = await adminPage.content();
    let leaks = LEAK_MARKERS.filter((m) => pageHtml.indexOf(m) !== -1);
    if (leaks.length > 0) {
      log.log("LEAKED markers: " + leaks.join(", "));
    }
    expect(leaks).is.empty;
    log.success("Support page does not echo wp-config / phpinfo markers");
  });
});
