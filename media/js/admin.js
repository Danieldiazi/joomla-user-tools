(() => {
  const init = () => {
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
