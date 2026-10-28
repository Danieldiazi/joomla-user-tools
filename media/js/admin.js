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
      const all = form.querySelector('[data-ut-add-all]');
      const sync = () => {
        const data = {};
        let ticked = 0;
        rows.forEach((row) => {
          const add = row.querySelector('[data-ut-add]');
          const email = row.querySelector('[data-ut-email]');
          email.disabled = !add.checked;
          email.required = add.checked;
          if (add.checked) ticked += 1;
          if (add.checked || email.value.trim()) {
            data[row.dataset.utUser] = { checked: add.checked, email: email.value };
          }
        });
        if (all) {
          all.checked = !!rows.length && ticked === rows.length;
          all.indeterminate = ticked > 0 && ticked < rows.length;
        }
        form.querySelector('[data-ut-registrations]').value = JSON.stringify(data);
        form.querySelector('[data-ut-ready]').value = '1';
      };
      rows.forEach((row) => row.querySelector('[data-ut-add]').addEventListener('change', sync));
      if (all) all.addEventListener('change', () => {
        rows.forEach((row) => { row.querySelector('[data-ut-add]').checked = all.checked; });
        sync();
      });

      // Optional: build each email as username@domain. Emails typed by hand are never replaced, and
      // the ones filled here are cleared again when the option is turned off.
      const auto = form.querySelector('[data-ut-email-auto]');
      if (auto) {
        const on = auto.querySelector('[data-ut-email-auto-on]');
        const domain = auto.querySelector('[data-ut-email-domain]');
        const note = auto.querySelector('[data-ut-email-skipped]');
        const localPart = /^[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*$/;
        const validDomain = /^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/;
        const fill = () => {
          domain.disabled = !on.checked;
          // The "@" is already shown before the field; a pasted "@example.com" works too.
          if (domain.value.startsWith('@')) domain.value = domain.value.replace(/^@+/, '');
          const host = domain.value.trim().toLowerCase();
          const usable = on.checked && validDomain.test(host);
          domain.classList.toggle('is-invalid', on.checked && host !== '' && !usable);
          let skipped = 0;
          rows.forEach((row) => {
            const email = row.querySelector('[data-ut-email]');
            const mine = email.dataset.utAuto === '1';
            if (!mine && email.value.trim()) return;
            const name = (row.dataset.utName || '').trim();
            if (usable && name.length <= 64 && localPart.test(name)) {
              email.value = `${name}@${host}`;
              email.dataset.utAuto = '1';
            } else {
              if (mine) email.value = '';
              email.dataset.utAuto = '0';
              if (usable) skipped += 1;
            }
          });
          if (note) {
            const message = on.checked && host !== '' && !usable
              ? text('COM_USERTOOLS_JS_EMAIL_DOMAIN_INVALID', 'Type a valid domain, for example example.com.')
              : (skipped === 1 ? text('COM_USERTOOLS_JS_EMAIL_SKIPPED_1', 'One username cannot be used as an email: type that email yourself.')
                : (skipped ? sprintf(text('COM_USERTOOLS_JS_EMAIL_SKIPPED', '%d usernames cannot be used as an email: type those emails yourself.'), skipped) : ''));
            note.textContent = message;
            note.hidden = !message;
          }
          sync();
        };
        rows.forEach((row) => {
          const email = row.querySelector('[data-ut-email]');
          email.addEventListener('input', () => { email.dataset.utAuto = '0'; sync(); });
        });
        on.addEventListener('change', fill);
        domain.addEventListener('input', fill);
      }
      rows.forEach((row) => row.querySelector('[data-ut-email]').addEventListener('change', sync));
      form.addEventListener('submit', sync);
      sync();
    });

    // Remove from groups: suggestions while typing the account.
    document.querySelectorAll('[data-ut-account-search]').forEach((field) => {
      const list = document.getElementById(field.getAttribute('list'));
      let timer = 0;
      let last = '';
      field.addEventListener('input', () => {
        clearTimeout(timer);
        const term = field.value.trim();
        if (term.length < 2 || term === last) return;
        timer = setTimeout(async () => {
          last = term;
          try {
            const base = field.dataset.utAccountSearch;
            const url = `${base}${base.includes('?') ? '&' : '?'}q=${encodeURIComponent(term)}`;
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const accounts = await response.json();
            list.replaceChildren(...accounts.map((account) => {
              const option = document.createElement('option');
              option.value = account.username;
              option.label = [account.name, account.email].filter(Boolean).join(' · ');
              return option;
            }));
          } catch (e) { /* Suggestions are a convenience: the full username or email still works. */ }
        }, 250);
      });
    });

    // Remove from groups: each unticked group with subgroups offers "and its subgroups"; when ticked,
    // the groups below it are unticked and locked. Every group decides on its own.
    document.querySelectorAll('[data-ut-ungroup-groups]').forEach((form) => {
      const boxes = [...form.querySelectorAll('[data-ut-keep]')];
      const counter = form.querySelector('[data-ut-ungroup-count]');
      const next = form.querySelector('[data-ut-ungroup-next]');
      const byId = new Map(boxes.map((box) => [box.dataset.utKeep, box]));
      const subgroups = new Map([...form.querySelectorAll('[data-ut-subgroups]')].map((box) => [box.dataset.utSubgroups, box]));
      const row = (box) => box.closest('[data-ut-ungroup-row]');
      const title = (box) => [...row(box).querySelector('label').childNodes]
        .find((node) => node.nodeType === 3 && node.textContent.trim()).textContent.trim();
      // utOwn keeps the operator's own tick, so a group unticked only by a cascade comes back
      // ticked when its parent is ticked again or "and its subgroups" is unticked.
      boxes.forEach((box) => { box.dataset.utOwn = box.checked ? '1' : '0'; });
      const cascades = (id) => {
        const box = byId.get(id);
        const sub = subgroups.get(id);
        return !!(box && sub && box.dataset.utOwn === '0' && !box.disabled && sub.checked);
      };
      const sync = () => {
        let removed = 0;
        // Rows are in tree order, so a parent is settled before its children.
        boxes.forEach((box) => {
          const parentId = (box.dataset.utParents || '').split(',').filter(Boolean).find((id) => cascades(id));
          const parent = parentId ? byId.get(parentId) : undefined;
          // A box disabled by a cascade is not submitted, so the server removes that group too.
          box.disabled = !!parent;
          box.checked = parent ? false : box.dataset.utOwn === '1';
          const sub = subgroups.get(box.dataset.utKeep);
          if (sub) {
            // "And its subgroups" only makes sense for a group the operator unticked.
            const offer = !parent && box.dataset.utOwn === '0';
            sub.closest('[data-ut-subgroups-wrap]').hidden = !offer;
            sub.disabled = !offer;
          }
          const note = row(box).querySelector('[data-ut-cascade-note]');
          if (note) {
            note.hidden = !parent;
            note.textContent = parent ? sprintf(text('COM_USERTOOLS_JS_UNGROUP_CASCADED', 'Removed because it is inside «%s».'), title(parent)) : '';
          }
          if (!box.checked) removed += 1;
        });
        if (counter) {
          counter.textContent = removed
            ? sprintf(text(removed === 1 ? 'COM_USERTOOLS_JS_UNGROUP_COUNT_1' : 'COM_USERTOOLS_JS_UNGROUP_COUNT', 'It will leave %d groups.'), removed) : '';
        }
        if (next) next.disabled = !removed;
      };
      boxes.forEach((box) => box.addEventListener('change', () => { box.dataset.utOwn = box.checked ? '1' : '0'; sync(); }));
      subgroups.forEach((sub) => sub.addEventListener('change', sync));
      // Send the operator's own ticks, not the cascades shown on screen: the server applies them
      // itself and can tell the review which group caused each removal.
      form.addEventListener('submit', () => {
        boxes.forEach((box) => { box.disabled = false; box.checked = box.dataset.utOwn === '1'; });
        // Also keep "and its subgroups" on groups the operator unticked even when a parent's cascade
        // currently covers them, so the choice survives "Back to edit" and undoing the parent's cascade.
        subgroups.forEach((sub, id) => { sub.disabled = !(byId.get(id) && byId.get(id).dataset.utOwn === '0'); });
      });
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
