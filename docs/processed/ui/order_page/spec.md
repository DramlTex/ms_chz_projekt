# Документация веб-страницы: `/orders/create`

## 1. Назначение страницы и роль в продукте
Страница предназначена для операторов лёгкой промышленности, которые оформляют заказы КМ. На одной форме пользователь выбирает карточку из НК, валидирует КИ, подписывает запросы УКЭП и отслеживает статусы документов True API/СУЗ. Ключевые метрики — количество успешно оформленных заказов, среднее время от загрузки страницы до отправки заказа, количество ошибок подписи.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】 Страница не занимается управлением складскими остатками и отчётами (`dispenser` покрывает отдельный раздел).

## 2. Информационная архитектура и навигация
- Входы/откуда приходят: дашборд «Мои заказы», карточка товара (deep-link с параметром `gtin`).
- Выходы/куда ведёт: страница статуса заказа (`/orders/:id`), раздел выгрузок (`/reports/dispenser`).
- URL-паттерн: `/orders/create?gtin=<gtin>&inn=<inn>`; canonical — `/orders/create`.
- Меню: раздел «Операции» → «Заказать КМ».

## 3. Состав страницы (UI-карта и состояния)
| ID/селектор | Тип | Назначение | Условия показа | SSR/CSR | Варианты состояния |
|---|---|---|---|---|---|
| `#product-selector` | block | Поиск карточки НК по GTIN/ID | Всегда | CSR | idle, loading, error, selected |
| `#product-details` | block | Просмотр полей карточки | Показывается при выбранном товаре | CSR | filled, collapsed |
| `#cis-validation` | block | Ввод списка КИ и проверка `/v3/mark-check` | После выбора товара | CSR | idle, validating, success, error |
| `#document-upload` | block | Прикрепление файлов (feed, сопроводительные) | Опционально (если требуется обновление) | CSR | empty, uploading, uploaded |
| `#signature-panel` | block | Выбор сертификата и подпись `data` / `X-Signature` | Когда доступны данные для подписи | CSR | idle, signing, signed, error |
| `#submit-order` | atom | Кнопка отправки заказа | Активна после валидаций и подписей | CSR | enabled, disabled, loading |
| `#status-timeline` | block | Показывает прогресс документов и заказов | После отправки | CSR | pending, processing, ready, failed |
| `#websocket-alerts` | atom | Поток уведомлений воркера | После отправки | CSR | empty, info, error |

Критичные состояния: `#cis-validation` должен отображать ошибки с деталями (некорректный КИ, превышение лимита 100). `#signature-panel` содержит fallback «Установите CryptoPro» при отсутствии плагина.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】【F:docs/reference/true_api/extracted/true_api_quickstart.md†L64-L110】

## 4. Поведение и сценарии (user flows)
1) `LOAD PAGE` → `fetch_product(gtin)` → `render details` → `user edits` → `cis validate` → `sign data` → `submit order` → `show status timeline`.
2) Ошибка подписи: `sign data` → `SignatureError` → показать модалку с инструкцией (переподключить сертификат, проверить CryptoPro).
3) Падение проверки КИ: `mark-check` вернул ошибку → вывести список проблемных КИ, запретить отправку до исправления.
4) WebSocket уведомление `ORDER_CLOSED` → обновить таймлайн, предложить оформить выбытие.

## 5. Данные и интеграции (API/контракты)
- Источники данных: REST бекенд (`/api/catalog/product`, `/api/true-api/auth/key`, `/api/orders`), WebSocket `/ws/orders/:id`.
- Внешние API:
  - `GET /v3/product` (НК) → карточка для заполнения заказа.【F:docs/reference/national_catalog/extracted/catalog_api.txt†L2001-L2037】
  - `POST /v3/mark-check` → валидация КИ (лимит 100).【F:docs/reference/national_catalog/extracted/catalog_api.txt†L5467-L5473】
  - `GET /auth/key` / `POST /auth/simpleSignIn` → токены True API/СУЗ.【F:docs/reference/true_api/extracted/true_api.txt†L1118-L1128】【F:docs/reference/true_api/extracted/true_api.txt†L1202-L1234】
  - `POST /lk/documents/create` → единый документ True API.【F:docs/reference/true_api/extracted/true_api.txt†L7296-L7338】
  - `POST /api/v3/order?omsId=...` → заказ в СУЗ.【F:docs/reference/suz/extracted/suz_pdf.txt†L1630-L1662】
- Ошибки API отображаются inline с расшифровкой (400/403/429). Rate-limit фоны обрабатываются прогресс-барами.

## 6. Контент и локализация (i18n)
| Ключ | Значение (ru) | Комментарий | Плейсхолдеры |
|---|---|---|---|
| `order.title` | Заказ кодов маркировки | Заголовок страницы | — |
| `validation.cis.limit` | В одном запросе допускается не более 100 кодов. | Сообщение об ошибке проверки КИ | `{limit}` |
| `signature.missing` | Установите CryptoPro Browser Plug-in и выберите сертификат. | Сообщение при отсутствии плагина | — |
| `status.ready` | Готово к закрытию | Статус таймлайна | — |

Формат дат — `DD.MM.YYYY HH:mm`, суммы — ₽ с двумя знаками. Текст короткий, без пассивных форм.

## 7. SEO и метаданные
- `<title>`: «Заказ кодов маркировки — Лёгкая промышленность».
- `meta description`: «Отправьте заказ кодов маркировки, подпишите документы УКЭП и отслеживайте статус True API/СУЗ».
- `robots` — `noindex, nofollow` (страница под авторизацией).
- OG: `og:title`, `og:description`, превью не требуется.

## 8. Доступность (a11y)
- Страница имеет landmark `<main>` и кнопку «Пропустить к содержимому».
- Все поля формы снабжены `<label>` с `for`, ошибки — `aria-live="assertive"`.
- Компоненты подписи и таймлайна доступны с клавиатуры (`Tab` + `Enter`).
- Контраст ≥ 4.5:1, уведомления дублируются текстом.

## 9. Аналитика и трекинг
| ID события | Триггер | Параметры payload | Отправка |
|---|---|---|---|
| `order.create_attempt` | Нажатие кнопки «Отправить» | `{gtin, hasFeed, signatureMode}` | `window.dataLayer.push` |
| `order.create_success` | Получен статус `ACCEPTED` из True API | `{orderId, durationSec}` | WebSocket → dataLayer |
| `order.signature_error` | Ошибка `SignatureError` | `{errorCode}` | Sentry + dataLayer |

Конверсии не отправляются в рекламные сети (строго корпоративный доступ).

## 10. Производительность и медиа
- LCP ≤ 2.5 c (основной блок `#product-selector` → использует SSR-шаблон без тяжёлых ресурсов).
- Списки КИ вводятся через textarea без перерисовок; результаты подтягиваются chunked.
- Lazy-loading для справочников (`import()` модулей при открытии секции).

## 11. Безопасность и приватность
- Все запросы идут по HTTPS, CSRF защищён токеном (встроено во фронтенд-бекенд).
- Подписи и приватные данные не логируются; хранится только hash транзакции.
- Куки `Secure`, `SameSite=Strict`.
- КИ и документы удаляются из памяти после отправки (используем Blob URL с revoke()).

## 12. Адаптивность и кроссбраузерность
- Breakpoints: ≥1280 desktop, 768–1279 tablet, <768 mobile (мобильный режим только для просмотра статусов, отправка заказа доступна на desktop/tablet).
- Поддерживаемые браузеры: Chromium 114+, Edge 114+, Госбраузер с CryptoPro plug-in (требование УКЭП). Safari не поддерживается для подписей.

## 13. Тестирование и критерии приёмки
- Unit: валидация КИ (≤100), блокировка отправки без подписи.
- E2E: happy-path (получение токенов, отправка заказа, закрытие), ошибка подписи, таймаут True API.
- A11y: проверка фокусов, `aria-live` для ошибок, контрасты.
- Smoke: загрузка карточки с GTIN, WebSocket уведомление `ORDER_CLOSED`.

## 14. Исполнительная часть (entrypoints/SSR/фичефлаги)
- SSR рендерит пустой каркас с базовой информацией о пользователе; данные подтягиваются через CSR запросы.
- Feature flag `enableNKBulkFeed` включает/отключает блок загрузки feed.
- Вход на страницу требует активной сессии и роли «operator_lp»; deep-link передаёт `gtin`.
- Статический прототип для UX-согласований лежит в `web/index.html` и обращается к мокам `../servis_CHZ-MS/api/*.php`.【F:web/index.html†L664-L668】
