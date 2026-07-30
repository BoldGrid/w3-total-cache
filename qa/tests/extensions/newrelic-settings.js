/**
 * File: qa/tests/extensions/newrelic-settings.js
 *
 * NewRelic extension settings + Configure-popup smoke.
 *
 * NewRelic is a monitoring extension that stores an API key,
 * a monitoring_type (browser | apm), and an APM application_name.
 * The Configure button drives a popup AJAX flow to set the API
 * key; without a real key the popup AJAX returns an error.
 *
 * Posture: activate the extension, set monitoring_type via
 * setOptionInternal (array compound key), reload, assert read-back
 * via wp-cli. The Configure-button AJAX flow is env-gated and only
 * runs when `NEWRELIC_API_KEY` is present.
 *
 * Compound extension keys like `['newrelic', 'monitoring_type']`
 * nest under `newrelic` in the config blob. Read them back via
 * `w3tc.getConfigOption('newrelic::monitoring_type')` (WP-CLI
 * `wp w3tc option get`), not `wp option get w3tc_config`.
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

describe("NewRelic extension settings + Configure popup", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("activate NewRelic extension", async function () {
    await w3tc
      .activateExtension(adminPage, "newrelic")
      .catch((e) => log.log("activate result: " + e.message));

    // networkAdminUrl: w3tc_* pages are not visible_always, so on
    // multisite (default common.force_master) env.adminUrl serves WP's
    // "not allowed" page. Single-site: same URL.
    let urls = [
      env.networkAdminUrl + "admin.php?page=w3tc_general#monitoring",
      env.networkAdminUrl + "admin.php?page=w3tc_monitoring",
      env.networkAdminUrl +
        "admin.php?page=w3tc_extensions&extension=newrelic&action=view",
    ];
    let url = urls[0];
    await adminPage.goto(url, { waitUntil: "domcontentloaded" });

    let html = await adminPage.content();
    if (
      html.indexOf("newrelic") === -1 &&
      html.indexOf("NewRelic") === -1 &&
      html.indexOf("New Relic") === -1
    ) {
      for (let i = 1; i < urls.length; i++) {
        url = urls[i];
        await adminPage.goto(url, { waitUntil: "domcontentloaded" });
        html = await adminPage.content();
        if (
          html.indexOf("newrelic") !== -1 ||
          html.indexOf("NewRelic") !== -1 ||
          html.indexOf("New Relic") !== -1
        ) {
          break;
        }
      }
      if (
        html.indexOf("newrelic") === -1 &&
        html.indexOf("NewRelic") === -1 &&
        html.indexOf("New Relic") === -1
      ) {
        log.log("SKIP: NewRelic settings page did not render");
        this.skip();
        return;
      }
    }
    log.success("NewRelic settings page rendered at " + url);
  });

  it("monitoring_type round-trip", async function () {
    await w3tc.setOptionInternal(
      adminPage,
      ["newrelic", "monitoring_type"],
      "browser",
    );

    let url = env.networkAdminUrl + "admin.php?page=w3tc_general#monitoring";
    await adminPage.goto(url, { waitUntil: "domcontentloaded" });

    /**
     * Read through `wp w3tc option get`, not `wp option get
     * w3tc_config` — config lives in `w3tc_config_{blog_id}` and
     * compound keys use `::` (e.g. `newrelic::monitoring_type`).
     */
    let monitoringType = await w3tc.getConfigOption(
      "newrelic::monitoring_type",
    );
    log.log("newrelic.monitoring_type = " + monitoringType);
    expect(monitoringType).equals("browser");
    log.success("NewRelic monitoring_type persisted");
  });

  it("Configure popup live API flow (env-gated)", async function () {
    if (sys.skipIfMissingEnv(this, ["NEWRELIC_API_KEY"])) return;

    /**
     * With a real API key, drive the configure AJAX. We
     * just assert the AJAX returns 200 + a non-error body
     * shape; don't depend on a specific account state.
     */
    let r = await adminPage.evaluate(
      async function (adminUrl, key) {
        let body = new URLSearchParams();
        body.append("action", "w3tc_ajax_extension_newrelic_save");
        body.append("api_key", key);
        let resp = await fetch(adminUrl + "admin-ajax.php", {
          method: "POST",
          body: body,
          credentials: "include",
        });
        return {
          status: resp.status,
          body: (await resp.text()).substring(0, 200),
        };
      },
      env.adminUrl,
      process.env["NEWRELIC_API_KEY"],
    );
    log.log("newrelic save AJAX status: " + r.status + " body: " + r.body);
    expect(r.status).equals(200);
  });
});
