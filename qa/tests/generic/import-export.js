function requireRoot(p) {
  return require("../../" + p);
}

const expect = require("chai").expect;
const log = require("mocha-logger");
const fs = require("fs");
const path = require("path");
const util = require("util");

fs.writeFileAsync = util.promisify(fs.writeFile);

const env = requireRoot("lib/environment");
const sys = requireRoot("lib/sys");
const w3tc = requireRoot("lib/w3tc");

/**environments: environments('blog') */

describe("import/export config", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("set options to something non-default", async () => {
    await w3tc.setOptions(adminPage, "w3tc_general", {
      pgcache__enabled: true,
    });
  });

  it("export config", async () => {
    await adminPage.goto(env.networkAdminUrl + "admin.php?page=w3tc_general");
    log.log("Downloading (exporting) config file...");

    let exportBody = await adminPage.evaluate(async () => {
      const btn = document.querySelector('input[name="w3tc_config_export"]');
      const form = btn.closest("form");
      const fd = new FormData(form);
      fd.set("w3tc_config_export", btn.value);
      const nonce =
        btn.getAttribute("data-w3tc-nonce") ||
        (typeof w3tc_admin_nonces !== "undefined" &&
          w3tc_admin_nonces.w3tc_config_export) ||
        "";
      if (nonce) {
        fd.set("_wpnonce", nonce);
      }
      const r = await fetch(form.action, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });
      if (!r.ok) {
        throw new Error("export HTTP " + r.status);
      }
      return await r.text();
    });

    expect(exportBody.trim().charAt(0)).equals("{");
    let exported = JSON.parse(exportBody);
    expect(exported["pgcache.enabled"]).true;

    const exportPath = path.join(env.wpPath, "export-data.json");
    log.log("writing file " + exportPath);
    await fs.writeFileAsync(exportPath, exportBody, "utf8");
  });

  it("import", async () => {
    //change settings again
    await w3tc.setOptions(adminPage, "w3tc_general", {
      pgcache__enabled: false,
    });

    // checking if we disabled pgcache
    await adminPage.goto(env.networkAdminUrl + "admin.php?page=w3tc_general");
    let checked = await adminPage.$eval("#pgcache__enabled", (e) =>
      e.getAttribute("checked"),
    );
    expect(checked).null;

    const exportPath = path.join(env.wpPath, "export-data.json");

    // uploading our exported before config
    log.log("importing file " + exportPath);
    let fileInput = await adminPage.$("input[name=config_file]");
    await fileInput.uploadFile(exportPath);

    await Promise.all([
      adminPage.click('input[name="w3tc_config_import"]'),
      adminPage.waitForNavigation({ timeout: 300000 }),
    ]);

    await w3tc.expectW3tcErrors(adminPage, false);
    expect(await w3tc.getConfigOption("pgcache.enabled", "boolean")).true;

    //checking if all settings was exported
    await adminPage.goto(env.networkAdminUrl + "admin.php?page=w3tc_general", {
      waitUntil: "domcontentloaded",
    });
    let checked2 = await adminPage.$eval("#pgcache__enabled", (e) =>
      e.getAttribute("checked"),
    );
    expect(checked2).equals("checked");
  });

  it("import skips engine keys owned by the General page", async () => {
    let engineBefore = await w3tc.getConfigOption("pgcache.engine");
    expect(engineBefore).not.empty;

    let otherEngine = engineBefore === "file" ? "file_generic" : "file";
    await w3tc.setOptionInternal(adminPage, "pgcache.engine", otherEngine);
    expect(await w3tc.getConfigOption("pgcache.engine")).equals(otherEngine);

    const exportPath = path.join(env.wpPath, "export-data.json");
    await adminPage.goto(env.networkAdminUrl + "admin.php?page=w3tc_general");
    let fileInput = await adminPage.$("input[name=config_file]");
    await fileInput.uploadFile(exportPath);
    await Promise.all([
      adminPage.click('input[name="w3tc_config_import"]'),
      adminPage.waitForNavigation({ timeout: 300000 }),
    ]);

    await w3tc.expectW3tcErrors(adminPage, false);
    expect(await w3tc.getConfigOption("pgcache.enabled", "boolean")).true;
    expect(await w3tc.getConfigOption("pgcache.engine")).equals(otherEngine);
    log.success("import restored pgcache.enabled and left pgcache.engine alone");
  });
});
