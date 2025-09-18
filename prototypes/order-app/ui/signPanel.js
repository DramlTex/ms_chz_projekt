import { sessionStore } from '../state/sessionStore.js';
import {
  fetchDraftsAwaitingSignature,
  fetchDocumentsForSignature,
  sendDetachedSignatures,
  SignatureServiceError,
} from '../services/signatureService.js';
import { signData } from '../services/cryptoProClient.js';
import { formatDisplayDateTime } from '../utils/datetime.js';

function setText(element, value) {
  if (!element) return;
  element.textContent = value ?? '';
}

function appendLog(element, message) {
  if (!element) return;
  const next = message ?? '';
  if (!element.textContent) {
    element.textContent = next;
  } else {
    element.textContent += `\n${next}`;
  }
  element.scrollTop = element.scrollHeight;
}

function clearLog(element) {
  if (element) {
    element.textContent = '';
  }
}

function isBase64Like(value) {
  if (typeof value !== 'string') return false;
  const normalized = value.replace(/\s+/g, '');
  return /^[0-9A-Za-z+/]+={0,2}$/.test(normalized);
}

function toBase64(value) {
  if (typeof value !== 'string') return '';
  if (isBase64Like(value)) {
    return value.replace(/\s+/g, '');
  }
  const encoder = new TextEncoder();
  const bytes = encoder.encode(value);
  let binary = '';
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary);
}

function formatStatus(details) {
  const unique = Array.from(new Set(details.filter(Boolean)));
  if (unique.length === 0) {
    return '—';
  }
  return unique.join(', ');
}

export function initSignPanel({
  table,
  tbody,
  wrapper,
  emptyState,
  selectAllCheckbox,
  refreshButton,
  clearButton,
  submitButton,
  statusElement,
  logElement,
  totalCounter,
  selectedCounter,
  notifier,
} = {}) {
  if (!table || !tbody || !wrapper || !selectAllCheckbox || !refreshButton || !submitButton) {
    throw new Error('Sign panel elements are required');
  }

  let drafts = [];
  const selectedIds = new Set();
  let loadingDrafts = false;
  let signing = false;
  let autoRequested = false;

  function updateCounters() {
    if (totalCounter) {
      totalCounter.textContent = String(drafts.length);
    }
    if (selectedCounter) {
      selectedCounter.textContent = String(selectedIds.size);
    }
  }

  function updateSelectAllState() {
    if (!selectAllCheckbox) return;
    const total = drafts.length;
    const selected = selectedIds.size;
    selectAllCheckbox.disabled = total === 0 || loadingDrafts || signing;
    selectAllCheckbox.checked = total > 0 && selected === total;
    selectAllCheckbox.indeterminate = selected > 0 && selected < total;
  }

  function canSignNow() {
    if (signing) return false;
    if (selectedIds.size === 0) return false;
    const pluginStatus = sessionStore.getPluginStatus();
    if (pluginStatus.status !== 'ready') return false;
    if (!sessionStore.getSelectedCertificate()) return false;
    return true;
  }

  function updateControls() {
    refreshButton.disabled = loadingDrafts || signing;
    if (clearButton) {
      clearButton.disabled = selectedIds.size === 0 || signing;
    }
    submitButton.disabled = !canSignNow();
    updateSelectAllState();
  }

  function renderDrafts() {
    tbody.innerHTML = '';
    if (!Array.isArray(drafts) || drafts.length === 0) {
      if (emptyState) emptyState.classList.remove('hidden');
      return;
    }
    if (emptyState) emptyState.classList.add('hidden');

    drafts.forEach((card, index) => {
      const row = document.createElement('tr');
      row.dataset.goodId = card.goodId;

      const checkboxCell = document.createElement('td');
      checkboxCell.className = 'cell-checkbox';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.dataset.cardId = card.goodId;
      checkbox.checked = selectedIds.has(card.goodId);
      checkbox.disabled = signing;
      checkbox.id = `sign-card-${index}`;
      checkbox.setAttribute('aria-label', `Карточка ${card.goodId}`);
      checkboxCell.appendChild(checkbox);

      const idCell = document.createElement('td');
      idCell.textContent = card.goodId;

      const gtinCell = document.createElement('td');
      gtinCell.textContent = card.gtin || '—';

      const nameCell = document.createElement('td');
      nameCell.textContent = card.name || '—';

      const statusCell = document.createElement('td');
      statusCell.textContent = formatStatus([card.status, ...card.statusDetails]);

      const updatedCell = document.createElement('td');
      updatedCell.textContent = formatDisplayDateTime(card.updatedAt);

      row.append(checkboxCell, idCell, gtinCell, nameCell, statusCell, updatedCell);
      tbody.appendChild(row);
    });
  }

  async function loadDrafts({ silent = false } = {}) {
    if (loadingDrafts) return;
    const credentials = sessionStore.getNationalCatalogCredentials();
    if (!credentials.apiKey && !credentials.bearerToken) {
      drafts = [];
      selectedIds.clear();
      renderDrafts();
      updateCounters();
      updateControls();
      if (!silent) {
        setText(statusElement, 'Укажите API-ключ или bearer-токен НК и сохраните их в блоке авторизации.');
      }
      return;
    }

    loadingDrafts = true;
    wrapper.classList.add('is-loading');
    setText(statusElement, 'Запрашиваем черновики, ожидающие подписи…');
    updateControls();

    const toDate = new Date();
    const fromDate = new Date(toDate);
    fromDate.setFullYear(fromDate.getFullYear() - 1);

    try {
      const result = await fetchDraftsAwaitingSignature({
        auth: credentials,
        fromDate,
        toDate,
      });
      drafts = result.items;
      selectedIds.clear();
      renderDrafts();
      updateCounters();
      const updated = formatDisplayDateTime(result.meta?.fetchedAt);
      if (drafts.length === 0) {
        setText(statusElement, 'Карточки, ожидающие подписи, не найдены.');
        if (!silent) {
          notifier?.info?.('Карточки, ожидающие подписи, не найдены.');
        }
      } else {
        setText(statusElement, `Найдено ${drafts.length} карточек. Обновлено: ${updated}.`);
        if (!silent) {
          notifier?.success?.('Список карточек для подписи обновлён.');
        }
      }
    } catch (error) {
      drafts = [];
      selectedIds.clear();
      renderDrafts();
      updateCounters();
      let message = 'Не удалось получить черновики для подписи.';
      if (error instanceof SignatureServiceError) {
        message = error.message;
      }
      setText(statusElement, message);
      notifier?.error?.('Черновики для подписи не загружены.');
      console.error(error);
    } finally {
      loadingDrafts = false;
      wrapper.classList.remove('is-loading');
      updateControls();
    }
  }

  async function signSelected() {
    if (!canSignNow()) {
      return;
    }
    const credentials = sessionStore.getNationalCatalogCredentials();
    if (!credentials.apiKey && !credentials.bearerToken) {
      setText(statusElement, 'Укажите API-ключ или bearer-токен НК.');
      notifier?.warning?.('Нет доступа к Национальному каталогу.');
      return;
    }
    const certificate = sessionStore.getSelectedCertificate();
    if (!certificate) {
      setText(statusElement, 'Выберите сертификат УКЭП перед подписью.');
      notifier?.warning?.('Не выбран сертификат УКЭП для подписи.');
      return;
    }

    const ids = drafts
      .map((item) => item.goodId)
      .filter((id) => selectedIds.has(id));
    if (ids.length === 0) {
      notifier?.warning?.('Нет выбранных карточек для подписи.');
      return;
    }

    signing = true;
    updateControls();
    setText(statusElement, 'Подготавливаем подпись…');
    clearLog(logElement);
    appendLog(logElement, '=== Новый запуск ===');

    const chunkSize = 25;
    const signedIds = new Set();
    let hasErrors = false;

    try {
      for (let start = 0; start < ids.length; start += chunkSize) {
        const chunkIds = ids.slice(start, start + chunkSize);
        setText(statusElement, `Получаем XML для подписи (${start + chunkIds.length}/${ids.length})…`);
        const documents = await fetchDocumentsForSignature({ goodIds: chunkIds, auth: credentials });
        const pack = [];
        const receivedIds = new Set(documents.documents.map((doc) => doc.goodId));
        chunkIds.forEach((goodId) => {
          if (!receivedIds.has(goodId)) {
            hasErrors = true;
            appendLog(logElement, `⚠️ ${goodId}: не удалось получить XML для подписи.`);
          }
        });

        for (const doc of documents.documents) {
          try {
            const xmlB64 = toBase64(doc.xml ?? '');
            if (!xmlB64) {
              hasErrors = true;
              appendLog(logElement, `⚠️ ${doc.goodId}: пустой XML.`);
              continue;
            }
            const signature = await signData({
              data: xmlB64,
              thumbprint: certificate.thumbprint,
              detached: true,
              encoding: 'base64',
            });
            pack.push({
              goodId: doc.goodId,
              base64Xml: xmlB64,
              signature,
            });
            appendLog(logElement, `✅ ${doc.goodId}`);
          } catch (error) {
            hasErrors = true;
            const message = error?.message ?? String(error);
            appendLog(logElement, `❌ ${doc.goodId}: ${message}`);
          }
        }

        if (pack.length === 0) {
          appendLog(logElement, '✋ Нет готовых подписей для отправки.');
          continue;
        }

        setText(statusElement, 'Отправляем пакет подписей в Национальный каталог…');
        try {
          const response = await sendDetachedSignatures({ pack, auth: credentials });
          if (response.signed.length > 0) {
            response.signed.forEach((id) => signedIds.add(String(id)));
            appendLog(logElement, `🌿 Подписано: ${response.signed.join(', ')}`);
          }
          if (Array.isArray(response.errors) && response.errors.length > 0) {
            hasErrors = true;
            appendLog(logElement, `⚠️ Ответ НК с ошибками: ${JSON.stringify(response.errors)}`);
          }
        } catch (error) {
          hasErrors = true;
          appendLog(logElement, `🔴 Ошибка отправки: ${error.message ?? error}`);
          throw error;
        }
      }

      if (signedIds.size > 0) {
        drafts = drafts.filter((item) => !signedIds.has(item.goodId));
        signedIds.forEach((id) => selectedIds.delete(id));
        renderDrafts();
        updateCounters();
        notifier?.success?.('Подписи отправлены в Национальный каталог.');
      }

      if (hasErrors) {
        setText(statusElement, 'Подпись выполнена с частичными ошибками. Проверьте лог.');
      } else {
        setText(statusElement, 'Все выбранные карточки подписаны и отправлены.');
      }
    } catch (error) {
      if (error instanceof SignatureServiceError) {
        setText(statusElement, error.message);
      } else {
        setText(statusElement, 'Не удалось отправить подпись. Попробуйте повторить позже.');
      }
      notifier?.error?.('Во время подписи произошла ошибка.');
      console.error(error);
    } finally {
      signing = false;
      updateControls();
    }
  }

  tbody.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (!target.dataset.cardId) {
      return;
    }
    const { cardId } = target.dataset;
    if (target.checked) {
      selectedIds.add(cardId);
    } else {
      selectedIds.delete(cardId);
    }
    updateCounters();
    updateControls();
  });

  selectAllCheckbox.addEventListener('change', (event) => {
    const { checked } = event.target;
    if (!Array.isArray(drafts) || drafts.length === 0) {
      return;
    }
    drafts.forEach((card) => {
      if (checked) {
        selectedIds.add(card.goodId);
      } else {
        selectedIds.delete(card.goodId);
      }
    });
    Array.from(tbody.querySelectorAll('input[type="checkbox"]')).forEach((input) => {
      input.checked = Boolean(checked);
    });
    updateCounters();
    updateControls();
  });

  refreshButton.addEventListener('click', () => {
    loadDrafts();
  });

  if (clearButton) {
    clearButton.addEventListener('click', () => {
      selectedIds.clear();
      Array.from(tbody.querySelectorAll('input[type="checkbox"]')).forEach((input) => {
        input.checked = false;
      });
      updateCounters();
      updateControls();
      setText(statusElement, 'Выбор карточек очищен.');
    });
  }

  submitButton.addEventListener('click', () => {
    signSelected();
  });

  sessionStore.addEventListener('certificate-selected', () => {
    updateControls();
  });

  sessionStore.addEventListener('plugin-status-changed', () => {
    updateControls();
  });

  sessionStore.addEventListener('national-catalog-credentials-changed', () => {
    const credentials = sessionStore.getNationalCatalogCredentials();
    if (credentials.apiKey || credentials.bearerToken) {
      if (!autoRequested) {
        autoRequested = true;
        loadDrafts({ silent: true });
      }
    } else {
      autoRequested = false;
      drafts = [];
      selectedIds.clear();
      renderDrafts();
      updateCounters();
      updateControls();
    }
  });

  updateCounters();
  updateControls();
  const credentials = sessionStore.getNationalCatalogCredentials();
  if (credentials.apiKey || credentials.bearerToken) {
    loadDrafts({ silent: true });
    autoRequested = true;
  }

  return {
    refresh: () => loadDrafts(),
    sign: () => signSelected(),
  };
}
