<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

$nkMeta     = nkGetAuthTokenMeta();
$suzMeta    = orderGetSuzTokenMeta();
$suzContext = orderGetSuzContext();

$cryptoProBootstrap = renderCryptoProExtensionBootstrap();

$productGroups = [
    ['value' => 'tobacco',      'label' => 'Табачная продукция'],
    ['value' => 'otp',          'label' => 'Альтернативная табачная продукция'],
    ['value' => 'nicotindev',   'label' => 'Электронные системы доставки никотина'],
    ['value' => 'ncp',          'label' => 'Никотиносодержащая продукция'],
    ['value' => 'lp',           'label' => 'Легкая промышленность'],
    ['value' => 'shoes',        'label' => 'Обувь'],
    ['value' => 'tires',        'label' => 'Шины и покрышки'],
    ['value' => 'perfumery',    'label' => 'Парфюмерия и косметика'],
    ['value' => 'electronics',  'label' => 'Фотокамеры и лампы-вспышки'],
    ['value' => 'pharma',       'label' => 'Лекарственные препараты'],
    ['value' => 'vetpharma',    'label' => 'Ветеринарные препараты'],
    ['value' => 'milk',         'label' => 'Молочная продукция'],
    ['value' => 'water',        'label' => 'Упакованная вода'],
    ['value' => 'beer',         'label' => 'Пиво и напитки на основе пива'],
    ['value' => 'nabeer',       'label' => 'Безалкогольное пиво'],
    ['value' => 'softdrinks',   'label' => 'Безалкогольные напитки'],
    ['value' => 'bio',          'label' => 'Биологически активные добавки'],
    ['value' => 'antiseptic',   'label' => 'Антисептики и дезсредства'],
    ['value' => 'petfood',      'label' => 'Корма для животных'],
    ['value' => 'seafood',      'label' => 'Икра и морепродукты'],
    ['value' => 'meat',         'label' => 'Мясная продукция'],
    ['value' => 'vetbio',       'label' => 'Ветеринарные биопрепараты'],
    ['value' => 'bicycle',      'label' => 'Велосипеды и рамы'],
    ['value' => 'wheelchairs',  'label' => 'Кресла-коляски'],
    ['value' => 'gadgets',      'label' => 'Умные часы и браслеты'],
    ['value' => 'titan',        'label' => 'Изделия из титана'],
    ['value' => 'radio',        'label' => 'Радиоэлектронная продукция'],
    ['value' => 'opticfiber',   'label' => 'Оптическое волокно'],
    ['value' => 'vegetableoil', 'label' => 'Растительные масла'],
    ['value' => 'chemistry',    'label' => 'Бытовая химия'],
    ['value' => 'conserve',     'label' => 'Консервы'],
    ['value' => 'construction', 'label' => 'Строительные материалы'],
    ['value' => 'fire',         'label' => 'Противопожарная продукция'],
    ['value' => 'books',        'label' => 'Книжная продукция'],
    ['value' => 'heater',       'label' => 'Отопительное оборудование'],
    ['value' => 'grocery',      'label' => 'Бакалея и сухие продукты'],
    ['value' => 'cableraw',     'label' => 'Кабельно-проводниковая продукция'],
    ['value' => 'autofluids',   'label' => 'Автохимия и технические жидкости'],
    ['value' => 'polymer',      'label' => 'Полимерная упаковка'],
    ['value' => 'sweets',       'label' => 'Кондитерские изделия'],
    ['value' => 'carparts',     'label' => 'Автокомпоненты'],
];

$initialData = [
    'productGroups' => $productGroups,
    'nk' => [
        'active'    => $nkMeta !== null,
        'expiresAt' => $nkMeta['expires_at'] ?? null,
    ],
    'suz' => [
        'active'        => $suzMeta !== null,
        'expiresAt'     => $suzMeta['expires_at'] ?? null,
        'omsId'         => $suzContext['oms_id'] ?? ($suzMeta['oms_id'] ?? ''),
        'omsConnection' => $suzContext['oms_connect'] ?? ($suzMeta['oms_connect'] ?? ''),
    ],
];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Заказ КМ по GTIN</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
            --bg-page: linear-gradient(135deg, #eef2f3, #e3ebf6);
            --bg-card: rgba(255, 255, 255, 0.98);
            --border-color: #d7dce5;
            --accent: #4364d8;
            --accent-dark: #2e4cb5;
            --muted: #6b7280;
            --danger: #b91c1c;
            --success: #0f766e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 2rem;
            background: var(--bg-page);
            color: #1f2937;
        }

        main {
            max-width: 920px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.15);
            padding: 2.5rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        h1 {
            margin: 0;
            font-size: 2.1rem;
        }

        h2 {
            margin: 0 0 0.75rem;
            font-size: 1.35rem;
        }

        section {
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.75rem;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        label {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 0.55rem 0.7rem;
            font-size: 1rem;
            font-family: inherit;
            background: #fff;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .grid {
            display: grid;
            gap: 1rem;
        }

        .grid--cols-2 {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        button {
            border-radius: 10px;
            border: none;
            padding: 0.65rem 1.2rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .button--primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
            box-shadow: 0 10px 20px rgba(67, 100, 216, 0.25);
        }

        .button--ghost {
            background: transparent;
            border: 1px solid var(--border-color);
            color: #1f2937;
        }

        .card-info {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            background: #fff;
        }

        .card-info__title {
            margin: 0 0 0.25rem;
            font-weight: 600;
        }

        .pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin: 0.5rem 0;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            background: rgba(67, 100, 216, 0.12);
            color: var(--accent-dark);
            font-size: 0.85rem;
        }

        .log {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #0f172a;
            color: #e2e8f0;
            padding: 1rem;
            font-family: "JetBrains Mono", "SFMono-Regular", ui-monospace, monospace;
            font-size: 0.9rem;
            line-height: 1.45;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .preview {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            background: #fff;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 0.9rem;
            line-height: 1.45;
            max-height: 260px;
            overflow: auto;
        }

        .hint {
            font-size: 0.9rem;
            color: var(--muted);
        }

        dl {
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 0.35rem 1rem;
            margin: 0;
        }

        dt {
            font-weight: 600;
        }

        dd {
            margin: 0;
            color: var(--muted);
        }

        .connection-status {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .buttons--compact {
            gap: 0.5rem;
        }

        .buttons--compact .button--ghost {
            padding: 0.45rem 0.9rem;
        }

        @media (max-width: 720px) {
            body { padding: 1rem; }
            main { padding: 1.5rem; }
        }
    </style>
    <?php if ($cryptoProBootstrap !== '') {
        echo $cryptoProBootstrap, "\n";
    } ?>
    <script src="../assets/js/cadesplugin_api.js"></script>
</head>
<body>
<main>
    <header>
        <h1>Заказ кодов маркировки</h1>
        <p class="hint">На этой странице используются уже полученные токены. Укажите GTIN, проверьте карточку и отправьте заказ в СУЗ.</p>
    </header>

    <section>
        <h2>Подключения</h2>
        <dl>
            <dt>OMS Connection</dt>
            <dd id="omsConnection"></dd>
            <dt>OMS ID</dt>
            <dd id="omsId"></dd>
            <dt>clientToken</dt>
            <dd id="suzStatus"></dd>
            <dt>Нац. каталог</dt>
            <dd>
                <div class="connection-status">
                    <span id="nkStatus"></span>
                    <div class="buttons buttons--compact">
                        <button type="button" class="button--ghost" id="nkAuthRequestBtn">Получить токен</button>
                        <button type="button" class="button--ghost" id="nkAuthResetBtn">Сбросить токен</button>
                    </div>
                </div>
            </dd>
        </dl>
    </section>

    <section>
        <h2>1. Карточка товара</h2>
        <div class="grid grid--cols-2">
            <label>
                GTIN карточки
                <input type="text" id="gtinInput" placeholder="0460…">
            </label>
            <div class="buttons">
                <button type="button" class="button--ghost" id="findCardBtn">Найти карточку</button>
            </div>
        </div>
        <div class="card-info" id="cardInfo" hidden></div>
        <p class="hint">Карточка ищется в Национальном каталоге по вашему действующему токену.</p>
    </section>

    <section>
        <h2>2. Параметры заказа</h2>
        <div class="grid grid--cols-2">
            <label>
                Товарная группа
                <select id="productGroup"></select>
            </label>
            <label>
                Способ выпуска товаров в оборот
                <select id="releaseMethod">
                    <option value="">Выберите значение</option>
                    <option value="PRODUCTION">Произведено в РФ</option>
                    <option value="IMPORT">Импортировано</option>
                    <option value="REMAINS">Маркировка остатков</option>
                </select>
            </label>
        </div>
        <div class="grid grid--cols-2">
            <label>
                Количество КМ
                <input type="number" id="quantityInput" min="1" step="1" value="1">
            </label>
            <label>
                Template ID (если нужен)
                <input type="text" id="templateIdInput" placeholder="Например, 10">
            </label>
        </div>
        <label>
            Дополнительные атрибуты товара (JSON, необязательно)
            <textarea id="productAttributes" placeholder='{"mrp":"31055"}'></textarea>
        </label>
        <label>
            Дополнительные атрибуты заказа (JSON, необязательно)
            <textarea id="orderAttributes" placeholder='{"comment":"Заказ из UI"}'></textarea>
        </label>
    </section>

    <section>
        <h2>3. Подпись и отправка</h2>
        <div class="buttons">
            <button type="button" class="button--ghost" id="loadCertsBtn">Загрузить сертификаты</button>
            <button type="button" class="button--primary" id="sendOrderBtn">Подписать и отправить заказ</button>
        </div>
        <label>
            Сертификат УКЭП
            <select id="certSelect" disabled>
                <option value="">Сертификаты не загружены</option>
            </select>
        </label>
        <div class="card-info" id="certInfo">
            <p class="card-info__title">Сертификат не выбран.</p>
            <p class="hint">Подключите CryptoPro и нажмите «Загрузить сертификаты».</p>
        </div>
        <div class="buttons">
            <button type="button" class="button--ghost" id="previewBtn">Показать JSON заказа</button>
        </div>
        <pre class="preview" id="orderPreview" hidden></pre>
        <pre class="log" id="actionLog">Готово к работе…</pre>
    </section>
</main>

<script>
(() => {
  const initial = <?php echo json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const $ = (selector) => document.querySelector(selector);

  const state = {
    card: null,
    certs: [],
    currentCert: -1,
    nk: initial.nk ? { ...initial.nk } : {},
    suz: initial.suz ? { ...initial.suz } : {},
  };

  const gtinInput = $('#gtinInput');
  const findCardBtn = $('#findCardBtn');
  const cardInfo = $('#cardInfo');
  const productGroupSelect = $('#productGroup');
  const releaseMethodSelect = $('#releaseMethod');
  const quantityInput = $('#quantityInput');
  const templateIdInput = $('#templateIdInput');
  const productAttributesInput = $('#productAttributes');
  const orderAttributesInput = $('#orderAttributes');
  const loadCertsBtn = $('#loadCertsBtn');
  const sendOrderBtn = $('#sendOrderBtn');
  const certSelect = $('#certSelect');
  const certInfo = $('#certInfo');
  const previewBtn = $('#previewBtn');
  const previewEl = $('#orderPreview');
  const actionLog = $('#actionLog');
  const omsConnectionEl = $('#omsConnection');
  const omsIdEl = $('#omsId');
  const suzStatusEl = $('#suzStatus');
  const nkStatusEl = $('#nkStatus');
  const nkAuthRequestBtn = $('#nkAuthRequestBtn');
  const nkAuthResetBtn = $('#nkAuthResetBtn');

  function formatExpiry(expiresAt) {
    if (!expiresAt) return '';
    const date = new Date(expiresAt * 1000);
    return date.toLocaleString();
  }

  function renderConnections() {
    const suz = state.suz || {};
    omsConnectionEl.textContent = suz.omsConnection || '—';
    omsIdEl.textContent = suz.omsId || '—';
    if (suz.active) {
      const tail = suz.expiresAt ? ' до ' + formatExpiry(suz.expiresAt) : '';
      suzStatusEl.textContent = 'clientToken активен' + tail;
      suzStatusEl.style.color = 'var(--success)';
    } else {
      suzStatusEl.textContent = 'clientToken отсутствует';
      suzStatusEl.style.color = 'var(--danger)';
    }
    const nk = state.nk || {};
    if (nkStatusEl) {
      if (nk.active) {
        const tail = nk.expiresAt ? ' до ' + formatExpiry(nk.expiresAt) : '';
        nkStatusEl.textContent = 'Токен активен' + tail;
        nkStatusEl.style.color = 'var(--success)';
      } else {
        nkStatusEl.textContent = 'Токен отсутствует';
        nkStatusEl.style.color = 'var(--danger)';
      }
    }
  }

  function setNkStatus(status) {
    state.nk = Object.assign({}, state.nk, status || {});
    if (!state.nk.active) {
      state.nk.expiresAt = null;
    }
    renderConnections();
  }

  function populateProductGroups() {
    productGroupSelect.innerHTML = '<option value="">Выберите товарную группу</option>';
    (initial.productGroups || []).forEach((item) => {
      const option = document.createElement('option');
      option.value = item.value;
      option.textContent = item.label;
      productGroupSelect.appendChild(option);
    });
  }

  function log(message) {
    if (!actionLog) return;
    actionLog.textContent += (actionLog.textContent ? '\n' : '') + message;
    actionLog.scrollTop = actionLog.scrollHeight;
  }

  function resetLog() {
    if (actionLog) {
      actionLog.textContent = 'Готово к работе…';
    }
  }

  function renderCard(card) {
    if (!cardInfo) return;
    if (!card) {
      cardInfo.hidden = true;
      cardInfo.innerHTML = '';
      return;
    }
    const pills = [];
    if (card.goodId) pills.push(`<span class="pill">ID ${card.goodId}</span>`);
    if (card.gtin) pills.push(`<span class="pill">GTIN ${card.gtin}</span>`);
    if (card.productGroup) pills.push(`<span class="pill">${card.productGroup}</span>`);

    cardInfo.innerHTML = `
      <h3 class="card-info__title">${card.name || 'Без названия'}</h3>
      <div class="pill-list">${pills.join(' ')}</div>
      <p class="hint">${card.brand ? 'Бренд: ' + card.brand + '. ' : ''}${card.tnved ? 'ТН ВЭД: ' + card.tnved : ''}</p>
    `;
    cardInfo.hidden = false;
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const text = await response.text();
    if (!response.ok) {
      let message = text;
      try {
        const data = JSON.parse(text);
        message = data.error || text;
      } catch (_) {}
      throw new Error(message || `${response.status} ${response.statusText}`);
    }
    if (!text) return {};
    return JSON.parse(text);
  }

  async function loadCard() {
    const gtin = gtinInput?.value.trim();
    if (!gtin) {
      log('❌ Укажите GTIN');
      return;
    }
    log(`🔍 Поиск карточки ${gtin}…`);
    try {
      const card = await fetchJson(`../api/orders/card.php?gtin=${encodeURIComponent(gtin)}`);
      state.card = card;
      renderCard(card);
      if (card.productGroup && !productGroupSelect.value) {
        productGroupSelect.value = card.productGroup;
      }
      if (card.templateId && !templateIdInput.value) {
        templateIdInput.value = card.templateId;
      }
      log('✅ Карточка найдена');
    } catch (error) {
      state.card = null;
      renderCard(null);
      log('❌ Ошибка поиска: ' + (error.message || error));
    }
  }

  function parseJsonField(field, name) {
    const raw = field?.value.trim();
    if (!raw) return {};
    try {
      const parsed = JSON.parse(raw);
      if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error('ожидается объект');
      }
      return parsed;
    } catch (error) {
      throw new Error(`Поле «${name}»: ${error.message || error}`);
    }
  }

  function collectOrderPayload() {
    if (!state.card) {
      throw new Error('Сначала найдите карточку.');
    }
    const productGroup = productGroupSelect.value.trim();
    if (!productGroup) {
      throw new Error('Выберите товарную группу.');
    }
    const releaseMethod = releaseMethodSelect.value.trim();
    if (!releaseMethod) {
      throw new Error('Выберите способ выпуска.');
    }
    const quantity = Number(quantityInput.value || '0');
    if (!Number.isFinite(quantity) || quantity <= 0) {
      throw new Error('Количество КМ должно быть положительным числом.');
    }

    const product = {
      gtin: state.card.gtin,
      quantity,
    };

    const templateRaw = templateIdInput.value.trim();
    if (templateRaw !== '') {
      const templateId = Number(templateRaw);
      if (!Number.isFinite(templateId)) {
        throw new Error('Template ID должен быть числом.');
      }
      product.templateId = templateId;
    }

    const productAttributes = parseJsonField(productAttributesInput, 'Атрибуты товара');
    if (Object.keys(productAttributes).length) {
      product.attributes = productAttributes;
    }

    const orderAttributes = parseJsonField(orderAttributesInput, 'Атрибуты заказа');
    const attributes = Object.assign({}, orderAttributes, {
      releaseMethodType: releaseMethod,
    });

    return {
      productGroup,
      products: [product],
      attributes,
    };
  }

  function showPreview() {
    if (!previewEl) return;
    try {
      const payload = collectOrderPayload();
      previewEl.textContent = JSON.stringify(payload, null, 2);
      previewEl.hidden = false;
      log('ℹ️ JSON заказа обновлён.');
    } catch (error) {
      previewEl.textContent = error.message || String(error);
      previewEl.hidden = false;
      log('❌ ' + (error.message || error));
    }
  }

  function clearCertificates() {
    state.certs = [];
    state.currentCert = -1;
    certSelect.innerHTML = '<option value="">Сертификаты не загружены</option>';
    certSelect.disabled = true;
    certInfo.querySelector('.card-info__title').textContent = 'Сертификат не выбран.';
    certInfo.querySelector('.hint').textContent = 'Подключите CryptoPro и нажмите «Загрузить сертификаты».';
    if (nkAuthRequestBtn) nkAuthRequestBtn.disabled = true;
  }

  async function loadCertificates() {
    if (typeof cadesplugin === 'undefined' || typeof cadesplugin.then !== 'function') {
      clearCertificates();
      log('❌ Плагин CryptoPro не найден');
      return;
    }
    try {
      const store = await cadesplugin.CreateObjectAsync('CAdESCOM.Store');
      await store.Open(2, 'My', 2);
      const certs = [];
      try {
        const collection = await store.Certificates;
        const count = await collection.Count;
        for (let i = 1; i <= count; i++) {
          const cert = await collection.Item(i);
          const validTo = new Date(await cert.ValidToDate);
          if (validTo < new Date()) {
            continue;
          }
          certs.push(cert);
        }
      } finally {
        try { await store.Close(); } catch (_) {}
      }
      state.certs = certs;
      certSelect.innerHTML = '';
      if (!certs.length) {
        clearCertificates();
        log('❌ Действующие сертификаты не найдены');
        return;
      }
      certSelect.disabled = false;
      for (let i = 0; i < certs.length; i++) {
        const cert = certs[i];
        const option = document.createElement('option');
        option.value = String(i);
        const subject = await cert.SubjectName;
        option.textContent = subject.replace(/\s*,\s*/g, ', ') || `Сертификат #${i + 1}`;
        certSelect.appendChild(option);
      }
      certSelect.value = '0';
      await applyCertSelection(0);
      log('✅ Сертификаты загружены');
      if (nkAuthRequestBtn) nkAuthRequestBtn.disabled = false;
    } catch (error) {
      clearCertificates();
      log('❌ Ошибка загрузки сертификатов: ' + (error.message || error));
    }
  }

  async function applyCertSelection(index) {
    state.currentCert = index;
    if (index === -1 || !state.certs[index]) {
      certInfo.querySelector('.card-info__title').textContent = 'Сертификат не выбран.';
      certInfo.querySelector('.hint').textContent = 'Выберите сертификат для подписи.';
      return;
    }
    const cert = state.certs[index];
    const subject = (await cert.SubjectName).replace(/\s*,\s*/g, ', ');
    const issuer = (await cert.IssuerName).replace(/\s*,\s*/g, ', ');
    const validTo = new Date(await cert.ValidToDate);
    certInfo.querySelector('.card-info__title').textContent = subject || 'Выбранный сертификат';
    certInfo.querySelector('.hint').textContent = `Выдан: ${issuer}. Действует до ${validTo.toLocaleString()}.`;
  }

  function getCurrentCert() {
    if (state.currentCert < 0 || !state.certs[state.currentCert]) {
      throw new Error('Выберите сертификат.');
    }
    return state.certs[state.currentCert];
  }

  async function signStringDetached(value, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    if (typeof sd.propset_ContentEncoding === 'function' && typeof cadesplugin.CADESCOM_STRING_TO_UCS2LE !== 'undefined') {
      try { await sd.propset_ContentEncoding(cadesplugin.CADESCOM_STRING_TO_UCS2LE); } catch (_) {}
    }
    await sd.propset_Content(value);
    return sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES, true);
  }

  async function signAttachedAuth(value, cert) {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    if (typeof sd.propset_ContentEncoding === 'function' && typeof cadesplugin.CADESCOM_STRING_TO_UCS2LE !== 'undefined') {
      try {
        await sd.propset_ContentEncoding(cadesplugin.CADESCOM_STRING_TO_UCS2LE);
      } catch (_) {
        // старые версии плагина могут не поддерживать установку кодировки
      }
    }
    await sd.propset_Content(value);
    return sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES);
  }

  async function refreshNkStatus(logStatus = false) {
    if (!nkStatusEl) return;
    try {
      const data = await fetchJson('../api/nk-auth.php?mode=status', { cache: 'no-store' });
      setNkStatus({
        active: Boolean(data.active),
        expiresAt: typeof data.expiresAt === 'number' ? data.expiresAt : null,
      });
      if (logStatus) {
        log('ℹ️ Статус токена НК обновлён');
      }
    } catch (error) {
      if (logStatus) {
        log('❌ Статус токена НК: ' + (error.message || error));
      }
    }
  }

  async function sendOrder() {
    resetLog();
    try {
      const payload = collectOrderPayload();
      const body = JSON.stringify(payload);
      const cert = getCurrentCert();
      log('✍️ Подписываем JSON заказа…');
      const signature = await signStringDetached(body, cert);
      log('📤 Отправляем заказ в СУЗ…');
      const response = await fetchJson('../api/orders/create-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: payload, raw: body, signature }),
      });
      log('✅ Ответ СУЗ: ' + JSON.stringify(response.response || response));
      previewEl.textContent = JSON.stringify(payload, null, 2);
      previewEl.hidden = false;
    } catch (error) {
      log('❌ ' + (error.message || error));
    }
  }

  async function requestNkToken() {
    if (!nkAuthRequestBtn) return;
    let cert;
    try {
      cert = getCurrentCert();
    } catch (error) {
      log('❌ ' + (error.message || error));
      return;
    }
    nkAuthRequestBtn.disabled = true;
    try {
      log('🔐 Запрашиваем challenge True API…');
      const challenge = await fetchJson('../api/nk-auth.php');
      if (!challenge.uuid || !challenge.data) {
        throw new Error('Некорректный ответ True API');
      }
      log('✍️ Подписываем challenge сертификатом…');
      const signature = await signAttachedAuth(challenge.data, cert);
      log('📨 Отправляем подпись для получения токена…');
      const response = await fetchJson('../api/nk-auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uuid: challenge.uuid, signature }),
      });
      log('✅ Токен НК получен');
      if (response.expiresAt) {
        log('ℹ️ Токен действует до ' + formatExpiry(response.expiresAt));
      }
      setNkStatus({ active: true, expiresAt: response.expiresAt || null });
    } catch (error) {
      log('❌ Авторизация НК: ' + (error.message || error));
    } finally {
      nkAuthRequestBtn.disabled = false;
    }
  }

  certSelect?.addEventListener('change', (event) => {
    const index = Number(event.target.value);
    if (!Number.isInteger(index) || index < 0) {
      applyCertSelection(-1);
      return;
    }
    applyCertSelection(index);
  });

  findCardBtn?.addEventListener('click', loadCard);
  loadCertsBtn?.addEventListener('click', loadCertificates);
  previewBtn?.addEventListener('click', showPreview);
  sendOrderBtn?.addEventListener('click', sendOrder);
  nkAuthRequestBtn?.addEventListener('click', () => { requestNkToken(); });
  nkAuthResetBtn?.addEventListener('click', async () => {
    nkAuthResetBtn.disabled = true;
    try {
      await fetchJson('../api/nk-auth.php', { method: 'DELETE' });
      log('ℹ️ Токен НК сброшен');
      setNkStatus({ active: false, expiresAt: null });
    } catch (error) {
      log('❌ Сброс токена НК: ' + (error.message || error));
    } finally {
      nkAuthResetBtn.disabled = false;
    }
  });

  populateProductGroups();
  renderConnections();
  refreshNkStatus(false);
})();
</script>
</body>
</html>
