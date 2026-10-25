(() => {
  const init = () => {
document.querySelectorAll('[data-ut-suspension-form]').forEach((form) => {
  const boxes = [...form.querySelectorAll('[data-ut-suspend-id]')];
  const all = form.querySelector('[data-ut-suspend-all]');
  const submit = form.querySelector('[data-ut-suspend-submit]');
  const sync = () => {
    const ids = boxes.filter(box => box.checked).map(box => Number(box.dataset.utSuspendId));
    form.querySelector('[data-ut-selected]').value = JSON.stringify(ids);
    form.querySelector('[data-ut-selection-ready]').value = '1';
    form.querySelector('[data-ut-suspension-count]').textContent = ids.length + ' cuentas seleccionadas';
    if (submit) submit.disabled = !ids.length;
    if (all) { all.checked = !!boxes.length && ids.length === boxes.length; all.indeterminate = ids.length > 0 && ids.length < boxes.length; }
  };
  boxes.forEach(box => box.addEventListener('change', sync));
  if (all) all.addEventListener('change', () => { boxes.forEach(box => { box.checked = all.checked; }); sync(); });
  form.addEventListener('submit', sync);
  sync();
});

document.querySelectorAll('[data-ut-exclusions]').forEach((form) => {
  const protectAdmins = form.querySelector('[data-ut-protect-admins]');
  const options = [...form.querySelectorAll('[data-ut-group-option]')];
  const syncGroups = () => {
    options.forEach((option) => {
      const input = option.querySelector('[data-ut-group]');
      const preserve = option.querySelector('[data-ut-group-preserve]');
      const note = option.querySelector('[data-ut-group-note]');
      const locked = protectAdmins.checked && input.dataset.utAdminGroup === '1';
      if (!input.disabled) { input.dataset.utManual = input.checked ? '1' : '0'; }
      const manual = input.dataset.utManual === '1';
      input.checked = locked || manual;
      input.disabled = locked;
      preserve.disabled = !(locked && manual);
      note.hidden = !locked;
      option.classList.toggle('ut-is-protected', locked);
    });
  };
  protectAdmins.addEventListener('change', syncGroups);
  syncGroups();
});

document.querySelectorAll('[data-ut-review]').forEach((form) => {
  const rows = [...form.querySelectorAll('[data-ut-user]')];
  const sync = () => {
    const data = {};
    rows.forEach((row) => {
      const add = row.querySelector('[data-ut-add]');
      const email = row.querySelector('[data-ut-email]');
      email.disabled = !add.checked;
      email.required = add.checked;
      if (add.checked || email.value.trim()) {
        data[row.dataset.utUser] = { checked: add.checked, email: email.value };
      }
    });
    form.querySelector('[data-ut-registrations]').value = JSON.stringify(data);
    form.querySelector('[data-ut-ready]').value = '1';
  };
  rows.forEach((row) => row.querySelector('[data-ut-add]').addEventListener('change', sync));
  form.addEventListener('submit', sync);
  sync();
});

  };
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
