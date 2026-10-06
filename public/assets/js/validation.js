/* Client-side UX validation only. The backend re-validates everything
 * (VAL-01) - this is purely to give the user faster feedback. */
(function (global) {
  'use strict';

  function showError(field, message) {
    clearError(field);
    var wrapper = field.closest('.field') || field.parentElement;
    if (wrapper) {
      wrapper.classList.add('has-error');
    }
    var el = document.createElement('div');
    el.className = 'field-error js-error';
    el.textContent = message;
    if (wrapper) {
      wrapper.appendChild(el);
    }
  }

  function clearError(field) {
    var wrapper = field.closest('.field') || field.parentElement;
    if (!wrapper) return;
    wrapper.classList.remove('has-error');
    var existing = wrapper.querySelector('.js-error');
    if (existing) existing.remove();
  }

  function clearAllErrors(form) {
    form.querySelectorAll('.js-error').forEach(function (el) { el.remove(); });
    form.querySelectorAll('.has-error').forEach(function (el) { el.classList.remove('has-error'); });
  }

  function isValidDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    var d = new Date(value + 'T00:00:00');
    return !isNaN(d.getTime());
  }

  /**
   * Returns the error message for a single field based on its data attributes
   * (data-required, data-type="number|date|email", data-min, data-max), or null.
   */
  function fieldError(field) {
    var value = (field.value || '').trim();

    if (field.hasAttribute('data-required') && value === '') {
      return 'This field is required.';
    }
    if (value === '') return null;

    var type = field.getAttribute('data-type');
    if (type === 'number') {
      var num = Number(value);
      if (isNaN(num)) return 'Must be a number.';
      var min = field.getAttribute('data-min');
      if (min !== null && num < Number(min)) return 'Must be at least ' + min + '.';
      var max = field.getAttribute('data-max');
      if (max !== null && num > Number(max)) return 'Must be at most ' + max + '.';
    } else if (type === 'date') {
      if (!isValidDate(value)) return 'Enter a valid date (YYYY-MM-DD).';
    } else if (type === 'email') {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address.';
    }
    return null;
  }

  /** Validates a form generically. Returns true if valid. */
  function validateForm(form) {
    clearAllErrors(form);
    var valid = true;

    form.querySelectorAll('[data-required], [data-type]').forEach(function (field) {
      var message = fieldError(field);
      if (message) {
        showError(field, message);
        valid = false;
      }
    });

    // data-any-positive="<selector>": at least one matching input must be greater than zero.
    var anySelector = form.getAttribute('data-any-positive');
    if (valid && anySelector) {
      var group = Array.prototype.filter.call(form.querySelectorAll(anySelector), function (el) { return !el.disabled; });
      var anyPositive = group.some(function (el) { return Number(el.value) > 0; });
      if (group.length > 0 && !anyPositive) {
        showError(group[0], 'Enter a quantity to receive for at least one item.');
        valid = false;
      }
    }

    return valid;
  }

  // Re-check a field as soon as it changes: the error disappears once it is valid.
  function revalidateField(event) {
    var field = event.target;
    if (!field.matches || !field.matches('[data-required], [data-type]')) return;
    var wrapper = field.closest('.field') || field.parentElement;
    if (!wrapper || !wrapper.classList.contains('has-error')) return;
    wrapper.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
    wrapper.classList.remove('has-error');
    var message = fieldError(field);
    if (message) showError(field, message);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.addEventListener('input', revalidateField);
      form.addEventListener('change', revalidateField);
      form.addEventListener('submit', function (event) {
        if (!validateForm(form)) {
          event.preventDefault();
          var firstError = form.querySelector('.has-error input, .has-error select, .has-error textarea');
          if (firstError) firstError.focus();
        }
      });
    });

    // data-confirm dialogs live in confirm-dialog.js.
  });

  global.IOMS_VALIDATION = {
    validateForm: validateForm,
  };
})(window);
