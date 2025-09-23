<?php
$defaultPhrase = "Демонстрационная фраза для подписи через CryptoPro";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Тест подписи через CryptoPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="cadesplugin_api.js"></script>
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

    function handlePluginError(error) {
        console.error(error);
        const message = error && error.message ? error.message : String(error);
        setStatus(`Ошибка плагина: ${message}`, "error");
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
        certificateList.innerHTML = "";
        certificateCache.clear();
        certificateInfo.hidden = true;

        try {
            await waitForPlugin();
            setStatus("Запрос сертификатов из локального хранилища…", "info");

            const store = await window.cadesplugin.CreateObjectAsync("CAdESCOM.Store");
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
                certificateCache.set(thumbprint, certificate);
            }

            if (!certificateList.options.length) {
                setStatus("Подходящие сертификаты не найдены. Проверьте, что в хранилище есть сертификат с закрытым ключом.", "error");
                signButton.disabled = true;
                return;
            }

            certificateList.selectedIndex = 0;
            signButton.disabled = false;
            updateCertificateInfo();
            setStatus("Сертификаты успешно загружены. Выберите подходящий и нажмите «Подписать фразу».", "success");
        } catch (error) {
            handlePluginError(error);
            signButton.disabled = true;
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

        const certificate = certificateCache.get(thumbprint);
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

    function updateCertificateInfo() {
        const option = certificateList.options[certificateList.selectedIndex];
        if (!option) {
            certificateInfo.hidden = true;
            return;
        }

        infoSubject.textContent = option.dataset.subject || "-";
        infoIssuer.textContent = option.dataset.issuer || "-";
        infoValidFrom.textContent = option.dataset.validFrom ? formatDate(new Date(option.dataset.validFrom)) : "-";
        infoValidTo.textContent = option.dataset.validTo ? formatDate(new Date(option.dataset.validTo)) : "-";
        certificateInfo.hidden = false;
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

    certificateList.addEventListener("change", updateCertificateInfo);

    waitForPlugin()
        .then(() => setStatus("Плагин CryptoPro загружен. Нажмите «Запросить сертификаты».", "info"))
        .catch(handlePluginError);
})();
</script>
</body>
</html>
