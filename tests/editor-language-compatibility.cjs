'use strict';

// Run actual retained/target language functions without a browser or site.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const crypto = require('crypto');
const assert = require('assert');
const root = path.resolve(__dirname, '..');
const option = process.argv.find(value => value.startsWith('--editor-root='));
const editorRoot = option ? option.slice('--editor-root='.length) : path.join(root, 'modules/h5peditor/h5peditor');
const formPath = path.join(editorRoot, 'scripts/h5peditor-form.js');
const formBytes = fs.readFileSync(formPath);
{
  const lock = JSON.parse(fs.readFileSync(path.join(root, 'docs/h5p-api-1.28/upstream-sources.lock.json')));
  const pin = lock.editor.files.find(file => file.path === 'scripts/h5peditor-form.js');
  assert.strictEqual(crypto.createHash('sha1').update(Buffer.from('blob ' + formBytes.length + '\0')).update(formBytes).digest('hex'), pin.gitBlob);
}
const adapterPath = path.join(root, 'modules/h5peditor/scripts/h5peditor-backdrop-compat.js');
const adapter = require(adapterPath);
let checks = 0;
function same(actual, expected) { checks++; assert.deepStrictEqual(actual, expected); }
function yes(value) { checks++; assert(value); }

function extractLanguageFunction(source) {
  const marker = 'ns.Form.prototype.setSubContentDefaultLanguage = ';
  const markerAt = source.indexOf(marker);
  assert(markerAt >= 0);
  const start = source.indexOf('function', markerAt);
  const brace = source.indexOf('{', start);
  let depth = 0, quote = null, escaped = false;
  for (let i = brace; i < source.length; i++) {
    const character = source[i];
    if (quote) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (character === '"' || character === "'") { quote = character; continue; }
    if (character === '{') depth++;
    if (character === '}' && --depth === 0) {
      return vm.runInNewContext('(' + source.slice(start, i + 1) + ')');
    }
  }
  throw new Error('Language function is incomplete');
}

function makeEditor() {
  function Form() {}
  Form.prototype.setSubContentDefaultLanguage = extractLanguageFunction(formBytes.toString());
  return { Form };
}
const editor = makeEditor();
const original = editor.Form.prototype.setSubContentDefaultLanguage;
const baseline = [{ metadata: { defaultLanguage: 'en' } }];
new editor.Form().setSubContentDefaultLanguage(baseline, 'fr');
same(baseline[0].metadata.defaultLanguage, 'en');

// Merely evaluating the browser asset does not install it into an editor.
const active = makeEditor();
const activeMethod = active.Form.prototype.setSubContentDefaultLanguage;
const browserWindow = { H5PEditor: active };
vm.runInNewContext(fs.readFileSync(adapterPath, 'utf8'), { window: browserWindow });
same(active.Form.prototype.setSubContentDefaultLanguage, activeMethod);
yes(typeof browserWindow.H5PBackdropEditorCompatibility.installNestedLanguageUpdates === 'function');
same(adapter.installNestedLanguageUpdates(editor), true);
const installed = editor.Form.prototype.setSubContentDefaultLanguage;
same(adapter.installNestedLanguageUpdates(editor), false);
same(editor.Form.prototype.setSubContentDefaultLanguage, installed);
yes(installed !== original);

const data = {
  metadata: { defaultLanguage: 'en', title: 'Français', license: 'CC BY' },
  params: [{
    library: 'H5P.TrueFalse 1.8', metadata: { defaultLanguage: 'en', title: 'Question' },
    params: { answer: false, score: 0, text: '<p>Été *answer*</p>',
      children: [[{ metadata: { defaultLanguage: 'en' }, value: '' }], null, 0] }
  }],
  language: 'preserve ordinary parameter', enabled: false
};
const expected = JSON.parse(JSON.stringify(data));
expected.metadata.defaultLanguage = 'fr';
expected.params[0].metadata.defaultLanguage = 'fr';
expected.params[0].params.children[0][0].metadata.defaultLanguage = 'fr';
const form = new editor.Form();
same(form.setSubContentDefaultLanguage(data, 'fr'), data);
same(data, expected);
const arrays = [[{ metadata: { defaultLanguage: 'fr' }, name: 'retained' }], []];
same(form.setSubContentDefaultLanguage(arrays, 'en'), arrays);
same(arrays[0][0].metadata.defaultLanguage, 'en');
same(arrays[0][0].name, 'retained');
same(arrays[1], []);
const sparse = new Array(3);
sparse[2] = { metadata: { defaultLanguage: 'en' } };
same(form.setSubContentDefaultLanguage(sparse, 'fr'), sparse);
same(sparse[2].metadata.defaultLanguage, 'fr');
yes(!(0 in sparse) && !(1 in sparse));
for (const scalar of [null, undefined, false, 0, '', 'language text', 12]) {
  same(form.setSubContentDefaultLanguage(scalar, 'fr'), scalar);
}
same(form.setSubContentDefaultLanguage({ plain: 42 }, 'fr'), { plain: 42 });
for (const invalid of [null, {}, { Form: function () {} }]) {
  checks++;
  assert.throws(() => adapter.installNestedLanguageUpdates(invalid), /Load the H5P editor form/);
}

// Explicit Step 3 iframe loading and installation are required.
for (const file of ['modules/h5peditor/h5peditor.module', 'modules/h5peditor/scripts/application.js']) {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  yes(file.endsWith('.module') ? source.includes('h5peditor-backdrop-compat.js') : source.includes('installNestedLanguageUpdates(this.H5PEditor)'));
}
same(fs.readFileSync(formPath), formBytes);
console.log('PASS: Actual ' + 'pinned target' + ' language function, nested data, idempotence and explicit installer');
console.log(checks + ' checks; 0 failures.');
