import { toApiDateTime } from '../utils/datetime.js';
import { getNationalCatalogBaseUrl } from '../config.js';

export class SignatureServiceError extends Error {
  constructor(message, { type = 'generic', status, cause } = {}) {
    super(message);
    this.name = 'SignatureServiceError';
    this.type = type;
    this.status = status;
    this.cause = cause;
  }
}

function normalizeBaseUrl() {
  const base = getNationalCatalogBaseUrl();
  if (!base) {
    throw new SignatureServiceError('Не указан базовый URL Национального каталога', {
      type: 'configuration',
    });
  }
  return base;
}

function prepareAuth(auth = {}) {
  const apiKey = auth.apiKey?.trim() ?? '';
  const bearerToken = auth.bearerToken?.trim() ?? '';
  if (!apiKey && !bearerToken) {
    throw new SignatureServiceError('Требуется API-ключ или bearer-токен Национального каталога.', {
      type: 'auth-required',
    });
  }
  return { apiKey, bearerToken };
}

function buildHeaders(bearerToken) {
  const headers = new Headers({ Accept: 'application/json' });
  if (bearerToken) {
    const value = bearerToken.startsWith('Bearer ')
      ? bearerToken
      : `Bearer ${bearerToken}`;
    headers.set('Authorization', value);
  }
  headers.set('Content-Type', 'application/json');
  return headers;
}

function buildQuery(params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return;
    }
    query.set(key, String(value));
  });
  return query;
}

function mapDraft(item) {
  const details = Array.isArray(item.good_detailed_status)
    ? item.good_detailed_status
    : Array.isArray(item.statusDetails)
      ? item.statusDetails
      : [];
  return {
    goodId: String(item.good_id ?? item.goodId ?? item.id ?? ''),
    gtin: item.gtin ?? item.gtin13 ?? '',
    name: item.good_name ?? item.name ?? item.title ?? '',
    status: item.good_status ?? item.status ?? '',
    statusDetails: details.map((value) => String(value ?? '')),
    updatedAt: item.to_date ?? item.updated_at ?? item.last_update ?? item.updatedAt ?? null,
  };
}

function requiresSignature(item) {
  const list = Array.isArray(item.good_detailed_status)
    ? item.good_detailed_status
    : Array.isArray(item.statusDetails)
      ? item.statusDetails
      : [];
  const normalize = (value) => String(value ?? '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
  return list.some((value) => {
    const normalized = normalize(value);
    return normalized.includes('notsigned') || normalized.includes('waitsign');
  });
}

export async function fetchDraftsAwaitingSignature({
  auth = {},
  fromDate = null,
  toDate = null,
  limit = 500,
} = {}) {
  const baseUrl = normalizeBaseUrl();
  const { apiKey, bearerToken } = prepareAuth(auth);

  const query = buildQuery({
    limit: Math.min(Math.max(limit, 1), 1000),
    offset: 0,
    good_status: 'draft',
    from_date: fromDate ? toApiDateTime(fromDate) : undefined,
    to_date: toDate ? toApiDateTime(toDate) : undefined,
    apikey: apiKey || undefined,
    token: !apiKey && bearerToken ? bearerToken : undefined,
  });

  const url = `${baseUrl}/v4/product-list?${query.toString()}`;
  let response;
  try {
    response = await fetch(url, {
      method: 'GET',
      headers: buildHeaders(bearerToken),
    });
  } catch (error) {
    throw new SignatureServiceError('Не удалось запросить черновики для подписи в Национальном каталоге.', {
      type: 'network',
      cause: error,
    });
  }

  if (!response.ok) {
    const text = await response.text().catch(() => '');
    throw new SignatureServiceError(`Ошибка НК при запросе черновиков: ${response.status}`, {
      type: 'http',
      status: response.status,
      cause: text,
    });
  }

  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new SignatureServiceError('Некорректный ответ НК при загрузке черновиков для подписи.', {
      type: 'parse',
      cause: error,
    });
  }

  const goods = payload?.result?.goods ?? payload?.cards ?? [];
  const drafts = goods.filter(requiresSignature).map(mapDraft);

  return {
    items: drafts,
    total: drafts.length,
    meta: {
      fetchedAt: new Date(),
      rawTotal: payload?.result?.total ?? goods.length ?? drafts.length,
    },
  };
}

export async function fetchDocumentsForSignature({ goodIds = [], auth = {} } = {}) {
  if (!Array.isArray(goodIds) || goodIds.length === 0) {
    throw new SignatureServiceError('Не указаны идентификаторы карточек для подписи.', {
      type: 'validation',
    });
  }
  if (goodIds.length > 25) {
    throw new SignatureServiceError('За один запрос допускается не более 25 карточек для подписи.', {
      type: 'validation',
    });
  }

  const baseUrl = normalizeBaseUrl();
  const { apiKey, bearerToken } = prepareAuth(auth);
  const query = buildQuery({
    apikey: apiKey || undefined,
    token: !apiKey && bearerToken ? bearerToken : undefined,
  });
  const url = `${baseUrl}/v3/feed-product-document?${query.toString()}`;
  const body = {
    goodIds: goodIds.map((id) => Number.parseInt(id, 10) || id),
    publicationAgreement: true,
  };

  let response;
  try {
    response = await fetch(url, {
      method: 'POST',
      headers: buildHeaders(bearerToken),
      body: JSON.stringify(body),
    });
  } catch (error) {
    throw new SignatureServiceError('Не удалось получить XML карточек для подписи.', {
      type: 'network',
      cause: error,
    });
  }

  if (!response.ok) {
    const text = await response.text().catch(() => '');
    throw new SignatureServiceError(`Ошибка НК при запросе XML для подписи: ${response.status}`, {
      type: 'http',
      status: response.status,
      cause: text,
    });
  }

  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new SignatureServiceError('Некорректный JSON при загрузке XML для подписи.', {
      type: 'parse',
      cause: error,
    });
  }

  const xmls = payload?.result?.xmls ?? payload?.xmls ?? [];
  const documents = xmls.map((item) => ({
    goodId: String(item.goodId ?? item.good_id ?? item.id ?? ''),
    xml: item.xml ?? item.xmlB64 ?? '',
  }));

  return {
    documents,
    meta: {
      fetchedAt: new Date(),
    },
  };
}

export async function sendDetachedSignatures({ pack = [], auth = {} } = {}) {
  if (!Array.isArray(pack) || pack.length === 0) {
    throw new SignatureServiceError('Нет данных для отправки подписи.', {
      type: 'validation',
    });
  }

  const baseUrl = normalizeBaseUrl();
  const { apiKey, bearerToken } = prepareAuth(auth);
  const query = buildQuery({
    apikey: apiKey || undefined,
    token: !apiKey && bearerToken ? bearerToken : undefined,
  });
  const url = `${baseUrl}/v3/feed-product-sign-pkcs?${query.toString()}`;

  let response;
  try {
    response = await fetch(url, {
      method: 'POST',
      headers: buildHeaders(bearerToken),
      body: JSON.stringify(pack),
    });
  } catch (error) {
    throw new SignatureServiceError('Не удалось отправить подпись в Национальный каталог.', {
      type: 'network',
      cause: error,
    });
  }

  const text = await response.text().catch(() => '');
  if (!response.ok) {
    throw new SignatureServiceError(`Ошибка НК при отправке подписи: ${response.status}`, {
      type: 'http',
      status: response.status,
      cause: text,
    });
  }

  let payload = {};
  if (text) {
    try {
      payload = JSON.parse(text);
    } catch (error) {
      throw new SignatureServiceError('Некорректный JSON в ответе НК при отправке подписи.', {
        type: 'parse',
        cause: error,
      });
    }
  }

  const result = payload?.result ?? payload ?? {};
  return {
    signed: Array.isArray(result.signed) ? result.signed.map((id) => String(id)) : [],
    errors: Array.isArray(result.errors) ? result.errors : [],
    raw: payload,
  };
}
