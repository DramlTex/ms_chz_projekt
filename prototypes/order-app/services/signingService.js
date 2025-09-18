import { getNationalCatalogBaseUrl } from '../config.js';
import { toApiDateTime } from '../utils/datetime.js';

export class NationalCatalogSigningError extends Error {
  constructor(message, { type = 'generic', status, cause } = {}) {
    super(message);
    this.name = 'NationalCatalogSigningError';
    this.type = type;
    this.status = status;
    this.cause = cause;
  }
}

function normalizeAuth(auth = {}) {
  const apiKey = auth.apiKey?.trim() ?? '';
  const bearerToken = auth.bearerToken?.trim() ?? '';
  if (!apiKey && !bearerToken) {
    throw new NationalCatalogSigningError(
      'Укажите API-ключ или bearer-токен Национального каталога.',
      { type: 'auth-required' },
    );
  }
  return { apiKey, bearerToken };
}

function buildUrl(path, query = {}, auth) {
  const base = getNationalCatalogBaseUrl();
  if (!base) {
    throw new NationalCatalogSigningError('Не указан базовый URL Национального каталога.', {
      type: 'configuration',
    });
  }
  const { apiKey, bearerToken } = normalizeAuth(auth);
  const url = new URL(path, base);
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });
  if (apiKey) {
    url.searchParams.set('apikey', apiKey);
  } else if (bearerToken) {
    url.searchParams.set('token', bearerToken);
  }
  return { url: url.toString(), bearerToken };
}

async function request(path, { method = 'GET', query = {}, body, auth } = {}) {
  const { url, bearerToken } = buildUrl(path, query, auth);
  const headers = new Headers({ Accept: 'application/json' });
  if (bearerToken) {
    const value = bearerToken.startsWith('Bearer ')
      ? bearerToken
      : `Bearer ${bearerToken}`;
    headers.set('Authorization', value);
  }
  let payload;
  if (body !== undefined && body !== null) {
    payload = typeof body === 'string' ? body : JSON.stringify(body);
    headers.set('Content-Type', 'application/json');
  }
  let response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: payload,
      credentials: 'include',
    });
  } catch (error) {
    throw new NationalCatalogSigningError(
      'Не удалось выполнить запрос к Национальному каталогу.',
      { type: 'network', cause: error },
    );
  }
  const text = await response.text();
  if (!response.ok) {
    throw new NationalCatalogSigningError(
      `Национальный каталог ответил ${response.status}.`,
      { type: 'http', status: response.status, cause: text },
    );
  }
  if (!text) {
    return null;
  }
  try {
    return JSON.parse(text);
  } catch (error) {
    throw new NationalCatalogSigningError(
      'Некорректный JSON в ответе Национального каталога.',
      { type: 'parse', cause: error },
    );
  }
}

function requiresSignature(row) {
  const source = row?.good_detailed_status ?? row?.statusDetails ?? [];
  const list = Array.isArray(source) ? source : [source];
  if (list.length === 0) return false;
  return list
    .map((value) => (typeof value === 'string' ? value : String(value ?? '')))
    .some((value) => {
      const normalized = value.toLowerCase().replace(/[^a-z0-9]/g, '');
      return normalized.includes('notsigned') || normalized.includes('waitsign');
    });
}

function mapCard(row) {
  const goodId = row.good_id ?? row.goodId ?? row.id ?? null;
  return {
    goodId: goodId !== null ? String(goodId) : '',
    name: row.good_name ?? row.name ?? row.title ?? '',
    status: row.good_status ?? row.status ?? '',
    statusDetails: Array.isArray(row.good_detailed_status)
      ? row.good_detailed_status
      : Array.isArray(row.statusDetails)
        ? row.statusDetails
        : [],
    updatedAt: row.to_date ?? row.updated_at ?? row.last_update ?? row.updatedAt ?? null,
  };
}

export async function fetchCardsAwaitingSignature({
  auth,
  fromDate,
  toDate,
  limit = 100,
  offset = 0,
} = {}) {
  const query = {
    limit,
    offset,
    good_status: 'draft',
  };
  if (fromDate instanceof Date && !Number.isNaN(fromDate.getTime())) {
    query.from_date = toApiDateTime(fromDate);
  }
  if (toDate instanceof Date && !Number.isNaN(toDate.getTime())) {
    query.to_date = toApiDateTime(toDate);
  }
  const payload = await request('/v4/product-list', {
    method: 'GET',
    query,
    auth,
  });
  const goods = Array.isArray(payload?.result?.goods)
    ? payload.result.goods
    : Array.isArray(payload?.cards)
      ? payload.cards
      : [];
  const filtered = goods.filter(requiresSignature).map(mapCard);
  return {
    cards: filtered,
    total: filtered.length,
    rawTotal: goods.length,
  };
}

export async function fetchDocumentsForSignature({ goodIds, auth } = {}) {
  const ids = Array.isArray(goodIds) ? goodIds.filter((id) => id !== null && id !== undefined) : [];
  if (ids.length === 0) {
    throw new NationalCatalogSigningError('Нет карточек для подписи.', {
      type: 'validation',
    });
  }
  const limited = ids.slice(0, 25);
  const payload = await request('/v3/feed-product-document', {
    method: 'POST',
    auth,
    body: {
      goodIds: limited,
      publicationAgreement: true,
    },
  });
  const xmls = Array.isArray(payload?.result?.xmls)
    ? payload.result.xmls
    : [];
  return xmls.map((item) => ({
    goodId: item.goodId ?? item.good_id ?? item.id ?? '',
    xml: item.xml ?? item.xmlBase64 ?? item.xmlB64 ?? '',
  }));
}

export async function sendDetachedSignatures({ signPack, auth } = {}) {
  if (!Array.isArray(signPack) || signPack.length === 0) {
    throw new NationalCatalogSigningError('Пакет подписи пуст.', {
      type: 'validation',
    });
  }
  const payload = await request('/v3/feed-product-sign-pkcs', {
    method: 'POST',
    auth,
    body: signPack,
  });
  const result = payload?.result ?? payload ?? {};
  return {
    signed: Array.isArray(result.signed) ? result.signed.map((id) => String(id)) : [],
    errors: Array.isArray(result.errors)
      ? result.errors.map((error) => ({
          goodId: error.goodId ?? error.good_id ?? error.id ?? '',
          message: error.message ?? error.error ?? 'Неизвестная ошибка.',
          code: error.code ?? null,
        }))
      : [],
    raw: payload,
  };
}
