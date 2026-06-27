const OLD_AUTH_KEY = 'sportgo_auth';
const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';
const ROLES_KEY = 'auth_roles';
const ROLE_GROUP_KEY = 'auth_role_group';
const REDIRECT_KEY = 'auth_redirect_to';
const SELECTED_CLUSTER_KEY = 'selected_cluster';

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
    SELECTED_CLUSTER_KEY,
  ].forEach((key) => localStorage.removeItem(key));
}

function extractError(data, fallback) {
  const first = data?.errors ? Object.values(data.errors)[0] : null;
  if (Array.isArray(first) && first[0]) return first[0];
  if (data?.message) return data.message;
  return fallback;
}

export async function api(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(path, { ...options, headers });
  const data = await response.json().catch(() => ({}));

  if (response.status === 401) {
    clearAuthStorage();
    throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
  }

  if (response.status === 403) {
    throw new Error(extractError(data, 'Bạn không có quyền thực hiện thao tác này.'));
  }

  if (!response.ok) {
    const error = new Error(extractError(data, 'Có lỗi xảy ra. Vui lòng thử lại.'));
    error.status = response.status;
    error.data = data;
    error.response = { status: response.status, data };
    throw error;
  }

  return data;
}

export async function apiFormData(path, formData, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(path, {
    method: options.method || 'POST',
    ...options,
    headers,
    body: formData,
  });
  const data = await response.json().catch(() => ({}));

  if (response.status === 401) {
    clearAuthStorage();
    throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
  }

  if (response.status === 403) {
    throw new Error(extractError(data, 'Bạn không có quyền thực hiện thao tác này.'));
  }

  if (!response.ok) {
    const error = new Error(extractError(data, 'Có lỗi xảy ra. Vui lòng thử lại.'));
    error.status = response.status;
    error.data = data;
    error.response = { status: response.status, data };
    throw error;
  }

  return data;
}

export async function apiDownload(path, options = {}) {
  const headers = {
    Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,application/octet-stream',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(path, { ...options, headers });

  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(extractError(data, 'Không thể tải file.'));
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
