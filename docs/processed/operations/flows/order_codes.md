# Документация модуля: docs/processed/operations/flows/order_codes.md

## История изменений
- 2025-09-16 — описан сквозной процесс заказа КМ для лёгкой промышленности (agent).

## Что должен делать код в общих чертах
Процесс «Заказ КМ» координирует взаимодействие пользователя с НК, True API и СУЗ: от выбора карточки товара до закрытия заказа и вывода КМ из оборота. Основные роли — оператор (инициирует заказ, подписывает документы), бекенд сервиса (автоматизирует обращения к API) и воркер (мониторит статусы и выполняет пост-обработку).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】【F:docs/reference/true_api/extracted/true_api.txt†L50348-L50404】【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】

## Последовательность действий в коде
1) Оператор на фронтенде выбирает карточку из НК (GET `/v3/product`) и заполняет атрибуты заказа.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】
2) Фронтенд вызывает `/auth/key`, пользователь подписывает `data` через CryptoPro и передаёт подпись на бекенд (`/auth/simpleSignIn`).【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1128】【F:docs/reference/suz/extracted/suz_pdf.txt†L529-L546】
3) Бекенд сохраняет токены, выполняет проверку КИ (`/v3/mark-check`), загружает справочники и формирует документ True API `/lk/documents/create`.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】
4) Документ отправляется в True API, воркер отслеживает его статус через `/api/v4/true-api/doc/list` и уведомляет фронтенд о приёме. 【F:docs/reference/true_api/extracted/true_api.txt†L50348-L50404】
5) После получения квитанции бекенд оформляет заказ КМ в СУЗ (`/api/v3/order?omsId=…`, `X-Signature`, `clientToken`).【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
6) Воркер опрашивает `/api/v3/order/list` и закрывает заказ по достижении готовности (`/api/v3/order/close`).【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】【F:docs/reference/suz/extracted/suz_pdf.txt†L7244-L7267】
7) При необходимости выполняет выбытие/утилизацию (`/api/v3/dropout`, `/api/v3/utilisation`) и фиксирует результат в БД. 【F:docs/reference/suz/extracted/suz_pdf.txt†L7438-L7524】【F:docs/reference/suz/extracted/suz_pdf.txt†L2184-L2190】

## Карта глобальных переменных
| Имя | Тип | Значение по умолчанию | Экспортируется | Назначение | Используется в |
|---|---|---|:---:|---|---|
| `order_timeout_minutes` | int | `60` | нет | Максимальное время ожидания статуса True API до эскалации. | Воркер статусов. |
| `suz_poll_interval` | int | `30` | нет | Интервал (сек) между запросами `/api/v3/order/list`. | Мониторинг СУЗ. |
| `max_mark_check_batch` | int | `100` | нет | Лимит записей в `mark-check`. | Валидация КИ. 【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】|
| `client_token_ttl_hours` | int | `10` | нет | Срок действия `clientToken`. | Планировщик переавторизации. 【F:docs/reference/suz/extracted/guides.txt†L6-L58】|

## Карта функций
| Имя | Видимость | Сигнатура | Краткое назначение | Исключения | Сайд-эффекты |
|---|---|---|---|---|---|
| `start_order_flow` | public | `start_order_flow(user_id, gtin)` | Инициализирует заказ КМ, подготавливает данные НК и авторизацию. | `AccessError`, `NotFoundError` | Создаёт запись заказа. |
| `submit_true_api_document` | private | `submit_true_api_document(order_id)` | Отправляет документ в True API и регистрирует задачу мониторинга. | `ValidationError` | Постановка в очередь воркера. |
| `create_suz_order_task` | private | `create_suz_order_task(order_id)` | Запускает фоновой процесс формирования заказа СУЗ. | `OrderValidationError` | Отправка сообщения в очередь. |
| `poll_statuses_task` | private | `poll_statuses_task()` | Плановый опрос статусов True API/СУЗ. | нет | Обновляет БД, отправляет уведомления. |
| `close_order_if_ready` | private | `close_order_if_ready(order_status)` | Выполняет `POST /api/v3/order/close`, если заказ готов. | `OrderStateError` | Записывает событие аудита. |
| `postprocess_order` | private | `postprocess_order(order_id)` | Проводит выбытие/утилизацию и завершает заказ. | `DropoutError`, `UtilisationError` | Обновляет бухгалтерию/учёт. |

## Описание функций
### `start_order_flow`
**Назначение.** Проверить права пользователя, подтянуть карточку товара, инициировать получение токенов и создать запись заказа в БД. Использует НК (`/v3/product`) и `AuthService.issue_tokens` для подготовки авторизации.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1128】
**Параметры.** `user_id`, `gtin`.
**Возвращает.** Объект заказа со статусом `INIT`.
**Исключения.** `AccessError`, `NotFoundError`.
**Сайд-эффекты.** Создание записи в таблице заказов.
**Локальные переменные.**
- `product` — карточка из НК.
- `auth_tokens` — результат выдачи токенов.

### `submit_true_api_document`
**Назначение.** Сформировать документ `/lk/documents/create`, подписать и отправить в True API, затем запланировать мониторинг статусов.【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】【F:docs/reference/true_api/extracted/true_api.txt†L50348-L50404】
**Параметры.** `order_id` — идентификатор заказа.
**Возвращает.** Ничего (обновляет БД).
**Исключения.** `ValidationError`.
**Сайд-эффекты.** Постановка задачи воркеру.
**Локальные переменные.**
- `document_payload` — JSON для True API.
- `task_id` — идентификатор фоновой задачи.

### `create_suz_order_task`
**Назначение.** После подтверждения True API сформировать запрос `/api/v3/order?omsId=…` и отправить его в СУЗ с подписью УКЭП.【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
**Параметры.** `order_id`.
**Возвращает.** Ничего (обновляет статус заказа).
**Исключения.** `OrderValidationError`, `SignatureError`.
**Сайд-эффекты.** Публикация события в очередь «suz_orders».
**Локальные переменные.**
- `suz_payload` — JSON заказа.
- `signature` — подпись `X-Signature`.

### `poll_statuses_task`
**Назначение.** Периодически вызывать `/api/v4/true-api/doc/list` и `/api/v3/order/list`, обновляя статусы документов и заказов.【F:docs/reference/true_api/extracted/true_api.txt†L50348-L50404】【F:docs/reference/suz/extracted/suz_pdf.txt†L6407-L6468】
**Параметры.** Нет.
**Возвращает.** Нет.
**Исключения.** Сетевые ошибки перезапускаются.
**Сайд-эффекты.** Обновление статусов, рассылка уведомлений.
**Локальные переменные.**
- `documents` — список документов True API.
- `orders` — список заказов СУЗ.

### `close_order_if_ready`
**Назначение.** При статусе `READY_TO_CLOSE` вызывает `/api/v3/order/close` и фиксирует результат в БД.【F:docs/reference/suz/extracted/suz_pdf.txt†L7244-L7267】
**Параметры.** `order_status`.
**Возвращает.** Ничего.
**Исключения.** `OrderStateError`.
**Сайд-эффекты.** Запись в журнал аудита.
**Локальные переменные.**
- `close_payload` — `{ "orderId": ... }`.

### `postprocess_order`
**Назначение.** Оформить выбытие или утилизацию кодов после закрытия заказа (`/api/v3/dropout`, `/api/v3/utilisation`).【F:docs/reference/suz/extracted/suz_pdf.txt†L7438-L7524】【F:docs/reference/suz/extracted/suz_pdf.txt†L2184-L2190】
**Параметры.** `order_id`.
**Возвращает.** Ничего.
**Исключения.** `DropoutError`, `UtilisationError`.
**Сайд-эффекты.** Обновление складских остатков и учётных систем.
**Локальные переменные.**
- `dropout_payload` — JSON выбытия.
- `utilisation_payload` — JSON утилизации.

## Описание исполнительной части кода
- Действия при импорте: планировщик регистрирует периодические задачи (`poll_statuses_task`), настраивает очередь сообщений.
- CLI/entrypoints: нет (используется внутри сервиса).
- Типичный вызов:
  1. `start_order_flow` → `submit_true_api_document` запускают основной процесс.
  2. После подтверждения документа воркер выполняет `create_suz_order_task`.
  3. `poll_statuses_task` обновляет статусы; при готовности вызывается `close_order_if_ready`.
  4. `postprocess_order` завершает процесс (выбытие/утилизация) и закрывает заказ.
