import {
  fetchCardsAwaitingSignature,
  fetchDocumentsForSignature,
  sendDetachedSignatures,
  NationalCatalogSigningError,
} from '../services/signingService.js';
import { sessionStore } from '../state/sessionStore.js';
import { signData } from '../services/cryptoProClient.js';
import { formatDisplayDateTime } from '../utils/datetime.js';

function isBase64(value) {
  if (!value) return false;
  const trimmed = value.replace(/\s+/g, '');
  if (trimmed.length === 0) return false;
  return /^[0-9A-Za-z+/]+={0,2}$/.test(trimmed);
}

function encodeBase64(text) {
  if (!text) return '';
  if (typeof text !== 'string') {
    return encodeBase64(String(text));
  }
  if (typeof window !== 'undefined' && typeof window.TextEncoder === 'function') {
    const encoder = new window.TextEncoder();
    const bytes = encoder.encode(text);
    let binary = '';
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });
    if (typeof window.btoa === 'function') {
      return window.btoa(binary);
    }
  }
  if (typeof globalThis !== 'undefined' && typeof globalThis.Buffer !== 'undefined') {
    return Buffer.from(text, 'utf-8').toString('base64');
  }
  throw new Error('В окружении недоступно кодирование Base64.');
}

function ensureBase64Xml(input) {
  const value = typeof input === 'string' ? input.trim() : String(input ?? '');
  if (isBase64(value)) {
    return value.replace(/\s+/g, '');
  }
  return encodeBase64(value);
}

function renderStatusDetails(details) {
  if (!Array.isArray(details) || details.length === 0) {
    return '—';
  }
  return details.join(', ');
}

export function initSigningPanel(elements) {
  const {
    tableBody,
    tableWrapper,
    emptyState,
    selectAllCheckbox,
    selectedCounter,
    reloadButton,
    signButton,
    statusElement,
    resultContainer,
    notifier,
  } = elements;

  if (!tableBody || !emptyState || !selectAllCheckbox || !selectedCounter || !reloadButton || !signButton || !statusElement || !resultContainer) {
    throw new Error('Signing panel elements are required.');
  }

  let cards = [];
  let selectedIds = new Set();
  let loading = false;
  let signing = false;
  let hasCredentials = false;
  let pluginReady = sessionStore.getPluginStatus().status === 'ready';
  let selectedCertificate = sessionStore.getSelectedCertificate();

  function updateSelectedCounter() {
    selectedCounter.textContent = String(selectedIds.size);
  }

  function setStatus(message) {
    statusElement.textContent = message ?? '';
  }

  function toggleResult(visible) {
    resultContainer.classList.toggle('hidden', !visible);
    if (!visible) {
      resultContainer.innerHTML = '';
    }
  }

  function setTableLoading(state) {
    tableWrapper?.classList.toggle('is-loading', state);
  }

  function updateControls() {
    reloadButton.disabled = loading || signing || !hasCredentials;
    selectAllCheckbox.disabled = loading || signing || cards.length === 0;
    signButton.disabled =
      signing ||
      loading ||
      !pluginReady ||
      !selectedCertificate ||
      selectedIds.size === 0 ||
      !hasCredentials;
    tableBody.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
      checkbox.disabled = loading || signing;
    });
  }

  function renderCards(list) {
    tableBody.innerHTML = '';
    selectedIds = new Set();
    selectAllCheckbox.checked = false;
    if (!Array.isArray(list) || list.length === 0) {
      emptyState.classList.remove('hidden');
      updateSelectedCounter();
      updateControls();
      return;
    }
    emptyState.classList.add('hidden');
    list.forEach((card) => {
      const row = document.createElement('tr');
      row.dataset.cardId = card.goodId;

      const selectCell = document.createElement('td');
      selectCell.className = 'cell-checkbox';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.value = card.goodId;
      checkbox.disabled = loading || signing;
      selectCell.appendChild(checkbox);

      const idCell = document.createElement('td');
      idCell.textContent = card.goodId || '—';

      const nameCell = document.createElement('td');
      nameCell.textContent = card.name || '—';

      const statusCell = document.createElement('td');
      statusCell.textContent = renderStatusDetails(card.statusDetails);

      const updatedCell = document.createElement('td');
      updatedCell.textContent = card.updatedAt ? formatDisplayDateTime(new Date(card.updatedAt)) : '—';

      row.append(selectCell, idCell, nameCell, statusCell, updatedCell);
      tableBody.appendChild(row);
    });
    updateSelectedCounter();
    updateControls();
  }

  async function loadCards({ silent = false } = {}) {
    const credentials = sessionStore.getNationalCatalogCredentials();
    hasCredentials = Boolean(credentials.apiKey || credentials.bearerToken);
    if (!hasCredentials) {
      cards = [];
      renderCards(cards);
      setStatus('Укажите API-ключ или bearer-токен Национального каталога, чтобы увидеть черновики.');
      updateControls();
      return;
    }
    loading = true;
    toggleResult(false);
    setTableLoading(true);
    updateControls();
    try {
      const rangeEnd = new Date();
      const rangeStart = new Date(rangeEnd);
      rangeStart.setFullYear(rangeStart.getFullYear() - 1);
      if (!silent) {
        setStatus('Запрашиваем черновики, ожидающие подписи…');
      }
      const response = await fetchCardsAwaitingSignature({
        auth: credentials,
        fromDate: rangeStart,
        toDate: rangeEnd,
        limit: 250,
      });
      cards = response.cards;
      renderCards(cards);
      if (cards.length === 0) {
        setStatus('Нет карточек, ожидающих подписи.');
      } else {
        setStatus(`Найдено ${cards.length} карточек для подписи. Выберите до 25 и запустите подпись.`);
        if (!silent) {
          notifier?.success(`Список для подписи обновлён. Доступно карточек: ${cards.length}.`);
        }
      }
    } catch (error) {
      cards = [];
      renderCards(cards);
      if (error instanceof NationalCatalogSigningError) {
        setStatus(error.message);
        if (error.type === 'auth-required') {
          notifier?.warning('Нужен API-ключ или bearer-токен Национального каталога.');
        } else if (!silent) {
          notifier?.error('Национальный каталог вернул ошибку при загрузке черновиков.');
        }
      } else {
        setStatus('Не удалось загрузить список карточек для подписи.');
        if (!silent) {
          notifier?.error('Ошибка сети при загрузке черновиков для подписи.');
        }
      }
      console.error(error);
    } finally {
      loading = false;
      setTableLoading(false);
      updateControls();
    }
  }

  async function signSelected() {
    if (signing) return;
    const credentials = sessionStore.getNationalCatalogCredentials();
    hasCredentials = Boolean(credentials.apiKey || credentials.bearerToken);
    if (!hasCredentials) {
      notifier?.warning('Добавьте доступ к Национальному каталогу перед подписью.');
      setStatus('Укажите API-ключ или bearer-токен Национального каталога.');
      return;
    }
    if (!pluginReady) {
      notifier?.warning('Сначала подключите CryptoPro в блоке авторизации.');
      setStatus('CryptoPro не подключен. Подключите плагин и выберите сертификат.');
      return;
    }
    if (!selectedCertificate) {
      notifier?.warning('Выберите сертификат УКЭП перед подписью.');
      setStatus('Не выбран сертификат УКЭП.');
      return;
    }
    const ids = Array.from(selectedIds);
    if (ids.length === 0) {
      notifier?.warning('Выберите хотя бы одну карточку для подписи.');
      return;
    }
    if (ids.length > 25) {
      notifier?.warning('За один раз можно подписать не более 25 карточек.');
      setStatus('Выберите не более 25 карточек для подписи.');
      return;
    }

    signing = true;
    toggleResult(false);
    updateControls();
    setStatus('Запрашиваем XML карточек для подписи…');
    try {
      const documents = await fetchDocumentsForSignature({
        goodIds: ids,
        auth: credentials,
      });
      if (!Array.isArray(documents) || documents.length === 0) {
        setStatus('Национальный каталог не вернул документы для подписи.');
        notifier?.warning('Нет документов для подписи.');
        return;
      }
      const signPack = [];
      const localErrors = [];
      for (const doc of documents) {
        const goodId = String(doc.goodId ?? '');
        if (!goodId) {
          localErrors.push({ goodId: '', message: 'Не удалось определить идентификатор карточки.' });
          continue;
        }
        setStatus(`Подписываем карточку ${goodId}…`);
        try {
          const xmlBase64 = ensureBase64Xml(doc.xml ?? '');
          if (!xmlBase64) {
            throw new Error('Отсутствуют данные XML для подписи.');
          }
          const signature = await signData({
            data: xmlBase64,
            thumbprint: selectedCertificate.thumbprint,
            detached: true,
            encoding: 'base64',
          });
          signPack.push({
            goodId,
            base64Xml: xmlBase64,
            signature,
          });
        } catch (error) {
          localErrors.push({
            goodId,
            message: error?.message ?? 'Не удалось подписать XML.',
          });
          console.error('sign error', error);
        }
      }

      if (signPack.length === 0) {
        setStatus('Не удалось сформировать подпись ни для одной карточки.');
        renderResult({ signed: [], errors: localErrors });
        notifier?.error('Подпись не сформирована. Проверьте сертификат и повторите попытку.');
        return;
      }

      setStatus('Отправляем подписи в Национальный каталог…');
      const response = await sendDetachedSignatures({ signPack, auth: credentials });
      const combinedErrors = [...localErrors];
      if (Array.isArray(response.errors)) {
        combinedErrors.push(...response.errors.map((error) => ({
          goodId: error.goodId ? String(error.goodId) : '',
          message: error.message ?? 'Ошибка публикации подписи.',
          code: error.code ?? null,
        })));
      }
      const signedIds = new Set(response.signed?.map((id) => String(id)) ?? []);
      if (signedIds.size > 0) {
        notifier?.success(`Подписано карточек: ${signedIds.size}.`);
        cards = cards.filter((card) => !signedIds.has(card.goodId));
        renderCards(cards);
      }
      if (combinedErrors.length > 0) {
        notifier?.warning('Некоторые подписи не приняты. Проверьте детали.');
      }
      const resultSummary = {
        signed: Array.from(signedIds),
        errors: combinedErrors,
      };
      renderResult(resultSummary);
      if (signedIds.size === 0 && combinedErrors.length === 0) {
        setStatus('Национальный каталог не вернул подтверждение подписи.');
      } else if (signedIds.size > 0 && combinedErrors.length === 0) {
        setStatus('Подписи успешно отправлены и приняты Национальным каталогом.');
      } else if (signedIds.size > 0) {
        setStatus('Часть подписей принята, проверьте сообщения об ошибках.');
      } else {
        setStatus('Национальный каталог вернул ошибки при обработке подписи.');
      }
    } catch (error) {
      if (error instanceof NationalCatalogSigningError) {
        setStatus(error.message);
        notifier?.error('Ошибка отправки подписи в Национальный каталог.');
      } else {
        setStatus('Не удалось отправить подпись. Попробуйте повторить позже.');
        notifier?.error('Сбой при отправке подписи.');
      }
      console.error(error);
    } finally {
      signing = false;
      updateControls();
    }
  }

  function renderResult(result) {
    if (!result) {
      toggleResult(false);
      return;
    }
    resultContainer.innerHTML = '';
    const fragments = [];
    if (Array.isArray(result.signed) && result.signed.length > 0) {
      const successBlock = document.createElement('div');
      const heading = document.createElement('h4');
      heading.textContent = 'Подписано:';
      const list = document.createElement('ul');
      list.className = 'signing-result__list';
      result.signed.forEach((id) => {
        const item = document.createElement('li');
        item.textContent = id;
        list.appendChild(item);
      });
      successBlock.append(heading, list);
      fragments.push(successBlock);
    }
    if (Array.isArray(result.errors) && result.errors.length > 0) {
      const errorBlock = document.createElement('div');
      const heading = document.createElement('h4');
      heading.textContent = 'Ошибки:';
      const list = document.createElement('ul');
      list.className = 'signing-result__list signing-result__list--errors';
      result.errors.forEach((error) => {
        const item = document.createElement('li');
        const code = error.code ? ` (код ${error.code})` : '';
        const id = error.goodId ? `${error.goodId}: ` : '';
        item.textContent = `${id}${error.message ?? 'Неизвестная ошибка.'}${code}`;
        list.appendChild(item);
      });
      errorBlock.append(heading, list);
      fragments.push(errorBlock);
    }
    if (fragments.length === 0) {
      toggleResult(false);
      return;
    }
    fragments.forEach((block) => resultContainer.appendChild(block));
    toggleResult(true);
  }

  reloadButton.addEventListener('click', () => loadCards({ silent: false }));
  signButton.addEventListener('click', signSelected);
  selectAllCheckbox.addEventListener('change', (event) => {
    if (loading || signing) {
      event.preventDefault();
      return;
    }
    const checked = selectAllCheckbox.checked;
    selectedIds = new Set();
    tableBody.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
      checkbox.checked = checked;
      if (checked) {
        selectedIds.add(checkbox.value);
      }
    });
    updateSelectedCounter();
    updateControls();
  });

  tableBody.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') {
      return;
    }
    if (target.checked) {
      selectedIds.add(target.value);
    } else {
      selectedIds.delete(target.value);
      selectAllCheckbox.checked = false;
    }
    updateSelectedCounter();
    updateControls();
  });

  sessionStore.addEventListener('national-catalog-credentials-changed', () => {
    const credentials = sessionStore.getNationalCatalogCredentials();
    hasCredentials = Boolean(credentials.apiKey || credentials.bearerToken);
    loadCards({ silent: true });
  });

  sessionStore.addEventListener('plugin-status-changed', (event) => {
    pluginReady = event.detail.status === 'ready';
    updateControls();
  });

  sessionStore.addEventListener('certificate-selected', (event) => {
    selectedCertificate = event.detail.certificate;
    updateControls();
  });

  loadCards({ silent: true });

  return {
    reload(options) {
      return loadCards(options);
    },
  };
}
