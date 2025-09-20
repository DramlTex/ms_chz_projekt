<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CHZ LP — Заказ КМ и ввод в оборот</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
  <script>
    window.APP_CONFIG = { apiRoot: '' };
  </script>
  <script src="vendor/crypto_pro/cadesplugin_api.js"></script>
  <script src="assets/js/signature.js" defer></script>
  <script src="assets/js/api.js" defer></script>
  <script src="assets/js/app.js" defer></script>
</head>
<body>
  <header>
    <h1>Заказ кодов маркировки и ввод в оборот</h1>
    <p class="muted">Боевой сценарий для товарной группы «Лёгкая промышленность»: авторизация, заказ КМ в СУЗ и отправка документа в True API.</p>
  </header>

  <main>
    <section>
      <h2>Шаг 1. Сертификат УКЭП и авторизация</h2>
      <div class="alert">
        <strong>Последовательность:</strong>
        <ol>
          <li>Выберите действующий сертификат УКЭП (CryptoPro Browser Plug-in).</li>
          <li>Получите challenge и подпишите его для True API и СУЗ.</li>
          <li>Проверьте, что токены активны — статус отобразится ниже.</li>
        </ol>
      </div>

      <div class="card">
        <h3>Сертификат</h3>
        <div class="flex-row">
          <div>
            <label for="certificateSelect">Сертификат из хранилища CryptoPro</label>
            <select id="certificateSelect"></select>
            <div class="inline-actions">
              <button id="refreshCertificatesBtn" type="button" class="secondary">Обновить список</button>
            </div>
            <p class="muted" id="selectedCertificateInfo">Сертификат не выбран</p>
          </div>
        </div>
      </div>

      <div class="grid two">
        <div class="card">
          <h3>True API (ГИС МТ)</h3>
          <p class="muted">Авторизация выполняется по подписи challenge из <code>/auth/key</code>. Дополнительно можно запросить единый uuidToken.</p>
          <label for="trueApiInn">ИНН организации (опционально)</label>
          <input type="text" id="trueApiInn" placeholder="10 или 12 цифр" />
          <label class="small" style="display:flex;align-items:center;gap:0.4rem;margin-top:0.6rem;">
            <input type="checkbox" id="trueApiUnitedToken" /> Запросить unitedToken
          </label>
          <div class="inline-actions" style="margin-top:0.8rem;">
            <button id="trueApiRequestKeyBtn" type="button">Получить challenge</button>
            <button id="trueApiSignInBtn" type="button">Подписать и войти</button>
          </div>
          <p class="muted">Статус: <span class="status-pill" id="trueApiStatus">Токен отсутствует</span></p>
          <pre class="code-block" id="trueApiChallenge">Нажмите «Получить challenge»</pre>
        </div>

        <div class="card">
          <h3>СУЗ-Облако</h3>
          <p class="muted">Для clientToken требуется OMS ID и OMS Connection, полученные в ЛК или у интегратора.</p>
          <label for="suzOmsId">OMS ID</label>
          <input type="text" id="suzOmsId" placeholder="GUID OMS" />
          <label for="suzOmsConnection">OMS Connection</label>
          <input type="text" id="suzOmsConnection" placeholder="GUID подключения" />
          <div class="inline-actions" style="margin-top:0.8rem;">
            <button id="suzRequestKeyBtn" type="button">Получить challenge</button>
            <button id="suzSignInBtn" type="button">Подписать и получить clientToken</button>
          </div>
          <p class="muted">Статус: <span class="status-pill" id="suzStatus">clientToken отсутствует</span></p>
          <pre class="code-block" id="suzChallenge">Нажмите «Получить challenge»</pre>
        </div>
      </div>
    </section>

    <section>
      <h2>Шаг 2. Карточки Национального каталога</h2>
      <form id="catalogForm" class="flex-row">
        <div>
          <label for="catalogSearch">Поиск</label>
          <input type="text" id="catalogSearch" placeholder="GTIN, наименование" />
        </div>
        <div>
          <label for="catalogGroup">Группа</label>
          <select id="catalogGroup">
            <option value="">Все</option>
            <option value="lp">Лёгкая промышленность</option>
            <option value="shoes">Обувь</option>
            <option value="textile">Текстиль</option>
          </select>
        </div>
        <div>
          <label for="catalogDateFrom">Дата обновления с</label>
          <input type="date" id="catalogDateFrom" />
        </div>
        <div>
          <label for="catalogDateTo">Дата обновления по</label>
          <input type="date" id="catalogDateTo" />
        </div>
        <div>
          <label for="catalogLimit">Лимит</label>
          <input type="number" id="catalogLimit" value="100" min="1" max="1000" />
        </div>
        <div style="align-self:flex-end;">
          <button type="submit">Загрузить карточки</button>
        </div>
      </form>
      <p class="muted">Найдено: <span id="catalogCounter">0</span></p>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th></th>
              <th>ID</th>
              <th>GTIN</th>
              <th>Наименование</th>
              <th>Бренд</th>
              <th>Количество</th>
              <th>Обновлено</th>
            </tr>
          </thead>
          <tbody id="catalogTableBody"></tbody>
        </table>
      </div>
      <div class="inline-actions">
        <button id="buildOrderTemplateBtn" type="button">Сформировать заказ из выбранных позиций</button>
      </div>
    </section>

    <section>
      <h2>Шаг 3. Заказ кодов маркировки (СУЗ)</h2>
      <div class="flex-row">
        <div class="card" style="flex:2 1 520px;">
          <label for="orderOmsId">OMS ID</label>
          <input type="text" id="orderOmsId" placeholder="OMS ID для заказа" />
          <label for="orderPayload">JSON заказа</label>
          <textarea id="orderPayload" placeholder='{"productGroup":"lp","orderType":"LP_KM_EMISSION","products":[]}'></textarea>
          <div class="inline-actions">
            <button id="sendOrderBtn" type="button">Подписать и отправить заказ</button>
            <button id="refreshOrdersBtn" type="button" class="secondary">Обновить список заказов</button>
          </div>
        </div>
        <div class="card" style="flex:1 1 280px;">
          <h3>Закрыть заказ</h3>
          <label for="closeOrderId">Идентификатор заказа</label>
          <input type="text" id="closeOrderId" placeholder="GUID заказа" />
          <button id="closeOrderBtn" type="button" class="secondary" style="margin-top:0.8rem;">Подписать и закрыть</button>
        </div>
      </div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Статус</th>
              <th>Товарная группа</th>
              <th>Количество</th>
              <th>Создан</th>
            </tr>
          </thead>
          <tbody id="ordersTableBody"></tbody>
        </table>
      </div>
    </section>

    <section>
      <h2>Шаг 4. Ввод в оборот (True API)</h2>
      <div class="card">
        <div class="flex-row">
          <div>
            <label for="documentProductGroup">Код товарной группы (pg)</label>
            <input type="text" id="documentProductGroup" value="lp" />
          </div>
          <div>
            <label for="documentFormat">Формат</label>
            <input type="text" id="documentFormat" value="MANUAL" />
          </div>
          <div>
            <label for="documentType">Тип документа</label>
            <input type="text" id="documentType" value="LP_INTRODUCTION" />
          </div>
        </div>
        <label for="documentBody">JSON документа (product_document)</label>
        <textarea id="documentBody" placeholder='{"products":[]}'></textarea>
        <div class="inline-actions">
          <button id="sendDocumentBtn" type="button">Подписать и отправить документ</button>
          <button id="refreshDocumentsBtn" type="button" class="secondary">Обновить список документов</button>
        </div>
      </div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Документ</th>
              <th>Тип</th>
              <th>Статус</th>
              <th>Дата</th>
            </tr>
          </thead>
          <tbody id="documentsTableBody"></tbody>
        </table>
      </div>
    </section>

    <section>
      <h2>Журнал операций</h2>
      <pre class="log-area" id="logArea">Готово к работе.</pre>
    </section>
  </main>
</body>
</html>
