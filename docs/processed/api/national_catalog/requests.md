# Документация модуля: docs/processed/api/national_catalog/requests.md

## История изменений
- 2025-09-16 — подготовлен реестр методов НК для подготовки заказов КМ (agent).

## Что должен делать код в общих чертах
Модуль `NationalCatalogClient` обеспечивает доступ к карточкам товаров Национального каталога (НК): чтение карточек, загрузку обновлений (feed), проверку КИ (`/v3/mark-check`), управление разрешительными документами и субаккаунтами. Эти данные используются для заполнения заказов КМ и валидации атрибутов перед отправкой в True API/СУЗ. Все запросы выполняются с `apikey` или авторизационным токеном владельца карточек и ограничены 25 товарами в пакетных запросах.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6185-L6193】

## Последовательность действий в коде
1) Получить `apikey` или bearer-токен владельца и выбрать базовый URL (prod или sandbox). 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2039-L2047】
2) Считать существующие карточки (GET `/v3/product`, `/v3/short-product`, `/v4/product-list`) чтобы заполнить данные для заказа КМ. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2015-L2037】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2085-L2099】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2129-L2147】
3) При необходимости обновить карточки: сформировать пакет `/v3/feed`, приложить документы (`/v3/feed-product-document`) и подпись (`/v3/feed-product-sign` / `-pkcs`). 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6185-L6193】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L17024】
4) Проверить статусы обработки через `/v3/feed-status` и `/v3/feed-moderation`. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6305-L6323】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6395-L6417】
5) Выполнить валидации: `POST /v3/mark-check` (≤100 КИ/ТН ВЭД), справочники `/v3/categories`, `/v3/attributes`, `/v3/isocountry`, `/v3/brands`. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L8917-L8925】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L9359-L9371】
6) Управлять доступом субаккаунтов и разрешительными документами (эндпоинты `/v3/linked-accounts*`, `/v3/rd/*`). 【F:docs/reference/national_catalog/extracted/nk_api.txt†L18120-L18212】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L4561-L4604】

## Карта глобальных переменных
| Имя | Тип | Значение по умолчанию | Экспортируется | Назначение | Используется в |
|---|---|---|:---:|---|---|
| `NK_BASE_SANDBOX` | str | `https://api.nk.sandbox.crptech.ru` | да | Базовый адрес песочницы НК. | Все запросы при тестировании. 【F:docs/reference/true_api/extracted/true_api.txt†L870-L873】|
| `NK_BASE_PROD` | str | `https://апи.национальный-каталог.рф` | да | Промышленный контур каталога. | Продакшен синхронизация. 【F:docs/reference/true_api/extracted/true_api.txt†L870-L873】|
| `NK_MAX_ITEMS_PER_REQUEST` | int | `25` | нет | Ограничение на количество товаров в пакетных запросах (`gtins`, `good_ids`, feed). | `feed-product`, `feed`, `mark-check`. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2007-L2009】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L17024】|
| `NK_API_KEY_HEADER` | str | `apikey` | да | Ключ владельца карточек; обязательно, если не используется bearer-токен. | Большинство запросов чтения/записи. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2039-L2047】|
| `NK_FEED_SIGNATURE_TYPE` | str | `CAdES-BES` | нет | Формат подписи при отправке `/v3/feed-product-sign` и `/v3/feed-product-sign-pkcs`. | Обновление карточек. 【F:docs/reference/national_catalog/extracted/nk_api.txt†L16690-L17024】|

## Карта функций
| Метод | URL | Назначение (контекст документа) |
|---|---|---|
| GET | `/v3/feed-product` | Получение карточки по `gtin`/`good_id` или списку до 25 позиций.【F:docs/reference/national_catalog/extracted/nk_api.txt†L410-L450】|
| GET | `/v3/product` | Чтение полной карточки товара по `gtin`/`good_id`. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】|
| GET | `/v3/short-product` | Ускоренная версия карточки (минимальные поля). 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2085-L2099】|
| GET | `/v4/product-list` | Пагинированный список карточек по дате обновления. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2129-L2147】|
| GET | `/v3/etagslist` | Получение хешей карточек для инкрементальной синхронизации. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2211-L2237】|
| POST | `/v3/feed` | Загрузка пакета изменений карточек (до 25 позиций). 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6185-L6193】|
| POST | `/v3/feed-product-document` | Прикрепление файлов (PDF/XML) к карточке. 【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L16290】|
| POST | `/v3/feed-product-sign` | Подпись CAdES-BES для карточек (обязательна для публикации). 【F:docs/reference/national_catalog/extracted/nk_api.txt†L16690-L16720】|
| POST | `/v3/feed-product-sign-pkcs` | Подпись PKCS для внешних систем. 【F:docs/reference/national_catalog/extracted/nk_api.txt†L17024-L17040】|
| GET | `/v3/feed-status` | Проверка статуса обработки пакета. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6305-L6323】|
| GET | `/v3/feed-moderation` | Отправка карточки на модерацию/проверка статуса. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6395-L6417】|
| POST | `/v3/mark-check` | Проверка КИ и ТН ВЭД (≤100 записей). 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】|
| GET | `/v3/categories` | Получение дерева категорий НК. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L8917-L8925】|
| GET | `/v3/attributes` | Список атрибутов по ТГ/ТН ВЭД. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L9359-L9371】|
| GET | `/v3/isocountry` | Справочник стран производителя. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L9609-L9617】|
| GET | `/v3/brands` | Справочник товарных знаков. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L9787-L9795】|
| GET | `/v3/generate-gtins` | Генерация черновиков GTIN для новых товаров. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L10025-L10039】|
| POST | `/v3/linked-accounts` | Управление субаккаунтами (список). 【F:docs/reference/national_catalog/extracted/nk_api.txt†L18120-L18164】|
| POST | `/v3/linked-accounts-documents` | Документы доступа субаккаунтов. 【F:docs/reference/national_catalog/extracted/nk_api.txt†L18164-L18212】|
| POST | `/v3/linked-accounts-sign` | Подписание разрешения субаккаунту. 【F:docs/reference/national_catalog/extracted/nk_api.txt†L18212-L18238】|
| POST | `/v4/rd-info-by-gtin` | Получение разрешительных документов по GTIN. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L4561-L4604】|
| POST | `/v4/rd-info` | Получение разрешительных документов по номеру и дате. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L4687-L4703】|

## Описание функций
### `fetch_product(gtin_or_id, session, api_key) -> Product`
**Назначение.** Получение полной карточки товара через `/v3/product` для заполнения заказа КМ и проверки атрибутов ЛП.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】
**Параметры.** `gtin_or_id` — код товара или идентификатор; `session` — HTTP-клиент; `api_key` — ключ владельца.
**Возвращает.** Объект `Product` с атрибутами и списком разрешительных документов.
**Исключения.** `NotFoundError` (404) или `BadRequestError` (400) при нарушении условий запроса.
**Сайд-эффекты.** Кеширование карточки для фронтенда.
**Локальные переменные.**
- `params: dict` — включает `gtin`, `good_id`, `apikey`.

### `sync_feed(feed_payload, session, api_key, signature) -> FeedTicket`
**Назначение.** Массовое обновление карточек через `/v3/feed` с прикреплением документов и подписи (`/v3/feed-product-document`, `/v3/feed-product-sign`).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6185-L6193】【F:docs/reference/national_catalog/extracted/nk_api.txt†L16270-L16720】
**Параметры.** `feed_payload` — JSON пакет (≤25 товаров); `session` — HTTP-клиент; `api_key` — ключ; `signature` — результат CAdES/PKCS подписи.
**Возвращает.** Идентификатор feed и статус отправки.
**Исключения.** `FeedValidationError` при нарушении лимитов или отсутствия подписи.
**Сайд-эффекты.** Логирование файла, обновление очереди публикаций.
**Локальные переменные.**
- `files: dict` — карта документов для `/v3/feed-product-document`.
- `sign_payload: dict` — данные для `/v3/feed-product-sign`.

### `check_feed_status(feed_id, session, api_key) -> FeedStatus`
**Назначение.** Проверка обработки пакета (`/v3/feed-status`) и статуса модерации (`/v3/feed-moderation`).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6305-L6323】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L6395-L6417】
**Параметры.** `feed_id` — идентификатор загрузки; `session`, `api_key` — контекст.
**Возвращает.** Статус (IN_PROGRESS/PROCESSED/REJECTED) и сообщения модерации.
**Исключения.** `NotFoundError` для устаревших или удалённых пакетов.
**Сайд-эффекты.** Обновление локального статуса карточек.
**Локальные переменные.**
- `status_response`, `moderation_response` — ответы API.

### `mark_check(codes: list[str], hs_codes: list[str], session, api_key) -> MarkCheckResult`
**Назначение.** Проверка валидности КИ и кодов ТН ВЭД (до 100 записей) перед заказом КМ (`/v3/mark-check`).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】
**Параметры.** `codes` — список КИ; `hs_codes` — список ТН ВЭД; `session`, `api_key` — контекст.
**Возвращает.** Результаты проверки (VALID/INVALID) с причинами.
**Исключения.** `ValidationError` при превышении лимита 100 или несоответствии формата.
**Сайд-эффекты.** Нет.
**Локальные переменные.**
- `payload: dict` — структура `cis_list`, `tnved_list`.

### `download_reference(reference_type, session, api_key) -> ReferenceData`
**Назначение.** Загрузка справочников (`/v3/categories`, `/v3/attributes`, `/v3/isocountry`, `/v3/brands`) для автозаполнения карточек ЛП.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L8917-L8925】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L9359-L9371】【F:docs/reference/national_catalog/extracted/catalog_api.txt†L9609-L9617】
**Параметры.** `reference_type` — enum (`categories`, `attributes`, `isocountry`, `brands`); `session`, `api_key` — контекст.
**Возвращает.** Справочник с метаданными (ID, названия, версии).
**Исключения.** `ReferenceError` при неизвестном типе или недействительном ключе.
**Сайд-эффекты.** Обновление локальных кешей фронтенда.
**Локальные переменные.**
- `endpoint: str` — выбранный путь.

### `manage_linked_accounts(action, payload, session, api_key) -> LinkedAccountResult`
**Назначение.** Управление субаккаунтами компании (`/v3/linked-accounts`, `/v3/linked-accounts-documents`, `/v3/linked-accounts-sign`).【F:docs/reference/national_catalog/extracted/nk_api.txt†L18120-L18238】
**Параметры.** `action` — `list`/`document`/`sign`; `payload` — тело запроса; `session`, `api_key` — контекст.
**Возвращает.** Список субаккаунтов или статус подписания доступа.
**Исключения.** `AccessError` при попытке открыть доступ к зарубежным GTIN (ограничение по документу).
**Сайд-эффекты.** Синхронизация прав в системах компании.
**Локальные переменные.**
- `url: str` — соответствующий endpoint.

## Описание исполнительной части кода
- Действия при импорте: нет.
- CLI/entrypoints: нет; модуль используется бэкендом и воркерами синхронизации.
- Типичный вызов:
  1. `product = fetch_product(gtin, session, api_key)` для проверки карточки.
  2. `feed_ticket = sync_feed(feed_payload, session, api_key, signature)` при обновлении.
  3. `status = check_feed_status(feed_ticket.id, session, api_key)` → при успехе перейти к заказу КМ.
  4. Перед заказом вызвать `mark_check(cises, hs_codes, ...)`.
  5. Обновить справочники `download_reference('categories', ...)` и выдать доступ субаккаунтам `manage_linked_accounts(...)`.
