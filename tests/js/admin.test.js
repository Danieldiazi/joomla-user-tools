// Behaviour of media/js/admin.js in a real DOM (jsdom).
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const script = fs.readFileSync(path.join(__dirname, '../../media/js/admin.js'), 'utf8');

const load = (html) => {
  const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { runScripts: 'outside-only' });
  dom.window.Joomla = { Text: { _: (key, fallback) => ({ COM_USERTOOLS_JS_SELECTED: '%d seleccionadas', COM_USERTOOLS_JS_TYPE_COUNT: 'Escribe %d' }[key] || fallback) } };
  dom.window.eval(script);
  // The script waits for DOMContentLoaded while jsdom is still parsing.
  return new Promise((resolve) => {
    if (dom.window.document.readyState !== 'loading') resolve(dom.window.document);
    else dom.window.document.addEventListener('DOMContentLoaded', () => setTimeout(() => resolve(dom.window.document), 0));
  });
};
const change = (el) => el.dispatchEvent(new el.ownerDocument.defaultView.Event('change', { bubbles: true }));
const input = (el) => el.dispatchEvent(new el.ownerDocument.defaultView.Event('input', { bubbles: true }));

test('registrations are serialised as one JSON field, including large lists', async () => {
  const rows = Array.from({ length: 1800 }, (_, i) => `<tr data-ut-user="k${i}"><td><input type="checkbox" data-ut-add${i % 2 ? '' : ' checked'}></td><td><input type="email" data-ut-email value="u${i}@example.com"></td></tr>`).join('');
  const doc = await load(`<form data-ut-review><input type="hidden" data-ut-registrations value="{}"><input type="hidden" data-ut-ready value="0"><table>${rows}</table></form>`);
  assert.equal(doc.querySelector('[data-ut-ready]').value, '1');
  const first = doc.querySelectorAll('[data-ut-user]');
  assert.equal(first[0].querySelector('[data-ut-email]').required, true);
  assert.equal(first[1].querySelector('[data-ut-email]').disabled, true);
  const add = first[1].querySelector('[data-ut-add]');
  add.checked = true;
  change(add);
  const data = JSON.parse(doc.querySelector('[data-ut-registrations]').value);
  assert.equal(Object.keys(data).length, 1800);
  assert.deepEqual(data.k1, { checked: true, email: 'u1@example.com' });
});

test('Super Users are always locked; backend groups follow "Exclude administrators"; manual choices come back', async () => {
  const group = (id, kind, checked) => `<div data-ut-group-option><input type="checkbox" data-ut-group data-ut-group-kind="${kind}" data-ut-manual="${checked ? 1 : 0}" value="${id}"${checked ? ' checked' : ''}>
    <input type="hidden" data-ut-group-preserve value="${id}" disabled><small data-ut-group-note data-ut-note-super="super" data-ut-note-backend="backend" hidden></small></div>`;
  const doc = await load(`<div data-ut-exclusions><input type="checkbox" data-ut-protect-admins checked>${group(8, 'super', false)}${group(6, 'backend', true)}${group(2, '', false)}</div>`);
  const [superUsers, manager, registered] = doc.querySelectorAll('[data-ut-group]');
  const protect = doc.querySelector('[data-ut-protect-admins]');
  assert.equal(superUsers.disabled && superUsers.checked, true);
  assert.equal(manager.disabled && manager.checked, true);
  assert.equal(manager.parentElement.querySelector('[data-ut-group-preserve]').disabled, false, 'a manual choice on a locked group is still submitted');
  assert.equal(registered.disabled, false);
  protect.checked = false;
  change(protect);
  assert.equal(superUsers.disabled && superUsers.checked, true, 'Super Users stay locked');
  assert.equal(superUsers.parentElement.querySelector('[data-ut-group-note]').textContent, 'super');
  assert.equal(manager.disabled, false);
  assert.equal(manager.checked, true, 'the manual selection is restored');
});

test('inactivity review: selection, select-all on visible rows and typed confirmation from the threshold', async () => {
  const rows = [1, 2, 3, 4].map((id) => `<tr><td><input type="checkbox" data-ut-suspend-id="${id}"></td><td>user${id}</td></tr>`).join('');
  const doc = await load(`<form data-ut-suspension-form>
    <input type="hidden" data-ut-selected value="[]"><input type="hidden" data-ut-selection-ready value="0">
    <input type="checkbox" data-ut-suspend-all><input type="search" data-ut-filter="t">
    <div><table id="t"><tbody>${rows}</tbody></table><p data-ut-filter-empty hidden></p></div>
    <p data-ut-suspension-count></p>
    <div data-ut-confirm data-ut-threshold="3"><div data-ut-typed hidden><label data-ut-typed-label></label><input data-ut-typed-input></div></div>
    <button data-ut-suspend-submit disabled></button></form>`);
  const boxes = doc.querySelectorAll('[data-ut-suspend-id]');
  assert.equal(doc.querySelector('[data-ut-selection-ready]').value, '1');
  assert.equal(doc.querySelector('[data-ut-suspend-submit]').disabled, true);
  boxes[0].checked = true;
  change(boxes[0]);
  assert.equal(doc.querySelector('[data-ut-selected]').value, '[1]');
  assert.equal(doc.querySelector('[data-ut-suspension-count]').textContent, '1 seleccionadas');
  assert.equal(doc.querySelector('[data-ut-typed]').hidden, true, 'below the threshold no number is typed');
  const filter = doc.querySelector('[data-ut-filter]');
  filter.value = 'user3';
  input(filter);
  assert.equal(doc.querySelectorAll('tbody tr')[0].hidden, true);
  const all = doc.querySelector('[data-ut-suspend-all]');
  all.checked = true;
  change(all);
  assert.equal(doc.querySelector('[data-ut-selected]').value, '[1,3]', 'select-all only adds visible rows');
  filter.value = '';
  input(filter);
  all.checked = true;
  change(all);
  assert.equal(doc.querySelector('[data-ut-selected]').value, '[1,2,3,4]');
  const typed = doc.querySelector('[data-ut-typed]');
  assert.equal(typed.hidden, false);
  assert.equal(doc.querySelector('[data-ut-typed-input]').required, true);
  assert.equal(doc.querySelector('[data-ut-typed-label]').textContent, 'Escribe 4');
});

test('table filter shows a message when nothing matches', async () => {
  const doc = await load('<input data-ut-filter="t"><div><table id="t"><tbody><tr><td>ana</td></tr><tr><td>luis</td></tr></tbody></table><p data-ut-filter-empty hidden></p></div>');
  const filter = doc.querySelector('[data-ut-filter]');
  filter.value = 'LUIS';
  input(filter);
  assert.deepEqual([...doc.querySelectorAll('tr')].map((r) => r.hidden), [true, false]);
  filter.value = 'nadie';
  input(filter);
  assert.equal(doc.querySelector('[data-ut-filter-empty]').hidden, false);
});

test('remove from groups: recursive option unticks and locks the groups below an unticked one', async () => {
  // Registered(2) > Author(3) > Editor(4); Registered > Socios(10); Manager(6)
  const box = (id, parents, title) => `<div data-ut-ungroup-row><input type="checkbox" name="keep[]" value="${id}" data-ut-keep="${id}" data-ut-parents="${parents}" checked>
    <label>${title}</label><small data-ut-cascade-note hidden></small></div>`;
  const doc = await load(`<form data-ut-ungroup-groups>${box(6, '1', 'Manager')}${box(2, '1', 'Registered')}${box(3, '1,2', 'Author')}${box(4, '1,2,3', 'Editor')}${box(10, '1,2', 'Socios')}
    <input type="checkbox" data-ut-recursive><p data-ut-ungroup-count></p><button data-ut-ungroup-next>Next</button></form>`);
  const get = (id) => doc.querySelector(`[data-ut-keep="${id}"]`);
  const next = doc.querySelector('[data-ut-ungroup-next]');
  const recursive = doc.querySelector('[data-ut-recursive]');
  assert.equal(next.disabled, true, 'nothing unticked, nothing to review');

  get(3).checked = false;
  change(get(3));
  assert.equal(get(4).checked, true, 'without the option, children stay');
  assert.match(doc.querySelector('[data-ut-ungroup-count]').textContent, /1/);
  assert.equal(next.disabled, false);

  recursive.checked = true;
  change(recursive);
  assert.equal(get(4).checked, false);
  assert.equal(get(4).disabled, true, 'a cascaded group is locked and not submitted');
  assert.match(get(4).parentElement.querySelector('[data-ut-cascade-note]').textContent, /Author/);
  assert.equal(get(10).checked, true, 'siblings are not touched');

  get(2).checked = false;
  change(get(2));
  assert.deepEqual([3, 4, 10].map((id) => get(id).checked), [false, false, false], 'everything below Registered');
  assert.equal(get(6).checked, true);
  assert.match(get(3).parentElement.querySelector('[data-ut-cascade-note]').textContent, /Registered/);

  get(2).checked = true;
  change(get(2));
  assert.deepEqual([3, 4, 10].map((id) => [get(id).checked, get(id).disabled]), [[false, false], [false, true], [true, false]],
    'ticking the parent again gives back each group its own tick');

  recursive.checked = false;
  change(recursive);
  assert.deepEqual([3, 4].map((id) => [get(id).checked, get(id).disabled]), [[false, false], [true, false]], 'turning the option off undoes the cascade');

  recursive.checked = true;
  change(recursive);
  assert.equal(get(4).disabled, true);
  const form = doc.querySelector('[data-ut-ungroup-groups]');
  form.dispatchEvent(new doc.defaultView.Event('submit', { cancelable: true }));
  const sent = [...form.querySelectorAll('[data-ut-keep]')].filter((box) => box.checked && !box.disabled).map((box) => Number(box.value));
  assert.deepEqual(sent, [6, 2, 4, 10], 'the operator\'s own ticks are sent (Editor stays ticked); the server applies the cascade');
});

test('remove from groups: account suggestions fill the list while typing', async () => {
  const doc = await load('<input data-ut-account-search="index.php?option=com_usertools&task=ungroup.suggest&abc=1" list="ut-list"><datalist id="ut-list"></datalist>');
  const win = doc.defaultView;
  const asked = [];
  win.fetch = async (url) => { asked.push(String(url)); return { ok: true, json: async () => [{ username: 'ana', name: 'Ana Pérez', email: 'ana@example.test' }, { username: 'anabel', name: '', email: 'b@example.test' }] }; };
  const field = doc.querySelector('[data-ut-account-search]');
  field.value = 'a';
  input(field);
  field.value = 'an';
  input(field);
  await new Promise((resolve) => setTimeout(resolve, 400));
  assert.equal(asked.length, 1, 'one request after the pause, none for a single letter');
  assert.match(asked[0], /task=ungroup\.suggest/);
  assert.match(asked[0], /q=an/);
  const options = [...doc.querySelectorAll('#ut-list option')];
  assert.deepEqual(options.map((o) => [o.value, o.label]), [['ana', 'Ana Pérez · ana@example.test'], ['anabel', 'b@example.test']]);
});
