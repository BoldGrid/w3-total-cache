/**
 * File: forums-api-gates.js
 *
 * Node harness for W3tcForumsApi.topicsFromResponse. Invoked by PHPUnit.
 *
 * @since 2.10.6
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const src = fs.readFileSync(
  path.join(__dirname, "../../pub/js/forums-api.js"),
  "utf8"
);

const sandbox = { window: {}, console: console };
sandbox.window = sandbox;
vm.runInNewContext(src, sandbox);

const A = sandbox.W3tcForumsApi;
if (!A) {
  throw new Error("W3tcForumsApi was not exported");
}

function assert(cond, msg) {
  if (!cond) {
    throw new Error(msg);
  }
}

const valid = {
  topics: [
    {
      title: "Page Cache",
      link: "https://www.boldgrid.com/support/page-cache/",
    },
  ],
};
const parsedValid = A.topicsFromResponse(valid);
assert(parsedValid.ok === true, "normalized topics must succeed");
assert(parsedValid.topics.length === 1, "one topic");
assert(parsedValid.topics[0].title === "Page Cache", "title preserved");
assert(
  parsedValid.topics[0].link === "https://www.boldgrid.com/support/page-cache/",
  "link preserved"
);

const empty = A.topicsFromResponse({ topics: [] });
assert(empty.ok === true && empty.topics.length === 0, "empty topics is ok");

const malformed = A.topicsFromResponse({ topics: [], error: "malformed" });
assert(malformed.ok === false, "error key must fail");

const transport = A.topicsFromResponse({
  errors: { http_request_failed: ["cURL error 28: https://evil.example"] },
  cookies: [{ name: "sid", value: "secret" }],
  headers: { "set-cookie": "sid=secret" },
});
assert(transport.ok === false, "legacy WP_Error envelope must fail");
assert(
  JSON.stringify(transport).indexOf("evil.example") === -1,
  "transport details must not leak"
);
assert(
  JSON.stringify(transport).indexOf("secret") === -1,
  "cookies/headers must not leak"
);

const legacyBody = A.topicsFromResponse({
  headers: { server: "nginx" },
  body: JSON.stringify([
    { title: "Minify", link: "https://www.boldgrid.com/support/minify/" },
    { title: "Bad", link: "javascript:alert(1)" },
  ]),
  cookies: [],
  response: { code: 200, message: "OK" },
});
assert(legacyBody.ok === true, "legacy body JSON must succeed");
assert(legacyBody.topics.length === 1, "javascript: links dropped");
assert(
  JSON.stringify(legacyBody).indexOf("nginx") === -1,
  "legacy headers must not leak"
);
assert(
  JSON.stringify(legacyBody).indexOf("javascript:") === -1,
  "javascript: links must not leak"
);

const relativeDropped = A.topicsFromResponse({
  topics: [
    {
      title: "Keep",
      link: "https://www.boldgrid.com/support/ok/",
    },
    { title: "Protocol relative", link: "//evil.example/x" },
    { title: "Root relative", link: "/support/injected" },
  ],
});
assert(relativeDropped.ok === true, "relative-link payload must succeed");
assert(relativeDropped.topics.length === 1, "non-absolute http(s) links dropped");
assert(
  relativeDropped.topics[0].link === "https://www.boldgrid.com/support/ok/",
  "absolute https kept"
);
assert(
  JSON.stringify(relativeDropped).indexOf("evil.example") === -1,
  "protocol-relative host must not leak"
);
assert(
  JSON.stringify(relativeDropped).indexOf("/support/injected") === -1,
  "root-relative path must not leak"
);
assert(
  A.sanitizeTopic({ title: "Nope", link: "//evil.example/x" }) === null,
  "sanitizeTopic drops protocol-relative"
);
assert(
  A.sanitizeTopic({ title: "Nope", link: "/support/injected" }) === null,
  "sanitizeTopic drops root-relative"
);

const emptyBody = A.topicsFromResponse({ body: "  " });
assert(emptyBody.ok === true && emptyBody.topics.length === 0, "empty body");

const badJson = A.topicsFromResponse({ body: "{not json" });
assert(badJson.ok === false, "malformed body JSON must fail");

const wrappedData = A.topicsFromResponse({
  data: [
    {
      title: "Object Cache",
      link: "https://www.boldgrid.com/support/object/",
    },
  ],
});
assert(wrappedData.ok === true, "bare data list must succeed");
assert(wrappedData.topics.length === 1, "bare data list topic count");
assert(wrappedData.topics[0].title === "Object Cache", "bare data list title");

const topLevel = A.topicsFromResponse([]);
assert(topLevel.ok === true && topLevel.topics.length === 0, "top-level []");

console.log("forums-api-gates: ok");
