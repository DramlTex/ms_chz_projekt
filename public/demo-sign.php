<?php
require_once __DIR__ . '/../bootstrap.php';

$defaultPhrase = "Демонстрационная фраза для подписи через CryptoPro";
$cryptoProBootstrap = renderCryptoProExtensionBootstrap();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Тест подписи через CryptoPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($cryptoProBootstrap !== '') {
        echo $cryptoProBootstrap, "\n";
    } ?>
    <script src="assets/js/cadesplugin_api.js"></script>
    <style>
        :root {
            color-scheme: light dark;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f5f5f5;
            color: #222;
        }

        body {
            margin: 0;
            padding: 2rem;
            display: flex;
            justify-content: center;
        }

        main {
            max-width: 840px;
            width: 100%;
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin-top: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        h2 {
            font-size: 1.3rem;
            margin: 1.5rem 0 0.75rem;
        }

        h1 span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
        }

        section + section {
            margin-top: 1.5rem;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        select,
        textarea,
        input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
        }

        button:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(79, 70, 229, 0.35);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .status {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .status.info {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status.success {
            background: #dcfce7;
            color: #15803d;
        }

        .status.error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .info-grid div {
            background: #f9fafb;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
        }

        code,
        pre {
            font-family: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            background: #111827;
            color: #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
            overflow-x: auto;
        }

        pre {
            margin-top: 0.5rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 280px;
        }

        .small {
            font-size: 0.875rem;
            color: #4b5563;
        }

        footer {
            margin-top: 2rem;
            font-size: 0.85rem;
            color: #6b7280;
            text-align: center;
        }

        @media (max-width: 600px) {
            body {
                padding: 1rem;
            }

            main {
                padding: 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<main>
    <h1><span>ЭЦП</span>Тестирование подписи CryptoPro</h1>
    <p class="small">Эта страница предназначена для проверки загрузки плагина CryptoPro и выполнения тестового подписания произвольной строки. Никакие данные на сервер не отправляются — всё происходит в браузере.</p>

    <section>
        <div class="actions">
            <button type="button" id="loadCertificates">Запросить сертификаты</button>
            <button type="button" id="signPhrase" disabled>Подписать фразу</button>
        </div>
        <div id="status" class="status info">Ожидание подключения плагина CryptoPro…</div>
    </section>

    <section>
        <label for="certificateList">Выберите сертификат</label>
        <select id="certificateList" size="5"></select>
        <div id="certificateInfo" class="info-grid" hidden>
            <div><strong>Владелец</strong><br><span id="infoSubject"></span></div>
            <div><strong>Издатель</strong><br><span id="infoIssuer"></span></div>
            <div><strong>Действителен с</strong><br><span id="infoValidFrom"></span></div>
            <div><strong>Действителен до</strong><br><span id="infoValidTo"></span></div>
        </div>
    </section>

    <section>
        <label for="phrase">Фраза для подписи</label>
        <textarea id="phrase" placeholder="Введите текст, который нужно подписать"><?php echo htmlspecialchars($defaultPhrase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
    </section>

    <section>
        <label for="signature">Результат (CAdES-BES, Base64)</label>
        <textarea id="signature" readonly placeholder="Подпись будет показана здесь"></textarea>
    </section>

    <section>
        <h2>Подпись по хэш-значению</h2>
        <p class="small">Введите готовый хэш (шестнадцатеричная строка без пробелов). Алгоритм определяется автоматически по выбранному сертификату.</p>
        <label for="hashInput">Хэш (hex)</label>
        <textarea id="hashInput" placeholder="Например, 3F2A…"></textarea>
        <div class="actions">
            <button type="button" id="signRawHash" disabled>Raw подпись (SignHash)</button>
            <button type="button" id="signDetachedHash" disabled>Detached CAdES (SignHash)</button>
        </div>
        <label for="rawSignatureOutput">Raw подпись (Base64)</label>
        <textarea id="rawSignatureOutput" readonly placeholder="Результат RawSignature.SignHash"></textarea>
        <label for="detachedSignatureOutput">Detached CAdES (Base64)</label>
        <textarea id="detachedSignatureOutput" readonly placeholder="Результат CadesSignedData.SignHash"></textarea>
    </section>

    <footer>
        CryptoPro Browser Plug-in должен быть установлен и активен в браузере. Если список сертификатов пуст, проверьте доступ к личному хранилищу.
    </footer>
</main>

<script>
(function () {
    "use strict";

    const certificateCache = new Map();
    const loadButton = document.getElementById("loadCertificates");
    const signButton = document.getElementById("signPhrase");
    const statusNode = document.getElementById("status");
    const certificateList = document.getElementById("certificateList");
    const signatureOutput = document.getElementById("signature");
    const phraseInput = document.getElementById("phrase");
    const certificateInfo = document.getElementById("certificateInfo");
    const infoSubject = document.getElementById("infoSubject");
    const infoIssuer = document.getElementById("infoIssuer");
    const infoValidFrom = document.getElementById("infoValidFrom");
    const infoValidTo = document.getElementById("infoValidTo");
    const hashInput = document.getElementById("hashInput");
    const rawSignatureOutput = document.getElementById("rawSignatureOutput");
    const detachedSignatureOutput = document.getElementById("detachedSignatureOutput");
    const signRawHashButton = document.getElementById("signRawHash");
    const signDetachedHashButton = document.getElementById("signDetachedHash");

    function setStatus(message, type = "info") {
        statusNode.textContent = message;
        statusNode.className = `status ${type}`;
    }

    function extractCn(distinguishedName) {
        const match = distinguishedName.match(/CN=([^,]+)/i);
        return match ? match[1].trim() : distinguishedName;
    }

    function formatDate(date) {
        return new Intl.DateTimeFormat("ru-RU", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit"
        }).format(date);
    }

    const HASH_ALGORITHMS_BY_OID = {
        "1.2.643.7.1.1.1.1": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256",
        "1.2.643.7.1.1.3.2": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256",
        "1.2.643.7.1.1.1.2": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_512",
        "1.2.643.7.1.1.3.3": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_512",
        "1.2.643.2.2.19": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411",
        "1.2.643.2.2.20": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411",
        "1.2.643.2.2.3": "CADESCOM_HASH_ALGORITHM_CP_GOST_3411"
    };

    const HASH_ALGORITHM_LABELS = {
        CADESCOM_HASH_ALGORITHM_CP_GOST_3411: "ГОСТ Р 34.11-2001",
        CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256: "ГОСТ Р 34.11-2012 (256)",
        CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_512: "ГОСТ Р 34.11-2012 (512)"
    };

    function normalizeHexDigest(value) {
        return (value || "").replace(/[^0-9a-f]/gi, "").toUpperCase();
    }

    function validateHexDigest(hex) {
        if (!hex) {
            return { valid: false, error: "Введите хэш в шестнадцатеричном формате." };
        }
        if (hex.length % 2 !== 0) {
            return { valid: false, error: "Число шестнадцатеричных символов должно быть чётным (по 2 символа на байт)." };
        }
        if (!/^[0-9A-F]+$/.test(hex)) {
            return { valid: false, error: "Хэш может содержать только символы 0-9 и A-F." };
        }
        return { valid: true };
    }

    function clearHashOutputs() {
        rawSignatureOutput.value = "";
        detachedSignatureOutput.value = "";
    }

    function updateHashButtonsState() {
        const hasCertificate = Boolean(certificateList.value);
        const normalized = normalizeHexDigest(hashInput.value);
        const validation = validateHexDigest(normalized);
        const enabled = hasCertificate && validation.valid;
        signRawHashButton.disabled = !enabled;
        signDetachedHashButton.disabled = !enabled;
    }

    function handlePluginError(error) {
        console.error(error);
        const message = error && error.message ? error.message : String(error);
        setStatus(`Ошибка плагина: ${message}`, "error");
    }

    async function getAsyncProperty(object, property) {
        if (!object) {
            return null;
        }
        try {
            const value = object[property];
            if (typeof value === "function") {
                return await value.call(object);
            }
            return await value;
        } catch (error) {
            console.warn(`Не удалось получить свойство ${property}`, error);
            return null;
        }
    }

    async function readCertificateAlgorithmOid(certificate) {
        const publicKey = await getAsyncProperty(certificate, "PublicKey");
        const publicKeyAlgorithm = await getAsyncProperty(publicKey, "Algorithm");
        const publicKeyOid = await getAsyncProperty(publicKeyAlgorithm, "Value");
        if (publicKeyOid) {
            return publicKeyOid;
        }

        const signatureAlgorithm = await getAsyncProperty(certificate, "SignatureAlgorithm");
        const signatureOid = await getAsyncProperty(signatureAlgorithm, "Value");
        if (signatureOid) {
            return signatureOid;
        }

        return null;
    }

    async function getCertificateHashAlgorithm(entry) {
        if (entry.hashAlgorithm) {
            return entry.hashAlgorithm;
        }

        const oid = await readCertificateAlgorithmOid(entry.certificate);
        if (!oid) {
            throw new Error("Не удалось определить алгоритм сертификата для подписи по хэшу.");
        }

        const constantName = HASH_ALGORITHMS_BY_OID[oid];
        if (!constantName) {
            throw new Error(`OID алгоритма ${oid} не поддерживается для подписи по хэшу.`);
        }

        if (!window.cadesplugin || typeof window.cadesplugin[constantName] === "undefined") {
            throw new Error("Константы CryptoPro для алгоритма хэширования недоступны.");
        }

        const algorithmValue = window.cadesplugin[constantName];
        entry.hashAlgorithm = algorithmValue;
        entry.hashAlgorithmOid = oid;
        entry.hashAlgorithmName = constantName;
        entry.hashAlgorithmLabel = HASH_ALGORITHM_LABELS[constantName] || constantName;
        return algorithmValue;
    }

    async function createHashedDataFromDigest(entry, hexDigest) {
        const normalized = normalizeHexDigest(hexDigest);
        const validation = validateHexDigest(normalized);
        if (!validation.valid) {
            throw new Error(validation.error);
        }

        const hashedData = await window.cadesplugin.CreateObjectAsync("CAdESCOM.HashedData");
        const algorithm = await getCertificateHashAlgorithm(entry);
        await hashedData.propset_Algorithm(algorithm);

        try {
            await hashedData.SetHashValue(normalized);
        } catch (error) {
            throw new Error(`Не удалось загрузить хэш в объект HashedData: ${error && error.message ? error.message : error}`);
        }

        return { hashedData, algorithmLabel: entry.hashAlgorithmLabel };
    }

    function waitForPlugin() {
        return new Promise((resolve, reject) => {
            if (!window.cadesplugin || typeof window.cadesplugin.then !== "function") {
                reject(new Error("cadesplugin_api.js не найден или плагин не инициализировался."));
                return;
            }
            window.cadesplugin.then(resolve, reject);
        });
    }

    async function loadCertificates() {
        signatureOutput.value = "";
        clearHashOutputs();
        certificateList.innerHTML = "";
        certificateCache.clear();
        certificateInfo.hidden = true;
        updateHashButtonsState();

        let store;

        try {
            await waitForPlugin();
            setStatus("Запрос сертификатов из локального хранилища…", "info");

            store = await window.cadesplugin.CreateObjectAsync("CAdESCOM.Store");
            await store.Open(
                window.cadesplugin.CADESCOM_CURRENT_USER_STORE,
                window.cadesplugin.CADESCOM_MY_STORE,
                window.cadesplugin.CAPICOM_STORE_OPEN_MAXIMUM_ALLOWED
            );

            const certificates = await store.Certificates;
            const count = await certificates.Count;

            for (let index = 1; index <= count; index += 1) {
                const certificate = await certificates.Item(index);
                const hasPrivateKey = await certificate.HasPrivateKey();

                if (!hasPrivateKey) {
                    continue;
                }

                const validator = await certificate.IsValid();
                const isValid = await validator.Result;

                if (!isValid) {
                    continue;
                }

                const thumbprint = await certificate.Thumbprint;
                const subject = await certificate.SubjectName;
                const issuer = await certificate.IssuerName;
                const validFrom = new Date(await certificate.ValidFromDate);
                const validTo = new Date(await certificate.ValidToDate);

                const option = document.createElement("option");
                option.value = thumbprint;
                option.textContent = `${extractCn(subject)} (до ${formatDate(validTo)})`;
                option.dataset.subject = subject;
                option.dataset.issuer = issuer;
                option.dataset.validFrom = validFrom.toISOString();
                option.dataset.validTo = validTo.toISOString();

                certificateList.appendChild(option);
                certificateCache.set(thumbprint, { certificate, thumbprint });
            }

            if (!certificateList.options.length) {
                setStatus("Подходящие сертификаты не найдены. Проверьте, что в хранилище есть сертификат с закрытым ключом.", "error");
                signButton.disabled = true;
                updateHashButtonsState();
                return;
            }

            certificateList.selectedIndex = 0;
            signButton.disabled = false;
            updateCertificateInfo();
            updateHashButtonsState();
            setStatus("Сертификаты успешно загружены. Выберите подходящий и нажмите «Подписать фразу».", "success");
        } catch (error) {
            handlePluginError(error);
            signButton.disabled = true;
            updateHashButtonsState();
        } finally {
            if (store) {
                try {
                    await store.Close();
                } catch (closeError) {
                    console.warn("Не удалось закрыть хранилище сертификатов", closeError);
                }
            }
        }
    }

    async function signPhrase() {
        try {
            await waitForPlugin();
        } catch (error) {
            handlePluginError(error);
            return;
        }

        const thumbprint = certificateList.value;
        if (!thumbprint) {
            setStatus("Сначала выберите сертификат в списке.", "error");
            return;
        }

        const certificateEntry = certificateCache.get(thumbprint);
        const certificate = certificateEntry ? certificateEntry.certificate : null;
        if (!certificate) {
            setStatus("Не удалось получить выбранный сертификат. Попробуйте запросить список заново.", "error");
            return;
        }

        const phrase = phraseInput.value.trim();
        if (!phrase) {
            setStatus("Введите или оставьте демонстрационную фразу для подписи.", "error");
            return;
        }

        setStatus("Выполняем подпись… Подтвердите операцию в диалоге CryptoPro.", "info");
        signatureOutput.value = "";

        try {
            const signer = await window.cadesplugin.CreateObjectAsync("CAdESCOM.CPSigner");
            await signer.propset_Certificate(certificate);

            const signedData = await window.cadesplugin.CreateObjectAsync("CAdESCOM.CadesSignedData");
            await signedData.propset_ContentEncoding(window.cadesplugin.CADESCOM_STRING_TO_UCS2LE);
            await signedData.propset_Content(phrase);

            const signature = await signedData.SignCades(
                signer,
                window.cadesplugin.CADESCOM_CADES_BES,
                false
            );

            signatureOutput.value = signature;
            setStatus("Подпись успешно сформирована.", "success");
        } catch (error) {
            handlePluginError(error);
        }
    }

    async function signHashRaw() {
        try {
            await waitForPlugin();
        } catch (error) {
            handlePluginError(error);
            return;
        }

        const thumbprint = certificateList.value;
        if (!thumbprint) {
            setStatus("Сначала выберите сертификат в списке.", "error");
            return;
        }

        const entry = certificateCache.get(thumbprint);
        if (!entry || !entry.certificate) {
            setStatus("Не удалось получить выбранный сертификат. Попробуйте запросить список заново.", "error");
            return;
        }

        rawSignatureOutput.value = "";

        try {
            const normalizedHex = normalizeHexDigest(hashInput.value);
            const validation = validateHexDigest(normalizedHex);
            if (!validation.valid) {
                setStatus(validation.error, "error");
                return;
            }

            const { hashedData, algorithmLabel } = await createHashedDataFromDigest(entry, normalizedHex);
            setStatus(`Подписываем хэш через RawSignature.SignHash (${algorithmLabel}).`, "info");

            const raw = await window.cadesplugin.CreateObjectAsync("CAdESCOM.RawSignature");
            const signature = await raw.SignHash(hashedData, entry.certificate);

            rawSignatureOutput.value = signature;
            setStatus(`Raw подпись по хэшу (${algorithmLabel}) успешно сформирована.`, "success");
        } catch (error) {
            handlePluginError(error);
        }
    }

    async function signHashDetached() {
        try {
            await waitForPlugin();
        } catch (error) {
            handlePluginError(error);
            return;
        }

        const thumbprint = certificateList.value;
        if (!thumbprint) {
            setStatus("Сначала выберите сертификат в списке.", "error");
            return;
        }

        const entry = certificateCache.get(thumbprint);
        if (!entry || !entry.certificate) {
            setStatus("Не удалось получить выбранный сертификат. Попробуйте запросить список заново.", "error");
            return;
        }

        detachedSignatureOutput.value = "";

        try {
            const normalizedHex = normalizeHexDigest(hashInput.value);
            const validation = validateHexDigest(normalizedHex);
            if (!validation.valid) {
                setStatus(validation.error, "error");
                return;
            }

            const { hashedData, algorithmLabel } = await createHashedDataFromDigest(entry, normalizedHex);
            setStatus(`Формируем CAdES подпись по хэшу (${algorithmLabel}).`, "info");

            const signer = await window.cadesplugin.CreateObjectAsync("CAdESCOM.CPSigner");
            await signer.propset_Certificate(entry.certificate);
            await signer.propset_CheckCertificate(true);

            const signedData = await window.cadesplugin.CreateObjectAsync("CAdESCOM.CadesSignedData");
            await signedData.propset_ContentEncoding(window.cadesplugin.CADESCOM_BASE64_TO_BINARY);
            await signedData.propset_Content("");

            const signature = await signedData.SignHash(
                hashedData,
                signer,
                window.cadesplugin.CADESCOM_CADES_BES
            );

            detachedSignatureOutput.value = signature;
            setStatus(`Detached CAdES подпись по хэшу (${algorithmLabel}) успешно сформирована.`, "success");
        } catch (error) {
            handlePluginError(error);
        }
    }

    function updateCertificateInfo() {
        const option = certificateList.options[certificateList.selectedIndex];
        if (!option) {
            certificateInfo.hidden = true;
            updateHashButtonsState();
            return;
        }

        infoSubject.textContent = option.dataset.subject || "-";
        infoIssuer.textContent = option.dataset.issuer || "-";
        infoValidFrom.textContent = option.dataset.validFrom ? formatDate(new Date(option.dataset.validFrom)) : "-";
        infoValidTo.textContent = option.dataset.validTo ? formatDate(new Date(option.dataset.validTo)) : "-";
        certificateInfo.hidden = false;
        updateHashButtonsState();
    }

    loadButton.addEventListener("click", () => {
        loadButton.disabled = true;
        loadCertificates().finally(() => {
            loadButton.disabled = false;
        });
    });

    signButton.addEventListener("click", () => {
        signButton.disabled = true;
        signPhrase().finally(() => {
            signButton.disabled = false;
        });
    });

    certificateList.addEventListener("change", () => {
        updateCertificateInfo();
        clearHashOutputs();
    });

    hashInput.addEventListener("input", () => {
        clearHashOutputs();
        updateHashButtonsState();
    });

    signRawHashButton.addEventListener("click", () => {
        signRawHashButton.disabled = true;
        signDetachedHashButton.disabled = true;
        signHashRaw().finally(() => {
            updateHashButtonsState();
        });
    });

    signDetachedHashButton.addEventListener("click", () => {
        signRawHashButton.disabled = true;
        signDetachedHashButton.disabled = true;
        signHashDetached().finally(() => {
            updateHashButtonsState();
        });
    });

    waitForPlugin()
        .then(() => setStatus("Плагин CryptoPro загружен. Нажмите «Запросить сертификаты».", "info"))
        .catch(handlePluginError);
})();
</script>
</body>
</html>
