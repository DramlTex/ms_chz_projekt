const TOKEN_LIFETIME_FALLBACK_HOURS = 9;
const TOKEN_RENEWAL_THRESHOLD_MS = 2 * 60 * 1000; // 2 minutes.

function cloneCertificate(cert) {
  if (!cert) return null;
  return {
    thumbprint: cert.thumbprint,
    subject: cert.subject,
    issuer: cert.issuer,
    validFrom: cert.validFrom ? new Date(cert.validFrom) : null,
    validTo: cert.validTo ? new Date(cert.validTo) : null,
    friendlyName: cert.friendlyName ?? '',
  };
}

function decodeJwt(token) {
  if (typeof token !== 'string' || token.split('.').length < 2) {
    return null;
  }
  try {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const decoded = atob(base64 + padding);
    return JSON.parse(decoded);
  } catch (error) {
    console.warn('Не удалось декодировать токен True API', error);
    return null;
  }
}

function calculateExpiration(token) {
  const payload = decodeJwt(token);
  const issuedAt = new Date();
  if (payload?.exp) {
    const exp = new Date(payload.exp * 1000);
    if (!Number.isNaN(exp.getTime())) {
      return { issuedAt, expiresAt: exp };
    }
  }
  const expiresAt = new Date(issuedAt.getTime() + TOKEN_LIFETIME_FALLBACK_HOURS * 60 * 60 * 1000);
  return { issuedAt, expiresAt };
}

class SessionStore extends EventTarget {
  constructor() {
    super();
    this.state = {
      pluginStatus: 'idle',
      pluginError: null,
      certificates: [],
      selectedCertificate: null,
      trueApiToken: null,
      trueApiTokenIssuedAt: null,
      trueApiTokenExpiresAt: null,
      trueApiTokenMeta: null,
      nationalCatalog: {
        apiKey: '',
        bearerToken: '',
      },
    };
  }

  setPluginStatus(status, error = null) {
    if (this.state.pluginStatus === status && this.state.pluginError === error) {
      return;
    }
    this.state.pluginStatus = status;
    this.state.pluginError = error;
    this.dispatchEvent(new CustomEvent('plugin-status-changed', {
      detail: { status, error },
    }));
  }

  setCertificates(certificates = []) {
    const list = Array.isArray(certificates)
      ? certificates.map((item) => cloneCertificate(item))
      : [];
    this.state.certificates = list;
    if (!list.find((item) => item.thumbprint === this.state.selectedCertificate?.thumbprint)) {
      this.state.selectedCertificate = list.length > 0 ? list[0] : null;
      this.dispatchEvent(new CustomEvent('certificate-selected', {
        detail: { certificate: cloneCertificate(this.state.selectedCertificate) },
      }));
    }
    this.dispatchEvent(new CustomEvent('certificates-changed', {
      detail: { certificates: this.getCertificates() },
    }));
  }

  selectCertificate(thumbprint) {
    if (!thumbprint) {
      this.state.selectedCertificate = null;
      this.dispatchEvent(new CustomEvent('certificate-selected', {
        detail: { certificate: null },
      }));
      return;
    }
    const found = this.state.certificates.find((item) => item.thumbprint === thumbprint) ?? null;
    this.state.selectedCertificate = found ? cloneCertificate(found) : null;
    this.dispatchEvent(new CustomEvent('certificate-selected', {
      detail: { certificate: this.getSelectedCertificate() },
    }));
  }

  setTrueApiToken(token, meta = {}) {
    if (!token) {
      this.state.trueApiToken = null;
      this.state.trueApiTokenIssuedAt = null;
      this.state.trueApiTokenExpiresAt = null;
      this.state.trueApiTokenMeta = null;
      this.dispatchEvent(new CustomEvent('true-api-token-changed', {
        detail: { token: null },
      }));
      return;
    }
    const { issuedAt, expiresAt } = calculateExpiration(token);
    this.state.trueApiToken = token;
    this.state.trueApiTokenIssuedAt = issuedAt;
    this.state.trueApiTokenExpiresAt = expiresAt;
    this.state.trueApiTokenMeta = meta;
    this.dispatchEvent(new CustomEvent('true-api-token-changed', {
      detail: {
        token,
        issuedAt: new Date(issuedAt),
        expiresAt: new Date(expiresAt),
        meta,
      },
    }));
  }

  clearTrueApiToken() {
    this.setTrueApiToken(null);
  }

  needsTrueApiTokenRefresh() {
    if (!this.state.trueApiToken || !this.state.trueApiTokenExpiresAt) {
      return true;
    }
    const now = Date.now();
    const expires = this.state.trueApiTokenExpiresAt.getTime();
    return expires - now <= TOKEN_RENEWAL_THRESHOLD_MS;
  }

  getTrueApiToken() {
    if (!this.state.trueApiToken) {
      return null;
    }
    return {
      token: this.state.trueApiToken,
      issuedAt: this.state.trueApiTokenIssuedAt ? new Date(this.state.trueApiTokenIssuedAt) : null,
      expiresAt: this.state.trueApiTokenExpiresAt ? new Date(this.state.trueApiTokenExpiresAt) : null,
      meta: this.state.trueApiTokenMeta ? { ...this.state.trueApiTokenMeta } : null,
    };
  }

  getCertificates() {
    return this.state.certificates.map((item) => cloneCertificate(item));
  }

  getSelectedCertificate() {
    return cloneCertificate(this.state.selectedCertificate);
  }

  getPluginStatus() {
    return {
      status: this.state.pluginStatus,
      error: this.state.pluginError,
    };
  }

  setNationalCatalogCredentials({ apiKey = '', bearerToken = '' } = {}) {
    this.state.nationalCatalog = {
      apiKey: apiKey.trim(),
      bearerToken: bearerToken.trim(),
    };
    this.dispatchEvent(new CustomEvent('national-catalog-credentials-changed', {
      detail: this.getNationalCatalogCredentials(),
    }));
  }

  getNationalCatalogCredentials() {
    return {
      apiKey: this.state.nationalCatalog.apiKey,
      bearerToken: this.state.nationalCatalog.bearerToken,
    };
  }
}

export const sessionStore = new SessionStore();
