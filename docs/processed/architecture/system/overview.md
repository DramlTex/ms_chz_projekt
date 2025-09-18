# Документация модуля: docs/processed/architecture/system/overview.md

## История изменений
- 2025-09-16 — сформирован целевой архитектурный каркас для сервиса заказа КМ (agent).

## Что должен делать код в общих чертах
Система должна позволять пользователю из лёгкой промышленности оформлять заказы КМ, контролировать статусы и передавать документы в True API/СУЗ без ручных операций. Бекенд обеспечивает единое хранение карточек товаров (кэш Национального каталога), очередь заявок и интеграцию с True API и СУЗ по УКЭП, фронтенд — простую страницу заказа с подписями через CryptoPro plug-in.【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1144】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】Основные сервисы: REST API на FastAPI (Python), воркер Celery для фоновой обработки документов, фронтенд на Next.js/TypeScript, PostgreSQL как хранилище заявок и справочников, Redis для очередей и кеша токенов.

## Последовательность действий в коде
1) Пользователь авторизуется и подтягивает карточку товара из НК (GET `/v3/product`, `/v3/feed-product`).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】
2) Фронтенд вызывает `GET /auth/key` и получает `uuid/data` для подписи УКЭП через CryptoPro (detached, ГОСТ).【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1128】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】
3) Подписанное значение отправляется на бекенд `POST /auth/simpleSignIn`, система сохраняет `jwt`/`clientToken` в Redis и связывает сессии.【F:docs/reference/true_api/extracted/true_api.txt†L1202-L1234】【F:docs/reference/suz/extracted/guides.txt†L6-L58】
4) Пользователь заполняет заказ, фронтенд валидирует КИ через `/v3/mark-check` и справочники (`/v3/categories`, `/v3/attributes`).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L8917-L8925】
5) Бекенд формирует документ `/lk/documents/create` и заказ `/api/v3/order?omsId=…`, подписывает payload и ставит задачу воркеру на отправку/мониторинг статусов.【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
6) Воркер отслеживает статусы через `/api/v4/true-api/doc/list` и `/api/v3/order/list`, обновляет базу и уведомляет фронтенд (WebSocket/Server-Sent Events).【F:docs/reference/true_api/extracted/true_api.txt†L50348-L50404】【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】
7) После выполнения выдаёт закрытие заказа (`/api/v3/order/close`) и при необходимости оформляет выбытие/утилизацию (`/api/v3/dropout`, `/api/v3/utilisation`).【F:docs/reference/suz/extracted/suz_pdf.txt†L7244-L7267】【F:docs/reference/suz/extracted/suz_pdf.txt†L7438-L7524】

## Карта глобальных переменных
| Имя | Тип | Значение по умолчанию | Экспортируется | Назначение | Используется в |
|---|---|---|:---:|---|---|
| `TRUE_API_BASE_SANDBOX` | str | `https://markirovka.sandbox.crptech.ru/api/v4/true-api` | да | Базовый URL True API для песочницы. | Аутентификация и документы. 【F:docs/reference/true_api/extracted/true_api.txt†L858-L868】|
| `SUZ_BASE_SANDBOX` | str | `https://suz.sandbox.crptech.ru/api/v3` | да | Базовый URL СУЗ для теста. | Заказы КМ. 【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1231】|
| `NK_BASE_SANDBOX` | str | `https://api.nk.sandbox.crptech.ru` | да | Доступ к НК. | Получение карточек/справочников. 【F:docs/reference/true_api/extracted/true_api.txt†L870-L873】|
| `CRYPTO_PRO_PLUGIN_ID` | str | `cadesplugin` | да | Идентификатор CryptoPro Browser Plug-in в браузере. | Фронтенд подписи. 【F:docs/reference/true_api/extracted/true_api_quickstart.md†L52-L74】|
| `REQUEST_RPS_LIMIT_TRUE_API` | int | `50` | нет | Ограничение True API на УОТ. | Rate limiter. 【F:docs/reference/true_api/extracted/true_api.txt†L852-L855】|
| `REQUEST_RPS_LIMIT_SUZ` | int | `10` | нет | Ограничение СУЗ по IP/omsId. | Rate limiter. 【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1235】|

## Карта функций
| Имя | Видимость | Сигнатура | Краткое назначение | Исключения | Сайд-эффекты |
|---|---|---|---|---|---|
| `AuthService.issue_tokens` | public | `issue_tokens(user_session, signed_data)` | Выполняет связку `/auth/key` → `/auth/simpleSignIn`, сохраняет токены. | `AuthenticationError` | Запись токенов в Redis. |
| `OrderService.create_true_api_document` | public | `create_true_api_document(payload, tokens)` | Формирует и отправляет документ `/lk/documents/create`. | `ValidationError`, `AuthorizationError` | Постановка задачи воркеру. |
| `OrderService.create_suz_order` | public | `create_suz_order(order, client_token, signature)` | Отправка заказа `/api/v3/order` и сохранение orderId. | `OrderValidationError`, `SignatureError` | Сохранение заказа в БД. |
| `CatalogueService.sync_product` | public | `sync_product(gtin)` | Получение и кеширование карточки из НК. | `NotFoundError` | Обновление PostgreSQL. |
| `CatalogueService.push_feed` | public | `push_feed(feed_payload, signature)` | Формирование и отправка feed (документы + подпись). | `FeedValidationError` | Ведение журнала публикаций. |
| `MonitoringWorker.poll_statuses` | private | `poll_statuses()` | Периодически проверяет статусы документов/заказов. | нет | Обновление БД, уведомление WebSocket. |
| `EdsService.sign_payload` | public | `sign_payload(data, certificate_thumbprint)` | Обёртка над CryptoPro plug-in для подписи. | `SignatureError` | Обращение к плагину в браузере. |

## Описание функций
### `AuthService.issue_tokens`
**Назначение.** Получить `uuid/data`, принять подписанные данные от клиента, вызвать `/auth/simpleSignIn` и сохранить jwt/uuidToken и `clientToken` для СУЗ.【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1128】【F:docs/reference/suz/extracted/guides.txt†L6-L58】
**Параметры.** `user_session` — идентификатор сессии; `signed_data` — результат подписи (PKCS7). 
**Возвращает.** Структуру токенов для True API и СУЗ.
**Исключения.** `AuthenticationError` при 4xx/5xx от API.
**Сайд-эффекты.** Кеш токенов в Redis.
**Локальные переменные.**
- `auth_challenge` — ответ `/auth/key`.
- `simple_sign_in_body` — JSON payload для True API.

### `OrderService.create_true_api_document`
**Назначение.** Собрать и отправить документ в True API через `/lk/documents/create` с подписью УКЭП.【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】
**Параметры.** `payload` — тело документа; `tokens` — jwt/uuidToken.
**Возвращает.** Ответ True API с идентификатором документа.
**Исключения.** `ValidationError`, `AuthorizationError`.
**Сайд-эффекты.** Постановка задания мониторинга.
**Локальные переменные.**
- `request_url` — `/lk/documents/create?pg=...`.
- `headers` — `Authorization`, `Accept`.

### `OrderService.create_suz_order`
**Назначение.** Отправить заказ КМ в СУЗ (`/api/v3/order?omsId=…`) с подписью `X-Signature` и сохранить `orderId`.【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
**Параметры.** `order` — данные заказа; `client_token`; `signature`.
**Возвращает.** Идентификатор заказа СУЗ.
**Исключения.** `OrderValidationError`, `SignatureError`.
**Сайд-эффекты.** Запись в таблицу `suz_orders`.
**Локальные переменные.**
- `endpoint` — `/api/v3/order?omsId=...`.
- `body` — JSON заказа.

### `CatalogueService.sync_product`
**Назначение.** Получить карточку товара из НК и синхронизировать с локальной БД для подстановки в заказы.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】
**Параметры.** `gtin` — код товара.
**Возвращает.** DTO с данными карточки.
**Исключения.** `NotFoundError`.
**Сайд-эффекты.** Обновление таблицы `products`.
**Локальные переменные.**
- `nk_response` — ответ API.

### `CatalogueService.push_feed`
**Назначение.** Подготовить пакет изменений карточек (`/v3/feed`), загрузить документы и подпись перед публикацией.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6185-L6193】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L16720】
**Параметры.** `feed_payload`, `signature`.
**Возвращает.** `feed_id` и статус отправки.
**Исключения.** `FeedValidationError`.
**Сайд-эффекты.** Журнал публикаций.
**Локальные переменные.**
- `documents_payload` — список файлов для `/v3/feed-product-document`.

### `MonitoringWorker.poll_statuses`
**Назначение.** Выполнять периодический опрос `/api/v4/true-api/doc/list` и `/api/v3/order/list` для актуализации статусов заказов и документов.【F:docs/reference/true_api/extracted/true_api.txt†L50348-L50404】【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】
**Параметры.** Нет (использует конфигурацию).
**Возвращает.** Нет — обновляет БД и отправляет события.
**Исключения.** Обрабатывает сетевые ошибки с повтором.
**Сайд-эффекты.** Обновление таблиц статусов, публикация уведомлений.
**Локальные переменные.**
- `documents` — список документов True API.
- `orders` — список заказов СУЗ.

### `EdsService.sign_payload`
**Назначение.** На фронтенде вызвать CryptoPro plug-in для подписания строки/хэша (detached CAdES).【F:docs/reference/true_api/extracted/true_api_quickstart.md†L64-L110】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】
**Параметры.** `data` — строка для подписи; `certificate_thumbprint` — выбранный сертификат.
**Возвращает.** Base64 подпись.
**Исключения.** `SignatureError` при отказе плагина.
**Сайд-эффекты.** Запрос доступа к сертификату у пользователя.
**Локальные переменные.**
- `plugin` — объект CryptoPro в окне браузера.

## Описание исполнительной части кода
- Действия при импорте: инициализация конфигурации, подключение к PostgreSQL и Redis, настройка клиентов True API/СУЗ/НК.
- CLI/entrypoints: `uvicorn app.main:app` (REST API), `celery -A worker beat`/`celery -A worker worker` (фоновые задачи).
- Типичный вызов:
  1. Пользователь открывает страницу заказа, фронтенд запрашивает карточки товаров (`fetch_product`).
  2. При создании заказа вызывается `AuthService.issue_tokens` → `OrderService.create_true_api_document` → `OrderService.create_suz_order`.
  3. `MonitoringWorker.poll_statuses` обновляет статусы; при завершении `OrderService` вызывает `create_dropout`/`submit_utilisation`.
  4. Данные карточек синхронизируются планово через `CatalogueService.sync_product` и `CatalogueService.push_feed`.
