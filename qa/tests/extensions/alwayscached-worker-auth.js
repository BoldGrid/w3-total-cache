/**
 * File: qa/tests/extensions/alwayscached-worker-auth.js
 *
 * sec-missing-auth-public-endpoints regression — AlwaysCached
 * queue-worker HTTP trigger.
 *
 * Before the fix, `Extension_AlwaysCached_Plugin::init()` ran
 * `Extension_AlwaysCached_Worker::run()` on any request carrying
 * `?w3tc_alwayscached`. That:
 *  1. spent up to 60s of CPU + I/O per request (DoS),
 *  2. issued server-side HTTP fetches for every URL on the
 *     queue (SSRF amplifier if the attacker can enqueue an
 *     internal URL through any flush-on-publish hook),
 *  3. mutated the `w3tc_alwayscached_worker_timestamp` option
 *     from unauthenticated context.
 *
 * After the fix, `authorize_worker_trigger_or_die()` requires:
 *  - admin in a browser session (`current_user_can('manage_options')`),
 *    OR
 *  - a matching pre-shared `W3TC_WORKER_SECRET` constant
 *    presented via `Authorization: Bearer <secret>`.
 *
 * Posture:
 *  - Anon GET → 403 with the literal body `Forbidden` and no
 *    WP HTML markup.
 *  - Anon GET with bogus Bearer → same 403.
 *  - Anon GET with the correct Bearer (after we install a
 *    fixture wp-config constant) → 200, body shows queue
 *    processing output.
 *  - Admin GET → 200, queue processing output (positive
 *    control via the existing admin session).
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
  return require("../../" + p);
}

const expect = require("chai").expect;
const log = require("mocha-logger");
const http = require("http");
const https = require("https");
const { URL } = require("url");
const util = require("util");
const exec = util.promisify(require("child_process").exec);

const env = requireRoot("lib/environment");
const sys = requireRoot("lib/sys");
const w3tc = requireRoot("lib/w3tc");

/**environments: environments('blog') */

const KNOWN_SECRET = "qa-alwayscached-worker-secret-aaaaaaaaaaaaaaaaaa";

function httpRequest(targetUrl, headers) {
  return new Promise((resolve, reject) => {
    let u = new URL(targetUrl);
    let mod = u.protocol === "http:" ? http : https;
    let req = mod.request(
      {
        method: "GET",
        hostname: u.hostname,
        port: u.port || (u.protocol === "http:" ? 80 : 443),
        path: u.pathname + u.search,
        headers: Object.assign({ Connection: "close" }, headers || {}),
        rejectUnauthorized: false,
      },
      (response) => {
        let data = "";
        response.on("data", (c) => (data += c));
        response.on("end", () =>
          resolve({
            statusCode: response.statusCode,
            headers: response.headers,
            body: data,
          }),
        );
      },
    );
    req.on("error", (e) => reject(e));
    req.end();
  });
}

describe("AlwaysCached worker HTTP-trigger auth gate", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  let triggerUrl =
    env.homeUrl +
    (env.homeUrl.indexOf("?") !== -1 ? "&" : "?") +
    "w3tc_alwayscached=1";

  /**
   * Pre-req: activate the extension. On free builds the
   * extension may not be available; skip the whole suite.
   */
  before(async function () {
    await w3tc.setOptions(adminPage, "w3tc_general", {
      pgcache__enabled: true,
    });
    await sys.afterRulesChange();

    await w3tc
      .activateExtension(adminPage, "alwayscached")
      .catch((e) => log.log("activate result: " + e.message));

    let r = await exec(
      "sudo -u www-data wp option get w3tc_config --format=json --path=" +
        env.wpPath +
        ' 2>/dev/null || echo "{}"',
    );
    let blob = {};
    try {
      blob = JSON.parse(r.stdout);
    } catch (e) {}
    let active = blob["extensions.active"] || {};
    if (typeof active.alwayscached === "undefined") {
      log.log(
        "SKIP: alwayscached extension not in extensions.active (free build?)",
      );
      this.skip();
    }
    log.success("AlwaysCached extension active");
  });

  it("anon GET to ?w3tc_alwayscached returns 403 Forbidden", async () => {
    let r = await httpRequest(triggerUrl);
    log.log(
      "anon ?w3tc_alwayscached -> " +
        r.statusCode +
        " body[0..120]=" +
        JSON.stringify(r.body.substring(0, 120)),
    );
    expect(r.statusCode).equals(403);
    expect(r.body).contains("Forbidden");
    /**
     * No WP page body should leak — the gate fires before
     * the worker emits any of its `<div>` processing markup.
     */
    expect(r.body).not.contains("<html");
    expect(r.body).not.contains("Processing queue");
    log.success("anon trigger correctly rejected");
  });

  it("anon GET with bogus Bearer token returns 403", async () => {
    let r = await httpRequest(triggerUrl, {
      Authorization: "Bearer not-the-real-secret-" + Date.now(),
    });
    log.log("bogus-bearer -> " + r.statusCode);
    expect(r.statusCode).equals(403);
    expect(r.body).contains("Forbidden");
    log.success("bogus Bearer correctly rejected");
  });

  it("anon GET with correct W3TC_WORKER_SECRET is accepted", async function () {
    /**
     * Install the constant in wp-config.php. We use a fixture
     * helper rather than the existing addWpConfigConstant if
     * available; otherwise inline the sed.
     */
    let wpConfigPath = env.wpPath + "wp-config.php";
    let probeMarker = "/* qa-alwayscached-worker-secret-marker */";

    /**
     * Idempotent insertion: only inject if the marker is
     * absent. Splice the define before the `That's all,
     * stop editing!` comment.
     */
    let cmd =
      "sudo grep -q '" +
      probeMarker +
      "' " +
      wpConfigPath +
      " || " +
      "sudo sed -i \"/That's all, stop editing/i define( 'W3TC_WORKER_SECRET', '" +
      KNOWN_SECRET +
      "' ); " +
      probeMarker +
      '" ' +
      wpConfigPath;
    try {
      await exec(cmd);
    } catch (e) {
      log.log(
        "wp-config patch error (may be OK on read-only matrix): " + e.message,
      );
      this.skip();
    }

    /**
     * Re-load the page so the new constant takes effect.
     * (The trigger URL is the front-end, so any next request
     * picks up the new wp-config define.)
     */
    let r = await httpRequest(triggerUrl, {
      Authorization: "Bearer " + KNOWN_SECRET,
    });
    log.log(
      "correct-bearer -> " +
        r.statusCode +
        " body[0..120]=" +
        JSON.stringify(r.body.substring(0, 120)),
    );
    expect(r.statusCode).equals(200);
    /**
     * Worker output is the body of the queue processor; it
     * contains either "Processing queue" or "Queue is empty"
     * per Extension_AlwaysCached_Worker::run().
     */
    let isWorkerOutput =
      r.body.indexOf("Processing queue") !== -1 ||
      r.body.indexOf("Queue is empty") !== -1 ||
      r.body.indexOf("Queue worker time slot exhaused") !== -1;
    expect(isWorkerOutput).equals(true);
    log.success("correct W3TC_WORKER_SECRET admitted; worker ran");

    /**
     * Clean up: remove the constant. Leave the wp-config in
     * the same state we found it so other specs aren't
     * affected.
     */
    let cleanupCmd = "sudo sed -i '/" + probeMarker + "/d' " + wpConfigPath;
    await exec(cleanupCmd).catch(() => {});
  });

  it("admin browser session can trigger the worker", async () => {
    /**
     * `adminPage` carries the admin session cookies from
     * beforeDefault. Navigate to the trigger URL and assert
     * we get worker output, not a 403.
     */
    let resp = await adminPage.goto(triggerUrl, {
      waitUntil: "domcontentloaded",
    });
    let body = await adminPage.content();
    log.log(
      "admin GET status: " +
        resp.status() +
        " body[0..200]=" +
        JSON.stringify(body.substring(0, 200)),
    );
    expect(resp.status()).equals(200);
    let isWorkerOutput =
      body.indexOf("Processing queue") !== -1 ||
      body.indexOf("Queue is empty") !== -1 ||
      body.indexOf("Queue worker time slot exhaused") !== -1;
    expect(isWorkerOutput).equals(true);
    log.success("admin session admitted; worker ran");
  });
});
