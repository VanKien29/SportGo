import { authService } from '../services/authService.js';

const AUTH_KEY = 'sportgo_auth';
const CLUSTER_KEY = 'sportgo_selected_cluster';
const PW_SETUP_KEY = 'sportgo_needs_pw_setup';

const MOCK_CLUSTERS = [
  { id: 'cluster-001', name: 'SportGo Cầu Giấy', address: '15 Duy Tân, Cầu Giấy, Hà Nội', courtCount: 8 },
  { id: 'cluster-002', name: 'SportGo Mỹ Đình', address: '20 Lê Đức Thọ, Nam Từ Liêm, Hà Nội', courtCount: 12 },
  { id: 'cluster-003', name: 'SportGo Hà Đông', address: '15 Quang Trung, Hà Đông, Hà Nội', courtCount: 6 },
];

function normalizeAuth(payload, existingToken = null) {
  const user = payload.user || {};
  const roleGroup = payload.role_group || 'user';

  return {
    token: payload.token || existingToken,
    user,
    roles: payload.roles || [],
    role_group: roleGroup,
    role: roleGroup,
    redirect_to: payload.redirect_to || '/',
    id: user.id,
    username: user.username,
    fullName: user.full_name || user.fullName,
    full_name: user.full_name || user.fullName,
    email: user.email,
    phone: user.phone,
    status: user.status,
  };
}

export function saveAuth(payload) {
  const authData = normalizeAuth(payload, getAuth()?.token || null);
  localStorage.setItem(AUTH_KEY, JSON.stringify(authData));
  return authData;
}

export function clearAuth() {
  localStorage.removeItem(AUTH_KEY);
  localStorage.removeItem(CLUSTER_KEY);
}

export function getAuth() {
  try {
    const raw = localStorage.getItem(AUTH_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function getToken() {
  return getAuth()?.token || null;
}

export async function restoreAuth() {
  const current = getAuth();
  if (!current?.token) return null;

  try {
    const payload = await authService.me();
    return saveAuth({ ...payload, token: current.token });
  } catch {
    clearAuth();
    return null;
  }
}

export async function login(identifier, password) {
  const data = await authService.login(identifier, password);
  return saveAuth(data);
}

export function register(payload) {
  return authService.register(payload);
}

export function verifyRegisterOtp(email, otp) {
  return authService.verifyRegisterOtp(email, otp);
}

export function sendForgotOtp(identifier) {
  return authService.sendForgotOtp(identifier);
}

export function verifyForgotOtp(identifier, otp) {
  return authService.verifyForgotOtp(identifier, otp);
}

export function resetPassword(identifier, otp, password, password_confirmation) {
  return authService.resetPassword(identifier, otp, password, password_confirmation);
}

export async function logout() {
  try {
    if (getToken()) {
      await authService.logout();
    }
  } finally {
    clearAuth();
  }
}

export async function consumeGoogleCallback(query) {
  if (!query.token) return null;
  if (query.needs_password_setup === '1') {
    localStorage.setItem(PW_SETUP_KEY, '1');
  } else {
    localStorage.removeItem(PW_SETUP_KEY);
  }
  saveAuth({
    token: query.token,
    user: {},
    roles: [],
    role_group: query.role_group || 'user',
    redirect_to: query.redirect_to || '/',
  });
  return restoreAuth();
}

export function loginWithGoogle() {
  window.location.href = '/api/auth/google/redirect';
}

export function needsPasswordSetup() {
  return localStorage.getItem(PW_SETUP_KEY) === '1';
}

export function clearPasswordSetupFlag() {
  localStorage.removeItem(PW_SETUP_KEY);
}

export function setPassword(password, password_confirmation) {
  return authService.setPassword(password, password_confirmation);
}

export function getSelectedCluster() {
  try {
    const raw = localStorage.getItem(CLUSTER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function selectCluster(cluster) {
  localStorage.setItem(CLUSTER_KEY, JSON.stringify(cluster));
}

export function getClusters() {
  return MOCK_CLUSTERS;
}

