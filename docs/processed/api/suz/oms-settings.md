# Настройки OMS и тест clientToken (стенд web)

## Источник
- Честный знак, True API v3 (актуально на 2024-02).
- Веб-интерфейс ms_chz_projekt `/public/orders/settings.php`.

## Эндпоинты
| Метод | URL | Назначение | Авторизация | Тело запроса | Ответ |
|---|---|---|---|---|---|
| GET | `/api/orders/suz-settings.php` | Получение сохранённых параметров OMS в сессии | Cookie PHPSESSID | нет | `{ "context": { ... } }` |
| POST | `/api/orders/suz-settings.php` | Сохранение параметров OMS (`omsId`, `omsConnection`, `participantInn`, `stationUrl`, `locationAddress`) | Cookie PHPSESSID | `{ "omsId": string, ... }` | `{ "status": "ok", "context": { ... } }` |
| DELETE | `/api/orders/suz-settings.php` | Очистка локального контекста OMS | Cookie PHPSESSID | нет | `{ "status": "ok" }` |
| GET | `/api/orders/suz-auth.php?mode=challenge` | Запрос challenge для подписи | нет | нет | `{ "uuid": string, "data": string }` |
| POST | `/api/orders/suz-auth.php` | Обмен подписи на `clientToken` СУЗ | Подпись PKCS#7, `omsConnection`, `omsId` | `{ "uuid": string, "signature": string, "omsConnection": string, "omsId": string }` | `{ "status": "ok", "expiresAt": int|null }` |
| GET | `/api/orders/suz-auth.php?mode=status` | Проверка состояния токена и контекста | Cookie PHPSESSID | нет | `{ "active": bool, "expiresAt": int|null, "omsId": string|null, "omsConnection": string|null, "context": { ... } }` |
| DELETE | `/api/orders/suz-auth.php` | Сброс клиентского токена | Cookie PHPSESSID | нет | `{ "status": "ok" }` |

## Требования к подписи
- Challenge из True API (`data`) подписывается присоединённой подписью PKCS#7 (CAdES-BES).
- На фронтенде используется `CAdESCOM.CadesSignedData` с `propset_ContentEncoding(CADESCOM_STRING_TO_UCS2LE)`.
- В запросе на обмен подписи (`POST /api/orders/suz-auth.php`) подпись передаётся как Base64 PKCS#7.

## Заголовки и логирование
- Сервер автоматически выставляет `Content-Type: application/json; charset=utf-8` и `Cache-Control: no-store`.
- Все запросы логируются через `ordersLog` в `orders_debug.log` (ключевые события: challenge, получение токена, ошибки, обновление настроек).
- SUZ REST-вызовы (`trueApiRequest`, `suzRequest`) добавляют стандартные заголовки `Accept`, `Content-Type`, `Accept-Encoding`.

## Процесс получения токена
1. Клиент читает сохранённый контекст `GET /api/orders/suz-settings.php` и выводит поля формы.
2. Пользователь вводит `omsConnection`, `omsId`, дополнительные данные и сохраняет их (`POST /api/orders/suz-settings.php`).
3. При тесте подключение запрашивается challenge (`GET /api/orders/suz-auth.php?mode=challenge`).
4. Challenge подписывается УКЭП, подпись отправляется вместе с `omsConnection` и `omsId` (`POST /api/orders/suz-auth.php`).
5. Сервер вызывает `TrueApiClient::exchangeTokenForConnection`, сохраняет `clientToken`, обновляет контекст, пишет лог и возвращает `expiresAt`.
6. При необходимости токен очищается `DELETE /api/orders/suz-auth.php`.

## Использование данных
- `omsId`, `omsConnection` — обязательны для `POST /api/v3/order` в `SuzClient::createOrder`.
- `participantInn`, `stationUrl`, `locationAddress` готовятся для последующих сценариев (оформление заказов, подстановка в JSON).
- `clientToken` и связанный `omsId` хранятся в сессии (`ORDER_SUZ_TOKEN_SESSION_KEY`).
