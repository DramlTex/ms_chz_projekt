# Документация по процессу подписания и обмена с Честным знаком

## Архитектура решения

Приложение состоит из статической страницы `index.html` с JavaScript-логикой и набора PHP-эндпоинтов, работающих как облегчённый backend-прокси к API Честного знака. JavaScript отвечает за взаимодействие с плагином КриптоПро (`cadesplugin_api.js`), формирование полезной нагрузки запросов и отправку подписанных данных на сервер. PHP-скрипты принимают эти запросы, прикладывают требуемые заголовки и обращаются к внешним сервисам `true-api` и `suzgrid` через вспомогательные функции из `config.php`. Все сессионные данные (токены, OMS-настройки) хранятся в PHP-сессии пользователя, что позволяет восстанавливать состояние между запросами.【F:index.html†L566-L758】【F:config.php†L221-L260】

## Работа с сертификатом пользователя

По нажатию «Загрузить сертификаты» клиентская часть открывает пользовательское хранилище `My` через COM-интерфейсы CAdES и извлекает действующие сертификаты, доступные для подписания. Сертификаты с истёкшим сроком действия отфильтровываются, после чего выбранный сертификат сохраняется в состоянии приложения и используется для всех операций подписи.【F:index.html†L573-L618】

## Авторизация в "НК" и "СУЗ"

Процесс авторизации состоит из повторяющейся пары действий: получение challenge на сервере и подписание его на клиенте.

1. **Получение challenge.** Для НК и СУЗ фронтенд вызывает `auth.php` с действиями `nk-challenge` или `suz-challenge`. PHP-прокси, в свою очередь, обращается к `GET https://markirovka.crpt.ru/api/v3/true-api/auth/key` и возвращает JSON с `uuid` и строкой `data`, которую требуется подписать.【F:auth.php†L9-L65】
2. **Подпись challenge.** Клиент вызывает `signAttached()`, который создаёт объект `CAdESCOM.CadesSignedData`, при необходимости устанавливает кодировку `CADESCOM_STRING_TO_UCS2LE`, прописывает текст challenge и формирует **attached** подпись в формате CAdES-BES. Результат не модифицируется и напрямую отправляется на сервер.【F:index.html†L623-L641】
3. **Обмен подписи на токен.**
   * Для НК вызывается `auth.php?action=nk-signin`. Сервер делает `POST /auth/simpleSignIn` на true-api с телом `{"uuid","data","unitedToken":true}`. Возвращённый `uuidToken`/`token` сохраняется в сессии с учётом таймаута (если `expiresIn` присутствует). Ответ клиента содержит флаг `ok` и время истечения токена.【F:auth.php†L17-L58】【F:config.php†L221-L239】
   * Для СУЗ дополнительно требуется заранее сохранить OMS-настройки. Запрос `auth.php?action=suz-signin` валидирует `omsConnection` и `omsId`, затем обращается к `POST /auth/simpleSignIn/{omsConnection}`. Из ответа извлекается `clientToken`/`uuidToken`, который кладётся в сессию и используется в дальнейшем как `clientToken`-заголовок.【F:auth.php†L68-L106】

### Сохранение OMS настроек

Форма OMS отправляет `omsConnection` и `omsId` в `auth.php?action=save-oms`. Сервер проверяет формат GUID для обоих значений и сохраняет их в сессии. Эти параметры участвуют в построении путей (`omsConnection`) и query-параметров (`omsId`) при запросах к СУЗ.【F:index.html†L673-L695】【F:auth.php†L108-L131】【F:config.php†L245-L260】

## Подпись и отправка производственных данных

### Поиск карточки товара

После успешной авторизации в НК клиент может найти карточку по GTIN. Вызов `card.php` добавляет заголовок `Authorization: Bearer <nk_token>` и запрашивает `GET /nk/feed-product?gtin=...`. PHP-скрипт извлекает нужные атрибуты (`good_id`, `TN VED`, `productGroup`, `templateId`) из ответа и возвращает их фронтенду.【F:card.php†L6-L62】

### Формирование заказа на коды

Для создания заказа собирается JSON с блоками `productGroup`, `products`, `attributes`. Перед отправкой полезная нагрузка сериализуется в строку и подписывается функцией `signDetached()`:

1. JSON переводится в UTF-8 и кодируется в Base64, чтобы отразить бинарное содержимое HTTP-тела.
2. В `CadesSignedData` устанавливается `ContentEncoding = CADESCOM_BASE64_TO_BINARY` и вызывается `SignCades` с флагом `detached = true`, что формирует **отсоединённую** подпись CAdES-BES.
3. Из результата удаляются пробелы и переводы строк, поскольку заголовок `X-Signature` должен содержать непрерывную base64-строку.【F:index.html†L643-L671】【F:index.html†L825-L918】

Полученная пара `{payload, signature}` отправляется POST-запросом на `order.php`. Backend очищает подпись от служебных символов на всякий случай, извлекает `clientToken` и `omsId` из сессии и вызывает `POST https://suzgrid.crpt.ru/api/v3/order?omsId=...` с телом заказа. На уровне HTTP добавляются заголовки:

* `Content-Type: application/json`
* `Accept: application/json`
* `clientToken: <значение из сессии>`
* `X-Signature: <очищенная подпись>`

Ответ СУЗ возвращается клиенту, который обновляет `orderId` и буферы, доступные для выгрузки кодов.【F:order.php†L6-L48】【F:index.html†L897-L917】

### Получение статусов и кодов

* `order_status.php` запрашивает `GET /order/status` у СУЗ с заголовком `clientToken` и параметром `omsId`, а при необходимости — `orderId`/`gtin`. Ответ используется для обновления списка буферов и контроля статуса заказа.【F:order_status.php†L6-L45】【F:index.html†L974-L1018】
* `codes.php` формирует query-параметры `omsId`, `orderId`, `quantity`, `gtin`, проверяя формат GTIN (14 цифр). Затем он обращается к `GET /codes` с заголовком `clientToken` и возвращает список КМ, который фронтенд сохраняет в файл в формате CSV или JSON.【F:codes.php†L6-L74】【F:index.html†L1023-L1094】

### Отчёт о нанесении (ввод в оборот)

JSON отчёта пользователь вводит вручную. Клиент сериализует объект, подписывает его `signDetached()` (тот же алгоритм, что и для заказа) и отправляет на `utilisation.php`. PHP-прокси добавляет заголовки `clientToken` и `X-Signature` и выполняет `POST /utilisation?omsId=...` в СУЗ. Ответ отображается в журнале пользователя.【F:index.html†L1096-L1136】【F:utilisation.php†L6-L36】

## Почему подпись считается валидной

* В сценариях авторизации (`signAttached`) используется **attached** подпись, когда подписываемый challenge встраивается внутрь CMS-структуры. Это соответствует требованиям `/auth/simpleSignIn`, которые ожидают в теле поле `data` с полным CMS-blob'ом.【F:index.html†L623-L641】【F:auth.php†L17-L105】
* При создании заказов и отчётов применяется **detached** подпись: HTTP-тело JSON отправляется «как есть», а CMS-подпись передаётся отдельно через заголовок `X-Signature`. Перед подписанием JSON преобразуется в последовательность байтов в точности так, как её увидит сервер (UTF-8 → Base64), поэтому верификация на стороне СУЗ проходит успешно.【F:index.html†L643-L671】【F:order.php†L34-L44】【F:utilisation.php†L24-L33】
* Серверная сторона гарантирует отсутствие лишних символов (очистка подписи, строгие заголовки) и хранит токены вместе с датой истечения, что предотвращает отправку устаревших `clientToken` и `nk_token`. Дополнительное логирование в `config.php` помогает отлаживать сетевые ошибки, скрывая при этом чувствительные заголовки.【F:order.php†L13-L44】【F:config.php†L10-L201】

## Сводка HTTP-заголовков

| Сценарий | Заголовки, отправляемые PHP | Примечания |
| --- | --- | --- |
| `GET /auth/key` (challenge) | `Content-Type: application/json`, `Accept: application/json` (по умолчанию) | Заголовки формируются в `apiRequestRaw`, пользовательские не требуются.【F:config.php†L87-L201】 |
| `POST /auth/simpleSignIn` | `Content-Type: application/json`, `Accept: application/json` | Тело содержит `uuid`, `data`, `unitedToken`.| 
| `POST /auth/simpleSignIn/{omsConnection}` | `Content-Type: application/json`, `Accept: application/json` | Путь включает сохранённый `omsConnection`. |
| `GET /nk/feed-product` | `Authorization: Bearer <nk_token>` | Токен берётся из сессии пользователя.【F:card.php†L19-L24】 |
| `POST /order?omsId=...` | `Content-Type: application/json`, `Accept: application/json`, `clientToken: …`, `X-Signature: …` | Подпись — CMS detached без пробелов.【F:order.php†L34-L44】 |
| `GET /order/status` | `Accept: application/json`, `clientToken: …` | `omsId` передаётся через query.【F:order_status.php†L18-L45】 |
| `GET /codes` | `Accept: application/json`, `clientToken: …` | Дополнительно — query `orderId`, `gtin`, `quantity`.【F:codes.php†L46-L74】 |
| `POST /utilisation?omsId=...` | `Content-Type: application/json`, `Accept: application/json`, `clientToken: …`, `X-Signature: …` | Подпись формируется тем же способом, что и для заказов.【F:utilisation.php†L24-L33】 |

Такой цикл «вызвать challenge → подписать → обменять на токен» и строгое соответствие формату подписанных сообщений обеспечивает успешную авторизацию и дальнейшее взаимодействие с API Честного знака.
