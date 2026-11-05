// Behaviour of media/js/admin.js in a real DOM (jsdom).
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const script = fs.readFileSync(path.join(__dirname, '../../media/js/admin.js'), 'utf8');

const load = (html) => {
  const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { runScripts: 'outside-only' });
  dom.window.Joomla = { Text: { _: (key, fallback) => ({ COM_USERTOOLS_JS_SELECTED: '%d seleccionadas', COM_USERTOOLS_JS_SELECTED_1: '%d seleccionada', COM_USERTOOLS_JS_TYPE_COUNT: 'Escribe %d' }[key] || fallback) } };
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

test('comparison block review: none selected, filtered select-all, independent registration, typed count', async () => {
  const cells = [1, 2, 3, 4].map((id) => '<tr><td><input type="checkbox" data-ut-compare-block-id="' + id + '"></td><td>user' + id + '</td></tr>').join('');
  const doc = await load('<form data-ut-review data-ut-compare-block-review>'
    + '<input data-ut-compare-block-selected value="[]"><input data-ut-compare-block-ready value="0">'
    + '<input data-ut-registrations value="{}"><input data-ut-ready value="0">'
    + '<input type="checkbox" data-ut-compare-block-all>'
    + '<input type="search" data-ut-filter="blocks">'
    + '<div><table id="blocks"><tbody>' + cells + '</tbody></table><p data-ut-filter-empty hidden></p></div>'
    + '<span data-ut-compare-block-counter></span>'
    + '<div data-ut-confirm data-ut-threshold="3"><div data-ut-typed hidden><label data-ut-typed-label></label><input data-ut-typed-input></div></div>'
    + '<table><tbody><tr data-ut-user="candidate" data-ut-name="newuser"><td><input type="checkbox" data-ut-add></td><td><input data-ut-email type="email"></td></tr></tbody></table></form>');
  const form = doc.querySelector('form');
  assert.equal(form.querySelector('[data-ut-compare-block-ready]').value, '1');
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[]', 'none selected by default');
  const check = form.querySelector('[data-ut-compare-block-id="1"]');
  check.checked = true;
  change(check);
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[1]');
  const filter = form.querySelector('[data-ut-filter]');
  filter.value = 'user3';
  input(filter);
  const all = form.querySelector('[data-ut-compare-block-all]');
  assert.equal(all.checked, false, 'visible subset is initially unselected even though a hidden row is selected');
  all.checked = true;
  change(all);
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[1,3]', 'select all touches visible rows only');
  assert.equal(all.checked, true, 'select-all now reflects fully selected visible rows');
  assert.equal(all.indeterminate, false, 'hidden, unselected rows must not make visible select-all indeterminate');
  all.checked = false;
  change(all);
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[1]', 'second click deselects only visible rows');
  assert.equal(form.querySelector('[data-ut-typed]').hidden, true);
  all.checked = true;
  change(all);
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[1,3]');
  assert.equal(form.querySelector('[data-ut-typed]').hidden, true, 'two blocks need no typed confirmation');
  filter.value = '';
  input(filter);
  assert.equal(all.checked, false);
  assert.equal(all.indeterminate, true, 'removing filter recalculates mixed state over all visible candidates');
  all.checked = true;
  change(all);
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[1,2,3,4]');
  filter.value = 'no such user';
  input(filter);
  assert.equal(all.disabled, true, 'no visible candidates disables select-all');
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[1,2,3,4]', 'filter never drops hidden selections');
  filter.value = '';
  input(filter);
  assert.equal(all.disabled, false);
  assert.equal(all.checked, true);
  assert.equal(form.querySelector('[data-ut-typed]').hidden, false, 'typed confirmation counts selected blocks');
  assert.equal(form.querySelector('[data-ut-typed-label]').textContent, 'Escribe 4');
  assert.equal(form.querySelector('[data-ut-compare-block-counter]').textContent, '4 seleccionadas');
  check.checked = false;
  change(check);
  assert.equal(form.querySelector('[data-ut-compare-block-selected]').value, '[2,3,4]');
  assert.equal(form.querySelector('[data-ut-registrations]').value, '{}', 'selection does not create new accounts');
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
  assert.equal(doc.querySelector('[data-ut-suspension-count]').textContent, '1 seleccionada', 'one account takes the singular key');
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
  assert.equal(doc.querySelector('[data-ut-suspension-count]').textContent, '4 seleccionadas');
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

test('remove from groups: "and its subgroups" is decided per group and cascades do not undo each other', async () => {
  // Manager(6) > Administrator(7); Registered(2) > Author(3) > Editor(4); Registered > Socios(10) > Junta(11)
  const rows = [[6, '1', 'Manager', true], [7, '1,6', 'Administrator'], [2, '1', 'Registered', true], [3, '1,2', 'Author', true], [4, '1,2,3', 'Editor'], [10, '1,2', 'Socios', true], [11, '1,2,10', 'Junta']];
  const html = rows.map(([id, parents, title, children]) => `<div data-ut-ungroup-row><input type="checkbox" name="keep[]" value="${id}" data-ut-keep="${id}" data-ut-parents="${parents}" checked>
    <label>${parents.includes(',') ? '<span>└</span>' : ''}${title}</label>
    ${children ? `<div data-ut-subgroups-wrap><input type="checkbox" name="recursive[]" value="${id}" data-ut-subgroups="${id}"><label>and its subgroups</label></div>` : ''}
    <small data-ut-cascade-note hidden></small></div>`).join('');
  const doc = await load(`<form data-ut-ungroup-groups>${html}<p data-ut-ungroup-count></p><button data-ut-ungroup-next>Next</button></form>`);
  const get = (id) => doc.querySelector(`[data-ut-keep="${id}"]`);
  const sub = (id) => doc.querySelector(`[data-ut-subgroups="${id}"]`);
  const offered = (id) => !sub(id).closest('[data-ut-subgroups-wrap]').hidden && !sub(id).disabled;
  const next = doc.querySelector('[data-ut-ungroup-next]');
  const click = (el) => { el.checked = !el.checked; change(el); };

  assert.equal(next.disabled, true, 'nothing unticked, nothing to review');
  assert.equal(offered(10), false, '"and its subgroups" only appears for an unticked group');

  click(get(10));
  assert.equal(offered(10), true);
  assert.equal(get(11).checked, true, 'without "and its subgroups", children stay');
  click(sub(10));
  assert.deepEqual([get(11).checked, get(11).disabled], [false, true], 'Junta is unticked and locked');
  assert.match(get(11).closest('[data-ut-ungroup-row]').querySelector('[data-ut-cascade-note]').textContent, /Socios/);

  click(get(3));
  click(sub(3));
  assert.deepEqual([get(4).checked, get(4).disabled], [false, true], 'the second cascade works');
  assert.deepEqual([get(11).checked, get(11).disabled], [false, true], 'and the first one is still there');
  assert.match(doc.querySelector('[data-ut-ungroup-count]').textContent, /4/);

  click(sub(10));
  assert.deepEqual([get(11).checked, get(11).disabled], [true, false], 'unticking "and its subgroups" gives the children back');
  assert.deepEqual([get(4).checked, get(4).disabled], [false, true], 'without touching the other group');

  click(get(2));
  click(sub(2));
  assert.equal(offered(3), false, 'a group locked by a cascade does not offer its own option');
  click(sub(2));
  click(get(2));
  assert.deepEqual([get(3).checked, get(4).disabled], [false, true], 'Author keeps its own choice and its cascade');

  // A child's own choice hidden under its parent's cascade is still sent.
  click(get(2));
  click(sub(2));
  assert.equal(get(3).disabled, true);
  const form = doc.querySelector('[data-ut-ungroup-groups]');
  form.dispatchEvent(new doc.defaultView.Event('submit', { cancelable: true }));
  const sent = (sel) => [...form.querySelectorAll(sel)].filter((box) => box.checked && !box.disabled).map((box) => Number(box.value));
  assert.deepEqual(sent('[data-ut-keep]'), [6, 7, 4, 11], 'the operator\'s own ticks are sent; the server applies the cascades');
  assert.deepEqual(sent('[data-ut-subgroups]'), [2, 3], 'Author keeps "and its subgroups" although Registered\'s cascade covers it');
});

test('account selector: suggestions show the full identity and send the chosen ID', async () => {
  const doc = await load('<div data-ut-account-picker><input data-ut-account-search="index.php?option=com_usertools&task=accounts.suggest&tool=ungroup&abc=1" data-ut-account-min="2" list="ut-list"><datalist id="ut-list"></datalist><input type="hidden" name="account" value="0" data-ut-account-id></div>');
  const win = doc.defaultView;
  const asked = [];
  const twins = [{ id: 12, label: 'ana — Ana Pérez (ana@example.test) · ID 12' }, { id: 31, label: 'ana2 — Ana Pérez (<b>@example.test) · ID 31' }];
  win.fetch = async (url) => { asked.push(String(url)); return { ok: true, json: async () => twins }; };
  const field = doc.querySelector('[data-ut-account-search]');
  const hidden = doc.querySelector('[data-ut-account-id]');
  field.value = 'a';
  input(field);
  field.value = 'an';
  input(field);
  await new Promise((resolve) => setTimeout(resolve, 400));
  assert.equal(asked.length, 1, 'one request after the pause, none for a single letter');
  assert.match(asked[0], /task=accounts\.suggest&tool=ungroup/);
  assert.match(asked[0], /q=an/);
  const options = [...doc.querySelectorAll('#ut-list option')];
  assert.deepEqual(options.map((o) => o.value), twins.map((t) => t.label), 'two people with the same name are told apart by username, email and ID');
  assert.equal(doc.querySelectorAll('#ut-list b').length, 0, 'labels are text, never HTML');
  field.value = twins[1].label;
  input(field);
  assert.equal(hidden.value, '31', 'choosing a suggestion sends its ID');
  field.value = 'ana2';
  input(field);
  assert.equal(hidden.value, '0', 'editing the text clears the chosen ID, so the server resolves what was typed');
});

test('account selector: a preselected account keeps its ID until the text changes', async () => {
  const doc = await load('<div data-ut-account-picker><input data-ut-account-search="x?y=1" list="l" value="12"><datalist id="l"></datalist><input type="hidden" value="40" data-ut-account-id></div>');
  const field = doc.querySelector('[data-ut-account-search]');
  const hidden = doc.querySelector('[data-ut-account-id]');
  change(field);
  assert.equal(hidden.value, '40', 'an all-digit username opened from the profile keeps that account');
  field.value = '13';
  input(field);
  assert.equal(hidden.value, '0');
});

const newAccounts = (names, extra = '') => `<form data-ut-review><input type="hidden" data-ut-registrations value="{}"><input type="hidden" data-ut-ready value="0">
  <div data-ut-email-auto><input type="checkbox" data-ut-email-auto-on><input type="text" data-ut-email-domain disabled><div data-ut-email-skipped hidden></div></div>
  <table><thead><tr><th><input type="checkbox" data-ut-add-all></th></tr></thead><tbody>${names.map((n, i) => `<tr data-ut-user="k${i}" data-ut-name="${n}">
  <td><input type="checkbox" data-ut-add></td><td><input type="email" data-ut-email value="${i === 0 ? extra : ''}" disabled></td></tr>`).join('')}</tbody></table></form>`;

test('bulk creation: optional email generator, no default selection and typed count', async () => {
  const html = '<form data-ut-review data-ut-bulkcreate-review><input data-ut-registrations value="{}"><input data-ut-ready value="0">'
    + '<div data-ut-email-auto><input type="checkbox" data-ut-email-auto-on><input data-ut-email-domain disabled><span data-ut-email-skipped hidden></span></div>'
    + '<input type="checkbox" data-ut-add-all><table><tbody>'
    + '<tr data-ut-user="a" data-ut-name="alpha"><td><input type="checkbox" data-ut-add></td><td><input data-ut-email type="email" disabled></td></tr>'
    + '<tr data-ut-user="b" data-ut-name="bravo"><td><input type="checkbox" data-ut-add></td><td><input data-ut-email type="email" disabled></td></tr>'
    + '</tbody></table><div data-ut-confirm data-ut-threshold="2"><div data-ut-typed hidden>'
    + '<label data-ut-typed-label></label><input data-ut-typed-input></div></div>'
    + '<strong data-ut-bulkcreate-selected></strong><button data-ut-bulkcreate-submit disabled></button></form>';
  const doc = await load(html);
  const form = doc.querySelector('form');
  const all = form.querySelector('[data-ut-add-all]');
  const typed = form.querySelector('[data-ut-typed]');
  const submit = form.querySelector('[data-ut-bulkcreate-submit]');
  assert.equal(submit.disabled, true, 'no rows are selected by default');
  assert.equal(form.querySelector('[data-ut-bulkcreate-selected]').textContent, '0');
  all.checked = true;
  change(all);
  assert.equal(submit.disabled, false);
  assert.equal(typed.hidden, false, 'typed confirmation follows the selected count');
  assert.equal(form.querySelector('[data-ut-typed-label]').textContent, 'Escribe 2');
  const on = form.querySelector('[data-ut-email-auto-on]');
  const domain = form.querySelector('[data-ut-email-domain]');
  on.checked = true;
  change(on);
  domain.value = 'example.org';
  input(domain);
  const data = JSON.parse(form.querySelector('[data-ut-registrations]').value);
  assert.equal(data.a.email, 'alpha@example.org');
  assert.equal(data.b.email, 'bravo@example.org');
  const first = form.querySelector('[data-ut-add]');
  first.checked = false;
  change(first);
  assert.equal(typed.hidden, true, 'one selected account does not require typed confirmation');
  assert.equal(form.querySelector('[data-ut-bulkcreate-selected]').textContent, '1');
});

test('new accounts: one box ticks or unticks them all and reflects partial selections', async () => {
  const doc = await load(newAccounts(['ana', 'luis', 'eva']));
  const all = doc.querySelector('[data-ut-add-all]');
  const adds = [...doc.querySelectorAll('[data-ut-add]')];
  all.checked = true;
  change(all);
  assert.deepEqual(adds.map((a) => a.checked), [true, true, true]);
  assert.equal(doc.querySelectorAll('[data-ut-email]:not([disabled])').length, 3, 'every email becomes editable and required');
  assert.equal(Object.values(JSON.parse(doc.querySelector('[data-ut-registrations]').value)).filter((r) => r.checked).length, 3);
  adds[1].checked = false;
  change(adds[1]);
  assert.equal(all.checked, false);
  assert.equal(all.indeterminate, true, 'partly ticked');
  all.checked = false;
  change(all);
  assert.deepEqual(adds.map((a) => a.checked), [false, false, false]);
  assert.equal(all.indeterminate, false);
});

test('new accounts: emails built from the username and domain, never over a typed one', async () => {
  const doc = await load(newAccounts(['ana', 'luis', 'josé maría'], 'ana.lopez@otro.es'));
  const on = doc.querySelector('[data-ut-email-auto-on]');
  const domain = doc.querySelector('[data-ut-email-domain]');
  const emails = [...doc.querySelectorAll('[data-ut-email]')];
  const note = doc.querySelector('[data-ut-email-skipped]');
  on.checked = true;
  change(on);
  assert.equal(domain.disabled, false);
  domain.value = '@Correo.com ';
  input(domain);
  assert.deepEqual(emails.map((e) => e.value), ['ana.lopez@otro.es', 'luis@correo.com', ''], 'the typed email stays; a username with spaces or accents is skipped');
  assert.equal(note.hidden, false);
  assert.match(note.textContent, /^One username/, 'singular message');
  assert.equal(domain.value, 'Correo.com ', 'a typed "@" is dropped: the field already shows it');
  const data = JSON.parse(doc.querySelector('[data-ut-registrations]').value);
  assert.equal(data.k1.email, 'luis@correo.com', 'filled emails are sent with the registrations');

  domain.value = 'otra.org';
  input(domain);
  assert.equal(emails[1].value, 'luis@otra.org', 'changing the domain updates the filled emails');
  emails[1].value = 'luis.g@otra.org';
  input(emails[1]);
  domain.value = 'tercera.net';
  input(domain);
  assert.equal(emails[1].value, 'luis.g@otra.org', 'an edited email is no longer touched');

  domain.value = 'no valido';
  input(domain);
  assert.equal(domain.classList.contains('is-invalid'), true);
  assert.equal(note.hidden, false);
  domain.value = 'correo.com';
  input(domain);
  emails[1].value = '';
  input(emails[1]);
  domain.value = 'correo.com ';
  input(domain);
  assert.equal(emails[1].value, 'luis@correo.com', 'an emptied email is filled again');
  on.checked = false;
  change(on);
  assert.deepEqual(emails.map((e) => e.value), ['ana.lopez@otro.es', '', ''], 'turning it off clears only what it filled');
  assert.equal(domain.disabled, true);
  assert.equal(note.hidden, true);
});

const progressPage = (passwords, initial = []) => `<div data-ut-progress data-ut-step-url="/step" data-ut-result-url="/result" data-ut-token="tok123"
  data-ut-total="5" data-ut-processed="1" data-ut-passwords="${passwords ? 1 : 0}">
  <div data-ut-progress-bar aria-valuenow="0"><div style="width:0%"></div></div><p data-ut-progress-text></p>
  <div data-ut-progress-error hidden><p data-ut-progress-error-text></p><button type="button" data-ut-progress-retry></button></div>
  ${passwords ? '<button type="button" data-ut-progress-copy></button><table><tbody data-ut-progress-rows></tbody></table><div data-ut-progress-finished hidden></div>' : ''}
  <script type="application/json" data-ut-progress-initial>${JSON.stringify(initial)}</script>
  <form data-ut-progress-discard><button name="task" value="compare.discard"></button></form></div>`;

const loadWithFetch = (html, responses) => {
  const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { runScripts: 'outside-only' });
  const calls = [];
  dom.window.Joomla = { Text: { _: (key, fallback) => fallback } };
  dom.window.fetch = async (url, options) => {
    calls.push({ url, token: options.body.get('tok123') });
    const next = responses.shift();
    if (next instanceof Error) throw next;
    if (!next) return new Promise(() => {});
    return { ok: next.status === undefined || next.status === 200, status: next.status || 200, json: async () => next.body };
  };
  dom.window.eval(script);
  return { doc: dom.window.document, calls };
};
const settle = () => new Promise((resolve) => { setTimeout(resolve, 20); });

test('progress: batches run one after another and one-time passwords stay on the page', async () => {
  const row = (n) => ({ id: n, username: `u${n}`, email: `u${n}@x.test`, password: `P${n}` });
  const { doc, calls } = loadWithFetch(progressPage(true, [row(1)]), [
    { body: { done: false, total: 5, processed: 3, created: [row(2), row(3)] } },
    { body: { done: true, total: 5, processed: 5, created: [row(4), row(5)] } },
  ]);
  await settle();
  assert.equal(calls.length, 2, 'it stops when the job is done');
  assert.ok(calls.every((c) => c.url === '/step' && c.token === '1'), 'every batch carries the form token');
  const cells = [...doc.querySelectorAll('[data-ut-progress-rows] tr')].map((tr) => tr.lastElementChild.textContent);
  assert.deepEqual(cells, ['P1', 'P2', 'P3', 'P4', 'P5'], 'passwords of every batch, including the first one rendered by the server');
  assert.equal(doc.querySelector('[data-ut-progress-finished]').hidden, false, 'the operator stays on the page to copy them');
  assert.equal(doc.querySelector('[data-ut-progress-bar]').getAttribute('aria-valuenow'), '100');
  assert.match(doc.querySelector('[data-ut-progress-text]').textContent, /^5 of 5/);
});

test('progress: an error from the server stops and offers to continue', async () => {
  const { doc, calls } = loadWithFetch(progressPage(false), [
    { body: { error: 'There is no comparison waiting to be applied.' } },
    { body: { done: false, total: 5, processed: 2, created: [] } },
  ]);
  await settle();
  assert.equal(calls.length, 1);
  const box = doc.querySelector('[data-ut-progress-error]');
  assert.equal(box.hidden, false);
  assert.equal(doc.querySelector('[data-ut-progress-error-text]').textContent, 'There is no comparison waiting to be applied.');
  doc.querySelector('[data-ut-progress-retry]').click();
  await settle();
  assert.equal(calls.length, 3, 'continue sends the next batch');
  assert.equal(box.hidden, true);
});

test('stylesheet: plain table wrappers hide vertical overflow, height-limited ones keep scrolling', () => {
  const css = fs.readFileSync(path.join(__dirname, '../../media/css/admin.css'), 'utf8');
  const dom = new JSDOM(`<!doctype html><style>.table-responsive{overflow-x:auto}${css}</style><body><div class="ut-app">
    <div class="table-responsive" id="plain"></div>
    <div class="table-responsive ut-access-scroll mt-3" id="tall"></div>
    <div class="table-responsive ut-table-scroll" id="list"></div></div></body>`);
  const style = (id) => dom.window.getComputedStyle(dom.window.document.getElementById(id));
  assert.equal(style('plain').overflowY, 'hidden');
  // jsdom keeps the `overflow` shorthand unexpanded: the rule must not set overflow-y on these wrappers.
  for (const id of ['tall', 'list']) {
    assert.notEqual(style(id).overflowY, 'hidden', id);
    assert.equal(style(id).overflow, 'auto', id);
  }
  assert.equal(style('tall').maxHeight, '40rem');
});

test('add accounts to groups: the target group is hidden and not sent in pairs mode', async () => {
  const doc = await load(`<form data-ut-addgroups-form>
    <input type="radio" name="mode" value="single" checked><input type="radio" name="mode" value="pairs">
    <div data-ut-ag-group><label for="g">Group</label><input id="g" name="group" value="#2"></div></form>`);
  const [single, pairs] = doc.querySelectorAll('input[name="mode"]');
  const wrapper = doc.querySelector('[data-ut-ag-group]');
  assert.equal(wrapper.hidden, false);
  pairs.checked = true;
  change(pairs);
  assert.equal(wrapper.hidden, true);
  assert.equal(doc.getElementById('g').disabled, true, 'a hidden value is not submitted');
  single.checked = true;
  change(single);
  assert.equal(wrapper.hidden, false);
  assert.equal(doc.getElementById('g').disabled, false);
});

test('stylesheet: sticky headers and first columns have an opaque surface even without Bootstrap variables', () => {
  const css = fs.readFileSync(path.join(__dirname, '../../media/css/admin.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
  const block = (selector) => {
    const m = css.match(new RegExp(selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\{([^}]*)\\}'));
    return m ? m[1] : '';
  };
  // Atum defines --body-bg but not --bs-body-bg: the sticky cells must still get a background.
  assert.match(block('.ut-app'), /max-width:1200px;--ut-surface:var\(--bs-body-bg,var\(--body-bg,Canvas\)\)/);
  for (const selector of ['.ut-access thead th', '.ut-access .ut-access-group', '.ut-table-scroll thead th']) {
    assert.match(block(selector), /position:sticky/, selector);
    assert.match(block(selector), /background-color:var\(--ut-surface\)/, selector);
  }
  assert.ok(!/background:var\(--bs-body-bg\)/.test(css), 'no sticky cell depends on --bs-body-bg alone');
});

test('name selections start empty and are synchronised before enabling apply', async () => {
  const doc = await load(`<table id="ut-names-table"><tr><td><input type="checkbox" data-ut-name-choice value="0" checked></td></tr><tr><td><input type="checkbox" data-ut-name-choice value="1" checked></td></tr></table>
    <form data-ut-names-review><p data-ut-names-none-selected hidden></p><input data-ut-names-selected value="[]"><input data-ut-names-ready value="0"><button data-ut-names-submit disabled></button></form>`);
  const none = doc.querySelector('[data-ut-names-none-selected]');
  const boxes = doc.querySelectorAll('[data-ut-name-choice]');
  const selected = doc.querySelector('[data-ut-names-selected]');
  const submit = doc.querySelector('button');
  assert.equal(doc.querySelector('[data-ut-names-ready]').value, '1');
  assert.equal(submit.disabled, false);
  boxes[0].checked = false;
  change(boxes[0]);
  assert.deepEqual(JSON.parse(selected.value), [1]);
  boxes[1].checked = false;
  change(boxes[1]);
  assert.equal(submit.disabled, true);
  assert.equal(none.hidden, false);
  assert.deepEqual(JSON.parse(selected.value), []);
  // Submission synchronises even if a browser restores a checkbox without a change event.
  boxes[0].checked = true;
  doc.querySelector('form').dispatchEvent(new doc.defaultView.Event('submit'));
  assert.deepEqual(JSON.parse(selected.value), [0]);
  assert.equal(none.hidden, true);
});

test('name apply remains disabled if selection controls cannot initialise', async () => {
  const doc = await load('<form data-ut-names-review><input data-ut-names-selected value="[]"><input data-ut-names-ready value="0"><button data-ut-names-submit disabled></button></form>');
  assert.equal(doc.querySelector('button').disabled, true);
  assert.equal(doc.querySelector('[data-ut-names-ready]').value, '0');
  assert.equal(doc.querySelector('[data-ut-names-selected]').value, '[]');
});

test('bulk name progress automatically posts the next saved batch', async () => {
  const doc = await load('<div data-ut-names-progress data-ut-names-auto="1">'
    + '<form data-ut-names-next><button name="task" value="names.next"></button></form></div>');
  const form = doc.querySelector('form');
  const submitted = [];
  form.requestSubmit = (button) => { submitted.push(button.value); };
  await new Promise((resolve) => setTimeout(resolve, 520));
  assert.deepEqual(submitted, ['names.next']);
});

test('bulk name progress stops automatic requests on errors or manual submission', async () => {
  const paused = await load('<div data-ut-names-progress data-ut-names-auto="0">'
    + '<form data-ut-names-next><button name="task" value="names.next"></button></form></div>');
  let requests = 0;
  paused.querySelector('form').requestSubmit = () => { requests += 1; };
  await new Promise((resolve) => setTimeout(resolve, 520));
  assert.equal(requests, 0, 'failed request requires a deliberate retry');

  const active = await load('<div data-ut-names-progress data-ut-names-auto="1">'
    + '<form data-ut-names-next><button name="task" value="names.next"></button></form></div>');
  const form = active.querySelector('form');
  form.requestSubmit = () => { requests += 1; };
  form.dispatchEvent(new active.defaultView.Event('submit', { bubbles: true }));
  await new Promise((resolve) => setTimeout(resolve, 520));
  assert.equal(requests, 0, 'a manual submission cancels the scheduled automatic one');
});
