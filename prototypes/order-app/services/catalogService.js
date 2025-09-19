import { toApiDateTime } from '../utils/datetime.js';
import { getNationalCatalogBaseUrl } from '../config.js';

const PRODUCT_LIST_ENDPOINT = '/v4/product-list';

/**
 * Ошибка запросов Национального каталога.
 */
export class CatalogServiceError extends Error {
  constructor(message, { type = 'generic', cause, status } = {}) {
    super(message);
    this.name = 'CatalogServiceError';
    this.type = type;
    this.cause = cause;
    this.status = status;
  }
}

function mapCard(item) {
  const toDate = item.to_date ?? item.updated_at ?? item.last_update ?? item.updatedAt;
  const detailed = Array.isArray(item.good_detailed_status)
    ? item.good_detailed_status
    : Array.isArray(item.statusDetails)
      ? item.statusDetails
      : [];
  return {
    goodId: String(item.good_id ?? item.goodId ?? item.id ?? ''),
    gtin: item.gtin ?? item.gtin13 ?? '',
    name: item.good_name ?? item.name ?? item.title ?? '',
    brand: item.brand_name ?? item.brand ?? '',
    tnved: item.tnved ?? item.hs_code ?? '',
    status: item.good_status ?? item.status ?? '',
    statusDetails: detailed,
    updatedAt: toDate ?? null,
    link: item.good_url ?? item.url ?? null,
  };
}

function normalizeResponse(raw) {
  if (!raw) {
    return { cards: [], total: 0, limit: 0, offset: 0 };
  }

  if (Array.isArray(raw.cards)) {
    return {
      cards: raw.cards.map(mapCard),
      total: raw.cards.length,
      limit: raw.cards.length,
      offset: 0,
    };
  }

  const goods = raw?.result?.goods ?? [];
  return {
    cards: goods.map(mapCard),
    total: raw?.result?.total ?? goods.length,
    limit: raw?.result?.limit ?? goods.length,
    offset: raw?.result?.offset ?? 0,
  };
}

/**
 * Запрашивает список карточек в НК (промышленный контур).
 * @param {{ fromDate?: Date | null; toDate?: Date | null; search?: string; limit?: number; offset?: number; auth?: { apiKey?: string; bearerToken?: string } }} params
 */
export async function fetchProductCards(params = {}) {
  const {
    fromDate = null,
    toDate = null,
    search = '',
    limit = 50,
    offset = 0,
    auth = {},
  } = params;

  const baseUrl = getNationalCatalogBaseUrl();
  if (!baseUrl) {
    throw new CatalogServiceError('Не указан базовый URL Национального каталога', {
      type: 'configuration',
    });
  }

  const apiKey = auth.apiKey?.trim() ?? '';
  const bearerToken = auth.bearerToken?.trim() ?? '';
  if (!apiKey && !bearerToken) {
    throw new CatalogServiceError('Требуется API-ключ или bearer-токен Национального каталога.', {
      type: 'auth-required',
    });
  }

  const query = new URLSearchParams();
  if (limit) query.set('limit', String(limit));
  if (offset) query.set('offset', String(offset));
  if (fromDate) query.set('from_date', toApiDateTime(fromDate));
  if (toDate) query.set('to_date', toApiDateTime(toDate));
  if (apiKey) query.set('apikey', apiKey);
  if (!apiKey && bearerToken) {
    query.set('token', bearerToken);
  }

  const headers = new Headers({ Accept: 'application/json' });
  if (bearerToken) {
    const value = bearerToken.startsWith('Bearer ') ? bearerToken : `Bearer ${bearerToken}`;
    headers.set('Authorization', value);
  }

  const url = `${baseUrl}${PRODUCT_LIST_ENDPOINT}?${query.toString()}`;
  try {
    const response = await fetch(url, { headers });
    if (response.ok) {
      const raw = await response.json();
      const normalized = normalizeResponse(raw);
      return finalizeResult(normalized, { search, source: 'nk-prod' });
    }
    const text = await response.text();
    if (response.status === 401 || response.status === 403) {
      throw new CatalogServiceError('Национальный каталог отклонил запрос: проверьте ключ или bearer-токен.', {
        type: 'auth-required',
        status: response.status,
        cause: text,
      });
    }
    throw new CatalogServiceError(`Ошибка НК: ${response.status}`, {
      type: 'http',
      cause: text,
      status: response.status,
    });
  } catch (error) {
    if (error instanceof CatalogServiceError) {
      throw error;
    }
    throw new CatalogServiceError('Не удалось выполнить запрос к Национальному каталогу', {
      type: 'network',
      cause: error,
    });
  }
}

function finalizeResult(normalized, { search, source }) {
  const trimmed = search?.trim()?.toLowerCase() ?? '';
  let cards = normalized.cards;
  if (trimmed) {
    cards = cards.filter((card) => {
      const haystack = [card.gtin, card.name, card.brand]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      return haystack.includes(trimmed);
    });
  }

  return {
    cards,
    pagination: {
      total: cards.length,
      limit: normalized.limit,
      offset: normalized.offset,
    },
    meta: {
      source,
      rawTotal: normalized.total,
      filteredBy: trimmed || null,
      fetchedAt: new Date(),
    },
  };
}
