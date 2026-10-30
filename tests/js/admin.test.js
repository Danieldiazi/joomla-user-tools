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

const newAccounts = (names, extra = '') => `<form data-ut-review><input type="hidden" data-ut-registrations value="{}"><input type="hidden" data-ut-ready value="0">
  <div data-ut-email-auto><input type="checkbox" data-ut-email-auto-on><input type="text" data-ut-email-domain disabled><div data-ut-email-skipped hidden></div></div>
  <table><thead><tr><th><input type="checkbox" data-ut-add-all></th></tr></thead><tbody>${names.map((n, i) => `<tr data-ut-user="k${i}" data-ut-name="${n}">
  <td><input type="checkbox" data-ut-add></td><td><input type="email" data-ut-email value="${i === 0 ? extra : ''}" disabled></td></tr>`).join('')}</tbody></table></form>`;

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
