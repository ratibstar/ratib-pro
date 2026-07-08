(function (window) {
  'use strict';

  var config = window.RatebAdminConfig || {};

  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'idem-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  function buildUrl(path, query) {
    var base = config.baseUrl || '';
    var url = base + path;
    var params = Object.assign({}, query || {});
    if (!params.lang && config.locale) {
      params.lang = config.locale;
    }
    var parts = [];
    Object.keys(params).forEach(function (key) {
      var value = params[key];
      if (value === undefined || value === null || value === '') {
        return;
      }
      parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
    });
    if (parts.length) {
      url += (url.indexOf('?') === -1 ? '?' : '&') + parts.join('&');
    }
    return url;
  }

  function parseError(payload, status) {
    if (!payload) {
      return 'HTTP ' + status;
    }
    if (payload.errors && payload.errors.length) {
      var first = payload.errors[0];
      if (typeof first === 'string') {
        return first;
      }
      if (first.message) {
        return first.message;
      }
      if (first.error) {
        return first.error;
      }
    }
    if (payload.error) {
      return String(payload.error);
    }
    return 'HTTP ' + status;
  }

  async function request(method, path, options) {
    options = options || {};
    var headers = Object.assign({
      'Accept': 'application/json',
      'X-Rateb-Locale': config.locale || 'ar'
    }, options.headers || {});

    var body = options.body;
    var isForm = typeof FormData !== 'undefined' && body instanceof FormData;
    if (body && !isForm) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }

    if (options.idempotent !== false && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
      if (!headers['Idempotency-Key']) {
        headers['Idempotency-Key'] = uuid();
      }
    }

    if (options.ifMatch) {
      headers['If-Match'] = String(options.ifMatch);
    }

    var response = await fetch(buildUrl(path, options.query), {
      method: method,
      headers: headers,
      body: body || undefined,
      credentials: 'same-origin'
    });

    var payload = null;
    var text = await response.text();
    if (text) {
      try {
        payload = JSON.parse(text);
      } catch (e) {
        payload = { raw: text };
      }
    }

    if (!response.ok) {
      var error = new Error(parseError(payload, response.status));
      error.status = response.status;
      error.payload = payload;
      throw error;
    }

    return {
      status: response.status,
      headers: response.headers,
      data: payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload,
      meta: payload && payload.meta ? payload.meta : {},
      errors: payload && payload.errors ? payload.errors : [],
      etag: response.headers.get('ETag')
    };
  }

  window.RatebAdminApi = {
    get: function (path, query, options) {
      return request('GET', path, Object.assign({}, options || {}, { query: query }));
    },
    post: function (path, body, options) {
      return request('POST', path, Object.assign({}, options || {}, { body: body }));
    },
    put: function (path, body, options) {
      return request('PUT', path, Object.assign({}, options || {}, { body: body }));
    },
    patch: function (path, body, options) {
      return request('PATCH', path, Object.assign({}, options || {}, { body: body }));
    },
    del: function (path, options) {
      return request('DELETE', path, options || {});
    },
    buildUrl: buildUrl
  };
})(window);
