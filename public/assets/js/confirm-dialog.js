/* In-page confirmation dialog for forms marked with data-confirm="message".
 * Vanilla JS on top of the native <dialog> element (focus trap, Esc and
 * backdrop come from the browser). Without JS the form still submits
 * normally; without <dialog> support it falls back to window.confirm. */
(function () {
  'use strict';

  let dialog = null;
  let titleEl = null;
  let messageEl = null;
  let confirmBtn = null;
  let resolver = null;

  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'confirm-dialog-title');
    dialog.setAttribute('aria-describedby', 'confirm-dialog-message');
    dialog.innerHTML =
      '<form method="dialog">' +
      '<h2 class="confirm-title" id="confirm-dialog-title">Please confirm</h2>' +
      '<p class="confirm-message" id="confirm-dialog-message"></p>' +
      '<div class="confirm-actions">' +
      '<button type="submit" value="cancel" class="btn btn-secondary">Go back</button>' +
      '<button type="submit" value="confirm" class="btn js-confirm-ok"></button>' +
      '</div></form>';
    document.body.appendChild(dialog);

    titleEl = dialog.querySelector('.confirm-title');
    messageEl = dialog.querySelector('.confirm-message');
    confirmBtn = dialog.querySelector('.js-confirm-ok');

    dialog.addEventListener('close', function () {
      const accepted = dialog.returnValue === 'confirm';
      dialog.returnValue = '';
      if (resolver) {
        const done = resolver;
        resolver = null;
        done(accepted);
      }
    });
    // Click on the backdrop (the dialog element itself) dismisses.
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close('cancel');
    });
  }

  /** Resolves true when the user confirms, false when they go back. */
  function ask(message, confirmLabel, danger) {
    if (typeof HTMLDialogElement === 'undefined') {
      return Promise.resolve(window.confirm(message));
    }
    if (!dialog) build();

    titleEl.textContent = danger ? 'Are you sure?' : 'Please confirm';
    messageEl.textContent = message;
    confirmBtn.textContent = confirmLabel;
    confirmBtn.className = 'btn js-confirm-ok' + (danger ? ' btn-danger' : '');

    return new Promise(function (resolve) {
      resolver = resolve;
      dialog.returnValue = '';
      dialog.showModal();
      // Focus the safe choice first so Enter never confirms by accident.
      dialog.querySelector('[value="cancel"]').focus();
    });
  }

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!form.matches || !form.matches('form[data-confirm]')) return;

    if (form.dataset.confirmed === '1') {
      delete form.dataset.confirmed;
      return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    const label = submitter ? submitter.textContent.trim() : 'Confirm';
    const danger = !!submitter && submitter.classList.contains('btn-danger');

    ask(form.getAttribute('data-confirm') || 'Are you sure?', label || 'Confirm', danger).then(function (accepted) {
      if (!accepted) return;
      form.dataset.confirmed = '1';
      if (form.requestSubmit) {
        form.requestSubmit(submitter || undefined);
      } else {
        form.submit();
      }
    });
  });
})();
