<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

$gtin = trim((string)($_GET['gtin'] ?? ''));
$initialCard = null;
if ($gtin !== '' && nkGetAuthTokenMeta() !== null) {
    try {
        $initialCard = NkApi::cardByGtin($gtin);
    } catch (Throwable $e) {
        $initialCard = null;
    }
}

$initialData = [
    'gtin' => $gtin,
    'card' => $initialCard,
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Заказ кодов маркировки — Лёгкая промышленность</title>
    <meta name="description" content="Отправьте заказ кодов маркировки, подпишите документы УКЭП и отслеживайте статус True API/СУЗ">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        :root {
            color-scheme: light;
            --bg-page: linear-gradient(135deg, #f5f7fa, #e4ecf7);
            --bg-card: #ffffff;
            --border: #d6dce5;
            --accent: #4364d8;
            --accent-dark: #3653bd;
            --danger: #b42318;
            --success: #157f2f;
            --muted: #6b7280;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 2.5rem;
            background: var(--bg-page);
            color: #1f2937;
        }

        a { color: var(--accent-dark); }

        .page {
            max-width: 1280px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.12);
            padding: 2.5rem 3rem 3.5rem;
        }

        header.page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
        }

        header.page-header h1 {
            margin: 0;
            font-size: 2.1rem;
            font-weight: 700;
        }

        .page-nav {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            border: none;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.65rem 1.35rem;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .button--primary {
            background: var(--accent);
            color: #fff;
        }

        .button--primary:not(:disabled):hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(67, 100, 216, 0.25);
        }

        .button--ghost {
            background: rgba(67, 100, 216, 0.08);
            color: var(--accent-dark);
        }

        .button--secondary {
            background: rgba(31, 41, 55, 0.08);
            color: #1f2937;
        }

        section.block {
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            margin-top: 2rem;
        }

        section.block:first-of-type {
            margin-top: 2.5rem;
        }

        section.block h2 {
            margin: 0;
            font-size: 1.35rem;
        }

        .block__meta {
            margin-top: 0.35rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .grid-two {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        .info-card {
            border-radius: 12px;
            border: 1px dashed rgba(67, 100, 216, 0.35);
            background: rgba(67, 100, 216, 0.05);
            padding: 1rem 1.25rem;
            display: grid;
            gap: 0.4rem;
        }

        .info-card strong {
            font-size: 1.05rem;
        }

        textarea,
        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 0.95rem;
            background: #f9fafb;
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-row label {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .stack {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }

        .log {
            font-family: "Fira Code", "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 0.85rem;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            min-height: 140px;
            max-height: 260px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .status-board {
            display: grid;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .status-board__item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(67, 100, 216, 0.12);
            background: rgba(67, 100, 216, 0.04);
        }

        .status-board__item h3 {
            margin: 0;
            font-size: 1rem;
        }

        .status-board__content {
            font-size: 0.85rem;
            color: #1f2937;
            white-space: pre-wrap;
        }

        .status-board__content--error {
            color: var(--danger);
        }

        @media (max-width: 900px) {
            body { padding: 1.5rem; }
            .page { padding: 2rem; }
        }
    </style>
    <script src="../api/crypto-pro-extension-bootstrap.php"></script>
    <script src="../assets/js/cadesplugin_api.js"></script>
</head>
<body>
<main class="page">
    <header class="page-header">
        <div>
            <h1>Заказ кодов маркировки</h1>
            <p class="block__meta">Лёгкая промышленность • авторизация True API и СУЗ выполняется на главной странице</p>
        </div>
        <nav class="page-nav">
            <a class="button button--ghost" href="../index.php">← К карточкам</a>
            <a class="button button--ghost" href="../index.php?suz=1">Настройки OMS и СУЗ</a>
            <button type="button" class="button button--secondary" id="refreshStatus">Обновить статусы</button>
        </nav>
    </header>

    <section class="block">
        <h2>Карточка товара</h2>
        <p class="block__meta">GTIN и атрибуты подтягиваются из Национального каталога.</p>
        <div class="grid-two" id="productInfo"></div>
    </section>

    <section class="block">
        <h2>Сертификат УКЭП</h2>
        <p class="block__meta">Используется для подписи документов, заказов и печатных форм. Токены True API и СУЗ получаются на главной странице.</p>
        <div class="grid-two">
            <div class="form-row">
                <label for="certSelect">Сертификат УКЭП</label>
                <select id="certSelect">
                    <option value="">Загрузка сертификатов…</option>
                </select>
            </div>
            <div class="info-card" id="certInfo">
                <strong>Сертификат не выбран</strong>
                <p class="block__meta">Выберите сертификат, чтобы подписывать документы и заказы.</p>
            </div>
        </div>
    </section>

    <section class="block">
        <h2>Проверка КИ (mark-check)</h2>
        <p class="block__meta">Поддерживается до 100 КИ/GTIN/ТНВЭД за один запрос.</p>
        <div class="stack">
            <div class="form-row">
                <label for="cisInput">Список КИ или GTIN (по одному в строке)</label>
                <textarea id="cisInput" placeholder="Введите коды…"></textarea>
            </div>
            <button type="button" class="button button--secondary" id="runMarkCheck">Проверить</button>
            <pre class="log" id="markCheckLog">Готово к проверке…</pre>
        </div>
    </section>

    <section class="block">
        <h2>Документ True API</h2>
        <p class="block__meta">Формирование и отправка документа `/lk/documents/create` (тип по умолчанию — LP_INTRODUCE_GOODS).</p>
        <div class="stack">
            <div class="grid-two">
                <div class="form-row">
                    <label for="documentType">Тип документа</label>
                    <input type="text" id="documentType" value="LP_INTRODUCE_GOODS">
                </div>
                <div class="form-row">
                    <label for="productGroup">Код товарной группы</label>
                    <input type="text" id="productGroup" value="lp">
                </div>
            </div>
            <div class="form-row">
                <label for="documentJson">JSON документа (будет закодирован в Base64)</label>
                <textarea id="documentJson" placeholder='{"product_document":{...}}'></textarea>
            </div>
            <div class="page-nav">
                <button type="button" class="button button--primary" id="sendDocument">Подписать и отправить</button>
            </div>
            <pre class="log" id="documentLog">Ожидание действий…</pre>
        </div>
    </section>

    <section class="block">
        <h2>Заказ в СУЗ</h2>
        <p class="block__meta">Подписывает JSON заказа и отправляет `/api/v3/order?omsId=…`.</p>
        <div class="stack">
            <div class="form-row">
                <label for="orderJson">JSON заказа СУЗ</label>
                <textarea id="orderJson" placeholder='{"productGroup":"lp_lite","products":[...]}'></textarea>
            </div>
            <div class="page-nav">
                <button type="button" class="button button--primary" id="sendOrder">Подписать и отправить заказ</button>
            </div>
            <pre class="log" id="orderLog">Ожидание действий…</pre>
        </div>
    </section>

    <section class="block">
        <h2>Печать кодов</h2>
        <p class="block__meta">Сформируйте PDF с КМ для печати этикеток.</p>
        <div class="stack">
            <div class="form-row">
                <label for="codesList">Коды маркировки</label>
                <textarea id="codesList" placeholder="Вставьте коды…"></textarea>
            </div>
            <button type="button" class="button button--secondary" id="downloadPdf">Скачать PDF</button>
        </div>
    </section>

    <section class="block">
        <h2>Статусы True API и СУЗ</h2>
        <p class="block__meta">Отслеживайте обработку документов и заказов. Укажите идентификаторы при необходимости.</p>
        <div class="grid-two">
            <div class="form-row">
                <label for="docId">Document ID (True API)</label>
                <input type="text" id="docId" placeholder="Идентификатор документа">
            </div>
            <div class="form-row">
                <label for="orderId">Order ID (СУЗ)</label>
                <input type="text" id="orderId" placeholder="Идентификатор заказа">
            </div>
        </div>
        <div class="status-board" id="statusBoard"></div>
    </section>
</main>
<script>
(() => {
  const initial = <?php echo json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const productInfo = document.getElementById('productInfo');
  const certSelect = document.getElementById('certSelect');
  const certInfoCard = document.getElementById('certInfo');
  const markCheckButton = document.getElementById('runMarkCheck');
  const markCheckLog = document.getElementById('markCheckLog');
  const cisInput = document.getElementById('cisInput');
  const documentTypeInput = document.getElementById('documentType');
  const documentPgInput = document.getElementById('productGroup');
  const documentTextarea = document.getElementById('documentJson');
  const documentLog = document.getElementById('documentLog');
  const sendDocumentButton = document.getElementById('sendDocument');
  const orderTextarea = document.getElementById('orderJson');
  const orderLog = document.getElementById('orderLog');
  const sendOrderButton = document.getElementById('sendOrder');
  const codesTextarea = document.getElementById('codesList');
  const downloadPdfButton = document.getElementById('downloadPdf');
  const docIdInput = document.getElementById('docId');
  const orderIdInput = document.getElementById('orderId');
  const statusBoard = document.getElementById('statusBoard');
  const refreshStatusButton = document.getElementById('refreshStatus');

  let certs = [];
  let currentCertIndex = -1;

  const utf8ToB64 = (value) => window.btoa(unescape(encodeURIComponent(value)));
  const parseJSON = (text) => {
    const trimmed = text.trim();
    if (!trimmed) {
      throw new Error('Поле JSON пустое');
    }
    return JSON.parse(trimmed);
  };

  const normalizeGtin = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const digits = raw.replace(/\D+/g, '');
    if (digits.length === 14) {
      return digits;
    }
    if (digits.length === 13) {
      return digits.startsWith('0') ? digits : `0${digits}`;
    }
    return raw;
  };

  const renderCertificateInfo = () => {
    if (!certInfoCard) return;
    certInfoCard.innerHTML = '';
    if (!certs.length) {
      const title = document.createElement('strong');
      title.textContent = 'Сертификаты не найдены';
      certInfoCard.appendChild(title);
      const hint = document.createElement('p');
      hint.className = 'block__meta';
      hint.textContent = 'Установите CryptoPro Browser Plug-in и перезагрузите страницу.';
      certInfoCard.appendChild(hint);
      return;
    }
    if (currentCertIndex < 0 || !certs[currentCertIndex]) {
      const title = document.createElement('strong');
      title.textContent = 'Сертификат не выбран';
      certInfoCard.appendChild(title);
      const hint = document.createElement('p');
      hint.className = 'block__meta';
      hint.textContent = 'Выберите сертификат, чтобы подписывать документы и заказы.';
      certInfoCard.appendChild(hint);
      return;
    }
    const meta = certs[currentCertIndex];
    const title = document.createElement('strong');
    title.textContent = meta.summary || meta.subjectName || 'Сертификат';
    certInfoCard.appendChild(title);
    if (meta.issuerName) {
      const issuer = document.createElement('p');
      issuer.className = 'block__meta';
      issuer.textContent = `Выдан: ${meta.issuerName}`;
      certInfoCard.appendChild(issuer);
    }
    const validFromDate = meta.validFrom instanceof Date && !Number.isNaN(meta.validFrom.getTime()) ? meta.validFrom : null;
    const validToDate = meta.validTo instanceof Date && !Number.isNaN(meta.validTo.getTime()) ? meta.validTo : null;
    if (validFromDate || validToDate) {
      const period = document.createElement('p');
      period.className = 'block__meta';
      const fromText = validFromDate ? validFromDate.toLocaleDateString() : '—';
      const toText = validToDate ? validToDate.toLocaleDateString() : '—';
      period.textContent = `Срок действия: ${fromText} — ${toText}`;
      certInfoCard.appendChild(period);
    }
    if (meta.thumbprint) {
      const thumb = document.createElement('p');
      thumb.className = 'block__meta';
      thumb.textContent = `Отпечаток: ${meta.thumbprint}`;
      certInfoCard.appendChild(thumb);
    }
  };

  const renderProduct = (card, fallbackGtin = '') => {
    productInfo.innerHTML = '';
    if (!card) {
      const gtinText = fallbackGtin || initialGtin || initial.gtin || '';
      const hint = gtinText ? `Попробуйте ещё раз для GTIN ${gtinText}.` : 'Введите GTIN на предыдущей странице.';
      productInfo.innerHTML = `<div class="info-card"><strong>Карточка не загружена</strong><p class="block__meta">${hint}</p></div>`;
      return;
    }
    const makeItem = (title, value) => `<div class="info-card"><strong>${title}</strong><p>${value || '—'}</p></div>`;
    const attrs = card.good_attrs || [];
    const findAttr = (name) => {
      const entry = attrs.find((attr) => attr.attr_name === name);
      return entry ? entry.attr_value : '';
    };
    const gtinValue = card.gtin || fallbackGtin || initialGtin || initial.gtin || '—';
    const html = [
      makeItem('Наименование', card.good_name || '—'),
      makeItem('GTIN', gtinValue),
      makeItem('Производитель', findAttr('Производитель') || findAttr('Изготовитель')),
      makeItem('Артикул', findAttr('Модель / артикул производителя')),
      makeItem('Цвет', findAttr('Цвет')),
      makeItem('Размер', findAttr('Размер одежды / изделия')),
    ].join('');
    productInfo.innerHTML = html;
  };

  const initialGtin = normalizeGtin(initial.gtin);
  renderProduct(initial.card, initialGtin);

  async function fetchJson(url, options) {
    const response = await fetch(url, options);
    const text = await response.text();
    if (!response.ok) {
      throw new Error(`${url} → ${response.status}\n${text}`);
    }
    return text ? JSON.parse(text) : {};
  }

  const logLine = (element, message) => {
    if (!element) return;
    element.textContent += (element.textContent ? '\n' : '') + message;
    element.scrollTop = element.scrollHeight;
  };

  const resetLog = (element, prefix = '===') => {
    if (element) {
      element.textContent = prefix ? `${prefix}\n` : '';
    }
  };

  async function loadProduct(gtin) {
    if (!gtin) return;
    const normalized = normalizeGtin(gtin);
    const query = normalized || gtin;
    try {
      const data = await fetchJson(`../api/orders/product.php?gtin=${encodeURIComponent(query)}`);
      renderProduct(data.card, query);
    } catch (error) {
      renderProduct(null, query);
      console.error(error);
    }
  }

  if (initial.gtin) {
    loadProduct(initial.gtin);
  }

  const getCodesFromTextarea = (textarea) => textarea.value.split(/\r?\n|[,;\t]+/).map((code) => code.trim()).filter(Boolean);

  let store;
  const loadCertificates = async () => {
    certs = [];
    currentCertIndex = -1;
    if (certInfoCard) {
      certInfoCard.innerHTML = '<strong>Загрузка сертификатов…</strong>';
    }
    try {
      store = await cadesplugin.CreateObjectAsync('CAdESCOM.Store');
      await store.Open();
      const certificates = await store.Certificates;
      const count = await certificates.Count;
      certSelect.innerHTML = '';
      for (let i = 1; i <= count; i += 1) {
        const cert = await certificates.Item(i);
        const subjectName = await cert.SubjectName;
        const issuerName = await cert.IssuerName;
        const validFrom = new Date(await cert.ValidFromDate);
        const validTo = new Date(await cert.ValidToDate);
        const thumbprint = await cert.Thumbprint;
        const summary = `${subjectName} (до ${validTo.toLocaleDateString()})`;
        const meta = { cert, subjectName, issuerName, validFrom, validTo, thumbprint, summary };
        certs.push(meta);
        const option = document.createElement('option');
        option.value = String(certs.length - 1);
        option.textContent = summary;
        certSelect.appendChild(option);
      }
      if (!certs.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Сертификаты не найдены';
        certSelect.appendChild(option);
        renderCertificateInfo();
      } else {
        currentCertIndex = 0;
        certSelect.value = '0';
        renderCertificateInfo();
      }
    } catch (error) {
      console.error('Certificates error', error);
      certSelect.innerHTML = '<option value="">Ошибка загрузки сертификатов</option>';
      certs = [];
      currentCertIndex = -1;
      if (certInfoCard) {
        certInfoCard.innerHTML = '<strong>Ошибка загрузки сертификатов</strong><p class="block__meta">Проверьте плагин CryptoPro.</p>';
      }
    } finally {
      if (store) {
        try { await store.Close(); } catch (e) { /* ignore */ }
      }
      if (certs.length === 0) {
        renderCertificateInfo();
      }
    }
  };

  const selectCertificate = (index) => {
    currentCertIndex = index;
    renderCertificateInfo();
  };

  certSelect?.addEventListener('change', (event) => {
    const value = event.target.value;
    selectCertificate(value === '' ? -1 : Number(value));
  });

  if (typeof cadesplugin !== 'undefined' && typeof cadesplugin.then === 'function') {
    cadesplugin.then(loadCertificates).catch((error) => {
      console.error('CryptoPro', error);
      if (certInfoCard) {
        certInfoCard.innerHTML = '<strong>CryptoPro недоступен</strong><p class="block__meta">Проверьте установку плагина и перезагрузите страницу.</p>';
      }
    });
  } else if (certInfoCard) {
    certInfoCard.innerHTML = '<strong>CryptoPro недоступен</strong><p class="block__meta">Установите плагин, чтобы выбрать сертификат.</p>';
  }

  async function signAttached(data, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    if (typeof sd.propset_ContentEncoding === 'function' && typeof cadesplugin.CADESCOM_STRING_TO_UCS2LE !== 'undefined') {
      try { await sd.propset_ContentEncoding(cadesplugin.CADESCOM_STRING_TO_UCS2LE); } catch (e) {}
    }
    await sd.propset_Content(data);
    return sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES);
  }

  async function signDetachedBase64(base64Data, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    if (typeof sd.propset_ContentEncoding === 'function') {
      try { await sd.propset_ContentEncoding(cadesplugin.CADESCOM_BASE64_TO_BINARY); } catch (e) {}
    }
    await sd.propset_Content(base64Data);
    const pkcs7 = await sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES, true);
    return pkcs7;
  }

  const ensureCert = () => {
    if (currentCertIndex < 0 || !certs[currentCertIndex]) {
      throw new Error('Выберите сертификат');
    }
    return certs[currentCertIndex].cert;
  };

  markCheckButton?.addEventListener('click', async () => {
    resetLog(markCheckLog, '=== mark-check ===');
    const codes = getCodesFromTextarea(cisInput);
    if (!codes.length) {
      logLine(markCheckLog, 'Введите коды для проверки');
      return;
    }
    try {
      const payload = codes.every((code) => code.length === 14) ? { gtins: codes } : { cis: codes };
      const response = await fetchJson('../api/orders/mark-check.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      logLine(markCheckLog, JSON.stringify(response.result, null, 2));
    } catch (error) {
      logLine(markCheckLog, 'Ошибка: ' + (error.message || error));
    }
  });

  sendDocumentButton?.addEventListener('click', async () => {
    resetLog(documentLog, '=== документ ===');
    try {
      const cert = ensureCert();
      const docJson = parseJSON(documentTextarea.value);
      const pretty = JSON.stringify(docJson, null, 2);
      const base64Doc = utf8ToB64(pretty);
      const signature = await signDetachedBase64(base64Doc, cert);
      const payload = {
        type: documentTypeInput.value.trim() || 'LP_INTRODUCE_GOODS',
        productGroup: documentPgInput.value.trim() || 'lp',
        documentFormat: 'MANUAL',
        productDocument: base64Doc,
        signature,
      };
      const response = await fetchJson('../api/orders/create-document.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      logLine(documentLog, JSON.stringify(response.response, null, 2));
      if (response.response?.document_id) {
        docIdInput.value = response.response.document_id;
      }
    } catch (error) {
      logLine(documentLog, 'Ошибка: ' + (error.message || error));
    }
  });

  sendOrderButton?.addEventListener('click', async () => {
    resetLog(orderLog, '=== заказ СУЗ ===');
    try {
      const cert = ensureCert();
      const orderJson = parseJSON(orderTextarea.value);
      const pretty = JSON.stringify(orderJson, null, 2);
      const signature = await signDetachedBase64(utf8ToB64(pretty), cert);
      const response = await fetchJson('../api/orders/create-suz-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ signature, payload: orderJson }),
      });
      logLine(orderLog, JSON.stringify(response.response, null, 2));
      if (response.response?.orderId) {
        orderIdInput.value = response.response.orderId;
      }
    } catch (error) {
      logLine(orderLog, 'Ошибка: ' + (error.message || error));
    }
  });

  downloadPdfButton?.addEventListener('click', async () => {
    try {
      const codes = getCodesFromTextarea(codesTextarea);
      if (!codes.length) {
        alert('Добавьте коды для печати');
        return;
      }
      const response = await fetch('../api/orders/print-codes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codes }),
      });
      if (!response.ok) {
        const text = await response.text();
        throw new Error(text);
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'codes.pdf';
      link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      alert(error.message || error);
    }
  });

  const renderStatusBoard = (data) => {
    const items = [];
    if (data.document) {
      items.push(`<div class="status-board__item"><h3>Документ True API</h3><div class="status-board__content">${JSON.stringify(data.document, null, 2)}</div></div>`);
    }
    if (data.orders) {
      items.push(`<div class="status-board__item"><h3>Заказы СУЗ</h3><div class="status-board__content">${JSON.stringify(data.orders, null, 2)}</div></div>`);
    }
    if (data.documentError) {
      items.push(`<div class="status-board__item"><h3>Ошибка документа</h3><div class="status-board__content status-board__content--error">${data.documentError}</div></div>`);
    }
    if (data.ordersError) {
      items.push(`<div class="status-board__item"><h3>Ошибка СУЗ</h3><div class="status-board__content status-board__content--error">${data.ordersError}</div></div>`);
    }
    statusBoard.innerHTML = items.join('') || '<div class="status-board__item"><h3>Нет данных</h3><div class="status-board__content">Получите документ и заказ, затем обновите статус.</div></div>';
  };

  const refreshStatus = async () => {
    try {
      const params = new URLSearchParams();
      const docId = docIdInput.value.trim();
      const orderId = orderIdInput.value.trim();
      if (docId) params.set('docId', docId);
      if (orderId) params.set('orderId', orderId);
      params.set('pg', documentPgInput.value.trim() || 'lp');
      const data = await fetchJson(`../api/orders/status.php?${params.toString()}`);
      renderStatusBoard(data.data);
    } catch (error) {
      alert(error.message || error);
    }
  };

  refreshStatusButton?.addEventListener('click', refreshStatus);
  renderStatusBoard({});
})();
</script>
</body>
</html>
