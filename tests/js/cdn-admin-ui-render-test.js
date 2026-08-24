#!/usr/bin/env node
/**
 * File: tests/js/cdn-admin-ui-render-test.js
 *
 * Node regression for CDN admin text/URL sinks. Markup in server
 * fields must stay in text nodes; javascript: hrefs must be dropped.
 *
 * @package W3TC
 * @since   2.10.6
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");
const assert = require("assert");

function createDocument() {
  function createNode(tag, nodeType) {
    return {
      tagName: tag,
      nodeType: nodeType,
      className: "",
      textContent: "",
      data: "",
      attributes: {},
      childNodes: [],
      appendChild: function (child) {
        this.childNodes.push(child);
        return child;
      },
      setAttribute: function (name, value) {
        this.attributes[name] = value;
      },
    };
  }

  return {
    createElement: function (tag) {
      return createNode(String(tag).toUpperCase(), 1);
    },
    createTextNode: function (text) {
      const node = createNode("", 3);
      node.data = String(text);
      node.textContent = String(text);
      return node;
    },
  };
}

const root = { document: createDocument() };
const source = fs.readFileSync(
  path.join(__dirname, "../../pub/js/cdn-admin-ui.js"),
  "utf8",
);
vm.runInNewContext(source, root);

const payload = '<img src=x onerror=alert(1)>';
const row = root.w3tc_cdn_log_entry(payload, 1, "<script>alert(2)</script>");

assert.strictEqual(row.className, "log-success");
assert.strictEqual(row.childNodes[0].nodeType, 3);
assert.strictEqual(row.childNodes[0].data, payload + " ");
assert.strictEqual(row.childNodes[1].tagName, "STRONG");
assert.strictEqual(row.childNodes[1].textContent, "<script>alert(2)</script>");
assert.strictEqual(typeof row.childNodes[1].innerHTML, "undefined");

const ok = root.document.createElement("a");
root.w3tc_cdn_set_url(ok, "/wp-admin/admin.php?page=w3tc_support");
assert.strictEqual(ok.attributes.href, "/wp-admin/admin.php?page=w3tc_support");

const bad = root.document.createElement("a");
root.w3tc_cdn_set_url(bad, "javascript:alert(1)");
assert.strictEqual(bad.attributes.href, undefined);

const dataUrl = root.document.createElement("a");
root.w3tc_cdn_set_url(dataUrl, "data:text/html,<script>alert(1)</script>");
assert.strictEqual(dataUrl.attributes.href, undefined);

console.log("cdn-admin-ui-render-test: ok");
