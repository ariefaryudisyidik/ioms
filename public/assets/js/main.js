/* General page glue: responsive nav toggle + live stock availability
 * widget used on the Sales Order creation form. Progressive enhancement:
 * everything here is additive UX, the underlying forms work without JS. */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.hamburger');
    var nav = document.querySelector('.app-nav');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        nav.classList.toggle('open');
      });
      document.addEventListener('click', function (event) {
        if (!nav.classList.contains('open')) return;
        if (nav.contains(event.target) || toggle.contains(event.target)) return;
        nav.classList.remove('open');
      });
    }

    initAvailabilityWidgets();
  });

  /**
   * Wires up any ".js-availability-check" row: a product <select> (with
   * data-sku on each <option>) + a warehouse <select>, reporting live
   * stock into the adjacent ".availability-box" without a page reload.
   */
  function initAvailabilityWidgets() {
    var rows = document.querySelectorAll('.js-item-row');
    rows.forEach(wireRow);

    var addBtn = document.querySelector('.js-add-item-row');
    if (addBtn) {
      // Start past the statically rendered row(s) (items[0]...) and never
      // reuse an index, even after a row in the middle is removed - reusing
      // a live index would merge an unrelated new row's fields into an
      // existing item.
      var nextIndex = document.querySelectorAll('.js-item-rows .js-item-row').length;
      addBtn.addEventListener('click', function () {
        var template = document.querySelector('.js-item-row-template');
        var container = document.querySelector('.js-item-rows');
        if (!template || !container) return;
        var clone = template.content.firstElementChild.cloneNode(true);
        // Each item field must share the same numeric index (items[N][field])
        // so PHP groups them into one row instead of one row per field -
        // a bare "items[][field]" repeated across fields makes PHP append a
        // new top-level index for every occurrence, splitting one logical
        // row into several incomplete ones.
        clone.querySelectorAll('[name*="__INDEX__"]').forEach(function (field) {
          field.name = field.name.replace('__INDEX__', String(nextIndex));
        });
        nextIndex += 1;
        container.appendChild(clone);
        wireRow(clone);
      });
    }

    document.addEventListener('click', function (event) {
      var removeBtn = event.target.closest('.js-remove-item-row');
      if (removeBtn) {
        var row = removeBtn.closest('.js-item-row');
        if (row) row.remove();
      }
    });
  }

  function wireRow(row) {
    var productSelect = row.querySelector('.js-product-select');
    var warehouseSelect = row.querySelector('.js-warehouse-select') || document.querySelector('.js-order-warehouse');
    var box = row.querySelector('.availability-box');

    if (!productSelect || !box) return;

    function check() {
      var option = productSelect.options[productSelect.selectedIndex];
      var sku = option ? option.getAttribute('data-sku') : '';
      if (!sku) {
        box.textContent = '';
        box.className = 'availability-box';
        return;
      }

      box.textContent = 'Checking availability...';
      box.className = 'availability-box';

      window.IOMS_API.getProductAvailability(sku).then(function (result) {
        if (!result.ok) {
          if (result.status === 401) {
            box.textContent = 'Session expired. Please log in again.';
          } else if (result.status === 404) {
            box.textContent = 'Product not found.';
          } else {
            box.textContent = (result.data && result.data.error) || 'Could not check availability.';
          }
          box.className = 'availability-box state-error';
          return;
        }

        var data = result.data;
        var warehouseId = warehouseSelect ? warehouseSelect.value : '';
        var warehouseName = warehouseSelect && warehouseSelect.options[warehouseSelect.selectedIndex]
          ? warehouseSelect.options[warehouseSelect.selectedIndex].textContent.trim()
          : null;

        var qtyInThisWarehouse = null;
        (data.warehouses || []).forEach(function (w) {
          if (warehouseName && w.warehouse === warehouseName) {
            qtyInThisWarehouse = w.quantity;
          }
        });

        var lines = [];
        lines.push('<strong>' + escapeHtml(data.name || '') + '</strong> - total stock: ' + Number(data.total));
        if (warehouseId && qtyInThisWarehouse !== null) {
          lines.push('In selected warehouse: <strong>' + Number(qtyInThisWarehouse) + '</strong> unit(s)');
        }
        var list = '<ul>' + (data.warehouses || []).map(function (w) {
          return '<li>' + escapeHtml(w.warehouse) + ': ' + Number(w.quantity) + '</li>';
        }).join('') + '</ul>';

        box.innerHTML = lines.join('<br>') + list;

        var qtyInput = row.querySelector('.js-qty-input');
        var lowStock = qtyInThisWarehouse !== null ? qtyInThisWarehouse : data.total;
        var warnOnExceed = !!row.closest('form[data-warn-exceed]');
        if (warnOnExceed && qtyInput && Number(qtyInput.value || 0) > lowStock) {
          box.className = 'availability-box state-warn';
          box.innerHTML += '<div>Warning: requested quantity may exceed available stock.</div>';
        } else {
          box.className = 'availability-box state-ok';
        }
      });
    }

    productSelect.addEventListener('change', check);
    if (warehouseSelect) warehouseSelect.addEventListener('change', check);
    var qtyInput = row.querySelector('.js-qty-input');
    if (qtyInput) qtyInput.addEventListener('input', check);
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }
})();
