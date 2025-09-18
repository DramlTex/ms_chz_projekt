import { orderStore } from '../state/orderStore.js';

/**
 * Блок с краткой информацией о выбранных карточках.
 */
export function initSelectionSummary({ counter, list, openButton, notifier }) {
  if (!counter || !list || !openButton) {
    throw new Error('Selection summary elements are required');
  }

  orderStore.addEventListener('selection-changed', ({ detail }) => {
    const { selected, totalSelected } = detail;
    counter.textContent = String(totalSelected);
    renderPreview(list, selected);
    openButton.disabled = totalSelected === 0;
    openButton.setAttribute('aria-disabled', String(totalSelected === 0));
  });

  openButton.addEventListener('click', () => {
    if (orderStore.getSelectionSize() === 0) {
      notifier?.warning('Выберите хотя бы одну карточку, чтобы оформить заказ.');
    }
  });
}

function renderPreview(list, selected) {
  list.innerHTML = '';
  if (!Array.isArray(selected) || selected.length === 0) {
    const item = document.createElement('li');
    item.textContent = 'Карточки не выбраны.';
    list.appendChild(item);
    return;
  }

  const preview = selected.slice(0, 3);
  preview.forEach((card) => {
    const item = document.createElement('li');
    const gtin = card.gtin ? `${card.gtin}` : 'GTIN не указан';
    item.textContent = `${gtin} — ${card.name}`;
    list.appendChild(item);
  });

  if (selected.length > preview.length) {
    const moreItem = document.createElement('li');
    moreItem.textContent = `+ ещё ${selected.length - preview.length}`;
    list.appendChild(moreItem);
  }
}
