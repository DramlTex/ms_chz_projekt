# Выписка: CryptoPro Browser Plug-in — SignHash
- Источник: [Вычисление подписи по хэш-значению](https://docs.cryptopro.ru/cades/plugin/plugin-samples/plugin-samples-raw-signature), [Создание и проверка отделенной подписи по хэш-значению данных](https://docs.cryptopro.ru/cades/plugin/plugin-samples/plugin-samples-cades-sign-hash)
- Дата выгрузки: 2025-09-23

## RawSignature.SignHash и VerifyHash
- Перед `SetHashValue` необходимо вызвать `propset_Algorithm`, чтобы объект `CAdESCOM.HashedData` знал ожидаемый формат digest; в примере используется `cadesplugin.CADESCOM_HASH_ALGORITHM_CP_GOST_3411` до заполнения хэша.
- Метод `SetHashValue` принимает строку шестнадцатеричных цифр, сгруппированных по два символа на байт, с пробелами или без них; другой формат вызывает ошибку параметров при подписи.
- `RawSignature.SignHash(oHashedData, oCertificate)` и `VerifyHash` работают с подготовленным `CPHashedData`; сертификат предварительно ищется в `CAPICOM_CURRENT_USER_STORE`, а затем хранилище закрывается.

## CadesSignedData.SignHash / VerifyHash
- Для отделённой подписи BES сначала готовят `HashedData` и настраивают алгоритм аналогично RawSignature, используя тот же шестнадцатеричный digest.
- Объект `CAdESCOM.CPSigner` должен получить сертификат через `propset_Certificate` и включить проверку `propset_CheckCertificate(true)` перед подписью.
- `CAdESCOM.CadesSignedData.SignHash(oHashedData, oSigner, cadesplugin.CADESCOM_CADES_BES)` создаёт отделённую подпись, а `VerifyHash` проверяет её с тем же алгоритмом и хэшем; несогласованность параметров приводит к ошибке 0x80070057 на этапе подписи или проверки.
