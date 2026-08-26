/**
 * File: support-page-gates.js
 *
 * Node harness for W3tcSupport load gates. Invoked by PHPUnit.
 *
 * @since 2.10.6
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ALLOWED_SCRIPT = 'https://www.wufoo.com/scripts/embed/form.js';
const src = fs.readFileSync(
  path.join(__dirname, '../../pub/js/support.js'),
  'utf8'
);

const created = [];
const mount = { id: 'w3tc-support-form-mount' };
const fallback = { id: 'w3tc-support-fallback', hidden: true };
const document = {
  readyState: 'complete',
  getElementById: function (id) {
    if (id === 'w3tc-support-form-mount' || id === mount.id) {
      return mount;
    }
    if (id === 'w3tc-support-fallback') {
      return fallback;
    }
    return null;
  },
  addEventListener: function () {},
  getElementsByTagName: function () {
    return [{ parentNode: null }];
  },
  createElement: function (tag) {
    const el = { tagName: tag, async: false, src: '' };
    created.push(el);
    return el;
  },
  head: {
    appendChild: function () {},
  },
};

const window = { document: document };
document.defaultView = window;

vm.runInNewContext(src, { window: window, document: document });

const S = window.W3tcSupport;
if (!S) {
  throw new Error('W3tcSupport was not exported');
}

const good = {
  page: 'w3tc_support',
  form_hash: 'm5pom8z0qy59rm',
  form_script: ALLOWED_SCRIPT,
  postprocess: 'cb',
  home_url: 'example.test',
  field_name: '',
  field_value: '',
};

function assert(cond, msg) {
  if (!cond) {
    throw new Error(msg);
  }
}

assert(S.gatesPass(null, true) === false, 'null data must fail');
assert(S.gatesPass(good, false) === false, 'missing consent must fail');
assert(
  S.gatesPass(Object.assign({}, good, { page: 'w3tc_general' }), true) ===
    false,
  'wrong page must fail'
);
assert(
  S.gatesPass(Object.assign({}, good, { form_script: 'https://evil.example/x.js' }), true) ===
    false,
  'wrong script URL must fail'
);
assert(
  S.gatesPass(Object.assign({}, good, { form_hash: '' }), true) === false,
  'empty hash must fail'
);
assert(S.gatesPass(good, true) === true, 'all gates must pass');

assert(S.loadForm(good, false) === false, 'loadForm without consent must no-op');
assert(created.length === 0, 'no script tag without consent');

assert(S.loadForm(good, true) === true, 'loadForm with gates must start');
assert(created.length === 1, 'one script tag after successful load');
assert(created[0].src === ALLOWED_SCRIPT, 'script src must be the allowlisted URL');
assert(
  mount.id === 'wufoo-' + good.form_hash,
  'mount id must match wufoo-{formHash}'
);

assert(S.loadForm(good, true) === false, 'second loadForm must no-op while in flight');
assert(created.length === 1, 'still one script tag after repeat load');

created[0].onerror();
assert(S.loaded === false, 'failed load must clear the in-flight lock');
assert(fallback.hidden === false, 'failed load must reveal the fallback copy');
assert(S.loadForm(good, true) === true, 'retry after failure must start');
assert(created.length === 2, 'retry injects another script tag');
assert(
  fallback.hidden === true,
  'retry must hide the fallback before the next embed starts'
);

window.WufooForm = function () {};
window.WufooForm.prototype.initialize = function () {};
window.WufooForm.prototype.display = function () {};
created[1].onload();
assert(
  fallback.hidden === true,
  'successful embed must leave the fallback hidden'
);

assert(
  S.defaultValues(good).indexOf('field6=') === -1,
  'defaultValues must not include name field6'
);
assert(
  S.defaultValues(good).indexOf('field9=') === -1,
  'defaultValues must not include email field9'
);
assert(
  S.defaultValues(good).indexOf('field8=') !== -1,
  'defaultValues must include site field8'
);

process.exit(0);
