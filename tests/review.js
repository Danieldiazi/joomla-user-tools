const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../media/js/admin.js'), 'utf8');
for (const size of [0, 1800]) {
  const ready = { value: '0' };
  const payload = { value: '{}' };
  const handlers = {};
  const rows = Array.from({ length: size }, (_, i) => {
    const add = { checked: i % 2 === 0, addEventListener: (type, cb) => { add.change = cb; } };
    const email = { value: 'user' + i + '@example.com', disabled: false, required: false };
    return {
      dataset: { utUser: 'key' + i }, add, email,
      querySelector: selector => selector === '[data-ut-add]' ? add : email
    };
  });
  const form = {
    querySelectorAll: () => rows,
    querySelector: selector => selector === '[data-ut-ready]' ? ready : payload,
    addEventListener: (event, cb) => { handlers[event] = cb; }
  };
  const document = {
    readyState: 'loading',
    querySelectorAll: selector => selector === '[data-ut-review]' ? [form] : [],
    addEventListener: (event, cb) => { handlers[event] = cb; }
  };
  vm.runInNewContext(script, { document });
  assert.equal(ready.value, '0');
  handlers.DOMContentLoaded();
  assert.equal(ready.value, '1');
  if (rows.length) {
    assert.equal(rows[0].email.required, true);
    assert.equal(rows[1].email.disabled, true);
    rows[1].add.checked = true;
    rows[1].add.change();
    assert.equal(rows[1].email.required, true);
    rows[0].email.value = 'edited@example.com';
    handlers.submit();
    const parsed = JSON.parse(payload.value);
    assert.equal(parsed.key0.email, 'edited@example.com');
    assert.equal(parsed.key1.checked, true);
    assert.equal(Object.keys(parsed).length, size);
  }
}
console.log('OK: review loads after DOM ready, preserves edits and serialises 1800 rows');

function groupOption(admin, manual, locked) {
  const input = { checked: locked || manual, disabled: locked,
    dataset: { utAdminGroup: admin ? '1' : '0', utManual: manual ? '1' : '0' } };
  const preserve = { disabled: !(locked && manual) };
  const note = { hidden: !locked };
  return { input, preserve, note, classList: { toggle() {} },
    querySelector: s => s === '[data-ut-group]' ? input : s === '[data-ut-group-preserve]' ? preserve : note };
}
const auto = groupOption(true, false, true);
const manual = groupOption(true, true, true);
const ordinary = groupOption(false, false, false);
const protect = { checked: true, addEventListener: (event, handler) => { protect.change = handler; } };
const exclusionForm = { querySelector: () => protect, querySelectorAll: () => [auto, manual, ordinary] };
vm.runInNewContext(script, { document: { readyState: 'complete',
  querySelectorAll: s => s === '[data-ut-exclusions]' ? [exclusionForm] : [] } });
assert.equal(auto.input.checked, true);
assert.equal(auto.input.disabled, true);
assert.equal(auto.preserve.disabled, true, 'Automatic protection must not become a manual exclusion');
assert.equal(manual.preserve.disabled, false, 'Preserve previously saved manual selections');
assert.equal(ordinary.input.disabled, false);
protect.checked = false; protect.change();
assert.equal(auto.input.checked, false);
assert.equal(auto.input.disabled, false);
assert.equal(auto.note.hidden, true);
assert.equal(manual.input.checked, true);
assert.equal(manual.input.disabled, false);
auto.input.checked = true;
protect.checked = true; protect.change();
assert.equal(auto.input.disabled, true);
assert.equal(auto.preserve.disabled, false);
protect.checked = false; protect.change();
assert.equal(auto.input.checked, true, 'Restore manual changes made while unlocked');
assert.equal(manual.preserve.disabled, true, 'Do not submit manual selections twice');
console.log('OK: automatic administrator groups locked, explained and restored');
