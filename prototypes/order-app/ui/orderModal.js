import { orderStore } from '../state/orderStore.js';
import { submitOrder } from '../services/orderService.js';
import { parseDateInput, formatDisplayDateTime } from '../utils/datetime.js';

/**
 * Модальное окно оформления заказа КМ.
 */
export function initOrderModal({ modal, form, closeButton, openButton, notifier }) {
  if (!modal || !form || !closeButton || !openButton) {
    throw new Error('Order modal elements are required');
  }

  const selectionCounter = modal.querySelector('#order-modal-selection-counter');
  const tableBody = modal.querySelector('#order-modal-table-body');
  const emptyState = modal.querySelector('#order-modal-empty');
  const statusElement = modal.querySelector('#order-modal-status');
  const resultSection = modal.querySelector('#order-modal-result');
  const clearButton = modal.querySelector('#order-clear');
  const submitButton = modal.querySelector('#order-submit');

  let quantities = new Map();
  let isSubmitting = false;

  orderStore.addEventListener('selection-changed', ({ detail }) => {
    const { selected } = detail;
    const nextQuantities = new Map();
    selected.forEach((card) => {
      const existing = quantities.get(card.goodId) ?? 100;
      nextQuantities.set(card.goodId, existing);
    });
    quantities = nextQuantities;
    renderSelectedCards(selected, tableBody, emptyState, quantities);
    selectionCounter.textContent = `${selected.length} позиций`;
    if (selected.length === 0) {
      hideModal();
    }
  });

  tableBody.addEventListener('input', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.dataset.cardId) {
      const value = Number.parseInt(target.value, 10);
      quantities.set(target.dataset.cardId, Number.isFinite(value) && value > 0 ? value : 0);
    }
  });

  openButton.addEventListener('click', () => {
    if (orderStore.getSelectionSize() === 0) {
      notifier?.warning('Сначала выберите карточки в таблице.');
      return;
    }
    statusElement.textContent = '';
    resultSection.classList.add('hidden');
    showModal();
    const firstInput = tableBody.querySelector('input[type="number"]');
    if (firstInput) {
      firstInput.focus();
    } else {
      form.querySelector('input[name="scenario"]').focus();
    }
  });

  closeButton.addEventListener('click', () => {
    hideModal();
    openButton.focus();
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal && !isSubmitting) {
      hideModal();
    }
  });

  clearButton.addEventListener('click', () => {
    orderStore.clearSelection();
    quantities.clear();
    statusElement.textContent = 'Выбор очищен.';
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (orderStore.getSelectionSize() === 0) {
      notifier?.warning('Нет выбранных карточек для заказа.');
      return;
    }

    const payload = collectPayload(form, quantities);
    const invalidEntries = Object.entries(payload.quantities).filter(([, qty]) => !Number.isFinite(qty) || qty <= 0);
    if (invalidEntries.length > 0) {
      statusElement.textContent = 'Проверьте количество КМ для всех карточек (минимум 1).';
      return;
    }

    try {
      setSubmitting(true);
      statusElement.textContent = 'Отправляем заказ в True API и СУЗ…';
      const result = await submitOrder({
        selectedCards: orderStore.getSelectedCards(),
        payload,
      });
      statusElement.textContent = `Заказ ${result.orderId} сформирован.`;
      renderResult(resultSection, result);
      resultSection.classList.remove('hidden');
      notifier?.success(`Заказ ${result.orderId} готов. Проверьте таймлайн выполнения.`);
      orderStore.clearSelection();
      quantities.clear();
    } catch (error) {
      statusElement.textContent = 'Не удалось оформить заказ. Попробуйте повторить позже.';
      notifier?.error('Ошибка при создании заказа.');
      console.error(error);
    } finally {
      setSubmitting(false);
    }
  });

  function setSubmitting(value) {
    isSubmitting = value;
    submitButton.disabled = value;
    clearButton.disabled = value;
    form.querySelectorAll('input, select, textarea, button').forEach((element) => {
      if (element !== closeButton) {
        element.toggleAttribute('aria-disabled', value);
      }
    });
  }

  function collectPayload(formElement, quantityMap) {
    const data = new FormData(formElement);
    const deadlineInput = data.get('deadline')?.toString() ?? '';
    const deadline = parseDateInput(deadlineInput);
    const quantitiesObject = {};
    quantityMap.forEach((value, key) => {
      quantitiesObject[key] = value;
    });
    return {
      scenario: data.get('scenario')?.toString() ?? 'printing',
      packaging: data.get('packaging')?.toString() ?? 'unit',
      deadline,
      comment: data.get('comment')?.toString().trim() ?? '',
      quantities: quantitiesObject,
    };
  }

  function hideModal() {
    hideModalElement(modal);
  }

  function showModal() {
    showModalElement(modal);
  }

  return {
    close() {
      hideModal();
    },
  };
}

function hideModalElement(modal) {
  modal.classList.add('hidden');
  document.body.classList.remove('modal-open');
}

function showModalElement(modal) {
  modal.classList.remove('hidden');
  document.body.classList.add('modal-open');
}

function renderSelectedCards(selected, tbody, emptyState, quantities) {
  tbody.innerHTML = '';
  if (!Array.isArray(selected) || selected.length === 0) {
    emptyState.classList.remove('hidden');
    return;
  }
  emptyState.classList.add('hidden');
  selected.forEach((card) => {
    const row = document.createElement('tr');
    row.dataset.cardId = card.goodId;

    const gtinCell = document.createElement('td');
    gtinCell.textContent = card.gtin || '—';

    const nameCell = document.createElement('td');
    nameCell.textContent = card.name || '—';

    const statusCell = document.createElement('td');
    statusCell.textContent = card.status || '—';

    const quantityCell = document.createElement('td');
    const input = document.createElement('input');
    input.type = 'number';
    input.min = '1';
    input.step = '1';
    input.required = true;
    input.dataset.cardId = card.goodId;
    input.value = String(quantities.get(card.goodId) ?? 100);
    quantityCell.appendChild(input);

    row.append(gtinCell, nameCell, statusCell, quantityCell);
    tbody.appendChild(row);
  });
}

function renderResult(container, result) {
  container.innerHTML = '';
  const heading = document.createElement('h3');
  heading.textContent = `Заказ ${result.orderId}`;

  const summary = document.createElement('ul');
  summary.className = 'modal__result-list';

  const items = [
    `Сценарий: ${describeScenario(result.scenario)}`,
    `Тип упаковки: ${describePackaging(result.packaging)}`,
    `Карточек: ${result.totalCards}`,
    `Всего КМ: ${result.totalQuantity}`,
  ];
  if (result.deadline) {
    items.push(`Плановая дата: ${formatDisplayDateTime(result.deadline)}`);
  }
  if (result.comment) {
    items.push(`Комментарий: ${result.comment}`);
  }

  items.forEach((text) => {
    const li = document.createElement('li');
    li.textContent = text;
    summary.appendChild(li);
  });

  const timeline = document.createElement('ol');
  timeline.className = 'modal__result-list';
  result.workflow.forEach((step) => {
    const li = document.createElement('li');
    li.innerHTML = `<strong>${step.title}</strong> — ${step.description} (${step.formatted})`;
    timeline.appendChild(li);
  });

  container.append(heading, summary, timeline);
}

function describeScenario(value) {
  switch (value) {
    case 'printing':
      return 'Печать';
    case 'introduction':
      return 'Ввод в оборот';
    case 'import':
      return 'Импорт';
    default:
      return value;
  }
}

function describePackaging(value) {
  switch (value) {
    case 'unit':
      return 'Штучная';
    case 'group':
      return 'Групповая';
    case 'transport':
      return 'Транспортная';
    default:
      return value;
  }
}

