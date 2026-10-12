'use strict';

// Exercise the actual H5P wrapper, with no DOM, browser, npm or site requests.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../library/js/jquery.js'), 'utf8');
const start = source.indexOf('var H5P = window.H5P');
assert.ok(start > 0, 'H5P wrapper must exist');
const calls = [];
const warnings = [];
const cmsQuery = { name: 'CMS jQuery' };
const namespace = { existing: true };
const h5pQuery = {
  fn: {
    load() { return this; },
    on(...args) { calls.push({ receiver: this, args }); return this; }
  },
  noConflict(deep) {
    calls.push({ deep });
    context.window.jQuery = cmsQuery;
    context.window.$ = cmsQuery;
    return this;
  }
};
const context = { window: { H5P: namespace, jQuery: h5pQuery, $: h5pQuery },
  jQuery: h5pQuery, console: { warn(message) { warnings.push(message); } } };
vm.runInNewContext(source.slice(start), context, { filename: 'H5P jQuery wrapper' });
assert.equal(calls[0].deep, true, 'Deep noConflict requested');
assert.equal(context.window.jQuery, cmsQuery, 'CMS jQuery restored');
assert.equal(context.window.$, cmsQuery, 'CMS $ restored');
assert.equal(context.H5P, namespace, 'Existing H5P namespace retained');
assert.equal(namespace.jQuery, h5pQuery, 'H5P uses its isolated jQuery');
const iframe = { name: 'editor iframe' };
const handler = () => {};
assert.equal(h5pQuery.fn.load.call(iframe, handler), iframe, 'Legacy load(fn) remains chainable');
assert.equal(calls[1].receiver, iframe, 'Legacy handler receives its element');
assert.deepEqual(calls[1].args, ['load', handler], 'Legacy handler forwarded to on(load, fn)');
assert.equal(warnings.length, 1, 'Legacy handler reports deprecation');
console.log('PASS: 10 assertions; isolated H5P wrapper and legacy editor load(fn) contract.');

// Characterize a pinned upstream defect; this is NOT a successful AJAX test.
// The wrapper saves jQuery.load instead of jQuery.fn.load. Leave the exact
// vendor bundle unchanged and report the URL-form limitation for Phase 3.
assert.throws(() => h5pQuery.fn.load.call(iframe, '/test'),
  error => error.name === 'TypeError');
console.log('KNOWN LIMITATION: pinned upstream load(url) wrapper throws TypeError; no browser qualification.');
