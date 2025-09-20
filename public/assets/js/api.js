const Api = (() => {
  const apiRoot = (window.APP_CONFIG && window.APP_CONFIG.apiRoot) || '';

  function buildUrl(path, params) {
    const normalizedPath = path.startsWith('/') ? path : `/${path}`;
    const query = new URLSearchParams();
    if (params && typeof params === 'object') {
      Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
          return;
        }
        query.append(key, String(value));
      });
    }
    const queryString = query.toString();
    return `${apiRoot}${normalizedPath}${queryString ? `?${queryString}` : ''}`;
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
