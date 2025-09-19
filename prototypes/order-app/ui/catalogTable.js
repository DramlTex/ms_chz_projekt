import { orderStore } from '../state/orderStore.js';
import { formatDisplayDateTime } from '../utils/datetime.js';

/**
 * Инициализация таблицы карточек НК.
 */
export function initCatalogTable({
  table,
  tbody,
  emptyState,
  sourceBadge,
  totalCounter,
  wrapper,
}) {
  if (!table || !tbody) {
    throw new Error('Table elements are required');
  }
  const selectAll = table.querySelector('[data-role="select-all"]');

  table.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.dataset.cardId) {
      orderStore.toggleSelection(target.dataset.cardId);
    }
    if (target === selectAll) {
      const cardIds = orderStore.getCards().map((card) => card.goodId);
      orderStore.setBulkSelection(cardIds, target.checked);
    }
  });

  orderStore.addEventListener('cards-changed', ({ detail }) => {
    renderRows(detail.cards, tbody);
    updateEmptyState(detail.cards, emptyState, table);
    updateSelectAll(selectAll, detail.cards);
    updateSource(sourceBadge, detail.meta);
    updateTotal(totalCounter, detail);
  });

  orderStore.addEventListener('selection-changed', ({ detail }) => {
    syncSelection(detail.selected, tbody);
    updateSelectAll(selectAll, orderStore.getCards());
  });

  orderStore.addEventListener('loading-changed', ({ detail }) => {
    if (wrapper) {
      wrapper.classList.toggle('is-loading', detail.loading);
    }
    table.setAttribute('aria-busy', String(detail.loading));
  });
}

function renderRows(cards, tbody) {
  tbody.innerHTML = '';
  cards.forEach((card) => {
    const row = document.createElement('tr');
    row.dataset.cardId = card.goodId;

    const selectCell = document.createElement('td');
    selectCell.className = 'cell-checkbox';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.dataset.cardId = card.goodId;
    checkbox.checked = orderStore.isSelected(card.goodId);
    checkbox.setAttribute('aria-label', `Выбрать карточку ${card.gtin || card.name}`);
    selectCell.appendChild(checkbox);

    const gtinCell = document.createElement('td');
    if (card.link) {
      const link = document.createElement('a');
      link.href = card.link;
      link.textContent = card.gtin || '—';
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      gtinCell.appendChild(link);
    } else {
      gtinCell.textContent = card.gtin || '—';
    }

    const nameCell = document.createElement('td');
    nameCell.textContent = card.name || '—';

    const brandCell = document.createElement('td');
    brandCell.textContent = card.brand || '—';

    const statusCell = document.createElement('td');
    statusCell.textContent = formatStatus(card.status, card.statusDetails);

    const updatedCell = document.createElement('td');
    updatedCell.textContent = card.updatedAt ? formatDisplayDateTime(card.updatedAt) : '—';

    row.append(selectCell, gtinCell, nameCell, brandCell, statusCell, updatedCell);
    tbody.appendChild(row);
  });
}

function formatStatus(status, details) {
  if (!status) return '—';
  if (!Array.isArray(details) || details.length === 0) {
    return status;
  }
  return `${status} (${details.join(', ')})`;
}

function updateEmptyState(cards, emptyState, table) {
  if (!emptyState) return;
  if (cards.length === 0) {
    emptyState.classList.remove('hidden');
    table.classList.add('hidden');
  } else {
    emptyState.classList.add('hidden');
    table.classList.remove('hidden');
  }
}

function syncSelection(selected, tbody) {
  const selectedIds = new Set(selected.map((card) => card.goodId));
  tbody.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
    const id = checkbox.dataset.cardId;
    checkbox.checked = selectedIds.has(id ?? '');
  });
}

function updateSelectAll(selectAll, cards) {
  if (!selectAll) return;
  const total = cards.length;
  const selected = orderStore.getSelectionSize();
  selectAll.checked = total > 0 && selected === total;
  selectAll.indeterminate = selected > 0 && selected < total;
}

function updateSource(sourceBadge, meta) {
  if (!sourceBadge) return;
  if (meta.fallback) {
    sourceBadge.textContent = 'Источник: демо-данные (без API ключа)';
  } else if (meta.source === 'nk-sandbox') {
    sourceBadge.textContent = 'Источник: НК (песочница)';
  } else if (meta.source) {
    sourceBadge.textContent = `Источник: ${meta.source}`;
  } else {
    sourceBadge.textContent = 'Источник: —';
  }
}

function updateTotal(totalCounter, detail) {
  if (!totalCounter) return;
  const total = detail.total ?? 0;
  const raw = detail.meta?.rawTotal ?? total;
  if (raw !== total) {
    totalCounter.textContent = `Найдено ${total} карточек (из ${raw} по запросу)`;
  } else {
    totalCounter.textContent = `Найдено ${total} карточек`;
  }
}
