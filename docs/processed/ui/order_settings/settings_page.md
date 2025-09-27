# Документация веб-страницы: /orders/settings.php

## История изменений
- 2024-10-05 — Страница превращена в редирект на главную (`/index.php?suz=1`), UI настроек и теста clientToken теперь расположен в модале `#suzSettingsModal` на списке карточек НК.【F:public/orders/settings.php†L1-L5】【F:public/index.php†L70-L188】【F:public/index.php†L944-L1090】

## 1. Назначение страницы и роль в продукте
Страница теперь служит техническим редиректом: при обращении к `/orders/settings.php` пользователь перенаправляется на `/index.php?suz=1`, где открывается модальное окно `#suzSettingsModal` с формой настроек OMS/СУЗ. В модале можно сохранить параметры подключения, выбрать сертификат, получить/сбросить `clientToken` и просмотреть журнал тестового подключения. Сам редирект не оформляет заказы и не изменяет карточки товаров.【F:public/orders/settings.php†L1-L5】【F:public/index.php†L70-L188】【F:public/index.php†L944-L1090】

## 2. Информационная архитектура и навигация
- Входы: кнопка «Настройки OMS и СУЗ» на странице списка карточек НК (`/index.php`), прямой deeplink `/orders/settings.php` или ручное добавление параметра `?suz=1`/хэша `#suz-settings` на главной странице.【F:public/index.php†L912-L920】【F:public/orders/settings.php†L1-L5】【F:public/index.php†L1342-L1345】
- Выходы: из модала можно вернуться к списку карточек или повторно открыть тестовое подключение; закрытие модала возвращает пользователя к `/index.php` без перезагрузки данных.【F:public/index.php†L1268-L1339】
- URL: `/orders/settings.php` выполняет 302-редирект на `/index.php?suz=1`; модал доступен и по хэшу `#suz-settings` для прямой активации.【F:public/orders/settings.php†L1-L5】【F:public/index.php†L1342-L1345】

## 3. Состав страницы (UI-карта и состояния)
| ID/селектор | Тип | Назначение | Условия показа | SSR/CSR | Варианты |
|---|---|---|---|---|---|
| `#suzSettingsModal` / `.modal-window--settings` | block | Контейнер модального окна настроек | Показывается по запросу (`?suz=1`, кнопка на странице) | CSR | hidden, open |【F:public/index.php†L70-L188】【F:public/index.php†L882-L1000】
| `#suzStatusCard` | block | Отображает текущее состояние `clientToken`, `omsConnection`, `omsId` | Всегда внутри модала | CSR | active, inactive, no-context |【F:public/index.php†L70-L188】【F:public/index.php†L1000-L1120】
| `#suzOmsConnection`, `#suzOmsId`, `#suzParticipantInn`, `#suzStationUrl`, `#suzLocationAddress` | atom | Поля ввода контекста OMS | Всегда | CSR | empty, filled |【F:public/index.php†L70-L188】【F:public/index.php†L1120-L1220】
| `#suzCert`, `#suzCertInfo` | atom/block | Выбор сертификата и карточка сведений | Требует CryptoPro | CSR | loading, selected, empty, error |【F:public/index.php†L70-L188】【F:public/index.php†L1220-L1320】【F:public/index.php†L1380-L1560】
| `#suzSaveSettings`, `#suzTestConnection`, `#suzResetToken` | atom | Управляющие кнопки | Всегда | CSR | enabled / disabled |【F:public/index.php†L70-L188】【F:public/index.php†L1320-L1400】【F:public/index.php†L1560-L1690】
| `#suzTestLog` | block | Журнал тестового подключения | Всегда | CSR | initial, progress, error |【F:public/index.php†L70-L188】【F:public/index.php†L1400-L1690】

## 4. Поведение и сценарии (user flows)
1. «Сохранить»: модал читает поля → `saveSuzContext` отправляет `POST /api/orders/suz-settings.php` → журнал получает строку «Настройки сохранены», карточка статуса обновляется без закрытия модала.【F:public/index.php†L944-L1090】【F:public/index.php†L1400-L1520】
2. «Тестовое подключение»: модал сохраняет контекст → `fetchJson('?mode=challenge')` → `signAttachedAuth` подписывает challenge выбранным сертификатом → `POST /api/orders/suz-auth.php` возвращает `expiresAt` → лог пишет прогресс и дату истечения, статус меняется на активный.【F:public/index.php†L944-L1090】【F:public/index.php†L1500-L1700】
3. «Сбросить token»: `DELETE /api/orders/suz-auth.php` → журнал фиксирует «clientToken удалён», статус переводится в «clientToken отсутствует».【F:public/index.php†L1560-L1700】
4. При открытии модала `showSuzModal` подставляет сохранённый контекст (`suzState.context`) и статус (`suzState.status`), поддерживает активацию через `?suz=1` или `#suz-settings`.【F:public/index.php†L70-L188】【F:public/index.php†L1320-L1400】【F:public/index.php†L1680-L1700】

## 5. Данные и интеграции (API/контракты)
- Источники: REST-эндпоинты `/api/orders/suz-settings.php` (GET/POST/DELETE) и `/api/orders/suz-auth.php` (GET challenge, POST token, DELETE); модал повторно использует их из `public/index.php` и не делает запросов на `/orders/settings.php`.【F:public/index.php†L944-L1090】【F:public/index.php†L1400-L1700】
- Ответы описаны в `docs/processed/api/suz/oms-settings.md`.
- Ошибки API отображаются пользователю через `alert` и строку в `#suzTestLog` («✗ Ошибка: …»), возвращаемые коды/тела выводятся полностью для диагностики.【F:public/index.php†L1560-L1700】
- Кэширование отсутствует (`Cache-Control: no-store`).

## 6. Контент и локализация (i18n)
| Ключ | Значение (ru) | Комментарий | Плейсхолдеры |
|---|---|---|---|
| modal.title | Настройки OMS и СУЗ | Заголовок в модале | — |【F:public/index.php†L70-L188】
| modal.subtitle | Сохраните параметры подключения и проверьте получение clientToken. | `p.suz-settings__meta` | — |【F:public/index.php†L70-L188】
| placeholders | «GUID подключения», «OMS ID», «Например, 7700000000», «https://suzgrid.crpt.ru/api/v3», «Город, улица, дом» | Поля ввода | — |【F:public/index.php†L70-L188】【F:public/index.php†L1120-L1220】
| status.messages | «clientToken отсутствует», «Токен активен», «Активен до …» | `#suzStatusCard` | `{date}` |【F:public/index.php†L944-L1090】
| cert.hint | Загрузка сертификатов…, Сертификат не выбран, CryptoPro недоступен… | `#suzCert`, `#suzCertInfo` | — |【F:public/index.php†L1220-L1400】
| log messages | «=== тест подключения ===», «→ Запрос challenge в True API», «✓ clientToken получен», «✗ Ошибка: …» | `#suzTestLog` | `{error}` |【F:public/index.php†L1400-L1700】

## 7. SEO и метаданные
- Собственных `<title>`/`meta` нет: `settings.php` отвечает редиректом, все метаданные берутся со страницы `/index.php` (список карточек).【F:public/orders/settings.php†L1-L5】【F:public/index.php†L70-L188】
- Robots и OG остаются как на главной странице; отдельная конфигурация не требуется.

## 8. Доступность (a11y)
- Модал `#suzSettingsModal` помечен `role="dialog"`, имеет кнопку закрытия, закрывается по клику на оверлей и по `Esc`.【F:public/index.php†L70-L188】【F:public/index.php†L882-L1000】【F:public/index.php†L118-L188】
- Все поля формы имеют `<label for=…>`, кнопки — `<button>` с текстовыми подписями и состояниями disabled.【F:public/index.php†L70-L188】【F:public/index.php†L1120-L1400】
- Журнал `#suzTestLog` реализован через `<pre>` и обновляется текстом; отдельного live-region нет, но контент читаем линейно.【F:public/index.php†L70-L188】【F:public/index.php†L1400-L1700】

## 9. Аналитика и трекинг
- Отсутствует: событийная аналитика не внедрена.

## 10. Производительность и медиа
- Единственный статический JS — `cadesplugin_api.js` (для работы расширения). 
- Страница не подгружает изображения, CSS inline. 
- Логи и запросы выполняются по требованию пользователя.

## 11. Безопасность и приватность
- Настройки хранятся в PHP-сессии (cookie `PHPSESSID`).
- Чувствительные поля (`omsConnection`, `omsId`) не логируются на клиенте, но события фиксируются на сервере (`orders_debug.log`).
- Нет внешних доменов кроме `fonts.gstatic.com` (preconnect).

## 12. Адаптивность и кроссбраузерность
- Макет — адаптивная сетка `grid` с `minmax(240px, 1fr)`; при ширине <900px уменьшается padding.
- Тестировалось на современных Chromium; требуется поддержка CryptoPro plugin (только Windows/IE-режим/Chromium через расширение).
- Для мобильных устройств страница доступна, но тест подписи возможен только с поддерживаемых браузеров.

## 13. Тестирование и критерии приёмки
- Smoke: открыть `/index.php?suz=1` → модал показан, поля заполняются сохранёнными значениями → `POST suz-settings` возвращает context, статус обновлён.【F:public/index.php†L70-L188】【F:public/index.php†L1400-L1520】
- Тест подписи: выбран сертификат в `#suzCert`, запрошен challenge, подпись сформирована, `clientToken` получен (лог содержит цепочку сообщений).【F:public/index.php†L1500-L1700】
- Негатив: пустой `omsConnection`/`omsId` → JS ошибка «Заполните omsConnection/omsId»; ошибка API (например, 400) отражается в `#suzTestLog` и через `alert`.【F:public/index.php†L1500-L1700】

## 14. Исполнительная часть (entrypoints/SSR/фичефлаги)
- `settings.php` не содержит собственного JS — после редиректа все сценарии выполняет скрипт из `public/index.php` (модуль настроек находится в общей обёртке CryptoPro).【F:public/orders/settings.php†L1-L5】【F:public/index.php†L1250-L1700】
- Требуется активная PHP-сессия и доступ к True API/СУЗ (окружение `config/app.php`).
- Для теста необходимо установленное расширение CryptoPro (`cadesplugin`).
