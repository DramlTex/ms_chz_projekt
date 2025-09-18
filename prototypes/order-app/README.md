# Прототип модульной страницы заказа КМ

Фронтенд-прототип демонстрирует разбиение функциональности на независимые модули:

- `services/catalogService.js` — загрузка карточек Национального каталога (`/v4/product-list`) в промышленном контуре. Требует
  действующий `apikey` или bearer-токен; fallback на демо-данные отключён.
- `services/authService.js` и `services/cryptoProClient.js` — получение challenge `/auth/key`, присоединённая подпись через CryptoPro и обмен
  подписи на bearer-токен True API.
- `state/orderStore.js` / `state/sessionStore.js` — хранилища для карточек, фильтров, выбранных позиций и авторизационных
  данных (сертификаты, токены, доступ к НК).
- `ui/authPanel.js`, `ui/catalogTable.js`, `ui/selectionSummary.js`, `ui/orderModal.js`, `ui/filters.js` — визуальные компоненты
  страницы и модальных окон.
- `services/orderService.js` — имитация отправки заказа КМ, проверяет наличие bearer-токена и отображает метаданные подписи.

HTML-страница `../order-page.html` подключает `app.js`, который orchestrates загрузку карточек, обновление состояния и работу с
модальным окном. По умолчанию запрашивается период последних трёх лет, как описано в документации НК (`docs/processed/api/national_catalog/requests.md`). Используются только боевые URL: `https://markirovka.crpt.ru/api/v3/true-api` и `https://апи.национальный-каталог.рф`.
