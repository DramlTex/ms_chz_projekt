/**
 * Вспомогательные функции для работы с датами.
 */

const FORMATTER_RU = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
});

const FORMATTER_RU_WITH_TIME = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

/**
 * Возвращает объект { from, to } для периода в годах.
 * @param {number} years Количество лет, которое нужно вычесть из текущей даты.
 */
export function defaultDateRange(years = 3) {
  const to = new Date();
  const from = new Date(to);
  from.setFullYear(from.getFullYear() - years);
  return { from, to };
}

/**
 * Преобразует дату к формату YYYY-MM-DD для полей типа date.
 */
export function toDateInputValue(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
    return '';
  }
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * Формирует строку для параметров API НК (YYYY-MM-DD HH:mm:ss).
 */
export function toApiDateTime(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
    return '';
  }
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

/**
 * Возвращает человекочитаемое представление даты.
 */
export function formatDisplayDate(value) {
  if (!value) {
    return '—';
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return FORMATTER_RU.format(date);
}

/**
 * Возвращает дату и время в формате DD.MM.YYYY HH:MM.
 */
export function formatDisplayDateTime(value) {
  if (!value) {
    return '—';
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return FORMATTER_RU_WITH_TIME.format(date);
}

/**
 * Парсит значение поля date в объект Date.
 */
export function parseDateInput(value) {
  if (!value) {
    return null;
  }
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return date;
}
