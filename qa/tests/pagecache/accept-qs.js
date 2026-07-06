function requireRoot(p) {
  return require("../../" + p);
}

const expect = require("chai").expect;
const log = require("mocha-logger");

const diskEnhanced = requireRoot("lib/disk-enhanced");
const env = requireRoot("lib/environment");
const sys = requireRoot("lib/sys");
const w3tc = requireRoot("lib/w3tc");

/**environments: multiply(environments('blog'), environments('pagecache')) */

describe("", function () {
  this.timeout(sys.suiteTimeout);
  before(sys.beforeDefault);
  after(sys.after);

  it("set options", async () => {
    await w3tc.setOptions(adminPage, "w3tc_general", {
      pgcache__enabled: true,
      browsercache__enabled: false,
      pgcache__engine: env.cacheEngineLabel,
    });
    await w3tc.setOptions(adminPage, "w3tc_browsercache", {
      browsercache__html__etag: false,
      browsercache__html__last_modified: false,
    });

    await sys.afterRulesChange();
  });

  it("check cached", async () => {
    // going to homepage to create cache
    await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
    await checkCached(env.homeUrl, true);
  });

  it("set options - qs", async () => {
    await w3tc.setOptions(adminPage, "w3tc_pgcache", {
      pgcache_accept_qs: "my_query",
    });

    await sys.afterRulesChange();
  });

  it("check", async () => {
    await checkCached(env.homeUrl, true);
    await checkCached(env.homeUrl + "?min=1", false);
    await checkCached(env.homeUrl + "?my_query=2", true);
    await checkCached(env.homeUrl + "?my_query=2&min=3", false);
    await checkCached(env.homeUrl + "?min=4&my_query=5", false);
  });
});

async function checkCached(url, isCached) {
  if (isCached && env.cacheEngineLabel === "file_generic") {
    await sys.clearDiskEnhancedHost();
  }

  log.log("opening " + url);
  await w3tc.gotoWithPotentialW3TCRepeat(page, url);
  await w3tc.gotoWithPotentialW3TCRepeat(page, url);

  if (isCached && env.cacheEngineLabel === "file_generic") {
    await diskEnhanced.warmCache(url);
  }

  let content = await page.content();
  if (isCached) {
    expect(content).matches(/Page Caching using ([a-zA-Z: ]+)[\r\n]/);
  } else {
    expect(content).matches(
      /Page Caching using ([a-zA-Z: ]+)\([^\)]+\)\s*[\r\n]/,
    );
  }

  log.log("opened " + url);

  let probe = null;
  if (isCached && env.cacheEngineLabel === "file_generic") {
    probe = await diskEnhanced.waitForFile(url, 10000);
  }

  const httpResponse = await sys.httpGet(url, {
    headers: {
      "User-Agent": sys.qaPageCacheUserAgent,
    },
    followRedirects: true,
  });

  content = await page.content();
  if (isCached) {
    expect(content).matches(/Page Caching using ([a-zA-Z: ]+)[\r\n]/);
  } else {
    expect(content).matches(
      /Page Caching using ([a-zA-Z: ]+)\([^\)]+\)\s*[\r\n]/,
    );
  }

  if (env.cacheEngineLabel == "file_generic") {
    log.log("make sure not passed to PHP fallback and handled by rules");
    let headers = httpResponse.headers;

    console.log(headers);
    let phpResponse = headers["w3tc_php"] != null;

    if (env.boxName.indexOf("php55") >= 0) {
      log.error(
        "php handled here in apache 2.4.7 - skip it since its apache bug",
      );
    } else {
      if (isCached) {
        if (!probe) {
          probe = await diskEnhanced.probe(url);
        }
        if (probe && probe.variants) {
          const plainKey = probe.page_key;
          expect(
            probe.variants[plainKey],
            "enhanced cache file missing for " +
              plainKey +
              " at " +
              probe.enhanced_path +
              "; dir listing: " +
              JSON.stringify(probe.enhanced_dir_list) +
              (probe.stale_old_only
                ? "; stale_old_only age=" + probe.old_age_sec + "s"
                : ""),
          ).is.true;
          if (env.boxName.indexOf("apache") >= 0) {
            // W3TC_URI_PATH_SLASH is written to .htaccess by the apache rules generator only.
            expect(
              probe.htaccess_uri,
              "stale .htaccess missing W3TC_URI_PATH_SLASH rules",
            ).is.true;
          }
        }
        if (phpResponse) {
          log.error("PHP handled request; probe: " + JSON.stringify(probe));
        }
        expect(phpResponse).is.false;
      } else {
        expect(phpResponse).is.true;
      }
    }
  }
  log.success("correctly " + (isCached ? "cached" : "not cached"));
}
