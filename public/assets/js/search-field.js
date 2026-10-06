/* Clear button for search boxes (partials/search-field.php): shown while the box has text; clicking it
 * empties the box and reloads the list without that filter. Vanilla JS; without JS the Reset button works. */
(function () {
  'use strict';

  function wire(box) {
    const input = box.querySelector('input');
    const clear = box.querySelector('.search-clear');
    if (!input || !clear) return;

    const sync = function () { clear.hidden = input.value === ''; };
    input.addEventListener('input', sync);
    clear.addEventListener('click', function () {
      input.value = '';
      sync();
      if (input.form) input.form.submit();
    });
    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.search-box').forEach(wire);
  });
})();
