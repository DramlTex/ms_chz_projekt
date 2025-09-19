function normalizeBase(url) {
  if (!url) {
    return '';
  }
  return url.replace(/\/$/, '');
}

export function getTrueApiBaseUrl() {
  return normalizeBase('https://markirovka.crpt.ru/api/v3/true-api');
}

export function getNationalCatalogBaseUrl() {
  return normalizeBase('https://апи.национальный-каталог.рф');
}

export function getConfigSnapshot() {
  return {
    trueApiBaseUrl: getTrueApiBaseUrl(),
    nationalCatalogBaseUrl: getNationalCatalogBaseUrl(),
  };
}
