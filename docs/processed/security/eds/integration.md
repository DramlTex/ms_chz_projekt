# Документация модуля: docs/processed/security/eds/integration.md

## История изменений
- 2025-09-24 — `initAuthPanel` автоматически запускает `ensureCryptoProPlugin` и загрузку сертификатов после монтирования панели, чтобы исключить ручной клик по кнопке подключения CryptoPro (agent).【F:prototypes/order-app/ui/authPanel.js†L332-L381】
- 2025-09-23 — `ensureCryptoProPlugin` добавляет поддержку устаревшего синхронного API `CreateObject` и выводит предупреждение, если асинхронный интерфейс недоступен (agent).【F:prototypes/order-app/services/cryptoProClient.js†L30-L166】【F:prototypes/order-app/services/cryptoProClient.js†L187-L290】
- 2025-09-22 — Добавлен HTML-пример CAdES-BES подписи строки через промис `cadesplugin.then`, включая перечень сертификатов и журнал ошибок (agent).【F:prototypes/crypto_pro/cades-sign-example.html†L1-L213】
- 2025-09-21 — `cadesplugin_api.js` валидирует наличие объекта плагина перед вызовом `CreateObject(CreateObjectAsync)` и выбрасывает понятную ошибку, если расширение недоступно (agent).【F:prototypes/crypto_pro/cadesplugin_api.js†L482-L511】【F:prototypes/crypto_pro/cadesplugin_api.js†L569-L570】
- 2025-09-20 — `cadesplugin_api.js` теперь ожидает появления DOM-элементов `<head>`/`<body>` перед подключением расширений и логирует ошибку, если контейнер недоступен (agent).【F:prototypes/crypto_pro/cadesplugin_api.js†L660-L746】
- 2025-09-19 — добавлена проверка доступности расширения CryptoPro и обновлены уведомления UI при отсутствии сертификатов (agent).【F:prototypes/order-app/services/cryptoProClient.js†L15-L45】【F:prototypes/order-app/ui/authPanel.js†L52-L109】
- 2025-09-18 — исправлена инициализация CryptoPro: промис плагина дожидается загрузки без повторного объявления переменных (agent).【F:prototypes/order-app/services/cryptoProClient.js†L83-L125】
- 2025-09-17 — добавлено ожидание промиса CryptoPro перед вызовом API (agent).
- 2025-09-16 — описана архитектура работы с УКЭП и CryptoPro (agent).

## Что должен делать код в общих чертах
Модуль `EdsIntegration` управляет жизненным циклом УКЭП: выбором сертификата в браузере, созданием откреплённых подписей для True API/СУЗ/НК и проверкой результатов на бекенде. Используется CryptoPro Browser Plug-in (CAdES-BES, ГОСТ Р 34.10-2012), подписи передаются в `product_document.signature`, `X-Signature` и feed-запросы НК.【F:docs/reference/true_api/extracted/true_api_quickstart.md†L64-L110】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L560】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16690-L17024】

## Последовательность действий в коде

1) Фронтенд подключает `cadesplugin_api.js`, вызывает `ensureCryptoProPlugin`, дожидается разрешения промиса, проверяет наличие `CreateObjectAsync` и при необходимости использует синхронный `CreateObject` (с предупреждением в консоли), дополнительно создавая `CAdESCOM.About`, чтобы убедиться в установке браузерного расширения CryptoPro и получить системный запрос доступа к сертификатам.【F:docs/reference/true_api/extracted/true_api_quickstart.md†L52-L86】【F:prototypes/order-app/services/cryptoProClient.js†L30-L170】
2) После успешной инициализации `listCertificates` открывает хранилище `CAPICOM_CURRENT_USER_STORE` "My", фильтрует действующие сертификаты ГОСТ Р 34.10-2012, сортирует их по сроку действия и сохраняет в `sessionStore` для UI.【F:prototypes/order-app/services/cryptoProClient.js†L142-L178】【F:prototypes/order-app/state/sessionStore.js†L45-L104】
3) `sessionStore` рассылает события `plugin-status-changed`, `certificates-changed` и `certificate-selected`, а `authPanel` автоматически вызывает `ensureCryptoProPlugin` при инициализации, обновляет кнопку «Подключить CryptoPro»/список сертификатов и показывает подсказки, если действующие сертификаты отсутствуют перед запросом токена True API.【F:prototypes/order-app/state/sessionStore.js†L64-L104】【F:prototypes/order-app/ui/authPanel.js†L28-L381】
4) При запросе токена True API вызывается `signTrueApiChallenge`/`signData`, выполняется CAdES-BES подпись XML-челленджа и результат отправляется на `/auth/simpleSignIn` для выдачи bearer-токена.【F:prototypes/order-app/services/cryptoProClient.js†L197-L240】【F:prototypes/order-app/ui/authPanel.js†L84-L112】【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1234】
5) Для СУЗ и Национального каталога повторно используется подпись: формируется `X-Signature` для REST-запросов и PKCS#7-файлы `feed-product-sign(-pkcs)`; бекенд валидирует цепочку сертификатов и сроки действия перед принятием документов.【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L560】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L17024】

## Карта глобальных переменных
| Имя | Тип | Значение по умолчанию | Экспортируется | Назначение | Используется в |
|---|---|---|:---:|---|---|
| `CRYPTO_PRO_PLUGIN_ID` | str | `cadesplugin` | да | Идентификатор плагина в браузере. | JS-обёртка подписи. 【F:docs/reference/true_api/extracted/true_api_quickstart.md†L52-L74】|
| `SIGNATURE_ALGORITHM` | str | `GOST R 34.10-2012 256` | нет | Алгоритм подписи для всех сценариев. | Фронтенд и бекенд. 【F:docs/reference/true_api/extracted/true_api_quickstart.md†L82-L110】|
| `SIGNATURE_TRANSFER_HEADER` | str | `X-Signature` | да | Заголовок для подписи в СУЗ. | СУЗ запросы. 【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L560】|
| `FEED_SIGNATURE_TYPE` | str | `CAdES-BES` | нет | Тип подписи при отправке feed в НК. | НК обновления. 【F:docs/reference/national_catalog/extracted/nk_api.txt†L16690-L16720】|
| `CERT_RENEWAL_THRESHOLD_DAYS` | int | `30` | нет | За сколько дней предупреждать об истечении сертификата. | UI уведомления. |

## Карта функций
| Имя | Видимость | Сигнатура | Краткое назначение | Исключения | Сайд-эффекты |
|---|---|---|---|---|---|
| `list_certificates` | public | `list_certificates()` | Возвращает доступные сертификаты пользователя из CryptoPro. | `PluginError` | Запрашивает доступ к плагину. |
| `sign_string` | public | `sign_string(data, thumbprint, mode)` | Подписывает строку/хэш в браузере (raw или CAdES). | `SignatureError` | Отправка запроса к плагину. |
| `verify_signature` | public | `verify_signature(data, signature)` | Проверка подписи на бекенде (ГОСТ, срок действия). | `SignatureError` | Обновление аудита. |
| `attach_signature_true_api` | private | `attach_signature_true_api(document)` | Заполняет поле `signature` в документе True API. | `SignatureError` | Модифицирует payload. |
| `attach_signature_suz` | private | `attach_signature_suz(request)` | Добавляет `X-Signature` и Base64 в заголовок. | `SignatureError` | Изменяет HTTP-запрос. |
| `attach_signature_nk_feed` | private | `attach_signature_nk_feed(feed_payload)` | Подготавливает структуры для `/v3/feed-product-sign` и `/v3/feed-product-sign-pkcs`. | `SignatureError` | Создаёт временные файлы подписи. |

## Описание функций
### `list_certificates`
**Назначение.** Через JS-API плагина (`cadesplugin.CreateObject`) получить список сертификатов, отфильтровать по действию и пригодности для ГОСТ 2012. Перед обращением `ensureCryptoProPlugin` дожидается готовности промиса `cadesplugin`, чтобы исключить ошибки `CreateObjectAsync` для неподготовленного плагина.【F:docs/reference/true_api/extracted/true_api_quickstart.md†L64-L110】【F:prototypes/order-app/services/cryptoProClient.js†L142-L178】
**Параметры.** Нет (использует API браузера).
**Возвращает.** Список `{thumbprint, subject, valid_to}`.
**Исключения.** `PluginError` при недоступности плагина.
**Сайд-эффекты.** Запрашивает разрешение пользователя на доступ к сертификатам.
**Локальные переменные.**
- `store` — хранилище сертификатов CryptoPro.

### `sign_string`
**Назначение.** Подписать строку для `/auth/simpleSignIn`, `X-Signature` или feed НК, поддерживая режимы `raw` (хэш) и `cades` (PKCS7).【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L538】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16690-L17024】
**Параметры.** `data`, `thumbprint`, `mode` (`raw`/`cades`).
**Возвращает.** Base64 подпись.
**Исключения.** `SignatureError`.
**Сайд-эффекты.** Нет.
**Локальные переменные.**
- `hashed_data` — хэш ГОСТ 34.11-2012.
- `signed_data` — результат CryptoPro.

### `verify_signature`
**Назначение.** На бекенде проверить подпись: валидность сертификата, принадлежность УОТ, корректность алгоритма. Используется для входящих документов/квитанций. 【F:docs/reference/true_api/extracted/true_api.txt†L1096-L1104】
**Параметры.** `data`, `signature`.
**Возвращает.** Результат проверки (bool + детали).
**Исключения.** `SignatureError` при несоответствии.
**Сайд-эффекты.** Запись проверки в аудит.
**Локальные переменные.**
- `cert_chain` — цепочка сертификатов.

### `attach_signature_true_api`
**Назначение.** При формировании документов добавить подпись в поле `signature` и удостовериться, что документ укладывается в лимиты (≤30 MB, ≤30 000 КИ).【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】【F:docs/reference/true_api/extracted/true_api.txt†L994-L995】
**Параметры.** `document` — JSON.
**Возвращает.** Обновлённый документ.
**Исключения.** `ValidationError`.
**Сайд-эффекты.** Нет.
**Локальные переменные.**
- `signature` — подпись документа.
- `payload_size` — размер тела запроса.

### `attach_signature_suz`
**Назначение.** Добавить `clientToken` и `X-Signature` к HTTP-запросам СУЗ в зависимости от метода (GET/POST).【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L538】【F:docs/reference/suz/extracted/suz_pdf.txt†L6421-L6453】
**Параметры.** `request` — объект HTTP.
**Возвращает.** Обновлённый запрос.
**Исключения.** `SignatureError`.
**Сайд-эффекты.** Модифицирует заголовки.
**Локальные переменные.**
- `to_sign` — строка для подписи.
- `signature` — Base64 подпись.

### `attach_signature_nk_feed`
**Назначение.** Создать подпись для пакета `/v3/feed` и сформировать структуру для `/v3/feed-product-sign`/`-pkcs` (CAdES-BES).【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L17024】
**Параметры.** `feed_payload`.
**Возвращает.** Кортеж `(signed_payload, signature_file)`.
**Исключения.** `SignatureError`.
**Сайд-эффекты.** Создаёт временный файл подписи.
**Локальные переменные.**
- `hash_context` — объект хэширования.
- `signature_file_path` — путь к подписи.

## Описание исполнительной части кода
- Действия при импорте: инициализация клиента CryptoPro на фронтенде, загрузка `cadesplugin_api.js` и проверка наличия сертификатов.
- CLI/entrypoints: нет.
- Типичный вызов:
  1. Фронтенд выполняет `list_certificates()` → пользователь выбирает сертификат.
  2. `sign_string(data, thumbprint, 'cades')` → подпись отправляется в бекенд.
  3. Бекенд через `attach_signature_true_api` и `attach_signature_suz` вставляет подписи в запросы, `verify_signature` проверяет входящие квитанции.
  4. Для обновлений НК вызывается `attach_signature_nk_feed` и загружаются документы.
