const AUTH_KEY = 'sportgo_auth';
const SELECTED_CLUSTER_KEY = 'sportgo_selected_cluster';

function readToken() {
  try {
    return JSON.parse(localStorage.getItem(AUTH_KEY) || 'null')?.token || null;
  } catch {
    return null;
  }
}

function extractError(data, fallback) {
  if (data?.message) return data.message;
  const first = data?.errors ? Object.values(data.errors)[0] : null;
  return Array.isArray(first) ? first[0] : fallback;
}

export async function api(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers || {}),
  };

  const token = readToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(path, { ...options, headers });
  const data = await response.json().catch(() => ({}));

  if (response.status === 401) {
    localStorage.removeItem(AUTH_KEY);
    localStorage.removeItem(SELECTED_CLUSTER_KEY);
    throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
  }

  if (!response.ok) {
    const error = new Error(extractError(data, 'Có lỗi xảy ra. Vui lòng thử lại.'));
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

