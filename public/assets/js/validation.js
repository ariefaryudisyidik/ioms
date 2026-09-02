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
   * Validates a form generically based on data attributes:
   *   data-required, data-type="number|date|email", data-min="0"
   * Returns true if valid.
   */
  function validateForm(form) {
    clearAllErrors(form);
    var valid = true;
    var fields = form.querySelectorAll('[data-required], [data-type]');

    fields.forEach(function (field) {
      var value = (field.value || '').trim();

      if (field.hasAttribute('data-required') && value === '') {
        showError(field, 'This field is required.');
        valid = false;
        return;
      }

      if (value === '') return;

      var type = field.getAttribute('data-type');
      if (type === 'number') {
        var num = Number(value);
        if (isNaN(num)) {
          showError(field, 'Must be a number.');
          valid = false;
          return;
        }
        var min = field.getAttribute('data-min');
        if (min !== null && num < Number(min)) {
          showError(field, 'Must be at least ' + min + '.');
          valid = false;
          return;
        }
      } else if (type === 'date') {
        if (!isValidDate(value)) {
          showError(field, 'Enter a valid date (YYYY-MM-DD).');
          valid = false;
          return;
        }
      } else if (type === 'email') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          showError(field, 'Enter a valid email address.');
          valid = false;
          return;
        }
      }
    });

    return valid;
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!validateForm(form)) {
          event.preventDefault();
          var firstError = form.querySelector('.has-error input, .has-error select, .has-error textarea');
          if (firstError) firstError.focus();
        }
      });
    });

    // Destructive action confirmations (progressive enhancement: forms
    // still submit normally if JS is disabled or the user confirms).
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('submit', function (event) {
        var message = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
      el.addEventListener('click', function (event) {
        if (el.tagName !== 'BUTTON' && el.tagName !== 'A') return;
      });
    });

    document.querySelectorAll('button[data-confirm], a[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (event) {
        var message = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  });

  global.IOMS_VALIDATION = {
    validateForm: validateForm,
  };
})(window);
