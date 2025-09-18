import { toApiDateTime } from '../utils/datetime.js';

const NK_SANDBOX_BASE = 'https://api.nk.sandbox.crptech.ru';
const PRODUCT_LIST_ENDPOINT = '/v4/product-list';
const DEMO_DATA_URL = new URL('../data/demo-cards.json', import.meta.url);

/**
 * Ошибка запросов Национального каталога.
 */
export class CatalogServiceError extends Error {
  constructor(message, { type = 'generic', cause } = {}) {
    super(message);
    this.name = 'CatalogServiceError';
    this.type = type;
    this.cause = cause;
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

async function loadDemoCards() {
  const response = await fetch(DEMO_DATA_URL);
  if (!response.ok) {
    throw new CatalogServiceError('Не удалось загрузить демонстрационные карточки', {
      type: 'demo-unavailable',
      cause: response,
    });
  }
  const payload = await response.json();
  return normalizeResponse(payload);
}

/**
 * Запрашивает список карточек в НК. При отсутствии ключа возвращает демо-данные.
 * @param {{ fromDate?: Date | null; toDate?: Date | null; search?: string; limit?: number; offset?: number; apiKey?: string }} params
 */
export async function fetchProductCards(params = {}) {
  const {
    fromDate = null,
    toDate = null,
    search = '',
    limit = 50,
    offset = 0,
    apiKey,
  } = params;

  const query = new URLSearchParams();
  if (limit) query.set('limit', String(limit));
  if (offset) query.set('offset', String(offset));
  if (fromDate) query.set('from_date', toApiDateTime(fromDate));
  if (toDate) query.set('to_date', toApiDateTime(toDate));

  const headers = new Headers({ Accept: 'application/json' });
  if (apiKey) {
    headers.set('apikey', apiKey);
  }

  const url = `${NK_SANDBOX_BASE}${PRODUCT_LIST_ENDPOINT}?${query.toString()}`;
  try {
    const response = await fetch(url, { headers });
    if (response.ok) {
      const raw = await response.json();
      const normalized = normalizeResponse(raw);
      return finalizeResult(normalized, { search, fallback: false, source: 'nk-sandbox' });
    }

    if (response.status === 401 || response.status === 403) {
      const demo = await loadDemoCards();
      return finalizeResult(demo, { search, fallback: true, source: 'demo' });
    }

    const text = await response.text();
    throw new CatalogServiceError(`Ошибка НК: ${response.status}`, {
      type: 'http',
      cause: text,
    });
  } catch (error) {
    if (error instanceof CatalogServiceError) {
      throw error;
    }
    const demo = await loadDemoCards();
    return finalizeResult(demo, { search, fallback: true, source: 'demo' });
  }
}

function finalizeResult(normalized, { search, fallback, source }) {
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
      fallback,
      rawTotal: normalized.total,
      filteredBy: trimmed || null,
      fetchedAt: new Date(),
    },
  };
}
