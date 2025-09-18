import { getTrueApiBaseUrl } from '../config.js';
import { signTrueApiChallenge } from './cryptoProClient.js';

export class AuthServiceError extends Error {
  constructor(message, { type = 'generic', status, cause } = {}) {
    super(message);
    this.name = 'AuthServiceError';
    this.type = type;
    this.status = status;
    this.cause = cause;
  }
}

function buildUrl(path) {
  const base = getTrueApiBaseUrl();
  if (!base) {
    throw new AuthServiceError('Не указан базовый URL True API', { type: 'configuration' });
  }
  return `${base}${path}`;
}

export async function fetchAuthChallenge() {
  const url = buildUrl('/auth/key');
  let response;
  try {
    response = await fetch(url, {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
  } catch (error) {
    throw new AuthServiceError('Не удалось выполнить запрос /auth/key', {
      type: 'network',
      cause: error,
    });
  }
  if (!response.ok) {
    const text = await response.text().catch(() => '');
    throw new AuthServiceError(`Ошибка /auth/key: ${response.status}`, {
      type: 'http',
      status: response.status,
      cause: text,
    });
  }
  const payload = await response.json().catch((error) => {
    throw new AuthServiceError('Некорректный JSON в ответе /auth/key', {
      type: 'parse',
      cause: error,
    });
  });
  if (!payload?.data) {
    throw new AuthServiceError('Ответ /auth/key не содержит данных для подписи', {
      type: 'validation',
      cause: payload,
    });
  }
  return { uuid: payload.uuid ?? null, data: payload.data };
}

async function exchangeSignatureForToken({ uuid, signature }) {
  const url = buildUrl('/auth/simpleSignIn');
  const body = {};
  if (uuid) body.uuid = uuid;
  body.data = signature;
  let response;
  try {
    response = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body),
    });
  } catch (error) {
    throw new AuthServiceError('Не удалось отправить подпись в /auth/simpleSignIn', {
      type: 'network',
      cause: error,
    });
  }
  if (!response.ok) {
    const text = await response.text().catch(() => '');
    throw new AuthServiceError(`Ошибка /auth/simpleSignIn: ${response.status}`, {
      type: 'http',
      status: response.status,
      cause: text,
    });
  }
  const payload = await response.json().catch((error) => {
    throw new AuthServiceError('Некорректный JSON в ответе /auth/simpleSignIn', {
      type: 'parse',
      cause: error,
    });
  });
  const token = payload?.token ?? payload?.uuidToken ?? payload?.unitedToken ?? null;
  if (!token) {
    throw new AuthServiceError('Ответ /auth/simpleSignIn не содержит токен', {
      type: 'validation',
      cause: payload,
    });
  }
  return { token, raw: payload };
}

export async function obtainTrueApiToken({ thumbprint }) {
  if (!thumbprint) {
    throw new AuthServiceError('Не выбран сертификат для подписи', { type: 'validation' });
  }
  const challenge = await fetchAuthChallenge();
  const signature = await signTrueApiChallenge(challenge.data, thumbprint);
  const exchange = await exchangeSignatureForToken({ uuid: challenge.uuid, signature });
  return {
    token: exchange.token,
    challenge,
    raw: exchange.raw,
  };
}
