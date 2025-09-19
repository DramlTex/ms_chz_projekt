Agents.md — Честный Знак (ГИС МТ) + CryptoPro: полный гайд для интеграции
TL;DR (быстрый путь)

Получить токен True API:
GET /auth/key → подписать data УКЭП → POST /auth/simpleSignIn → получить token (или uuidToken). Токен действует до 10 часов.

Получить динамический clientToken СУЗ:
зарегистрировать соединение интегратора → GET /auth/key → подписать data УКЭП → POST /auth/simpleSignIn/{omsConnection} → clientToken.

Заказать КМ в СУЗ → получить КМ → перевести в APPLIED (отправить отчёт об использовании) → (опционально) агрегация в СУЗ → проверять статусы КИ в True API.

Движение товара в True API: единый метод /lk/documents/create?pg= для «Ввод в оборот», «Отгрузка», «Приёмка», «Вывод/Возврат» и др. Подписываем откреплённой УКЭП поле product_document.

Подпись УКЭП на фронте: CryptoPro Browser Plug‑in — примеры «raw signature / sign hash» и CAdES‑BES (detached). 


0. Термины и роли

ГИС МТ (Честный Знак) — True API, ЭДО Лайт, НКМТ, СУЗ‑Облако.

УОТ — участник оборота товаров (ваш аккаунт).

КМ/КИ — код маркировки / идентификации.

УКЭП — усиленная квалифицированная электронная подпись (ГОСТ Р 34.10‑2012).

МЧД — машиночитаемая доверенность (для авторизации физлиц от организации).

1. Стенды и базовые URL

True API (общие операции):

Пром:
https://markirovka.crpt.ru/api/v3/true-api
https://markirovka.crpt.ru/api/v4/true-api

Песочница:
https://markirovka.sandbox.crptech.ru/api/v3/true-api
https://markirovka.sandbox.crptech.ru/api/v4/true-api

В новых релизах методы могут появляться в версии, отличной от v3 — подменяйте в URL. Старая версия поддерживается ~полгода. Рекомендуется заголовок Accept: */* (иначе 406). Лимит: ≤50 req/s от одного УОТ. Макс. размер документа: ≤30 MB. Макс. КИ в одном документе: ≤30 000.

СУЗ‑Облако (заказ КМ):

Пром: https://suzgrid.crpt.ru

Песочница: https://suz.sandbox.crptech.ru, https://suzintegrator.sandbox.crptech.ru (интеграторские операции/Swagger)

НКМТ (каталог):

Пром: https://апи.национальный-каталог.рф

Песочница: https://nk.sandbox.crptech.ru, https://api.nk.sandbox.crptech.ru
Для доступа нужен api-key (запрос в поддержку НК).

ЭДО Лайт:

Пром: https://elk.edo.crpt.tech

Песочница: https://edo.sandbox.crptech.ru

2. Аутентификация и токены
2.1. True API — единая аутентификация

Шаг 1. Получить пару uuid/data:

curl -X GET "$TRUE_API_BASE/auth/key" \
  -H "accept: application/json"
# => { "uuid":"...", "data":"RANDOM_STRING" }


data — случайная строка для подписи УКЭП.

Шаг 2. Подписать data и получить токен:

Вариант A (JWT-токен, классический):

curl -X POST "$TRUE_API_BASE/auth/simpleSignIn" \
  -H "accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "uuid": "UUID_С_ШАГА_1",
    "data": "BASE64_PKCS7_ATTACHED_SIGNATURE"
  }'
# => { "token":"<JWT>" }


Вариант B (единый токен UUID):

curl -X POST "$TRUE_API_BASE/auth/simpleSignIn" \
  -H "accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "data": "BASE64_PKCS7_SIGNATURE_OR_RANDOM",
    "unitedToken": true
  }'
# => { "uuidToken":"123e4567-e89b-12d3-a456-426655440000" }


Если авторизуется физлицо по МЧД и есть несколько организаций, передавайте inn организации. Ошибки: 400 (нет uuid/data), 403 (не найдена МЧД/ИНН в сертификате/подпись невалидна/организация заблокирована), 401 (токен не действителен). Токен живёт не более 10 ч.

Важно: используйте Accept: */*, учитывая, что методы True API могут возвращать application/json или бинарный ZIP/octet-stream. При неправильном Accept сервер вернёт 406.

2.2. СУЗ‑Облако — динамический clientToken

Как получить omsId / omsConnection: в ЛК ГИС МТ → «Управление заказами» → «Устройства». Для интеграционных сервисов дополнительно регистрируем соединение у интегратора (см. ниже).

Вариант 1. Через единый метод (коротко):

Повторяем шаг «GET /auth/key» и «POST /auth/simpleSignIn/{omsConnection}» (подписываем data УКЭП), в ответ — clientToken.

Вариант 2. Регистрация соединения интегратора:

# регистрация соединения интегратора (пример)
curl -X POST "https://suzintegrator.sandbox.crptech.ru/api/v2/integration/connection?omsId=<OMS_ID>" \
  -H "Content-Type: application/json" \
  -H "X-RegistrationKey: <регистрационный_ключ_интегратора>" \
  -H "X-Signature: <BASE64_PKCS7_SIGNATURE_OF_BODY>" \
  -d '{ "name":"My connector", "address":"https://my.service/callback" }'
# => { "omsConnection":"<GUID>" }


Затем: GET /auth/key → Подпись data → POST /auth/simpleSignIn/{omsConnection} → clientToken. Храним его и передаём в заголовке clientToken: в вызовах СУЗ‑Облака.

Пинги и операции в СУЗ требуют clientToken и omsId. Пример GET /api/v2/<pg>/ping?omsId=... возвращает omsId при валидном токене.

3. CryptoPro Browser Plug‑in: установка и код подписи
3.1. Установка

Windows: установить плагин и браузерное расширение CryptoPro Extension for CAdES Browser Plug‑in (Chromium‑бразуеры), включить расширение. 
КриптоПро

macOS: аналогично, установить плагин и включить расширение. 
КриптоПро

Общие страницы: «Работа с плагином», «Методы cadesplugin», «Активация объектов», «Примеры». 
КриптоПро
+3
КриптоПро
+3
КриптоПро
+3

3.2. Что именно подписывать в ГИС МТ

True API auth/simpleSignIn: подписываем строку data из /auth/key. Для JWT‑токена ожидается присоединённая PKCS#7 подпись в Base64 (detached не подходит); для некоторых интеграций (напр., при получении clientToken СУЗ) допускается присоединённая или откреплённая подпись. Проверьте требования конкретного метода (см. ниже по разделам API).

Единый метод /lk/documents/create: поле signature — откреплённая УКЭП в Base64 на незакодированное JSON‑тело бизнес‑документа (а вот тело product_document передаётся в Base64 в поле product_document).

3.3. Подпись «как есть» (Raw signature по хэшу)

Идея: формируем хэш с помощью CAdESCOM.HashedData, затем вычисляем подпись методом RawSignature.SignHash. Это удобно для сценариев типа «подписать уже посчитанный на бэке хэш» или для строгого контроля алгоритма. Пример из документации: работа с объектами RawSignature и CPHashedData (GOST 34.11‑2012 256/512, в зависимости от сертификата). 
КриптоПро
+1

Скелет кода (JS, плагин):

async function signBase64ByHash(base64Data, thumbprint) {
  // 1) Ищем сертификат в хранилище по отпечатку
  const store = await cadesplugin.CreateObjectAsync("CAdESCOM.Store");
  await store.Open(); // Current user / MY по умолчанию
  const certs = await store.Certificates.Find(
    cadesplugin.CAPICOM_CERTIFICATE_FIND_SHA1_HASH, thumbprint);
  if (await certs.Count === 0) throw new Error("Cert not found");
  const cert = await certs.Item(1);

  // 2) Готовим хэш входных байт (base64 → bytes)
  const hashed = await cadesplugin.CreateObjectAsync("CAdESCOM.HashedData");
  await hashed.propset_Algorithm(
    cadesplugin.CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256);
  await hashed.SetHashValue(base64Data); // SetHashValue ожидает base64

  // 3) Подписываем хэш «raw»-методом
  const raw = await cadesplugin.CreateObjectAsync("CAdESCOM.RawSignature");
  const signature = await raw.SignHash(hashed, cert);
  await store.Close();
  return signature; // base64 подпись
}


Объект IRawSignature.SignHash принимаeт CPHashedData и сертификат; выбор алгоритма хэша должен соответствовать алгоритму ключа сертификата (ГОСТ‑2012 256/512). Подробности — в описании метода и примерах КриптоПро. 
КриптоПро
+1

3.4. Откреплённая CAdES‑BES подпись (detached) по хэшу

Идея: создаём и проверяем отделённую подпись CAdES‑BES по хэш‑значению данных (часто нужно для бизнес‑документов ГИС МТ). 
КриптоПро

Скелет кода (JS, плагин):

async function signDetachedJSON(jsonString, thumbprint) {
  // 1) Вычислить хэш JSON (как bytes)
  const hashed = await cadesplugin.CreateObjectAsync("CAdESCOM.HashedData");
  await hashed.propset_Algorithm(
    cadesplugin.CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256);
  await hashed.Hash(jsonString);

  // 2) Собрать detached CAdES-BES
  const signer = await cadesplugin.CreateObjectAsync("CAdESCOM.CPSigner");
  const store = await cadesplugin.CreateObjectAsync("CAdESCOM.Store");
  await store.Open();
  const certs = await store.Certificates.Find(
    cadesplugin.CAPICOM_CERTIFICATE_FIND_SHA1_HASH, thumbprint);
  if (await certs.Count === 0) throw new Error("Cert not found");
  await signer.propset_Certificate(await certs.Item(1));

  const sigData = await cadesplugin.CreateObjectAsync("CAdESCOM.CadesSignedData");
  await sigData.propset_ContentEncoding(cadesplugin.CADESCOM_BASE64_TO_BINARY);
  await sigData.propset_Content(""); // пустое содержимое → detached
  const signature = await sigData.SignHash(hashed, signer, cadesplugin.CADESCOM_CADES_BES);

  await store.Close();
  return signature; // base64 PKCS#7 (detached)
}


См. официальный пример «Создание и проверка отделенной подписи по хэш‑значению данных». Для загрузки/чтения файлов есть FileAPI‑примеры. 
КриптоПро
+1

3.5. Присоединённая CAdES‑BES (attached) — для auth/simpleSignIn (JWT)

Для классической авторизации True API ожидает присоединённую подпись строки data (PKCS#7 в Base64). В плагине это удобно делать через CAdESCOM.CadesSignedData с контентом data и типом CADES_BES.

4. True API: основные методы и паттерны

Всегда ставьте Accept: */* и Authorization: Bearer <TOKEN>. Для методов с бинарными ответами (ZIP/CSV) сервер может вернуть application/zip/octet‑stream.

4.1. Проверка УОТ и МОД

Проверка регистрации УОТ по ИНН:
GET /participants?inns=7712345678&inns=... (≤100 ИНН).
Успех: краткая/расширенная информация (если свой ИНН/с токеном). Ошибки: 400 (некорректный ИНН), 404 (УОТ не найден).

Проверка статуса МОД (ЕГАИС, только ТГ «beer»):
POST /api/v3/true-api/mods/info с телом:

{ "pg": ["beer"], "inn": "1234567890", "kpp": ["123456789"] }


Ответ: статусы по товарной группе и МОД, флаги isBlocked. Ошибки 400/404 с подробным error_message.

Список МОД по УОТ (фильтры inns, kpp, fiasId, productGroups, пагинация):
GET /api/v3/true-api/mods/list?inns=...&limit=...&page=... → result[], total, nextPage. Ошибки 400/401/404/500.

4.2. Коды идентификации (КИ)

Публичная информация по списку КИ:
POST /cises/info (см. справочник полей) — используйте для проверки статусов КИ и агрегатов. Допускается проверка КИ агрегата и вложений.

4.3. Единый метод подачи документов

URL: POST /lk/documents/create?pg=<код_товарной_группы>
Тело (общий случай):

{
  "document_format": "MANUAL|XML|CSV",
  "product_document": "<BASE64(тело_документа)>",
  "type": "<КОД_ТИПА_ДОКУМЕНТА>",
  "signature": "<BASE64(откреплённая УКЭП на незакодированное тело)>"
}


Для УПД/УКД предусмотрены парные поля second_product_document + second_signature (титул покупателя). Ответ: 200/201 с doc_id. Ошибки: отсутствие обязательных полей, неверный pg, запрет на табачные ТГ в этом методе, 401/403/422.

Поддерживаемые типы (фрагмент):
AGGREGATION_DOCUMENT, DISAGGREGATION_DOCUMENT, REAGGREGATION_DOCUMENT, LP_INTRODUCE_GOODS (+ CSV/XML варианты), LP_SHIP_GOODS, LP_ACCEPT_GOODS, LP_RETURN, LK_RECEIPT (вывод) и др. Полный перечень в справочнике документа.

Пример: Формирование упаковки (JSON):

{
  "participantId": "7700000000",
  "aggregationUnits": [{
    "unitSerialNumber": "SSCC_OR_KITU",
    "aggregationType": "AGGREGATION",
    "sntins": ["КИ_вложения_1", "КИ_вложения_2", "..."]
  }]
}


Требования к КИТУ/КИГУ (длины, статусы APPLIED/INTRODUCED, согласованность вложений) — см. описание метода.

Ввод в оборот/Отгрузка/Приёмка/Вывод/Возврат: такие же правила — формируете бизнес‑документ (JSON/XML/CSV), кодируете его в Base64 → кладёте в product_document → отдельно подписываете незакодированное тело откреплённой УКЭП → в signature.

Проверка статуса документа:
GET /doc/{docId}/info → status == CHECKED_OK при успехе.

4.4. ЭДО Лайт: договоры, подписи, балансы

Список черновиков договоров (для подписания УОТ):
GET /elk/crm-api/documents → метаданные, signtype, edoDocumentId. 401 при отсутствии токена.

Получить тело договора (PDF):
GET /elk/crm-api/document?guid={id} — ссылка, Content-Disposition содержит имя файла (декодируйте filename*=UTF-8''...).

Подписать договор:
POST /elk/outgoing-documents/{documentId}/signature (Content-Type: text/plain, тело — Base64 откреплённой подписи) → {id}.

Статусы заключения договоров:
GET /elk/outgoing-documents?limit=&offset= → items[] { id, status }. status=4 = подписан обеими сторонами.

Получить ZIP документа ЭДО с подписями/квитанциями:
GET /elk/outgoing-documents/{documentId} (или входящие /elk/incoming-documents/{documentId}/content).

Счёт на оплату (прислать на e‑mail):
POST /elk/crm-api/prequests → { "status":200 } при успехе.

Баланс по ТГ:
GET /elk/product-groups/balance/all (по всем ТГ) или
GET /elk/product-groups/balance?productGroupId= (по одной). Ошибки 401/404 при неверном pid в токене.

5. СУЗ‑Облако (короткий сценарий)

Проверить доступность СУЗ:
GET /api/v2/<pg>/ping?omsId=... + заголовок clientToken: ... → omsId.

Создать заказ на эмиссию КМ:
POST /api/v2/<pg>/orders?omsId=... (clientToken в заголовке) — тело зависит от ТГ. См. Swagger СУЗ.

Статус массива КМ → получить КМ → Отчёт об использовании (перевод в APPLIED). Далее можно отправить Отчёт об агрегации (если делаете агрегацию на линии).

Подробная «лесенка» шагов (Postman, подпись, очередность) — см. раздел «Краткое описание основных операций…» в общей инструкции по API.

6. НКМТ (каталог): заметки

Нужен api-key (запрос в поддержку НК).

Доступны методы: получение атрибутов, торговых марок, субаккаунтов, генерация черновиков GTIN, создание/обновление карточек, подписание/модерация, статусы фидов и т.п. Точки входа см. в разделе «Методы “Национального каталога”» True API документа.

Рекомендация: сначала публикуйте карточки в НКМТ (или GS1), после чего используйте СУЗ/True API для КМ/документов. Пошагово — в общей «Инструкции по работе с API».

7. Требования к форматам, экранирование, заголовки

JSON: RFC 7159, UTF‑8, для необязательных полей — указывайте null, иначе возможны ошибки валидации.

CSV: RFC 4180, разделитель «,», иерархические структуры агрегатов не поддерживаются (дублирование данных).

XML: UTF‑8, <?xml ...?>, проверка по XSD (актуальные XSD — в разделе «Помощь» ЛК ГИС МТ).

Экранирование:
URL — RFC 3986; JSON‑строки — RFC 8259; CSV — RFC 4180 (окружать КИ с особыми символами в кавычки "); XML — W3C (замена < на &lt; и т.п.). Примеры в доке True API.

Заголовки: всегда Accept: */* (иначе 406 при бинарном ответе), Authorization: Bearer <token> (для приватных методов).

8. Полезные паттерны и готовые запросы
8.1. Авторизация True API (JWT)
# 1) Получить uuid/data
curl -s "$TRUE_API_BASE/auth/key" -H "accept: application/json"

# 2) Подписать data присоединённой УКЭП (CryptoPro) → BASE64_PKCS7

# 3) Получить token
curl -s -X POST "$TRUE_API_BASE/auth/simpleSignIn" \
  -H "accept: application/json" -H "Content-Type: application/json" \
  -d "{ \"uuid\":\"$UUID\", \"data\":\"$BASE64_PKCS7\" }"


8.2. Единый метод — пример «Ввод в оборот. Производство РФ»
# product_document.json → формируете согласно типу LP_INTRODUCE_GOODS
B64=$(base64 -w0 product_document.json)
SIG=$(cat product_document.json | sign_detached_with_crypto_pro)

curl -s -X POST "$TRUE_API_BASE/lk/documents/create?pg=milk" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{
        \"document_format\": \"MANUAL\",
        \"product_document\": \"$B64\",
        \"type\": \"LP_INTRODUCE_GOODS\",
        \"signature\": \"$SIG\"
      }"


8.3. Проверка статуса документа
curl -s "$TRUE_API_BASE/doc/$DOC_ID/info" -H "Authorization: Bearer $TOKEN"
# status == CHECKED_OK → OK


8.4. СУЗ: пинг + заказ КМ (пример)
curl -s "$SUZ_BASE/api/v2/milk/ping?omsId=$OMS_ID" \
  -H "clientToken: $CLIENT_TOKEN"
# => { "omsId": ... }

curl -s -X POST "$SUZ_BASE/api/v2/milk/orders?omsId=$OMS_ID" \
  -H "clientToken: $CLIENT_TOKEN" -H "Content-Type: application/json" \
  -d @order.json


9. Ошибки и диагностика

401 — «Токен не действителен. Необходимо получить новый токен аутентификации».
Проверяйте заголовок Authorization/clientToken, срок действия (≤10 ч).

400 — отсутствие обязательных полей (document_format, product_document, type, signature и т.д.), «JSON parse error», «не указан обязательный параметр pg».

403 — недоступная функциональность товарной группе / недостаток прав / неверный тип УКЭП/МЧД.

406 — неверный Accept (ставьте Accept: */*).

True API rate‑limit: ≤50 запросов/сек от одного УОТ (иначе блокировка).

Логирование/поиск причин на стороне CryptoPro: включайте лог CAdES, смотрите DbgView/реестр — в официальной инструкции по логированию. 
КриптоПро

10. Важные ограничения/известные изменения (OMS/True API)

Планируется отключение поддержки «прикреплённой» подписи в некоторых местах (следите за анонсами).

Ограничение агрегирующих массивов (sntins) до 30 000 КИ.

Изменения по ТГ («моторные масла», «радиоэлектронная продукция», наборы шаблонов КМ, даты экспериментов/закрытий, формат SSCC с AI(00) до 20 символов и т.п.).
Подробности — в свежем «OMS‑CLOUD 4.0 ANNOUNCEMENT…» (Rev. 1.18).

11. Практические советы по CryptoPro

Алгоритм хэширования выбирайте согласованно с ключом сертификата (ГОСТ 34.11‑2012 256/512). В примерах — CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256. 
КриптоПро

Для detached подписи по хэшу используйте CadesSignedData.SignHash (CAdES‑BES). Для «raw» подписи — RawSignature.SignHash. Примеры официальные: RawSignature/CPHashedData и CAdES‑detached по хэшу. 


Для чтения больших файлов и стримов пользуйтесь FileAPI‑примером от CryptoPro. 
КриптоПро

Для установки/включения расширения ориентируйтесь на инструкции для вашей ОС/браузера. 


12. Часто используемые справочники и «где смотреть»

Список поддерживаемых ТГ, статусы КИ/документов, типы документов — приложение в True API 553.0.

Инструкция по старту интеграции (Postman, подписи, последовательность) — «Инструкция по работе с API» (v36.0).

Динамический clientToken СУЗ (через unified auth) и интеграторское соединение — «Инструкция по получению динамического клиентского токена».

Анонсы предстоящих изменений OMS — документ «OMS‑CLOUD ANNOUNCEMENT…» (Rev. 1.18 и ниже).

13. Чек‑лист вывода в прод

 УКЭП действующая; при необходимости — МЧД для физлица.

 Карточки товаров опубликованы в НКМТ (или GS1).

 Настроены omsId, omsConnection, получен clientToken СУЗ.

 Проверены лимиты: ≤50 rps, ≤30 MB, ≤30 000 КИ в документе.

 Установлен CryptoPro plug‑in + расширение, протестированы сценарии подписи (attached/detached/hash). 

 Заголовки Accept: */*, Authorization, корректные кодировки/экранирование.

14. Приложение — быстрые ссылки

CryptoPro Browser Plug‑in (официальные docs):

Установка Windows / macOS / обзор плагина:
https://docs.cryptopro.ru/cades/plugin/plugin-installation-windows
 •
https://docs.cryptopro.ru/cades/plugin/plugin-installation-macos
 •
https://docs.cryptopro.ru/cades/plugin
 


Работа с плагином, методы, примеры:
https://docs.cryptopro.ru/cades/plugin/plugin-usage
 •
https://docs.cryptopro.ru/cades/plugin/plugin-methods
 •
https://docs.cryptopro.ru/cades/plugin/plugin-samples
 


Подпись по хэшу / «raw» + CAdES‑detached:
https://docs.cryptopro.ru/cades/plugin/plugin-samples/plugin-samples-raw-signature

https://docs.cryptopro.ru/cades/plugin/plugin-samples/plugin-samples-cades-sign-hash
 

FileAPI/Stream пример:
https://docs.cryptopro.ru/cades/plugin/plugin-samples/plugin-samples-fileapi_stream
 
КриптоПро

ГИС МТ (официальные docs, последняя версия):

True API v553.0 (полный справочник + примеры аутентификации/подписей):
(разделы «Единая аутентификация», «Единый метод создания документов», справочники типов/статусов и пр.)

Инструкция по работе с API (маршруты, Postman, подписи):

Инструкция по получению динамического clientToken СУЗ (унифицированная аутентификация):

Анонсы OMS (ожидаемые изменения API/ограничения):

15. Примечания по совместимости и нюансы

Версионирование True API: новые методы могут появляться в v4 и выше — заменяйте префикс версии в базовом URL. Старая версия поддерживается ~полгода.

SSCC формат: для большинства ТГ будет требоваться SSCC с AI(00) (20 цифр). Уточнены сроки перехода. Планируйте доработки.

Серийники и шаблоны КМ: по «радиоэлектронике» расширение длины серийного номера, возможная аннуляция старых КМ — следите за анонсами.