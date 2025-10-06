# Шпаргалка по цепочке заказа КМ через API СУЗ

## 1. Создание заказа
- Метод: `POST /api/v3/orders?omsId={UUID}`.
- Заголовки: `clientToken` (маркер безопасности) и `X-Signature` (откреплённая подпись тела заказа).
- Тело запроса — JSON с `productGroup`, массивом `products` (GTIN, quantity, templateId) и `attributes.releaseMethodType`.

## 2. Получение статусов и буферов
- Метод: `GET /api/v3/orders?omsId={UUID}`.
- Возвращает массив `orderInfos`, где каждая запись содержит `orderId`, `orderStatus` и коллекцию `buffers`.
- Каждый буфер включает GTIN, templateId, счётчики `leftInBuffer`, `totalCodes`, `bufferStatus` и т. д.

## 3. Выгрузка массивов КМ
- Метод: `GET /api/v3/orders/{orderId}/codes?omsId={UUID}&bufferId={UUID}&count={N}&documentFormat=CSV|JSON`.
- Ответ содержит бинарный файл (CSV или JSON) с кодами. Его удобно возвращать на фронтенд в Base64 и сохранять как Blob.

## 4. Отчёт о нанесении (ввод в оборот)
- Метод: `POST /api/v3/utilisation?omsId={UUID}`.
- Заголовки: `clientToken`, `X-Signature`.
- Тело (`UtilisationReport`): `productGroup`, `utilisationType` (`UTILISATION`, `RESORT`, `REMARK`), массив полных КМ `sntins`, дополнительные атрибуты по товарной группе.
- В соответствии с документацией (раздел 4.4.11), `sntins` всегда передаются полными кодами с кодом проверки, максимум 30 000 КМ за запрос.

Эти шаги позволяют пройти цепочку «карточка → заказ → получение буферов → выгрузка КМ → ввод в оборот» в одном интерфейсе.
