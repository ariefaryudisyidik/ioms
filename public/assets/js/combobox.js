/* Searchable select. Progressive enhancement of every <select> on the page: the select stays in the DOM (hidden) as the source of truth, so form
 * submission, validation and the live stock widget keep working unchanged. A button shows the chosen
 * value (with a clear "x"); opening it shows a panel with a search box above the option list.
 * Without JS the plain select is used. Vanilla JS, no library. */
(function () {
  'use strict';

  let uid = 0;
  const NO_RESULTS = 'No results found';
  const SEARCH_PLACEHOLDER = 'Start typing to search';

  function readOptions(select) {
    return Array.from(select.options)
      .filter(function (option) { return option.value !== ''; })
      .map(function (option) { return { value: option.value, label: option.textContent.trim() }; });
  }

  function el(tag, className, attrs) {
    const node = document.createElement(tag);
    node.className = className;
    Object.keys(attrs || {}).forEach(function (name) { node.setAttribute(name, attrs[name]); });
    return node;
  }

  function createElements(select) {
    uid += 1;
    const listId = 'combobox-list-' + uid;
    const placeholder = (select.options[0] ? select.options[0].textContent : '').trim() || 'Select an option';

    const wrapper = el('div', 'combobox');
    const trigger = el('button', 'combobox-trigger', {
      type: 'button', role: 'combobox', 'aria-haspopup': 'listbox', 'aria-expanded': 'false', 'aria-controls': listId,
    });
    const value = el('span', 'combobox-value');
    trigger.append(value);

    const clear = el('button', 'combobox-clear', { type: 'button', 'aria-label': 'Clear selection' });
    clear.textContent = '×';
    clear.hidden = true;

    const panel = el('div', 'combobox-panel');
    panel.hidden = true;
    const search = el('input', 'combobox-search', {
      type: 'text', autocomplete: 'off', spellcheck: 'false', placeholder: SEARCH_PLACEHOLDER,
      'aria-label': 'Search options', 'aria-controls': listId,
    });
    const list = el('ul', 'combobox-list', { id: listId, role: 'listbox' });
    panel.append(search, list);

    wrapper.append(trigger, clear, panel);
    return { wrapper, trigger, value, clear, panel, search, list, placeholder, id: uid };
  }

  function enhance(select) {
    if (!select || select.dataset.combobox === '1') return;
    select.dataset.combobox = '1';

    const items = readOptions(select);
    const hasEmpty = select.options.length > 0 && select.options[0].value === '';
    const ui = createElements(select);
    let visible = [];
    let active = -1;

    function selectedLabel() {
      const found = items.find(function (item) { return item.value === select.value; });
      return found ? found.label : '';
    }

    function updateDisplay() {
      const label = selectedLabel();
      ui.value.textContent = label || ui.placeholder;
      ui.trigger.classList.toggle('is-placeholder', label === '');
      const clearable = hasEmpty && label !== '';
      ui.trigger.classList.toggle('has-clear', clearable);
      ui.clear.hidden = !clearable;
    }

    function setActive(index) {
      const rows = ui.list.querySelectorAll('.combobox-option');
      if (rows.length === 0) {
        active = -1;
        ui.search.removeAttribute('aria-activedescendant');
        return;
      }
      active = (index + rows.length) % rows.length;
      rows.forEach(function (row, i) { row.classList.toggle('is-active', i === active); });
      ui.search.setAttribute('aria-activedescendant', rows[active].id);
      rows[active].scrollIntoView({ block: 'nearest' });
    }

    function closePanel(returnFocus) {
      if (ui.panel.hidden) return;
      ui.panel.hidden = true;
      ui.trigger.setAttribute('aria-expanded', 'false');
      ui.search.removeAttribute('aria-activedescendant');
      active = -1;
      if (returnFocus) ui.trigger.focus();
    }

    function emitChange() {
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function choose(item) {
      select.value = item.value;
      updateDisplay();
      closePanel(true);
      emitChange();
    }

    function clearSelection() {
      select.value = '';
      updateDisplay();
      emitChange();
    }

    function buildRow(item, index) {
      const row = el('li', 'combobox-option', {
        id: 'combobox-' + ui.id + '-option-' + index, role: 'option',
        'aria-selected': item.value === select.value ? 'true' : 'false',
      });
      row.textContent = item.label;
      // mousedown (not click): the search box keeps focus so focusout does not close the panel first.
      row.addEventListener('mousedown', function (event) {
        event.preventDefault();
        choose(item);
      });
      return row;
    }

    function render(query) {
      const needle = query.trim().toLowerCase();
      visible = items.filter(function (item) { return item.label.toLowerCase().includes(needle); });
      ui.list.replaceChildren();
      if (visible.length === 0) {
        const empty = el('li', 'combobox-empty');
        empty.textContent = NO_RESULTS;
        ui.list.append(empty);
        return;
      }
      ui.list.append(...visible.map(buildRow));
    }

    function openPanel() {
      ui.search.value = '';
      render('');
      ui.panel.hidden = false;
      ui.trigger.setAttribute('aria-expanded', 'true');
      const selectedIndex = visible.findIndex(function (item) { return item.value === select.value; });
      setActive(selectedIndex >= 0 ? selectedIndex : 0);
      ui.search.focus();
    }

    function onTriggerKeydown(event) {
      const opens = ['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key);
      const types = event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey;
      if (opens) event.preventDefault();
      // For a printable key the character is typed into the search box that receives focus.
      if ((opens || types) && ui.panel.hidden) openPanel();
    }

    function onSearchKeydown(event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
      } else if (event.key === 'Enter') {
        event.preventDefault();
        if (visible[active]) choose(visible[active]);
      } else if (event.key === 'Escape') {
        event.stopPropagation();
        closePanel(true);
      } else if (event.key === 'Tab') {
        closePanel(false);
      }
    }

    select.parentNode.insertBefore(ui.wrapper, select);
    select.classList.add('combobox-source');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    updateDisplay();

    ui.trigger.addEventListener('click', function () {
      if (ui.panel.hidden) openPanel();
      else closePanel(false);
    });
    ui.trigger.addEventListener('keydown', onTriggerKeydown);
    ui.clear.addEventListener('mousedown', function (event) { event.preventDefault(); });
    ui.clear.addEventListener('click', function (event) {
      // preventDefault: a wrapping <label> would otherwise forward this click to the trigger.
      event.preventDefault();
      clearSelection();
      ui.trigger.focus();
    });
    ui.search.addEventListener('input', function () {
      render(ui.search.value);
      setActive(0);
    });
    ui.search.addEventListener('keydown', onSearchKeydown);
    ui.panel.addEventListener('mousedown', function (event) {
      if (event.target !== ui.search) event.preventDefault();
    });
    ui.panel.addEventListener('click', function (event) { event.preventDefault(); });
    ui.wrapper.addEventListener('focusout', function (event) {
      if (!ui.wrapper.contains(event.relatedTarget)) closePanel(false);
    });
    document.addEventListener('mousedown', function (event) {
      if (!ui.wrapper.contains(event.target)) closePanel(false);
    });
    select.addEventListener('change', updateDisplay);

    // The visible control keeps the field's label: clicking the label focuses it.
    const label = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    if (label) {
      const labelId = label.id || 'combobox-label-' + ui.id;
      label.id = labelId;
      ui.trigger.setAttribute('aria-labelledby', labelId);
      label.addEventListener('click', function (event) {
        event.preventDefault();
        ui.trigger.focus();
      });
    }
  }

  function enhanceAll(root) {
    (root || document).querySelectorAll('select').forEach(enhance);
  }

  window.IOMS_COMBOBOX = { enhance: enhance, enhanceAll: enhanceAll };
  document.addEventListener('DOMContentLoaded', function () { enhanceAll(document); });
})();
