const PLUGIN_SCRIPT_URL = new URL('../../crypto_pro/cadesplugin_api.js', import.meta.url);

let pluginPromise = null;

function loadScript() {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${PLUGIN_SCRIPT_URL}"]`);
    if (existing) {
      resolve();
      return;
    }
    const script = document.createElement('script');
    script.src = PLUGIN_SCRIPT_URL;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Не удалось загрузить скрипт CryptoPro. Убедитесь, что файл cadesplugin_api.js доступен.'));
    document.head.appendChild(script);
  });
}

export function ensureCryptoProPlugin() {
  if (pluginPromise) {
    return pluginPromise;
  }
  pluginPromise = (async () => {
    if (typeof window === 'undefined') {
      throw new Error('CryptoPro доступен только в браузере');
    }
    if (!window.cadesplugin) {
      await loadScript();
    }
    if (!window.cadesplugin) {
      throw new Error('CryptoPro plug-in не обнаружен. Установите плагин и перезапустите браузер.');
    }
    if (typeof window.cadesplugin.then === 'function') {
      return window.cadesplugin.then(
        (plugin) => plugin,
        (error) => {
          throw error instanceof Error ? error : new Error(String(error));
        },
      );
    }
    return window.cadesplugin;
  })();
  return pluginPromise;
}

function formatDistinguishedName(dn) {
  if (!dn) return '';
  return dn
    .split(',')
    .map((part) => part.trim())
    .map((part) => part.replace(/^[A-Z]+=\s*/i, (match) => match.trim() + ' '))
    .join(', ');
}

function isGostCertificate(friendlyName = '') {
  const normalized = friendlyName.toLowerCase();
  return normalized.includes('34.10') || normalized.includes('gost');
}

export async function listCertificates() {
  const plugin = await ensureCryptoProPlugin();
  const store = await plugin.CreateObjectAsync('CAdESCOM.Store');
  await store.Open(plugin.CAPICOM_CURRENT_USER_STORE, 'My', plugin.CAPICOM_STORE_OPEN_MAXIMUM_ALLOWED);
  const certificates = await store.Certificates;
  const count = await certificates.Count;
  const now = new Date();
  const result = [];
  for (let index = 1; index <= count; index += 1) {
    const certificate = await certificates.Item(index);
    const validTo = new Date(await certificate.ValidToDate);
    if (Number.isNaN(validTo.getTime()) || validTo < now) {
      continue;
    }
    const validFrom = new Date(await certificate.ValidFromDate);
    const thumbprint = await certificate.Thumbprint;
    const subject = formatDistinguishedName(await certificate.SubjectName);
    const issuer = formatDistinguishedName(await certificate.IssuerName);
    const publicKey = await certificate.PublicKey;
    const algorithm = publicKey ? await publicKey.Algorithm : null;
    const friendlyName = algorithm ? await algorithm.FriendlyName : '';
    if (!isGostCertificate(friendlyName)) {
      continue;
    }
    result.push({
      thumbprint,
      subject,
      issuer,
      validFrom,
      validTo,
      friendlyName,
    });
  }
  await store.Close();
  result.sort((a, b) => b.validTo - a.validTo);
  return result;
}

async function getCertificateByThumbprint(plugin, thumbprint) {
  const store = await plugin.CreateObjectAsync('CAdESCOM.Store');
  await store.Open(plugin.CAPICOM_CURRENT_USER_STORE, 'My', plugin.CAPICOM_STORE_OPEN_MAXIMUM_ALLOWED);
  try {
    const certificates = await store.Certificates;
    const matched = await certificates.Find(plugin.CAPICOM_CERTIFICATE_FIND_SHA1_HASH, thumbprint);
    const count = await matched.Count;
    if (count === 0) {
      throw new Error('Сертификат с указанным отпечатком не найден в хранилище.');
    }
    return { certificate: await matched.Item(1), store };
  } catch (error) {
    await store.Close();
    throw error;
  }
}

async function configureSignedContent(plugin, signedData, data, encoding) {
  const normalizedData = typeof data === 'string' ? data : String(data ?? '');
  switch (encoding) {
    case 'base64':
      await signedData.propset_ContentEncoding(plugin.CADESCOM_BASE64_TO_BINARY);
      await signedData.propset_Content(normalizedData);
      break;
    case 'string':
    default:
      await signedData.propset_ContentEncoding(plugin.CADESCOM_STRING_TO_UCS2LE);
      await signedData.propset_Content(normalizedData);
      break;
  }
}

export async function signData({ data, thumbprint, detached = true, encoding = 'string' } = {}) {
  const hasData = data !== undefined && data !== null && String(data).length > 0;
  if (!hasData) {
    throw new Error('Нет данных для подписи');
  }
  const normalizedThumbprint = typeof thumbprint === 'string' ? thumbprint.trim() : thumbprint;
  if (!normalizedThumbprint) {
    throw new Error('Не указан сертификат для подписи');
  }
  const plugin = await ensureCryptoProPlugin();
  const { certificate, store } = await getCertificateByThumbprint(plugin, normalizedThumbprint);
  try {
    const signer = await plugin.CreateObjectAsync('CAdESCOM.CPSigner');
    await signer.propset_Certificate(certificate);
    await signer.propset_CheckCertificate(true);

    const signedData = await plugin.CreateObjectAsync('CAdESCOM.CadesSignedData');
    await configureSignedContent(plugin, signedData, data, encoding);

    const signature = await signedData.SignCades(
      signer,
      plugin.CADESCOM_CADES_BES,
      detached,
      plugin.CADESCOM_ENCODE_BASE64,
    );
    return signature;
  } finally {
    await store.Close();
  }
}

export async function signTrueApiChallenge(data, thumbprint) {
  return signData({ data, thumbprint, detached: false, encoding: 'string' });
}
