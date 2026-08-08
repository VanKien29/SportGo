const OLD_AUTH_KEY = 'sportgo_auth';
const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';
const ROLES_KEY = 'auth_roles';
const ROLE_GROUP_KEY = 'auth_role_group';
const REDIRECT_KEY = 'auth_redirect_to';
const PERMISSIONS_KEY = 'auth_permissions';
const VENUE_STAFF_PERMISSIONS_KEY = 'venue_staff_permissions';
const SELECTED_CLUSTER_KEY = 'selected_cluster';
const apiCache = new Map();
const API_CACHE_TTL = 60000;
const DEFAULT_REQUEST_TIMEOUT_MS = 15000;

async function fetchWithTimeout(path, options = {}) {
  const {
    timeoutMs = DEFAULT_REQUEST_TIMEOUT_MS,
    signal: externalSignal,
    ...fetchOptions
  } = options;
  const controller = new AbortController();
  let timedOut = false;
  let timeoutId = null;
  const startedAt = typeof performance !== 'undefined' ? performance.now() : 0;

  const abortFromCaller = () => controller.abort(externalSignal.reason);
  if (externalSignal) {
    if (externalSignal.aborted) abortFromCaller();
    else externalSignal.addEventListener('abort', abortFromCaller, { once: true });
  }

  if (timeoutMs > 0) {
    timeoutId = setTimeout(() => {
      timedOut = true;
      controller.abort();
    }, timeoutMs);
  }

  try {
    return await fetch(path, { ...fetchOptions, signal: controller.signal });
  } catch (error) {
    if (timedOut) {
      const timeoutError = new Error('Kết nối quá lâu. Vui lòng thử lại.');
      timeoutError.status = 408;
      timeoutError.code = 'REQUEST_TIMEOUT';
      throw timeoutError;
    }
    throw error;
  } finally {
    if (timeoutId) clearTimeout(timeoutId);
    externalSignal?.removeEventListener('abort', abortFromCaller);
    if (typeof performance !== 'undefined' && typeof performance.measure === 'function') {
      const duration = performance.now() - startedAt;
      if (duration > 800) {
        performance.measure(`sportgo:api:${String(path).replace(/[^a-z0-9]+/gi, '-').slice(0, 80)}`, {
          start: startedAt,
          duration,
        });
      }
    }
  }
}

export async function apiCached(path, options = {}) {
  const { cacheTtl = API_CACHE_TTL, ...requestOptions } = options;
  const method = String(requestOptions.method || 'GET').toUpperCase();
  if (method !== 'GET' || cacheTtl <= 0) return api(path, requestOptions);

  const key = `${method}:${path}`;
  const cached = apiCache.get(key);
  if (cached && Date.now() - cached.time < cacheTtl) return cached.data;

  const data = await api(path, requestOptions);
  apiCache.set(key, { data, time: Date.now() });
  return data;
}

export function invalidateCache(pathPrefix = '') {
  for (const key of apiCache.keys()) {
    if (key.endsWith(pathPrefix) || key.includes(`:${pathPrefix}`)) apiCache.delete(key);
  }
}

export function readToken() {
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) return token;

  try {
    return JSON.parse(localStorage.getItem(OLD_AUTH_KEY) || 'null')?.token || null;
  } catch {
    return null;
  }
}

function clearAuthStorage() {
  [
    OLD_AUTH_KEY,
    TOKEN_KEY,
    USER_KEY,
    ROLES_KEY,
    ROLE_GROUP_KEY,
    REDIRECT_KEY,
    PERMISSIONS_KEY,
    VENUE_STAFF_PERMISSIONS_KEY,
    SELECTED_CLUSTER_KEY,
  ].forEach((key) => localStorage.removeItem(key));
}

function isTechnicalErrorMessage(message) {
  return /SQLSTATE|Base table or view not found|Connection:\s|Stack trace|PDOException|QueryException|vendor[\\/]|Class\s+["'].*["']\s+not found|Call to undefined/i.test(String(message || ''));
}

function extractError(data, fallback) {
  const first = data?.errors ? Object.values(data.errors)[0] : null;
  const candidate = Array.isArray(first) && first[0] ? first[0] : data?.message;
  if (candidate && !isTechnicalErrorMessage(candidate)) return candidate;
  return fallback;
}

function makeApiError(response, data, fallback) {
  const error = new Error(extractError(data, fallback));
  error.status = response.status;
  error.data = data;
  error.response = { status: response.status, data };
  return error;
}

function venueClusterHeaders(path) {
  const appliesToVenueWorkspace = String(path).startsWith('/api/owner/')
    || String(path).startsWith('/api/chat/');
  const clusterId = localStorage.getItem(SELECTED_CLUSTER_KEY);

  return appliesToVenueWorkspace && clusterId
    ? { 'X-Venue-Cluster-Id': clusterId }
    : {};
}

export async function api(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
    ...venueClusterHeaders(path),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetchWithTimeout(path, { ...options, headers });
  const data = await response.json().catch(() => ({}));

  if (response.status === 401) {
    clearAuthStorage();
    if (token && (!options.method || options.method.toUpperCase() === 'GET')) {
      const retryHeaders = { ...headers };
      delete retryHeaders.Authorization;
      const retryResponse = await fetchWithTimeout(path, { ...options, headers: retryHeaders });
      if (retryResponse.ok) {
        return await retryResponse.json().catch(() => ({}));
      }
    }
    throw makeApiError(response, data, 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
  }

  if (response.status === 403) {
    throw makeApiError(response, data, 'Bạn không có quyền thực hiện thao tác này.');
  }

  if (!response.ok) {
    throw makeApiError(response, data, 'Có lỗi xảy ra. Vui lòng thử lại.');
  }

  return data;
}

export async function apiFormData(path, formData, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...venueClusterHeaders(path),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetchWithTimeout(path, {
    method: options.method || 'POST',
    ...options,
    headers,
    body: formData,
  });
  const data = await response.json().catch(() => ({}));

  if (response.status === 401) {
    clearAuthStorage();
    throw makeApiError(response, data, 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
  }

  if (response.status === 403) {
    throw makeApiError(response, data, 'Bạn không có quyền thực hiện thao tác này.');
  }

  if (!response.ok) {
    throw makeApiError(response, data, 'Có lỗi xảy ra. Vui lòng thử lại.');
  }

  return data;
}

export async function apiDownload(path, options = {}) {
  const headers = {
    Accept: [
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/pdf',
      'image/*',
      'text/csv',
      'application/octet-stream',
    ].join(','),
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...venueClusterHeaders(path),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetchWithTimeout(path, { ...options, headers });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      clearAuthStorage();
    }
    throw makeApiError(response, data, 'Không thể tải file.');
  }

  const blob = await response.blob();
  const disposition = response.headers.get('Content-Disposition') || '';
  const filename = disposition.match(/filename="?([^"]+)"?/i)?.[1] || 'export.xlsx';
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
