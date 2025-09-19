import { defaultDateRange, formatDisplayDate, formatDisplayDateTime } from './utils/datetime.js';
import { fetchProductCards, CatalogServiceError } from './services/catalogService.js';
import { orderStore } from './state/orderStore.js';
import { sessionStore } from './state/sessionStore.js';
import { initCatalogTable } from './ui/catalogTable.js';
import { initSelectionSummary } from './ui/selectionSummary.js';
import { initOrderModal } from './ui/orderModal.js';
import { initFilters } from './ui/filters.js';
import { createNotifier } from './ui/notifications.js';
import { initAuthPanel } from './ui/authPanel.js';

const DEFAULT_YEARS = 3;

document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('catalog-table');
  const tbody = document.getElementById('catalog-table-body');
  const emptyState = document.getElementById('catalog-empty-state');
  const sourceBadge = document.getElementById('catalog-source-badge');
  const totalCounter = document.getElementById('catalog-total-counter');
  const tableWrapper = document.querySelector('[data-role="table-wrapper"]');

  const authInitButton = document.getElementById('auth-init-plugin');
  const authPluginStatus = document.getElementById('auth-plugin-status');
  const authCertificateSelect = document.getElementById('auth-certificates');
  const authRefreshButton = document.getElementById('auth-refresh-certificates');
  const authRequestTokenButton = document.getElementById('auth-request-token');
  const authTokenStatus = document.getElementById('auth-token-status');
  const authApiKeyInput = document.getElementById('auth-nk-apikey');
  const authBearerInput = document.getElementById('auth-nk-bearer');
  const authSaveButton = document.getElementById('auth-save-nk');
  const authNkStatus = document.getElementById('auth-nk-status');
  const authSignaturePayload = document.getElementById('auth-signature-payload');
  const authSignatureOutput = document.getElementById('auth-signature-output');
  const authSignatureButton = document.getElementById('auth-signature-sign');
  const authSignatureStatus = document.getElementById('auth-signature-status');

  const counter = document.getElementById('selected-count');
  const selectionList = document.getElementById('selection-list');
  const openModalButton = document.getElementById('open-order-modal');

  const modalElement = document.getElementById('order-modal');
  const orderForm = document.getElementById('order-form');
  const closeModalButton = document.getElementById('order-modal-close');

  const statusElement = document.getElementById('catalog-request-status');
  const filterForm = document.getElementById('catalog-filter-form');
  const resetButton = document.getElementById('reset-filters');

  const notifier = createNotifier(document.getElementById('notification-stack'));

  initAuthPanel({
    initButton: authInitButton,
    pluginStatusElement: authPluginStatus,
    certificateSelect: authCertificateSelect,
    refreshButton: authRefreshButton,
    requestTokenButton: authRequestTokenButton,
    tokenStatusElement: authTokenStatus,
    nkApiKeyInput: authApiKeyInput,
    nkBearerInput: authBearerInput,
    nkSaveButton: authSaveButton,
    nkStatusElement: authNkStatus,
    signaturePayloadInput: authSignaturePayload,
    signatureOutput: authSignatureOutput,
    signatureButton: authSignatureButton,
    signatureStatusElement: authSignatureStatus,
    notifier,
  });

  initCatalogTable({
    table,
    tbody,
    emptyState,
    sourceBadge,
    totalCounter,
    wrapper: tableWrapper,
  });

  initSelectionSummary({
    counter,
    list: selectionList,
    openButton: openModalButton,
    notifier,
  });

  initOrderModal({
    modal: modalElement,
    form: orderForm,
    closeButton: closeModalButton,
    openButton: openModalButton,
    notifier,
  });

  const defaultFilters = getDefaultFilters();
  let filterController;

  filterController = initFilters({
    form: filterForm,
    resetButton,
    onSubmit: (rawFilters) => {
      const prepared = normalizeFilters(rawFilters);
      filterController.setValues(prepared);
      orderStore.setFilters(prepared);
      loadCards(prepared);
    },
    onReset: () => {
      const prepared = getDefaultFilters();
      filterController.setValues(prepared);
      orderStore.setFilters(prepared);
      loadCards(prepared);
    },
  });

  filterController.setValues(defaultFilters);
  orderStore.setFilters(defaultFilters);
  loadCards(defaultFilters, { silentFallback: true });

  sessionStore.addEventListener('national-catalog-credentials-changed', () => {
    const credentials = sessionStore.getNationalCatalogCredentials();
    if (!credentials.apiKey && !credentials.bearerToken) {
      orderStore.setCards([], {
        total: 0,
        source: '—',
        fallback: false,
      });
      statusElement.textContent = 'Укажите API-ключ или токен Национального каталога для загрузки карточек.';
      return;
    }
    loadCards(orderStore.getFilters(), { silentFallback: true });
  });

  async function loadCards(filters, { silentFallback = false } = {}) {
    const normalized = normalizeFilters(filters);
    const periodDescription = describeFilters(normalized);
    const credentials = sessionStore.getNationalCatalogCredentials();
    if (!credentials.apiKey && !credentials.bearerToken) {
      statusElement.textContent = 'Укажите API-ключ или токен Национального каталога в блоке авторизации.';
      orderStore.setCards([], {
        total: 0,
        source: '—',
        fallback: false,
      });
      return;
    }
    statusElement.textContent = `Запрашиваем карточки ${periodDescription}…`;
    orderStore.setLoading(true);
    try {
      const result = await fetchProductCards({
        fromDate: normalized.fromDate,
        toDate: normalized.toDate,
        search: normalized.search,
        auth: credentials,
      });
      orderStore.setCards(result.cards, {
        total: result.pagination.total,
        rawTotal: result.meta.rawTotal,
        source: result.meta.source,
        fetchedAt: result.meta.fetchedAt,
      });
      const summary = [`Получено ${result.pagination.total} карточек`];
      if (result.meta.filteredBy) {
        summary.push(`по фильтру "${result.meta.filteredBy}"`);
      }
      summary.push(`обновлено: ${formatDisplayDateTime(result.meta.fetchedAt)}`);
      if (!silentFallback) {
        notifier.success(`Карточки обновлены. Найдено ${result.pagination.total} позиций.`);
      }
      statusElement.textContent = summary.join('. ');
    } catch (error) {
      orderStore.setCards([], {
        total: 0,
        rawTotal: 0,
        source: '—',
        fallback: false,
      });
      if (error instanceof CatalogServiceError) {
        statusElement.textContent = error.message;
        if (error.type === 'auth-required') {
          notifier.warning('Нужен API-ключ или bearer-токен Национального каталога.');
        } else {
          notifier.error('Сервис Национального каталога вернул ошибку. Проверьте параметры или повторите позже.');
        }
      } else {
        statusElement.textContent = 'Не удалось получить карточки. Проверьте соединение или попробуйте снова.';
        notifier.error('Карточки не загружены: ошибка сети.');
      }
    } finally {
      orderStore.setLoading(false);
    }
  }
});

function getDefaultFilters() {
  const range = defaultDateRange(DEFAULT_YEARS);
  return {
    fromDate: range.from,
    toDate: range.to,
    search: '',
  };
}

function normalizeFilters(raw = {}) {
  const now = new Date();
  const toDate = raw.toDate instanceof Date && !Number.isNaN(raw.toDate.getTime()) ? raw.toDate : now;
  const fromCandidate = raw.fromDate instanceof Date && !Number.isNaN(raw.fromDate.getTime())
    ? raw.fromDate
    : new Date(toDate);
  if (!(raw.fromDate instanceof Date)) {
    fromCandidate.setFullYear(fromCandidate.getFullYear() - DEFAULT_YEARS);
  }
  let fromDate = fromCandidate;
  let finalToDate = toDate;
  if (fromDate > finalToDate) {
    [fromDate, finalToDate] = [finalToDate, fromDate];
  }
  return {
    fromDate,
    toDate: finalToDate,
    search: raw.search ?? '',
  };
}

function describeFilters(filters) {
  const from = formatDisplayDate(filters.fromDate);
  const to = formatDisplayDate(filters.toDate);
  if (from && to && from !== '—' && to !== '—') {
    return `за период ${from} — ${to}`;
  }
  if (to && to !== '—') {
    return `по дату ${to}`;
  }
  return 'за последние три года';
}
