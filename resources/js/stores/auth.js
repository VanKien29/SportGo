import { authService } from '../services/authService.js';

const OLD_AUTH_KEY = 'sportgo_auth';
const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';
const ROLES_KEY = 'auth_roles';
const ROLE_GROUP_KEY = 'auth_role_group';
const REDIRECT_KEY = 'auth_redirect_to';
const PW_SETUP_KEY = 'sportgo_needs_pw_setup';
const SELECTED_CLUSTER_KEY = 'selected_cluster';

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

function readJson(key, fallback) {
  try {
    const value = localStorage.getItem(key);
    return value ? JSON.parse(value) : fallback;
  } catch {
    return fallback;
  }
}

function readOldAuth() {
  return readJson(OLD_AUTH_KEY, null);
}

export function saveAuth(payload) {
  const authData = normalizeAuth(payload, getToken());

  localStorage.setItem(TOKEN_KEY, authData.token || '');
  localStorage.setItem(USER_KEY, JSON.stringify(authData.user || {}));
  localStorage.setItem(ROLES_KEY, JSON.stringify(authData.roles || []));
  localStorage.setItem(ROLE_GROUP_KEY, authData.role_group || 'user');
  localStorage.setItem(REDIRECT_KEY, authData.redirect_to || '/');
  localStorage.removeItem(OLD_AUTH_KEY);

  return authData;
}

export function clearAuth() {
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

export function getAuth() {
  const oldAuth = readOldAuth();
  if (oldAuth?.token) return oldAuth;

  const token = localStorage.getItem(TOKEN_KEY);
  if (!token) return null;

  const user = readJson(USER_KEY, {});
  const roles = readJson(ROLES_KEY, []);
  const roleGroup = localStorage.getItem(ROLE_GROUP_KEY) || 'user';

  return normalizeAuth({
    token,
    user,
    roles,
    role_group: roleGroup,
    redirect_to: localStorage.getItem(REDIRECT_KEY) || '/',
  });
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || readOldAuth()?.token || null;
}

export async function restoreAuth() {
  const currentToken = getToken();
  if (!currentToken) return null;

  try {
    const payload = await authService.me();
    return saveAuth({ ...payload, token: currentToken });
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

export function resendRegisterOtp(email) {
  return authService.resendRegisterOtp(email);
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
