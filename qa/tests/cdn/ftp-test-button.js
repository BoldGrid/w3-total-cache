function requireRoot(p) {
  return require("../../" + p);
}

const expect = require("chai").expect;
const log = require("mocha-logger");

const dom = requireRoot("lib/dom");
const env = requireRoot("lib/environment");
const sys = requireRoot("lib/sys");
const w3tc = requireRoot("lib/w3tc");
const wp = requireRoot("lib/wp");

/**environments:environments('blog') */

describe("", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("test", async () => {
    //
    // set options
    //
    await w3tc.setOptions(adminPage, "w3tc_general", {
      cdn__enabled: true,
      browsercache__enabled: false,
      cdn__engine: "ftp",
      minify__enabled: true,
      minify__engine: "file",
    });

    await w3tc.setOptions(adminPage, "w3tc_cdn", {
      cdn__includes__enable: false,
      cdn__theme__enable: false,
      cdn_ftp_host: "wp.sandbox",
      cdn_ftp_user: "www-data",
      cdn_ftp_pass: "sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU",
      cdn_ftp_path: env.cdnFtpExportDir,
      cdn_cnames_0: env.cdnFtpExportHostPort,
    });

    await sys.afterRulesChange();

    await adminPage.goto(env.networkAdminUrl + "admin.php?page=w3tc_cdn");
    let text = await clickCdnTestAndWait();
    expect(text).equals("Test passed");

    /**
     * Test uses saved settings, not the unsaved form. Point the
     * host field at loopback without saving; Test must still pass
     * against the stored RFC1918 sandbox host.
     */
    await adminPage.$eval("#cdn_ftp_host", (e) => {
      e.value = "127.0.0.1";
    });
    let textAfterUnsavedHost = await clickCdnTestAndWait();
    expect(textAfterUnsavedHost).equals("Test passed");
    expect(textAfterUnsavedHost).not.contains("loopback");
  });
});

async function clickCdnTestAndWait() {
  await adminPage.evaluate(() => document.querySelector("#cdn_test").click());
  await adminPage.waitForFunction(() => {
    let el = document.querySelector("#cdn_test_status");
    return el && el.textContent === "Testing...";
  });
  await adminPage.waitForFunction(() => {
    let el = document.querySelector("#cdn_test_status");
    return el && el.textContent && el.textContent !== "Testing...";
  });

  return adminPage.$eval("#cdn_test_status", (e) => e.textContent);
}
