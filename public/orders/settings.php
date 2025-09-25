<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

$context = orderGetSuzContext();
$suzMeta = orderGetSuzTokenMeta();
$cryptoProBootstrap = renderCryptoProExtensionBootstrap();

$initial = [
    'context' => $context,
    'status'  => [
        'active'       => $suzMeta !== null,
        'expiresAt'    => $suzMeta['expires_at'] ?? null,
        'omsId'        => $suzMeta['oms_id'] ?? $context['oms_id'] ?? '',
        'omsConnection'=> $suzMeta['oms_connect'] ?? $context['oms_connect'] ?? '',
    ],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Настройки OMS и СУЗ</title>
    <meta name="description" content="Настройка параметров OMS, тест clientToken и проверка подключения к СУЗ">
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
            max-width: 960px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.12);
            padding: 2.5rem 3rem 3rem;
        }

        header.page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
        }

        header.page-header h1 {
            margin: 0;
            font-size: 2rem;
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
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
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

        input, select, textarea {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 0.95rem;
            background: #f9fafb;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .info-card {
            border-radius: 14px;
            background: rgba(67, 100, 216, 0.08);
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-card strong {
            font-size: 1rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            padding: 0.3rem 0.9rem;
            background: rgba(67, 100, 216, 0.15);
            color: var(--accent-dark);
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-pill--inactive {
            background: rgba(180, 35, 24, 0.12);
            color: var(--danger);
        }

        .log {
            font-family: "Fira Code", "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 0.85rem;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            min-height: 120px;
            max-height: 240px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 1rem;
        }

        @media (max-width: 900px) {
            body { padding: 1.5rem; }
            .page { padding: 2rem; }
        }
    </style>
    <?php if ($cryptoProBootstrap !== '') { echo $cryptoProBootstrap, "\n"; } ?>
    <script src="../assets/js/cadesplugin_api.js"></script>
</head>
<body>
<main class="page">
    <header class="page-header">
        <div>
            <h1>Настройки OMS и СУЗ</h1>
            <p class="block__meta">Сохраните параметры подключения и проверьте получение clientToken.</p>
        </div>
        <nav class="page-nav">
            <a class="button button--ghost" href="create.php">← К заказу</a>
            <a class="button button--ghost" href="../index.php">Карточки НК</a>
        </nav>
    </header>

    <section class="block">
        <h2>Параметры подключения OMS</h2>
        <p class="block__meta">Введите данные из личного кабинета Честного знака. Сохраняются в текущей сессии.</p>
        <div class="info-card" id="statusCard"></div>
        <div class="grid-two">
            <div class="form-row">
                <label for="omsConnection">Идентификатор соединения (omsConnection)</label>
                <input type="text" id="omsConnection" placeholder="GUID подключения">
            </div>
            <div class="form-row">
                <label for="omsId">OMS ID</label>
                <input type="text" id="omsId" placeholder="00000000-0000-0000-0000-000000000000">
            </div>
            <div class="form-row">
                <label for="participantInn">ИНН участника оборота</label>
                <input type="text" id="participantInn" placeholder="Например, 7700000000">
            </div>
            <div class="form-row">
                <label for="stationUrl">Сервер станции управления заказами</label>
                <input type="text" id="stationUrl" placeholder="https://suzgrid.crpt.ru/api/v3">
            </div>
        </div>
        <div class="form-row" style="margin-top:1.25rem;">
            <label for="locationAddress">Адрес нахождения</label>
            <textarea id="locationAddress" placeholder="Город, улица, дом"></textarea>
        </div>
        <div class="grid-two" style="margin-top:1.5rem; align-items:end;">
            <div class="form-row">
                <label for="certSelect">Сертификат УКЭП</label>
                <select id="certSelect">
                    <option value="">Загрузка сертификатов…</option>
                </select>
            </div>
            <div class="page-nav" style="justify-content:flex-end;">
                <button type="button" class="button button--secondary" id="saveSettings">Сохранить</button>
                <button type="button" class="button button--primary" id="testConnection">Тестовое подключение</button>
                <button type="button" class="button button--ghost" id="resetToken">Сбросить token</button>
            </div>
        </div>
        <pre class="log" id="testLog">Готово к тестированию…</pre>
    </section>
</main>
<script>
(() => {
  const initial = <?php echo json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const statusCard = document.getElementById('statusCard');
  const omsConnectionInput = document.getElementById('omsConnection');
  const omsIdInput = document.getElementById('omsId');
  const participantInnInput = document.getElementById('participantInn');
  const stationUrlInput = document.getElementById('stationUrl');
  const locationAddressInput = document.getElementById('locationAddress');
  const saveButton = document.getElementById('saveSettings');
  const testButton = document.getElementById('testConnection');
  const resetButton = document.getElementById('resetToken');
  const certSelect = document.getElementById('certSelect');
  const testLog = document.getElementById('testLog');

  let certs = [];
  let currentCertIndex = -1;
  let store;

  const logLine = (message) => {
    if (!testLog) return;
    testLog.textContent += (testLog.textContent ? '\n' : '') + message;
    testLog.scrollTop = testLog.scrollHeight;
  };

  const resetLog = () => {
    if (testLog) {
      testLog.textContent = '=== тест подключения ===';
    }
  };

  const formatStatus = (meta) => {
    if (!meta?.active) {
      return '<span class="status-pill status-pill--inactive">clientToken отсутствует</span>';
    }
    if (!meta.expiresAt) {
      return '<span class="status-pill">Токен активен</span>';
    }
    const date = new Date(meta.expiresAt * 1000);
    return `<span class="status-pill">Активен до ${date.toLocaleString()}</span>`;
  };

  const renderStatus = (meta, context) => {
    const info = [];
    const connection = context?.omsConnection ?? context?.oms_connect ?? '';
    const omsId = context?.omsId ?? context?.oms_id ?? '';
    if (connection) info.push(`omsConnection: ${connection}`);
    if (omsId) info.push(`omsId: ${omsId}`);
    statusCard.innerHTML = `${formatStatus(meta)}<p class="block__meta">${info.join(' • ') || 'Параметры не заданы'}</p>`;
  };

  const applyContext = (context) => {
    if (!context) return;
    omsConnectionInput.value = context.oms_connect || '';
    omsIdInput.value = context.oms_id || '';
    participantInnInput.value = context.participant_inn || '';
    stationUrlInput.value = context.station_url || '';
    locationAddressInput.value = context.location_address || '';
  };

  const fetchJson = async (url, options) => {
    const response = await fetch(url, options);
    const text = await response.text();
    if (!response.ok) {
      throw new Error(`${url} → ${response.status}\n${text}`);
    }
    return text ? JSON.parse(text) : {};
  };

  const loadCertificates = async () => {
    certs = [];
    currentCertIndex = -1;
    try {
      store = await cadesplugin.CreateObjectAsync('CAdESCOM.Store');
      await store.Open();
      const certificates = await store.Certificates;
      const count = await certificates.Count;
      certSelect.innerHTML = '';
      for (let i = 1; i <= count; i += 1) {
        const cert = await certificates.Item(i);
        const subjectName = await cert.SubjectName;
        const validTo = new Date(await cert.ValidToDate);
        const summary = `${subjectName} (до ${validTo.toLocaleDateString()})`;
        certs.push({ cert, summary });
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
      }
    } catch (error) {
      console.error('Certificates error', error);
      certSelect.innerHTML = '<option value="">Ошибка загрузки сертификатов</option>';
    } finally {
      if (store) {
        try { await store.Close(); } catch (e) { /* ignore */ }
      }
    }
  };

  const ensureCert = () => {
    if (currentCertIndex < 0 || !certs[currentCertIndex]) {
      throw new Error('Выберите сертификат');
    }
    return certs[currentCertIndex].cert;
  };

  certSelect?.addEventListener('change', (event) => {
    const value = event.target.value;
    currentCertIndex = value === '' ? -1 : Number(value);
  });

  if (typeof cadesplugin !== 'undefined' && typeof cadesplugin.then === 'function') {
    cadesplugin.then(loadCertificates).catch((error) => {
      console.error('CryptoPro', error);
    });
  }

  const signAttached = async (data, cert) => {
    const signer = await cadesplugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(cert);
    const sd = await cadesplugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    if (typeof sd.propset_ContentEncoding === 'function' && typeof cadesplugin.CADESCOM_STRING_TO_UCS2LE !== 'undefined') {
      try { await sd.propset_ContentEncoding(cadesplugin.CADESCOM_STRING_TO_UCS2LE); } catch (e) {}
    }
    await sd.propset_Content(data);
    return sd.SignCades(signer, cadesplugin.CADESCOM_CADES_BES);
  };

  const currentContext = () => ({
    omsConnection: omsConnectionInput.value.trim(),
    omsId: omsIdInput.value.trim(),
    participantInn: participantInnInput.value.trim(),
    stationUrl: stationUrlInput.value.trim(),
    locationAddress: locationAddressInput.value.trim(),
  });

  const saveContext = async (showLog = true) => {
    const payload = currentContext();
    const response = await fetchJson('../api/orders/suz-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    if (showLog) {
      resetLog();
      logLine('Настройки сохранены');
    }
    return response.context || payload;
  };

  saveButton?.addEventListener('click', async () => {
    try {
      await saveContext(true);
    } catch (error) {
      alert(error.message || error);
    }
  });

  testButton?.addEventListener('click', async () => {
    resetLog();
    try {
      const context = await saveContext(false);
      const cert = ensureCert();
      if (!context.oms_connect && !context.omsConnection) {
        throw new Error('Заполните omsConnection');
      }
      const omsConnection = context.oms_connect || context.omsConnection;
      const omsId = context.oms_id || context.omsId;
      if (!omsId) {
        throw new Error('Заполните omsId');
      }
      logLine('→ Запрос challenge в True API');
      const challenge = await fetchJson('../api/orders/suz-auth.php?mode=challenge');
      logLine('→ Подписываем challenge сертификатом');
      const signature = await signAttached(challenge.data, cert);
      logLine('→ Обмениваем на clientToken');
      const response = await fetchJson('../api/orders/suz-auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uuid: challenge.uuid, signature, omsConnection, omsId }),
      });
      logLine('✓ clientToken получен');
      if (response.expiresAt) {
        const date = new Date(response.expiresAt * 1000);
        logLine(`Токен действует до ${date.toLocaleString()}`);
      }
      renderStatus({ active: true, expiresAt: response.expiresAt }, {
        omsConnection,
        omsId,
      });
    } catch (error) {
      logLine('✗ Ошибка: ' + (error.message || error));
      alert(error.message || error);
    }
  });

  resetButton?.addEventListener('click', async () => {
    resetLog();
    try {
      await fetchJson('../api/orders/suz-auth.php', { method: 'DELETE' });
      logLine('clientToken удалён');
      renderStatus({ active: false }, currentContext());
    } catch (error) {
      logLine('✗ Ошибка: ' + (error.message || error));
      alert(error.message || error);
    }
  });

  applyContext(initial.context);
  renderStatus(initial.status, initial.context);
})();
</script>
</body>
</html>
