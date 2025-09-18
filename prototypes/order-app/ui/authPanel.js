import { sessionStore } from '../state/sessionStore.js';
import { ensureCryptoProPlugin, listCertificates } from '../services/cryptoProClient.js';
import { obtainTrueApiToken, AuthServiceError } from '../services/authService.js';
import { formatDisplayDateTime } from '../utils/datetime.js';
import { getConfigSnapshot } from '../config.js';

function maskSecret(value) {
  if (!value) return '';
  const trimmed = value.trim();
  if (trimmed.length <= 4) {
    return '••••';
  }
  return `••••${trimmed.slice(-4)}`;
}

function setStatus(element, message) {
  if (element) {
    element.textContent = message ?? '';
  }
}

function disable(element, value) {
  if (element) {
    element.disabled = Boolean(value);
  }
}

export function initAuthPanel(elements) {
  const {
    initButton,
    pluginStatusElement,
    certificateSelect,
    refreshButton,
    requestTokenButton,
    tokenStatusElement,
    nkApiKeyInput,
    nkBearerInput,
    nkSaveButton,
    nkStatusElement,
    notifier,
  } = elements;

  if (!initButton || !pluginStatusElement || !certificateSelect || !refreshButton || !requestTokenButton || !tokenStatusElement || !nkApiKeyInput || !nkBearerInput || !nkSaveButton || !nkStatusElement) {
    throw new Error('Auth panel elements are required');
  }

  const config = getConfigSnapshot();
  setStatus(pluginStatusElement, `Рабочий контур True API: ${config.trueApiBaseUrl}. Национальный каталог: ${config.nationalCatalogBaseUrl}.`);

  let certificatesLoading = false;
  let requestingToken = false;

  async function ensurePlugin() {
    sessionStore.setPluginStatus('loading');
    try {
      await ensureCryptoProPlugin();
      sessionStore.setPluginStatus('ready');
      await loadCertificates();
      notifier?.success('CryptoPro подключен. Сертификаты обновлены.');
    } catch (error) {
      sessionStore.setPluginStatus('error', error);
      notifier?.error(error.message ?? 'Не удалось подключить CryptoPro.');
    }
  }

  async function loadCertificates() {
    if (certificatesLoading) return;
    certificatesLoading = true;
    disable(refreshButton, true);
    try {
      const list = await listCertificates();
      sessionStore.setCertificates(list);
      if (list.length === 0) {
        notifier?.warning('Сертификаты УКЭП не найдены или все просрочены.');
      }
    } catch (error) {
      notifier?.error(error.message ?? 'Не удалось получить список сертификатов.');
    } finally {
      certificatesLoading = false;
      disable(refreshButton, false);
    }
  }

  async function handleRequestToken() {
    if (requestingToken) return;
    const certificate = sessionStore.getSelectedCertificate();
    if (!certificate) {
      notifier?.warning('Выберите сертификат УКЭП перед получением токена.');
      return;
    }
    requestingToken = true;
    disable(requestTokenButton, true);
    setStatus(tokenStatusElement, 'Получаем challenge True API и запрашиваем подпись…');
    try {
      const result = await obtainTrueApiToken({ thumbprint: certificate.thumbprint });
      sessionStore.setTrueApiToken(result.token, {
        challengeUuid: result.challenge.uuid,
        rawResponse: result.raw,
      });
      notifier?.success('Bearer-токен True API получен.');
    } catch (error) {
      if (error instanceof AuthServiceError) {
        setStatus(tokenStatusElement, error.message);
      } else {
        setStatus(tokenStatusElement, 'Не удалось получить токен True API.');
      }
      sessionStore.clearTrueApiToken();
      notifier?.error(error.message ?? 'Ошибка получения токена True API.');
    } finally {
      requestingToken = false;
      disable(requestTokenButton, false);
    }
  }

  function handleSaveNkCredentials() {
    const apiKey = nkApiKeyInput.value.trim();
    const bearer = nkBearerInput.value.trim();
    sessionStore.setNationalCatalogCredentials({ apiKey, bearerToken: bearer });
    nkApiKeyInput.value = '';
    nkBearerInput.value = '';
    notifier?.success('Данные для Национального каталога сохранены.');
  }

  function updateCertificatesSelect(certificates) {
    certificateSelect.innerHTML = '';
    if (!certificates || certificates.length === 0) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = 'Нет доступных сертификатов';
      certificateSelect.appendChild(option);
      certificateSelect.disabled = true;
      return;
    }
    certificates.forEach((certificate) => {
      const option = document.createElement('option');
      option.value = certificate.thumbprint;
      option.textContent = `${certificate.subject || certificate.friendlyName} (до ${formatDisplayDateTime(certificate.validTo)})`;
      certificateSelect.appendChild(option);
    });
    certificateSelect.disabled = false;
    const selected = sessionStore.getSelectedCertificate();
    if (selected) {
      certificateSelect.value = selected.thumbprint;
    }
  }

  function updateTokenStatus(detail) {
    if (!detail?.token) {
      setStatus(tokenStatusElement, 'Bearer-токен True API не получен.');
      return;
    }
    const issued = detail.issuedAt ? formatDisplayDateTime(detail.issuedAt) : '—';
    const expires = detail.expiresAt ? formatDisplayDateTime(detail.expiresAt) : '—';
    setStatus(
      tokenStatusElement,
      `Bearer-токен True API получен: ${maskSecret(detail.token)}. Выдан: ${issued}. Истекает: ${expires}.`,
    );
  }

  function updateNkStatus(credentials) {
    const parts = [];
    if (credentials.apiKey) {
      parts.push(`API-ключ: ${maskSecret(credentials.apiKey)}`);
    }
    if (credentials.bearerToken) {
      parts.push(`Bearer: ${maskSecret(credentials.bearerToken)}`);
    }
    if (parts.length === 0) {
      setStatus(nkStatusElement, 'Укажите API-ключ или токен Национального каталога для загрузки карточек.');
    } else {
      setStatus(nkStatusElement, parts.join('. '));
    }
  }

  function updatePluginUi(status) {
    switch (status.status) {
      case 'loading':
        setStatus(pluginStatusElement, 'Инициализация CryptoPro… Разрешите доступ к сертификатам.');
        break;
      case 'ready': {
        const certificates = sessionStore.getCertificates();
        setStatus(
          pluginStatusElement,
          certificates.length > 0
            ? `CryptoPro подключен. Найдено сертификатов: ${certificates.length}.`
            : 'CryptoPro подключен, но сертификаты не найдены.',
        );
        break;
      }
      case 'error':
        setStatus(
          pluginStatusElement,
          `Ошибка CryptoPro: ${status.error?.message ?? status.error ?? 'подробнее см. консоль.'}`,
        );
        break;
      default:
        setStatus(
          pluginStatusElement,
          `Рабочий контур True API: ${config.trueApiBaseUrl}. Национальный каталог: ${config.nationalCatalogBaseUrl}.`,
        );
        break;
    }
    const ready = status.status === 'ready';
    disable(initButton, ready || status.status === 'loading');
    disable(refreshButton, !ready || certificatesLoading);
    disable(requestTokenButton, !ready || requestingToken || !sessionStore.getSelectedCertificate());
    certificateSelect.disabled = !ready || sessionStore.getCertificates().length === 0;
  }

  initButton.addEventListener('click', ensurePlugin);
  refreshButton.addEventListener('click', loadCertificates);
  requestTokenButton.addEventListener('click', handleRequestToken);
  nkSaveButton.addEventListener('click', handleSaveNkCredentials);
  certificateSelect.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLSelectElement) {
      sessionStore.selectCertificate(target.value);
    }
    disable(requestTokenButton, requestingToken || !sessionStore.getSelectedCertificate());
  });

  sessionStore.addEventListener('plugin-status-changed', (event) => {
    updatePluginUi(event.detail);
  });
  sessionStore.addEventListener('certificates-changed', (event) => {
    updateCertificatesSelect(event.detail.certificates);
    disable(requestTokenButton, requestingToken || !sessionStore.getSelectedCertificate());
  });
  sessionStore.addEventListener('certificate-selected', (event) => {
    const certificate = event.detail.certificate;
    if (certificate) {
      certificateSelect.value = certificate.thumbprint;
    }
    disable(requestTokenButton, requestingToken || !certificate);
  });
  sessionStore.addEventListener('true-api-token-changed', (event) => {
    updateTokenStatus(event.detail);
  });
  sessionStore.addEventListener('national-catalog-credentials-changed', (event) => {
    updateNkStatus(event.detail);
  });

  // Initial state.
  updatePluginUi(sessionStore.getPluginStatus());
  updateCertificatesSelect(sessionStore.getCertificates());
  updateTokenStatus(sessionStore.getTrueApiToken());
  updateNkStatus(sessionStore.getNationalCatalogCredentials());
}
