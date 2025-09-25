# Документация веб-страницы: `/orders/create`

## История изменений
- 2024-10-05 — Панель авторизации True API и СУЗ перенесена на главную страницу, на странице заказа остались выбор сертификата и действия с документами; запрос карточки теперь добавляет ведущий ноль к GTIN перед обращением в API.【F:public/index.php†L70-L188】【F:public/index.php†L944-L1090】【F:public/orders/create.php†L1-L120】【F:public/orders/create.php†L460-L520】

## 1. Назначение страницы и роль в продукте
Страница предназначена для операторов лёгкой промышленности, которые оформляют заказы КМ. Пользователь подгружает карточку НК, проверяет КИ, формирует документы и заказы, подписывает их УКЭП и отслеживает статусы True API/СУЗ. Авторизация и настройка OMS/СУЗ теперь выполняется на главной странице (`#suzSettingsModal`), поэтому на странице заказа остаётся только выбор сертификата и работа с готовыми токенами из сессии.【F:public/index.php†L70-L188】【F:public/index.php†L944-L1090】【F:public/orders/create.php†L1-L120】【F:public/orders/create.php†L460-L520】 Страница не занимается управлением складскими остатками и отчётами (`dispenser` покрывает отдельный раздел).

## 2. Информационная архитектура и навигация
- Входы/откуда приходят: дашборд «Мои заказы», карточка товара (deep-link с параметром `gtin`).
- Выходы/куда ведёт: страница статуса заказа (`/orders/:id`), раздел выгрузок (`/reports/dispenser`), кнопка «Настройки OMS и СУЗ» ведёт на `/index.php?suz=1`, который открывает модал с настройками.【F:public/orders/create.php†L256-L306】【F:public/index.php†L70-L188】【F:public/orders/settings.php†L1-L5】
- URL-паттерн: `/orders/create?gtin=<gtin>&inn=<inn>`; canonical — `/orders/create`.
- Меню: раздел «Операции» → «Заказать КМ».

## 3. Состав страницы (UI-карта и состояния)
| ID/селектор | Тип | Назначение | Условия показа | SSR/CSR | Варианты состояния |
|---|---|---|---|---|---|
| `#productInfo` | block | Просмотр карточки НК и атрибутов | Всегда; показывает заглушку при отсутствии данных | CSR | empty, filled |【F:public/orders/create.php†L268-L322】【F:public/orders/create.php†L460-L520】
| `#certSelect`, `#certInfo` | atom/block | Выбор сертификата и сводка по нему | Всегда; обновляется через CryptoPro | CSR | loading, selected, empty, error |【F:public/orders/create.php†L256-L322】【F:public/orders/create.php†L460-L520】
| `#cisInput`, `#markCheckLog` | block | Проверка КИ через `/api/orders/mark-check.php` | Доступно сразу после загрузки | CSR | idle, validating, success, error |【F:public/orders/create.php†L328-L368】【F:public/orders/create.php†L640-L720】
| `#documentJson`, `#sendDocument`, `#documentLog` | block | Подготовка и отправка документа True API | Всегда | CSR | idle, signing, success, error |【F:public/orders/create.php†L368-L400】【F:public/orders/create.php†L720-L760】
| `#orderJson`, `#sendOrder`, `#orderLog` | block | Формирование заказа СУЗ и подпись | Всегда | CSR | idle, signing, success, error |【F:public/orders/create.php†L400-L440】【F:public/orders/create.php†L760-L804】
| `#codesList`, `#downloadPdf` | block | Генерация PDF с кодами | При наличии кодов в textarea | CSR | idle, ready |【F:public/orders/create.php†L400-L440】【F:public/orders/create.php†L804-L836】
| `#statusBoard`, `#refreshStatus` | block | Отображение статусов документов и заказов | После нажатия «Обновить статусы» | CSR | empty, data, error |【F:public/orders/create.php†L400-L468】【F:public/orders/create.php†L836-L880】

Критичные состояния: `#certInfo` должен отображать ошибки загрузки сертификатов и подсказки без плагина, `#markCheckLog` и другие логи — выводить расшифровку ошибок API, `#statusBoard` — иметь читаемую заглушку при отсутствии данных.【F:public/orders/create.php†L256-L322】【F:public/orders/create.php†L640-L880】

## 4. Поведение и сценарии (user flows)
1) `LOAD PAGE` → `normalizeGtin(gtin)` → `GET ../api/orders/product.php` → `renderProduct` заполняет карточку или показывает заглушку → пользователь при необходимости корректирует поля документа/заказа.【F:public/orders/create.php†L460-L520】【F:public/orders/create.php†L520-L600】
2) Пользователь выбирает сертификат → `loadCertificates` читает CryptoPro → `renderCertificateInfo` показывает сведения или ошибки → действия без сертификата блокируются через `ensureCert`.【F:public/orders/create.php†L256-L322】【F:public/orders/create.php†L520-L620】【F:public/orders/create.php†L620-L720】
3) `mark-check`: ввод кодов → `POST ../api/orders/mark-check.php` → результат выводится в `#markCheckLog`; ошибки записываются и не прерывают работу остальных блоков.【F:public/orders/create.php†L640-L720】
4) `sendDocument`/`sendOrder`: JSON → `ensureCert` → `signDetachedBase64` → `POST create-document.php` или `create-suz-order.php` → ответы и ошибки пишутся в соответствующие логи; при успехе сохраняются идентификаторы для блока статусов.【F:public/orders/create.php†L720-L804】
5) `refreshStatus`: пользователь вводит `docId`/`orderId` → `GET ../api/orders/status.php` → `renderStatusBoard` показывает данные или ошибки. Статусы True API/СУЗ рассчитываются на бэкенде, токены берутся из сессии (авторизация выполнена ранее через `/index.php?suz=1`).【F:public/orders/create.php†L836-L880】【F:public/index.php†L70-L188】【F:public/index.php†L944-L1090】

## 5. Данные и интеграции (API/контракты)
- Источники данных: REST-бекенд (`../api/orders/product.php`, `mark-check.php`, `create-document.php`, `create-suz-order.php`, `status.php`, `print-codes.php`). Токены True API и СУЗ берутся из сессии, т.к. авторизация происходит на главной странице.【F:public/orders/create.php†L460-L520】【F:public/orders/create.php†L640-L880】【F:public/index.php†L70-L188】
- Внешние API:
  - `GET /v3/product` (НК) — карточка товара, перед запросом GTIN нормализуется до 14 символов (добавляется ведущий ноль).【F:public/orders/create.php†L520-L600】
  - `POST /v3/mark-check` — валидация КИ (до 100 кодов за вызов).【F:public/orders/create.php†L640-L720】
  - `POST /lk/documents/create` — отправка документа True API (детали в `create-document.php`).【F:public/orders/create.php†L720-L760】
  - `POST /api/v3/order` — создание заказа СУЗ (через `create-suz-order.php`).【F:public/orders/create.php†L760-L804】
- Ошибки API выводятся в соответствующих логах (`#markCheckLog`, `#documentLog`, `#orderLog`) и через `alert` при необходимости; rate-limit не имеет отдельного UI, ошибки показываются как текст ответов.【F:public/orders/create.php†L640-L804】

## 6. Контент и локализация (i18n)
| Ключ | Значение (ru) | Комментарий | Плейсхолдеры |
|---|---|---|---|
| `order.title` | Заказ кодов маркировки | Заголовок страницы | — |【F:public/orders/create.php†L21-L80】
| `order.subtitle` | Лёгкая промышленность • авторизация True API и СУЗ выполняется на главной странице | Подзаголовок `p.block__meta` под заголовком | — |【F:public/orders/create.php†L248-L272】
| `certificate.empty` | Сертификат не выбран | Карточка `#certInfo` | — |【F:public/orders/create.php†L256-L322】
| `certificate.error` | CryptoPro недоступен. Проверьте установку плагина и перезагрузите страницу. | Сообщение при ошибке загрузки сертификатов | — |【F:public/orders/create.php†L560-L620】
| `validation.cis.limit` | В одном запросе допускается не более 100 кодов. | Сообщение об ошибке проверки КИ | `{limit}` |【F:public/orders/create.php†L640-L720】
| `status.empty` | Нет данных | Заглушка `#statusBoard` | — |【F:public/orders/create.php†L836-L880】

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
- LCP ≤ 2.5 c (основной блок `#productInfo` рендерится без тяжёлых ресурсов, карточка подтягивается отдельным запросом).【F:public/orders/create.php†L268-L322】【F:public/orders/create.php†L520-L600】
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
- Unit: валидация КИ (≤100), блокировка действий без выбранного сертификата (`ensureCert`).【F:public/orders/create.php†L620-L804】
- E2E: happy-path (нормализация GTIN с лидирующим нулём, отправка документа и заказа, обновление статусов), ошибка подписи, таймаут True API/СУЗ.【F:public/orders/create.php†L520-L804】【F:public/orders/create.php†L836-L880】
- A11y: проверка фокусов, доступности `#certInfo`, `#statusBoard`, сообщений в логах.
- Smoke: загрузка карточки с 13-значным GTIN (ожидаем добавление ведущего нуля), отображение сообщения при отсутствии CryptoPro, выгрузка PDF с кодами.【F:public/orders/create.php†L520-L836】

## 14. Исполнительная часть (entrypoints/SSR/фичефлаги)
- SSR рендерит каркас и передаёт `initial` с GTIN и карточкой; все запросы выполняются на клиенте через REST-эндпоинты.【F:public/orders/create.php†L1-L120】【F:public/orders/create.php†L520-L804】
- Загрузка сертификатов и подписи выполняются через CryptoPro (`cadesplugin`), а набор сертификатов синхронизируется с модалами на главной странице (`#suzSettingsModal`, `#nkAuthModal`).【F:public/index.php†L70-L188】【F:public/index.php†L1250-L1540】【F:public/orders/create.php†L256-L620】
- Настройки OMS/СУЗ вызываются через редирект `/orders/settings.php` → `/index.php?suz=1`, где открывается модал с теми же API `suz-settings.php` и `suz-auth.php`.【F:public/orders/settings.php†L1-L5】【F:public/index.php†L70-L188】【F:public/index.php†L944-L1090】
- Вход на страницу требует активной сессии и роли «operator_lp»; deep-link передаёт `gtin`.
- Статический прототип для UX-согласований лежит в `web/index.html` и обращается к мокам `../servis_CHZ-MS/api/*.php`.【F:web/index.html†L664-L668】
