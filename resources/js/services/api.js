const OLD_AUTH_KEY = 'sportgo_auth';
const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';
const ROLES_KEY = 'auth_roles';
const ROLE_GROUP_KEY = 'auth_role_group';
const REDIRECT_KEY = 'auth_redirect_to';
const PERMISSIONS_KEY = 'auth_permissions';
const VENUE_STAFF_PERMISSIONS_KEY = 'venue_staff_permissions';
const SELECTED_CLUSTER_KEY = 'selected_cluster';
const inFlightGetRequests = new Map();

// Request không bị hủy theo thời gian trên web. Component vẫn có thể truyền
// AbortSignal để hủy request cũ khi người dùng đổi trang hoặc đổi bản ghi.
async function fetchRequest(path, options = {}) {
  const { timeoutMs: _ignoredTimeout, ...fetchOptions } = options;
  const startedAt = typeof performance !== 'undefined' ? performance.now() : 0;

  try {
    return await fetch(path, fetchOptions);
  } finally {
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
  if (candidate && /too many attempts/i.test(candidate)) {
    return 'Bạn đang thao tác quá nhanh. Vui lòng thử lại sau giây lát.';
  }
  if (candidate && !isTechnicalErrorMessage(candidate)) return candidate;
  return fallback;
}

function makeApiError(response, data, fallback) {
  let message = extractError(data, fallback);
  if (response?.status === 429) {
    if (!message || /too many attempts/i.test(message) || message === fallback) {
      message = 'Bạn đang thao tác quá nhanh. Vui lòng thử lại sau giây lát.';
    }
  }
  const error = new Error(message);
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

async function requestApi(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
    ...venueClusterHeaders(path),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetchRequest(path, { ...options, headers });
  const data = await response.json().catch(() => ({}));

  if (response.status === 401) {
    clearAuthStorage();
    if (token && (!options.method || options.method.toUpperCase() === 'GET')) {
      const retryHeaders = { ...headers };
      delete retryHeaders.Authorization;
      const retryResponse = await fetchRequest(path, { ...options, headers: retryHeaders });
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

// Coalesce identical concurrent GETs only. Completed responses are removed
// immediately, so this does not introduce response caching or stale data.
export function api(path, options = {}) {
  // Some screen bootstraps must always start a new request.  In particular,
  // they cannot wait on a previously started navigation request that may have
  // been abandoned by Vue while the user changed page or venue.
  const { dedupe = true, ...requestOptions } = options;
  const method = String(requestOptions.method || 'GET').toUpperCase();
  if (method !== 'GET' || requestOptions.signal || !dedupe) {
    return requestApi(path, requestOptions);
  }

  const requestKey = `${method}:${path}:${readToken() || ''}`;
  const currentRequest = inFlightGetRequests.get(requestKey);
  if (currentRequest) return currentRequest;

  const request = requestApi(path, requestOptions).finally(() => {
    if (inFlightGetRequests.get(requestKey) === request) {
      inFlightGetRequests.delete(requestKey);
    }
  });
  inFlightGetRequests.set(requestKey, request);
  return request;
}

export async function apiFormData(path, formData, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...venueClusterHeaders(path),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetchRequest(path, {
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
  const { filename: requestedFilename, ...requestOptions } = options;
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

  const response = await fetchRequest(path, {
    ...requestOptions,
    cache: 'no-store',
    credentials: requestOptions.credentials || 'same-origin',
    headers,
  });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      clearAuthStorage();
    }
    throw makeApiError(response, data, 'Không thể tải file.');
  }

  const blob = await response.blob();
  const disposition = response.headers.get('Content-Disposition') || '';
  const encodedFilename = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
  const plainFilename = disposition.match(/filename="?([^";]+)"?/i)?.[1];
  const filename = requestedFilename
    || (encodedFilename ? decodeURIComponent(encodedFilename) : plainFilename)
    || 'download';
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  link.remove();
  // Chrome can cancel the download when the object URL is revoked in the
  // same task as the synthetic click, especially for streamed responses.
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
