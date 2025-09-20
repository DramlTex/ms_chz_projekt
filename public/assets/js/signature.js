(function (global) {
  const state = {
    certificates: [],
    certificateMap: new Map(),
  };

  function ensurePlugin() {
    if (typeof global.cadesplugin === 'undefined') {
      return Promise.reject(new Error('CryptoPro plug-in не найден. Установите расширение.'));
    }
    if (typeof global.cadesplugin.then === 'function') {
      return global.cadesplugin;
    }
    return Promise.resolve(global.cadesplugin);
  }

  function utf8ToBase64(text) {
    return global.btoa(unescape(encodeURIComponent(text)));
  }

  function serializeCertificate(cert) {
    return Promise.all([
      cert.SubjectName,
      cert.IssuerName,
      cert.Thumbprint,
      cert.ValidFromDate,
      cert.ValidToDate,
      cert.SerialNumber,
    ]).then(([subject, issuer, thumbprint, validFrom, validTo, serial]) => ({
      subject,
      issuer,
      thumbprint: thumbprint ? thumbprint.toUpperCase() : '',
      validFrom: validFrom ? new Date(validFrom).toISOString() : null,
      validTo: validTo ? new Date(validTo).toISOString() : null,
      serialNumber: serial || null,
    }));
  }

  async function loadCertificates() {
    const plugin = await ensurePlugin();
    const store = await plugin.CreateObjectAsync('CAdESCOM.Store');
    await store.Open(2, 'My', 2);
    const collection = await store.Certificates;
    const count = await collection.Count;

    state.certificates = [];
    state.certificateMap.clear();

    for (let index = 1; index <= count; index += 1) {
      const cert = await collection.Item(index);
      const validTo = new Date(await cert.ValidToDate);
      if (Number.isFinite(validTo.getTime()) && validTo < new Date()) {
        continue;
      }
      const info = await serializeCertificate(cert);
      state.certificates.push(info);
      state.certificateMap.set(info.thumbprint, cert);
    }

    if (!state.certificates.length) {
      throw new Error('Не найдены действующие сертификаты в хранилище.');
    }

    return state.certificates.slice();
  }

  async function getCertificate(thumbprint) {
    if (!thumbprint) {
      throw new Error('Не выбран сертификат.');
    }
    const upper = thumbprint.toUpperCase();
    if (state.certificateMap.has(upper)) {
      return state.certificateMap.get(upper);
    }
    await loadCertificates();
    if (state.certificateMap.has(upper)) {
      return state.certificateMap.get(upper);
    }
    throw new Error('Сертификат не найден в хранилище.');
  }

  async function buildSigner(cert) {
    const plugin = await ensurePlugin();
    const signer = await plugin.CreateObjectAsync('CAdESCOM.CSigner');
    await signer.propset_Certificate(cert);
    if (typeof signer.propset_Options === 'function') {
      await signer.propset_Options(plugin.CAPICOM_CERTIFICATE_INCLUDE_WHOLE_CHAIN);
    }
    return signer;
  }

  async function signBase64Detached(base64Payload, thumbprint) {
    const plugin = await ensurePlugin();
    const certificate = await getCertificate(thumbprint);
    const signer = await buildSigner(certificate);
    const signedData = await plugin.CreateObjectAsync('CAdESCOM.CadesSignedData');

    try {
      if (typeof signedData.propset_ContentEncoding === 'function') {
        await signedData.propset_ContentEncoding(plugin.CADESCOM_BASE64_TO_BINARY);
        await signedData.propset_Content(base64Payload);
      } else {
        await signedData.propset_Content(global.atob(base64Payload));
      }
    } catch (error) {
      await signedData.propset_Content(global.atob(base64Payload));
    }

    return signedData.SignCades(
      signer,
      plugin.CADESCOM_CADES_BES,
      true,
    );
  }

  async function signUtf8Detached(text, thumbprint) {
    return signBase64Detached(utf8ToBase64(text), thumbprint);
  }

  async function signForAuth(challenge, thumbprint) {
    const plugin = await ensurePlugin();
    const certificate = await getCertificate(thumbprint);
    const signer = await buildSigner(certificate);
    const signedData = await plugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    await signedData.propset_Content(challenge);
    return signedData.SignCades(
      signer,
      plugin.CADESCOM_CADES_BES,
      false,
    );
  }

  global.Signature = {
    ensureReady: ensurePlugin,
    loadCertificates,
    getCertificates() {
      return state.certificates.slice();
    },
    signForAuth,
    signUtf8Detached,
    signBase64Detached,
  };
})(window);
