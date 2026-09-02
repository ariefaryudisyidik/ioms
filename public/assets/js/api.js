/* Thin fetch wrapper for IOMS JSON API endpoints. */
(function (global) {
  'use strict';

  /**
   * GET /api/products/{sku}/availability
   * Resolves with { ok, status, data } - never rejects on HTTP errors so
   * callers can render a friendly message instead of an uncaught error.
   */
  function getProductAvailability(sku) {
    var url = '/api/products/' + encodeURIComponent(sku) + '/availability';

    return fetch(url, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (data) {
        return { ok: response.ok, status: response.status, data: data };
      });
    }).catch(function () {
      return { ok: false, status: 0, data: { error: 'Network error. Please check your connection.' } };
    });
  }

  global.IOMS_API = {
    getProductAvailability: getProductAvailability,
  };
})(window);
