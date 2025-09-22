const Api = (() => {
  function resolveApiBase() {
    const rawRoot = (window.APP_CONFIG && window.APP_CONFIG.apiRoot) || '';
    const trimmed = typeof rawRoot === 'string' ? rawRoot.trim() : '';

    const ensureTrailingSlash = (value) => (value.endsWith('/') ? value : `${value}/`);

    if (!trimmed) {
      return new URL('./', window.location.href);
    }

    if (/^https?:\/\//i.test(trimmed)) {
      return new URL(ensureTrailingSlash(trimmed));
    }

    if (trimmed.startsWith('//')) {
      return new URL(`${window.location.protocol}${ensureTrailingSlash(trimmed)}`);
    }

    if (trimmed.startsWith('/')) {
      return new URL(ensureTrailingSlash(trimmed), window.location.origin);
    }

    return new URL(ensureTrailingSlash(trimmed), window.location.href);
  }

  const apiBase = resolveApiBase();

  function buildUrl(path, params) {
    const rawPath = typeof path === 'string' ? path.trim() : '';
    const isAbsolute = /^https?:\/\//i.test(rawPath) || rawPath.startsWith('//');
    const normalizedPath = rawPath.replace(/^\/+/g, '');

    const url = isAbsolute
      ? new URL(rawPath, window.location.href)
      : new URL(normalizedPath, apiBase);

    if (params && typeof params === 'object') {
      Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
          return;
        }
        url.searchParams.append(key, String(value));
      });
    }

    return url.toString();
  }

  async function request(path, options = {}) {
    const { method = 'GET', params, body, headers } = options;
    const url = buildUrl(path, params);
    const init = {
      method,
      headers: {
        'Accept': 'application/json',
        ...(headers || {}),
      },
    };

    if (body !== undefined) {
      init.body = JSON.stringify(body);
      init.headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, init);
    const text = await response.text();
    let data = null;
    if (text) {
      try {
        data = JSON.parse(text);
      } catch (error) {
        throw new Error(`Некорректный JSON от ${url}: ${error.message}`);
      }
    }

    if (!response.ok) {
      const message = data && data.error ? data.error : `HTTP ${response.status}`;
      const err = new Error(message);
      err.status = response.status;
      err.payload = data;
      throw err;
    }

    return data;
  }

  return {
    auth: {
      session() {
        return request('/api/auth.php', { params: { action: 'session' } });
      },
      requestTrueApiKey(inn) {
        return request('/api/auth.php', { params: { action: 'true-api-key', inn } });
      },
      signInTrueApi(payload) {
        return request('/api/auth.php?action=true-api-signin', {
          method: 'POST',
          body: payload,
        });
      },
      requestSuzKey(omsId) {
        return request('/api/auth.php', { params: { action: 'suz-key', omsId } });
      },
      signInSuz(payload) {
        return request('/api/auth.php?action=suz-signin', {
          method: 'POST',
          body: payload,
        });
      },
    },
    catalog: {
      list(params) {
        return request('/api/catalog.php', { params: { action: 'list', ...(params || {}) } });
      },
      details(goodIds) {
        return request('/api/catalog.php?action=details', {
          method: 'POST',
          body: { goodIds },
        });
      },
      docsForSign(goodIds, publicationAgreement = true) {
        return request('/api/catalog.php?action=docs-for-sign', {
          method: 'POST',
          body: { goodIds, publicationAgreement },
        });
      },
      sendSignatures(pack) {
        return request('/api/catalog.php?action=send-signatures', {
          method: 'POST',
          body: { pack },
        });
      },
    },
    orders: {
      create(payload) {
        return request('/api/orders.php?action=create', {
          method: 'POST',
          body: payload,
        });
      },
      list(params) {
        return request('/api/orders.php', {
          params: { action: 'list', ...(params || {}) },
        });
      },
      close(payload) {
        return request('/api/orders.php?action=close', {
          method: 'POST',
          body: payload,
        });
      },
      dropout(payload) {
        return request('/api/orders.php?action=dropout', {
          method: 'POST',
          body: payload,
        });
      },
      utilisation(payload) {
        return request('/api/orders.php?action=utilisation', {
          method: 'POST',
          body: payload,
        });
      },
    },
    documents: {
      create(payload) {
        return request('/api/documents.php?action=create', {
          method: 'POST',
          body: payload,
        });
      },
      list(params) {
        return request('/api/documents.php', {
          params: { action: 'list', ...(params || {}) },
        });
      },
      info(params) {
        return request('/api/documents.php', {
          params: { action: 'info', ...(params || {}) },
        });
      },
    },
  };
})();
