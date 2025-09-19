# Документация модуля: docs/processed/api/suz/requests.md

## История изменений
- 2025-09-16 — собраны основные запросы СУЗ-Облака для заказа КМ (agent).

## Что должен делать код в общих чертах
Модуль `SuzClient` реализует интеграцию с API СУЗ-Облака для оформления заказов кодов маркировки, контроля статусов и постобработки (агрегации, списания, возвратов). Он работает поверх единой аутентификации ГИС МТ: получает `clientToken`, подписывает запросы откреплённой УКЭП в заголовке `X-Signature` и соблюдает частотные ограничения (≤10 запросов в секунду на пару IP/omsId).【F:docs/reference/suz/extracted/guides.txt†L6-L58】【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1235】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】
Для товарной группы «Лёгкая промышленность» клиент должен поддерживать полное жизненное состояние заказа: от отправки заявки `/api/v3/order` до закрытия `/api/v3/order/close`, обработки незавершённых заказов и вспомогательных операций (dropout, utilisation, aggregation). Каждая операция требует `clientToken`, JSON-тело с атрибутами шаблонов и подпись в `X-Signature` согласно ГОСТ Р 34.10-2012.【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】【F:docs/reference/suz/extracted/suz_pdf.txt†L7244-L7267】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】

## Последовательность действий в коде
1) Определить базовый URL стенда (sandbox или prod) и подготовить HTTP-клиент с ограничением на ≤10 rps и поддержкой ГОСТ TLS. 【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1235】
2) Получить `omsConnection` через UI/регистрацию установки, затем выполнить `GET /auth/key` → `POST /auth/simpleSignIn/{omsConnection}` для динамического `clientToken`.【F:docs/reference/suz/extracted/guides.txt†L49-L80】【F:docs/reference/suz/extracted/guides.txt†L6-L40】
3) Сформировать тело заказа (productGroup, products, attributes), подписать JSON и отправить `POST /api/v3/order?omsId=…` с `clientToken` и `X-Signature`.【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
4) Отслеживать прогресс: использовать `GET /api/v3/order/list` и `POST /api/v3/order/close` для закрытия выполненных заказов. 【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】【F:docs/reference/suz/extracted/suz_pdf.txt†L7244-L7267】
5) Выполнять последующие действия (агрегация, выбытие, утилизация, оформление излишков) через соответствующие методы `/api/v3/aggregation`, `/api/v3/dropout`, `/api/v3/utilisation`, `/api/v3/surplus`. 【F:docs/reference/suz/extracted/suz_pdf.txt†L5330-L5376】【F:docs/reference/suz/extracted/suz_pdf.txt†L7438-L7524】【F:docs/reference/suz/extracted/suz_pdf.txt†L2180-L2190】
6) Подписывать и выгружать накладные/документы СУЗ (`/api/v3/documents/sign`) и при необходимости регистрировать интеграционное соединение (`/api/v3/integration/connection`). 【F:docs/reference/suz/extracted/suz_pdf.txt†L5000-L5048】【F:docs/reference/suz/extracted/suz_pdf.txt†L2700-L2720】

## Карта глобальных переменных
| Имя | Тип | Значение по умолчанию | Экспортируется | Назначение | Используется в |
|---|---|---|:---:|---|---|
| `SUZ_BASE_SANDBOX` | str | `https://suz.sandbox.crptech.ru/api/v3` | да | URL песочницы для всех методов заказа КМ. | Заказы, списания, агрегирование. 【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1231】|
| `SUZ_BASE_PROD` | str | `https://suzgrid.crpt.ru/api/v3` | да | Производственный контур СУЗ. | Продакшен-выгрузки и подача заказов. 【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1234】|
| `SUZ_RPS_LIMIT` | int | `10` | нет | Рекомендуемый лимит обращений на пару IP/omsId; превышение → блокировка. | Rate limiter клиента. 【F:docs/reference/suz/extracted/suz_pdf.txt†L1224-L1235】|
| `SUZ_SIGNATURE_HEADER` | str | `X-Signature` | да | Имя заголовка для откреплённой подписи Base64. | Все защищённые методы (POST/GET). 【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】|
| `SUZ_TOKEN_HEADER` | str | `clientToken` | да | Динамический маркер безопасности, полученный через единую аутентификацию. | Все методы, кроме операций с Authorization token. 【F:docs/reference/suz/extracted/suz_pdf.txt†L6421-L6453】|

## Карта функций
| Метод | URL | Назначение (контекст документа) |
|---|---|---|
| POST | `/api/v3/order?omsId={omsId}` | Создание заказа КМ по товарной группе (пример: табачная продукция, шаблоны, атрибуты).【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】|
| GET | `/api/v3/order/list?omsId={omsId}` | Получение списка и статусов бизнес-заказов; не чаще 100 rps. 【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】|
| POST | `/api/v3/order/close?omsId={omsId}` | Закрытие заказа/подзаказа КМ по идентификатору. 【F:docs/reference/suz/extracted/suz_pdf.txt†L7244-L7267】|
| POST | `/api/v3/dropout?omsId={omsId}` | Создание документа «Выбытие» (dropout) после эмиссии. 【F:docs/reference/suz/extracted/suz_pdf.txt†L7438-L7524】|
| POST | `/api/v3/aggregation?omsId={omsId}` | Формирование агрегатов/упаковок. 【F:docs/reference/suz/extracted/suz_pdf.txt†L5330-L5376】|
| POST | `/api/v3/utilisation?omsId={omsId}` | Утилизация кодов (универсальный метод).【F:docs/reference/suz/extracted/suz_pdf.txt†L2184-L2190】|
| POST | `/api/v2/nabeer/utilisation?omsId={omsId}` | Утилизация для ТГ «Пиво и напитки». 【F:docs/reference/suz/extracted/suz_pdf.txt†L2178-L2188】|
| POST | `/api/v2/wheelchairs/utilisation?omsId={omsId}` | Утилизация для ТГ «Кресла-коляски». 【F:docs/reference/suz/extracted/suz_pdf.txt†L2178-L2188】|
| POST | `/api/v3/surplus?omsId={omsId}` | Оформление излишков (surplus).【F:docs/reference/suz/extracted/suz_pdf.txt†L2190-L2200】|
| GET | `/api/v3/providers?omsId={omsId}` | Получение перечня производителей/поставщиков. 【F:docs/reference/suz/extracted/suz_pdf.txt†L2300-L2325】|
| POST | `/api/v3/documents/sign?omsId={omsId}` | Подписание документов (накладных) через СУЗ. 【F:docs/reference/suz/extracted/suz_pdf.txt†L5000-L5048】|
| GET | `/api/v3/ping?omsId={omsId}` | Проверка соединения и доступности СУЗ. 【F:docs/reference/suz/extracted/suz_pdf.txt†L2400-L2410】|
| GET | `/api/v3/integration/connection?omsId={omsId}` | Получение списка зарегистрированных интеграционных подключений (limit/offset).【F:docs/reference/suz/extracted/suz_pdf.txt†L2460-L2485】|
| POST | `/api/v3/integration/connection?omsId={omsId}` | Регистрация интеграционного соединения для получения `omsConnection`. 【F:docs/reference/suz/extracted/guides.txt†L17-L40】【F:docs/reference/suz/extracted/suz_pdf.txt†L2486-L2505】|
| POST | `/api/v3/orders` | Исторический endpoint (без параметров) — создание заказа через XML/CSV; использовать только в совместимых ТГ. 【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L560】|

## Описание функций
### `create_order(request: SuzOrder, session, client_token, signature) -> OrderResponse`
**Назначение.** Отправка заказа КМ через `/api/v3/order?omsId=…`, включая атрибуты шаблонов и список продуктов.【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
**Параметры.** `request` — бизнес-заказ (productGroup, products, attributes); `session` — HTTP-клиент; `client_token` — динамический маркер; `signature` — Base64 CAdES detached.
**Возвращает.** Идентификатор заказа и статус обработки (accepted/rejected).
**Исключения.** `OrderValidationError` при несоответствии товарной группы или лицензии, `SignatureError` при неверной подписи.
**Сайд-эффекты.** Логирование запроса (без подписи/персональных данных).
**Локальные переменные.**
- `url: str` — `/api/v3/order?omsId={omsId}`.
- `payload: dict` — сериализованный заказ.

### `list_orders(oms_id, session, client_token) -> OrderList`
**Назначение.** Получение статусов заказов (до 100 запросов в секунду) для восстановления состояния АСУТП.【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】
**Параметры.** `oms_id` — идентификатор установки; `session`, `client_token` — контекст соединения.
**Возвращает.** Пагинированный список заказов и их бизнес-статусы.
**Исключения.** `RateLimitError` при превышении 100 rps, `AuthorizationError` при отсутствии токена.
**Сайд-эффекты.** Синхронизация локального кеша заказов.
**Локальные переменные.**
- `params: dict` — фильтры (orderId, statuses, pagination).

### `close_order(order_id, oms_id, session, client_token, signature) -> CloseResult`
**Назначение.** Закрытие заказа/подзаказа КМ по идентификатору через `/api/v3/order/close`.
**Параметры.** `order_id` — GUID заказа; `oms_id`, `session`, `client_token`, `signature` — контекст вызова.
**Возвращает.** Ответ API о закрытии заказа.
**Исключения.** `OrderStateError` если заказ ещё не готов к закрытию.
**Сайд-эффекты.** Добавление события в журнал операций.
**Локальные переменные.**
- `payload: dict` — `{"orderId": ...}`.

### `create_dropout(request: DropoutRequest, session, client_token, signature) -> TaskInfo`
**Назначение.** Формирование документа выбытия (`/api/v3/dropout`) после эмиссии или списания КМ. 【F:docs/reference/suz/extracted/suz_pdf.txt†L7438-L7524】
**Параметры.** `request` — состав выбытия; `session`, `client_token`, `signature` — контекст.
**Возвращает.** Результат обработки выбытия и идентификатор документа.
**Исключения.** `DropoutError` при неверных КИ или статусе заказа.
**Сайд-эффекты.** Связь с учётной системой (отражение выбытия).
**Локальные переменные.**
- `url: str` — `/api/v3/dropout?omsId={omsId}`.

### `submit_utilisation(request: UtilisationRequest, session, client_token, signature) -> TaskInfo`
**Назначение.** Отправка утилизации (`/api/v3/utilisation`) или отраслевых вариантов (`/api/v2/nabeer/...`, `/api/v2/wheelchairs/...`).【F:docs/reference/suz/extracted/suz_pdf.txt†L2178-L2190】
**Параметры.** `request` — данные для утилизации; `session`, `client_token`, `signature` — контекст.
**Возвращает.** Результат обработки (accepted/rejected) и идентификатор операции.
**Исключения.** `UtilisationError` при неверной товарной группе или статусе КИ.
**Сайд-эффекты.** Обновление остатков в складской системе.
**Локальные переменные.**
- `endpoint: str` — выбранный путь в зависимости от ТГ.

### `manage_integration_connection(action, payload, session, token)`
**Назначение.** Регистрация или получение интеграционного подключения через `/api/v3/integration/connection` для выдачи `omsConnection` и мониторинга подключений.【F:docs/reference/suz/extracted/guides.txt†L17-L40】【F:docs/reference/suz/extracted/suz_pdf.txt†L2460-L2485】
**Параметры.** `action` — `'GET'` или `'POST'`; `payload` — регистрационные данные; `session`, `token` — HTTP-клиент и авторизация.
**Возвращает.** Список подключений или результат регистрации.
**Исключения.** `IntegrationError` при дубликатах или нарушении ролевой модели.
**Сайд-эффекты.** Обновление справочника подключений.
**Локальные переменные.**
- `url: str` — `/api/v3/integration/connection`.

## Описание исполнительной части кода
- Действия при импорте: нет; модуль предоставляет класс клиента и набор функций.
- CLI/entrypoints: нет, используется сервисами бекенда и планировщиками фоновых задач.
- Типичный вызов:
  1. Получить `clientToken` → `token = auth.get_client_token(oms_connection)`.
  2. `order_id = create_order(order_request, session, token, signature)`.
  3. Мониторить `list_orders(oms_id, ...)` до статуса `READY_TO_CLOSE`.
  4. `close_order(order_id, oms_id, session, token, signature)`.
  5. При необходимости вызвать `create_dropout`/`submit_utilisation` и синхронизировать статусы с ERP.
