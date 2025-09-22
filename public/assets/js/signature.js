(function (global) {
  const state = { certificates: [], certificateMap: new Map() };

  function normalizeThumbprint(value) {
    return (value || '').replace(/\s+/g, '').toUpperCase();
  }

  // Ждём не просто промис, а появление фабрики CreateObjectAsync
  async function waitForCadesReady(maxMs = 10000) {
    const start = Date.now();

    if (global.cadesplugin && typeof global.cadesplugin.then === 'function') {
      try { await global.cadesplugin; } catch (_) {}
    }

    for (;;) {
      const p = global.cadesplugin;
      if (p && typeof p.CreateObjectAsync === 'function') return p;
      if (Date.now() - start > maxMs) {
        throw new Error('CryptoPro plug-in не успел инициализироваться (нет CreateObjectAsync).');
      }
      await new Promise(r => setTimeout(r, 100));
    }
  }

  async function ensurePlugin() {
    return waitForCadesReady();
  }

  async function createObject(name, maybePlugin) {
    const plugin = maybePlugin || (await ensurePlugin());
    if (typeof plugin.CreateObjectAsync === 'function') {
      return plugin.CreateObjectAsync(name);
    }
    if (typeof plugin.CreateObject === 'function') {
      return plugin.CreateObject(name);
    }
    throw new Error('CryptoPro plug-in загружен, но фабрики объектов нет (CreateObjectAsync).');
  }

  function utf8ToBase64(text) {
    return global.btoa(unescape(encodeURIComponent(text)));
  }

  function serializeCertificate(cert) {
    return Promise.all([
      cert.SubjectName, cert.IssuerName, cert.Thumbprint,
      cert.ValidFromDate, cert.ValidToDate, cert.SerialNumber,
    ]).then(([subject, issuer, thumbprint, validFrom, validTo, serial]) => ({
      subject,
      issuer,
      thumbprint: normalizeThumbprint(thumbprint),
      validFrom: validFrom ? new Date(validFrom).toISOString() : null,
      validTo: validTo ? new Date(validTo).toISOString() : null,
      serialNumber: serial || null,
    }));
  }

  async function loadCertificates() {
    const plugin = await ensurePlugin();
    const store = await createObject('CAdESCOM.Store', plugin);
    const open = store.Open || store.open;
    if (typeof open !== 'function') {
      throw new Error('CryptoPro plug-in: метод открытия хранилища недоступен.');
    }

    const location = plugin.CAPICOM_CURRENT_USER_STORE ?? 2;
    const mode = plugin.CAPICOM_STORE_OPEN_MAXIMUM_ALLOWED ?? 2;

    await open.call(store, location, 'My', mode);
    try {
      const collection = await store.Certificates;
      const count = await collection.Count;

      state.certificates = [];
      state.certificateMap.clear();

      for (let index = 1; index <= count; index += 1) {
        const cert = await collection.Item(index);
        const validTo = new Date(await cert.ValidToDate);
        if (Number.isFinite(validTo.getTime()) && validTo < new Date()) continue;

        const info = await serializeCertificate(cert);
        if (!info.thumbprint) continue;

        state.certificates.push(info);
        state.certificateMap.set(info.thumbprint, cert);
      }

      if (!state.certificates.length) {
        throw new Error('Не найдены действующие сертификаты в хранилище.');
      }
      return state.certificates.slice();
    } finally {
      const close = store.Close || store.close;
      if (typeof close === 'function') await close.call(store);
    }
  }

  async function getCertificate(thumbprint) {
    if (!thumbprint) throw new Error('Не выбран сертификат.');
    const upper = normalizeThumbprint(thumbprint);
    if (state.certificateMap.has(upper)) return state.certificateMap.get(upper);
    await loadCertificates();
    if (state.certificateMap.has(upper)) return state.certificateMap.get(upper);
    throw new Error('Сертификат не найден в хранилище.');
  }

  async function buildSigner(cert) {
    const plugin = await ensurePlugin();
    // Можно использовать CSigner (как у тебя) или CPSigner — обе работают
    const signer = await createObject('CAdESCOM.CSigner', plugin);
    if (typeof signer.propset_Certificate === 'function') {
      await signer.propset_Certificate(cert);
    } else if ('Certificate' in signer) {
      signer.Certificate = cert;
    } else {
      throw new Error('Не удалось привязать сертификат к подписанту.');
    }
    const includeChain = plugin.CAPICOM_CERTIFICATE_INCLUDE_WHOLE_CHAIN ?? 0;
    if (typeof signer.propset_Options === 'function') {
      await signer.propset_Options(includeChain);
    } else if ('Options' in signer) {
      signer.Options = includeChain;
    }
    return signer;
  }

  async function signBase64Detached(base64Payload, thumbprint) {
    const plugin = await ensurePlugin();
    const certificate = await getCertificate(thumbprint);
    const signer = await buildSigner(certificate);
    const signedData = await createObject('CAdESCOM.CadesSignedData', plugin);

    try {
      const encodingValue = plugin.CADESCOM_BASE64_TO_BINARY ?? 1;
      if (typeof signedData.propset_ContentEncoding === 'function') {
        await signedData.propset_ContentEncoding(encodingValue);
      } else if ('ContentEncoding' in signedData) {
        signedData.ContentEncoding = encodingValue;
      }
      if (typeof signedData.propset_Content === 'function') {
        await signedData.propset_Content(base64Payload);
      } else if ('Content' in signedData) {
        signedData.Content = base64Payload;
      } else {
        await signedData.propset_Content(global.atob(base64Payload));
      }
    } catch (error) {
      if (typeof signedData.propset_Content === 'function') {
        await signedData.propset_Content(global.atob(base64Payload));
      } else if ('Content' in signedData) {
        signedData.Content = global.atob(base64Payload);
      } else {
        throw error;
      }
    }

    const cadesType = plugin.CADESCOM_CADES_BES ?? 1;
    return signedData.SignCades(signer, cadesType, true);
  }

  async function signUtf8Detached(text, thumbprint) {
    return signBase64Detached(utf8ToBase64(text), thumbprint);
  }

  async function signForAuth(challenge, thumbprint) {
    const plugin = await ensurePlugin();
    const certificate = await getCertificate(thumbprint);
    const signer = await buildSigner(certificate);
    const signedData = await createObject('CAdESCOM.CadesSignedData', plugin);
    if (typeof signedData.propset_Content === 'function') {
      await signedData.propset_Content(challenge);
    } else if ('Content' in signedData) {
      signedData.Content = challenge;
    }
    const cadesType = plugin.CADESCOM_CADES_BES ?? 1;
    return signedData.SignCades(signer, cadesType, false);
  }

  global.Signature = {
    ensureReady: ensurePlugin,
    loadCertificates,
    getCertificates() { return state.certificates.slice(); },
    signForAuth,
    signUtf8Detached,
    signBase64Detached,
  };
})(window);
