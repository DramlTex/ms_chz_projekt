import { parseDateInput, toDateInputValue } from '../utils/datetime.js';

/**
 * Настройка формы фильтрации карточек.
 */
export function initFilters({ form, resetButton, onSubmit, onReset }) {
  if (!form) {
    throw new Error('Filter form is required');
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const data = new FormData(form);
    const filters = {
      fromDate: parseDateInput(data.get('from')), 
      toDate: parseDateInput(data.get('to')),
      search: data.get('search')?.toString().trim() ?? '',
    };
    onSubmit?.(filters);
  });

  if (resetButton) {
    resetButton.addEventListener('click', () => {
      form.reset();
      onReset?.();
    });
  }

  return {
    setValues(values = {}) {
      if (Object.prototype.hasOwnProperty.call(values, 'fromDate')) {
        form.elements.from.value = toDateInputValue(values.fromDate);
      }
      if (Object.prototype.hasOwnProperty.call(values, 'toDate')) {
        form.elements.to.value = toDateInputValue(values.toDate);
      }
      if (Object.prototype.hasOwnProperty.call(values, 'search')) {
        form.elements.search.value = values.search ?? '';
      }
    },
  };
}
