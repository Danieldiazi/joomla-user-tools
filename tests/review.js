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
    querySelectorAll: () => [form],
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
