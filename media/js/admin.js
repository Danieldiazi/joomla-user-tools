(() => {
  const text = (key, fallback) => (window.Joomla && Joomla.Text ? Joomla.Text._(key, fallback) : fallback);
  const sprintf = (pattern, value) => pattern.replace('%d', value).replace('%s', value);

  // Filter long tables while keeping hidden rows in the form.
  const initFilters = (root) => {
    root.querySelectorAll('[data-ut-filter]').forEach((input) => {
      const table = document.getElementById(input.dataset.utFilter);
      if (!table) return;
      const empty = table.parentElement.querySelector('[data-ut-filter-empty]');
      input.addEventListener('input', () => {
        const term = input.value.trim().toLocaleLowerCase();
        let visible = 0;
        table.querySelectorAll('tbody tr').forEach((row) => {
          const show = !term || row.textContent.toLocaleLowerCase().includes(term);
          row.hidden = !show;
          if (show) visible += 1;
        });
        if (empty) empty.hidden = visible > 0;
      });
    });
  };

  // Reinforced confirmation: from the threshold on, the number of accounts must be typed.
  const syncTyped = (form, count) => {
    const box = form.querySelector('[data-ut-confirm]');
    const typed = box && box.querySelector('[data-ut-typed]');
    if (!typed) return;
    const threshold = Number(box.dataset.utThreshold || 0);
    const needed = threshold > 0 && count >= threshold;
    typed.hidden = !needed;
    const input = typed.querySelector('[data-ut-typed-input]');
    input.required = needed;
    const label = typed.querySelector('[data-ut-typed-label]');
    label.textContent = sprintf(text('COM_USERTOOLS_JS_TYPE_COUNT', 'Type %d to confirm'), count);
  };

  const init = () => {
    initFilters(document);

    document.querySelectorAll('[data-ut-suspension-form]').forEach((form) => {
      const boxes = [...form.querySelectorAll('[data-ut-suspend-id]')];
      const all = form.querySelector('[data-ut-suspend-all]');
      const submit = form.querySelector('[data-ut-suspend-submit]');
      const counter = form.querySelector('[data-ut-suspension-count]');
      const sync = () => {
        const ids = boxes.filter((box) => box.checked).map((box) => Number(box.dataset.utSuspendId));
        form.querySelector('[data-ut-selected]').value = JSON.stringify(ids);
        form.querySelector('[data-ut-selection-ready]').value = '1';
        if (counter) counter.textContent = sprintf(text('COM_USERTOOLS_JS_SELECTED', '%d selected'), ids.length);
        if (submit) submit.disabled = !ids.length;
        if (all) {
          all.checked = !!boxes.length && ids.length === boxes.length;
          all.indeterminate = ids.length > 0 && ids.length < boxes.length;
        }
        syncTyped(form, ids.length);
      };
      boxes.forEach((box) => box.addEventListener('change', sync));
      if (all) all.addEventListener('change', () => {
        // "Select all" applies to the rows currently shown by the filter.
        boxes.forEach((box) => { if (!box.closest('tr').hidden) box.checked = all.checked; });
        sync();
      });
      form.addEventListener('submit', sync);
      sync();
    });

    document.querySelectorAll('[data-ut-exclusions]').forEach((card) => {
      const protectAdmins = card.querySelector('[data-ut-protect-admins]');
      const options = [...card.querySelectorAll('[data-ut-group-option]')];
      const syncGroups = () => {
        options.forEach((option) => {
          const input = option.querySelector('[data-ut-group]');
          const preserve = option.querySelector('[data-ut-group-preserve]');
          const note = option.querySelector('[data-ut-group-note]');
          const kind = input.dataset.utGroupKind;
          // Super Users are always protected; other backend groups follow "Exclude administrators".
          const locked = kind === 'super' || (kind === 'backend' && protectAdmins.checked);
          if (!input.disabled) input.dataset.utManual = input.checked ? '1' : '0';
          const manual = input.dataset.utManual === '1';
          input.checked = locked || manual;
          input.disabled = locked;
          preserve.disabled = !(locked && manual);
          note.hidden = !locked;
          if (locked) note.textContent = kind === 'super' ? note.dataset.utNoteSuper : note.dataset.utNoteBackend;
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

    document.querySelectorAll('[data-ut-copy]').forEach((button) => {
      button.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(button.dataset.utCopy);
          button.classList.replace('btn-outline-secondary', 'btn-success');
        } catch (e) { /* Clipboard not available: the password stays selectable. */ }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
