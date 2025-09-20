(function () {
  const elements = {};
  const state = {
    certificate: null,
    certificateThumbprint: '',
    trueApi: {
      challenge: null,
      token: null,
      expiresAt: null,
      organization: null,
    },
    suz: {
      challenge: null,
      clientToken: null,
      expiresAt: null,
      omsId: '',
      omsConnection: '',
    },
    catalog: {
      items: [],
    },
  };

  function $id(id) {
    const cached = elements[id];
    if (cached) {
      return cached;
    }
    const node = document.getElementById(id);
    elements[id] = node;
    return node;
  }

  function log(message) {
    const target = $id('logArea');
    if (!target) {
      // eslint-disable-next-line no-console
      console.log(message);
      return;
    }
    const timestamp = new Date().toLocaleTimeString('ru-RU', { hour12: false });
    target.textContent += `\n[${timestamp}] ${message}`;
    target.scrollTop = target.scrollHeight;
  }

  function updateStatus(id, status, text) {
    const node = $id(id);
    if (!node) return;
    node.textContent = text;
    node.classList.remove('success', 'danger');
    if (status) {
      node.classList.add(status);
    }
  }

  function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (!Number.isFinite(date.getTime())) {
      return value;
    }
    return date.toLocaleString('ru-RU');
  }

  async function refreshCertificates() {
    const select = $id('certificateSelect');
    if (!select) return;
    select.innerHTML = '<option>Загрузка сертификатов…</option>';
    try {
      const certificates = await Signature.loadCertificates();
      select.innerHTML = '';
      certificates.forEach((cert) => {
        const option = document.createElement('option');
        option.value = cert.thumbprint;
        option.textContent = `${cert.subject} (до ${formatDate(cert.validTo)})`;
        select.appendChild(option);
      });
      state.certificateThumbprint = certificates[0]?.thumbprint || '';
      if (state.certificateThumbprint) {
        select.value = state.certificateThumbprint;
      }
      updateCertificateInfo();
      log('✅ Сертификаты загружены.');
    } catch (error) {
      select.innerHTML = '<option value="">Сертификаты недоступны</option>';
      state.certificateThumbprint = '';
      log(`❌ ${error.message || error}`);
    }
  }

  function updateCertificateInfo() {
    const info = $id('selectedCertificateInfo');
    if (!info) return;
    if (!state.certificateThumbprint) {
      info.textContent = 'Сертификат не выбран';
      return;
    }
    const cert = Signature.getCertificates().find((item) => item.thumbprint === state.certificateThumbprint);
    if (!cert) {
      info.textContent = 'Сертификат не выбран';
      return;
    }
    info.textContent = `${cert.subject} — действует до ${formatDate(cert.validTo)}`;
  }

  function requireCertificate() {
    if (!state.certificateThumbprint) {
      throw new Error('Выберите сертификат для подписи.');
    }
  }

  async function loadSession() {
    try {
      const session = await Api.auth.session();
      if (session.trueApi) {
        state.trueApi.token = session.trueApi.token || null;
        state.trueApi.expiresAt = session.trueApi.expiresAt || null;
        state.trueApi.organization = session.trueApi.organization || null;
      }
      if (session.suz) {
        state.suz.clientToken = session.suz.clientToken || null;
        state.suz.expiresAt = session.suz.expiresAt || null;
        state.suz.omsId = session.suz.omsId || '';
        state.suz.omsConnection = session.suz.omsConnection || '';
        const omsInput = $id('suzOmsId');
        if (omsInput && state.suz.omsId) {
          omsInput.value = state.suz.omsId;
        }
        const connectionInput = $id('suzOmsConnection');
        if (connectionInput && state.suz.omsConnection) {
          connectionInput.value = state.suz.omsConnection;
        }
        const orderOmsInput = $id('orderOmsId');
        if (orderOmsInput && state.suz.omsId) {
          orderOmsInput.value = state.suz.omsId;
        }
      }
      updateAuthStatus();
    } catch (error) {
      log(`⚠️ Не удалось загрузить сессию: ${error.message || error}`);
    }
  }

  function updateAuthStatus() {
    if (state.trueApi.token) {
      const details = state.trueApi.organization ? `${state.trueApi.organization.name || ''}` : 'Токен получен';
      updateStatus('trueApiStatus', 'success', `${details} (до ${formatDate(state.trueApi.expiresAt)})`);
    } else {
      updateStatus('trueApiStatus', '', 'Токен отсутствует');
    }

    if (state.suz.clientToken) {
      updateStatus('suzStatus', 'success', `clientToken активен (до ${formatDate(state.suz.expiresAt)})`);
    } else {
      updateStatus('suzStatus', '', 'clientToken отсутствует');
    }
  }

  async function requestTrueApiChallenge() {
    const inn = ($id('trueApiInn')?.value || '').trim();
    log('Запрашиваем challenge True API…');
    const response = await Api.auth.requestTrueApiKey(inn || undefined);
    state.trueApi.challenge = response.challenge;
    const pre = $id('trueApiChallenge');
    if (pre) {
      pre.textContent = JSON.stringify(response.challenge, null, 2);
    }
    log('✅ Challenge True API получен.');
  }

  async function signInTrueApi() {
    requireCertificate();
    if (!state.trueApi.challenge || !state.trueApi.challenge.data || !state.trueApi.challenge.uuid) {
      throw new Error('Сначала запросите challenge True API.');
    }
    const inn = ($id('trueApiInn')?.value || '').trim();
    const unitedToken = Boolean($id('trueApiUnitedToken')?.checked);
    log('Подписываем challenge True API…');
    const signature = await Signature.signForAuth(state.trueApi.challenge.data, state.certificateThumbprint);
    const payload = {
      uuid: state.trueApi.challenge.uuid,
      signature,
    };
    if (inn) {
      payload.inn = inn;
    }
    if (unitedToken) {
      payload.unitedToken = true;
    }
    const result = await Api.auth.signInTrueApi(payload);
    state.trueApi.token = result.token || null;
    state.trueApi.expiresAt = result.expiresAt || null;
    state.trueApi.organization = result.organization || null;
    updateAuthStatus();
    log('✅ Авторизация True API завершена.');
  }

  async function requestSuzChallenge() {
    const omsId = ($id('suzOmsId')?.value || '').trim();
    if (!omsId) {
      throw new Error('Укажите omsId.');
    }
    log('Запрашиваем challenge СУЗ…');
    const response = await Api.auth.requestSuzKey(omsId);
    state.suz.challenge = response.challenge;
    const pre = $id('suzChallenge');
    if (pre) {
      pre.textContent = JSON.stringify(response.challenge, null, 2);
    }
    log('✅ Challenge СУЗ получен.');
  }

  async function signInSuz() {
    requireCertificate();
    if (!state.suz.challenge || !state.suz.challenge.data) {
      throw new Error('Сначала запросите challenge СУЗ.');
    }
    const omsId = ($id('suzOmsId')?.value || '').trim();
    const omsConnection = ($id('suzOmsConnection')?.value || '').trim();
    if (!omsId || !omsConnection) {
      throw new Error('Укажите omsId и omsConnection.');
    }
    log('Подписываем challenge СУЗ…');
    const signature = await Signature.signForAuth(state.suz.challenge.data, state.certificateThumbprint);
    const payload = {
      signature,
      omsId,
      omsConnection,
    };
    if (state.suz.challenge.uuid) {
      payload.uuid = state.suz.challenge.uuid;
    }
    const result = await Api.auth.signInSuz(payload);
    state.suz.clientToken = result.clientToken || null;
    state.suz.expiresAt = result.expiresAt || null;
    state.suz.omsId = omsId;
    state.suz.omsConnection = omsConnection;
    const orderOmsInput = $id('orderOmsId');
    if (orderOmsInput) {
      orderOmsInput.value = omsId;
    }
    updateAuthStatus();
    log('✅ Авторизация СУЗ завершена.');
  }

  async function loadCatalog(event) {
    event.preventDefault();
    const params = {
      search: $id('catalogSearch')?.value || undefined,
      group: $id('catalogGroup')?.value || undefined,
      dateFrom: $id('catalogDateFrom')?.value || undefined,
      dateTo: $id('catalogDateTo')?.value || undefined,
      limit: $id('catalogLimit')?.value || undefined,
    };
    log('Запрашиваем карточки НК…');
    const result = await Api.catalog.list(params);
    const goods = result.goods || result || [];
    state.catalog.items = goods;
    renderCatalog(goods);
    log(`ℹ️ Загружено карточек: ${goods.length}`);
  }

  function resolveGtin(item) {
    if (item.gtin) return String(item.gtin);
    const identifiers = item.identified_by || item.identifiers || [];
    for (const entry of identifiers) {
      if ((entry.type || '').toLowerCase() === 'gtin' && entry.value) {
        return String(entry.value);
      }
    }
    return '';
  }

  function resolveName(item) {
    return item.good_name || item.name || item.title || '';
  }

  function resolveBrand(item) {
    return item.brand_name || item.brand || '';
  }

  function renderCatalog(items) {
    const body = $id('catalogTableBody');
    const counter = $id('catalogCounter');
    if (!body) return;
    body.innerHTML = '';
    items.forEach((item, index) => {
      const row = document.createElement('tr');
      row.dataset.index = String(index);

      const checkboxCell = document.createElement('td');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.dataset.index = String(index);
      checkboxCell.appendChild(checkbox);
      row.appendChild(checkboxCell);

      const idCell = document.createElement('td');
      idCell.innerHTML = `<span class="badge">${item.goodId || item.good_id || ''}</span>`;
      row.appendChild(idCell);

      const gtinCell = document.createElement('td');
      gtinCell.textContent = resolveGtin(item);
      row.appendChild(gtinCell);

      const nameCell = document.createElement('td');
      nameCell.textContent = resolveName(item);
      row.appendChild(nameCell);

      const brandCell = document.createElement('td');
      brandCell.textContent = resolveBrand(item);
      row.appendChild(brandCell);

      const quantityCell = document.createElement('td');
      const quantityInput = document.createElement('input');
      quantityInput.type = 'number';
      quantityInput.min = '1';
      quantityInput.value = '100';
      quantityInput.className = 'quantity-input';
      quantityCell.appendChild(quantityInput);
      row.appendChild(quantityCell);

      const updatedCell = document.createElement('td');
      updatedCell.textContent = formatDate(item.updated_at || item.update_date || item.modifiedAt || item.modified_at);
      row.appendChild(updatedCell);

      body.appendChild(row);
    });

    if (counter) {
      counter.textContent = String(items.length);
    }
  }

  function collectSelectedGoods() {
    const rows = Array.from(document.querySelectorAll('#catalogTableBody tr'));
    return rows
      .map((row) => {
        const checkbox = row.querySelector('input[type="checkbox"]');
        if (!checkbox || !checkbox.checked) {
          return null;
        }
        const index = Number(row.dataset.index || checkbox.dataset.index || 0);
        const quantityInput = row.querySelector('input[type="number"]');
        const quantity = quantityInput ? Math.max(1, Number(quantityInput.value || '1')) : 1;
        const source = state.catalog.items[index] || {};
        return {
          index,
          quantity,
          source,
        };
      })
      .filter(Boolean);
  }

  function buildOrderTemplate() {
    const goods = collectSelectedGoods();
    if (!goods.length) {
      throw new Error('Выберите позиции каталога для формирования шаблона.');
    }
    const products = goods.map(({ source, quantity }) => ({
      gtin: resolveGtin(source),
      name: resolveName(source),
      quantity,
      templateId: source.templateId || '',
    }));
    const payload = {
      productGroup: 'lp',
      orderType: 'LP_KM_EMISSION',
      products,
      serialNumberType: 'OPERATOR',
      packagingType: 'UNIT',
    };
    const textarea = $id('orderPayload');
    if (textarea) {
      textarea.value = JSON.stringify(payload, null, 2);
    }
    log('📦 Заготовка заказа сформирована. Проверьте templateId и дополнительные атрибуты.');
  }

  async function sendOrder() {
    requireCertificate();
    const textarea = $id('orderPayload');
    if (!textarea || !textarea.value.trim()) {
      throw new Error('Заполните payload заказа.');
    }
    const omsId = ($id('orderOmsId')?.value || '').trim() || state.suz.omsId;
    if (!omsId) {
      throw new Error('Укажите omsId для заказа.');
    }
    const clientToken = state.suz.clientToken;
    if (!clientToken) {
      throw new Error('clientToken отсутствует. Авторизуйтесь в СУЗ.');
    }
    let payload;
    try {
      payload = JSON.parse(textarea.value);
    } catch (error) {
      throw new Error(`Некорректный JSON заказа: ${error.message}`);
    }
    const canonical = JSON.stringify(payload, null, 2);
    log('✍️ Подписываем заказ СУЗ…');
    const signature = await Signature.signUtf8Detached(canonical, state.certificateThumbprint);
    const result = await Api.orders.create({
      omsId,
      payload,
      signature,
    });
    log(`✅ Заказ отправлен. Ответ: ${JSON.stringify(result.order || result, null, 2)}`);
    await refreshOrders();
  }

  async function refreshOrders() {
    if (!state.suz.clientToken) {
      log('⚠️ Невозможно запросить список заказов без clientToken.');
      return;
    }
    const omsId = state.suz.omsId || ($id('orderOmsId')?.value || '').trim();
    if (!omsId) {
      log('⚠️ Укажите omsId для получения списка заказов.');
      return;
    }
    log('Запрашиваем список заказов…');
    const result = await Api.orders.list({ omsId, limit: 50 });
    renderOrders(result.orders || result.list || result);
  }

  function renderOrders(list) {
    const body = $id('ordersTableBody');
    if (!body) return;
    const orders = Array.isArray(list) ? list : (list?.records || []);
    body.innerHTML = '';
    orders.forEach((order) => {
      const row = document.createElement('tr');
      const idCell = document.createElement('td');
      idCell.textContent = order.orderId || order.id || '';
      row.appendChild(idCell);

      const statusCell = document.createElement('td');
      statusCell.textContent = order.status || order.state || '';
      row.appendChild(statusCell);

      const pgCell = document.createElement('td');
      pgCell.textContent = order.productGroup || order.pg || '';
      row.appendChild(pgCell);

      const qtyCell = document.createElement('td');
      qtyCell.textContent = order.quantity || order.totalQuantity || '';
      row.appendChild(qtyCell);

      const createdCell = document.createElement('td');
      createdCell.textContent = formatDate(order.createdAt || order.createDate || order.created_at);
      row.appendChild(createdCell);

      body.appendChild(row);
    });
  }

  async function closeOrder() {
    requireCertificate();
    const orderId = ($id('closeOrderId')?.value || '').trim();
    if (!orderId) {
      throw new Error('Укажите orderId.');
    }
    const payload = { orderId };
    const canonical = JSON.stringify(payload, null, 2);
    const signature = await Signature.signUtf8Detached(canonical, state.certificateThumbprint);
    const result = await Api.orders.close({
      orderId,
      signature,
    });
    log(`ℹ️ Ответ закрытия заказа: ${JSON.stringify(result, null, 2)}`);
    await refreshOrders();
  }

  async function sendDocument() {
    requireCertificate();
    const productGroup = ($id('documentProductGroup')?.value || '').trim();
    if (!productGroup) {
      throw new Error('Укажите код товарной группы.');
    }
    const docFormat = ($id('documentFormat')?.value || '').trim();
    const docType = ($id('documentType')?.value || '').trim();
    const bodyField = $id('documentBody');
    if (!bodyField || !bodyField.value.trim()) {
      throw new Error('Введите JSON документа.');
    }
    let parsed;
    try {
      parsed = JSON.parse(bodyField.value);
    } catch (error) {
      throw new Error(`Некорректный JSON документа: ${error.message}`);
    }
    const canonical = JSON.stringify(parsed, null, 2);
    log('✍️ Подписываем документ True API…');
    const signature = await Signature.signUtf8Detached(canonical, state.certificateThumbprint);
    const productDocument = btoa(unescape(encodeURIComponent(canonical)));
    if (!state.trueApi.token) {
      throw new Error('Отсутствует токен True API. Авторизуйтесь.');
    }
    const payload = {
      document_format: docFormat || 'MANUAL',
      type: docType || 'LP_INTRODUCTION',
      product_document: productDocument,
      signature,
    };
    const result = await Api.documents.create({
      productGroup,
      payload,
    });
    log(`✅ Документ отправлен. Ответ: ${JSON.stringify(result.document || result, null, 2)}`);
    await refreshDocuments();
  }

  async function refreshDocuments() {
    if (!state.trueApi.token) {
      log('⚠️ Нет токена True API для просмотра документов.');
      return;
    }
    const productGroup = ($id('documentProductGroup')?.value || '').trim();
    if (!productGroup) {
      log('⚠️ Укажите код товарной группы.');
      return;
    }
    const result = await Api.documents.list({ productGroup, limit: 20 });
    renderDocuments(result.documents || result || []);
  }

  function renderDocuments(list) {
    const body = $id('documentsTableBody');
    if (!body) return;
    body.innerHTML = '';
    const documents = Array.isArray(list) ? list : (list.records || list.items || []);
    documents.forEach((doc) => {
      const row = document.createElement('tr');
      const idCell = document.createElement('td');
      idCell.textContent = doc.document_id || doc.documentId || doc.id || '';
      row.appendChild(idCell);

      const typeCell = document.createElement('td');
      typeCell.textContent = doc.type || doc.documentType || '';
      row.appendChild(typeCell);

      const statusCell = document.createElement('td');
      statusCell.textContent = doc.status || doc.documentStatus || '';
      row.appendChild(statusCell);

      const createdCell = document.createElement('td');
      createdCell.textContent = formatDate(doc.uploadedAt || doc.createDate || doc.created_at);
      row.appendChild(createdCell);

      body.appendChild(row);
    });
  }

  function bindEvents() {
    $id('certificateSelect')?.addEventListener('change', (event) => {
      state.certificateThumbprint = event.target.value;
      updateCertificateInfo();
    });

    $id('refreshCertificatesBtn')?.addEventListener('click', (event) => {
      event.preventDefault();
      refreshCertificates().catch((error) => log(error.message || error));
    });

    $id('trueApiRequestKeyBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await requestTrueApiChallenge();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('trueApiSignInBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await signInTrueApi();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('suzRequestKeyBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await requestSuzChallenge();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('suzSignInBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await signInSuz();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('catalogForm')?.addEventListener('submit', async (event) => {
      try {
        await loadCatalog(event);
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('buildOrderTemplateBtn')?.addEventListener('click', (event) => {
      event.preventDefault();
      try {
        buildOrderTemplate();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('sendOrderBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await sendOrder();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('refreshOrdersBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await refreshOrders();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('closeOrderBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await closeOrder();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('sendDocumentBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await sendDocument();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });

    $id('refreshDocumentsBtn')?.addEventListener('click', async (event) => {
      event.preventDefault();
      try {
        await refreshDocuments();
      } catch (error) {
        log(`❌ ${error.message || error}`);
      }
    });
  }

  async function init() {
    bindEvents();
    try {
      await Signature.ensureReady();
      log('✅ CryptoPro готов к работе.');
    } catch (error) {
      log(`⚠️ CryptoPro: ${error.message || error}`);
    }
    await refreshCertificates();
    await loadSession();
  }

  document.addEventListener('DOMContentLoaded', () => {
    init().catch((error) => log(error.message || error));
  });
})();
