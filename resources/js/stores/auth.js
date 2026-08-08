import { authService } from '../services/authService.js';
import { adminAuthService } from '../services/adminAuthService.js';

const OLD_AUTH_KEY = 'sportgo_auth';
const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';
const ROLES_KEY = 'auth_roles';
const ROLE_GROUP_KEY = 'auth_role_group';
const REDIRECT_KEY = 'auth_redirect_to';
const PERMISSIONS_KEY = 'auth_permissions';
const VENUE_STAFF_PERMISSIONS_KEY = 'venue_staff_permissions';
const PW_SETUP_KEY = 'sportgo_needs_pw_setup';
const SELECTED_CLUSTER_KEY = 'selected_cluster';

let adminValidatedToken = null;
let adminRestorePromise = null;
let authRestorePromise = null;

function normalizeAuth(payload, existingToken = null) {
  const user = payload.user || {};
  const roleGroup = payload.role_group || 'user';

  return {
    token: payload.token || existingToken,
    user,
    roles: payload.roles || [],
    permissions: payload.permissions || [],
    venue_staff_permissions: payload.venue_staff_permissions || {},
    role_group: roleGroup,
    role: roleGroup,
    redirect_to: payload.redirect_to || '/',
    id: user.id,
    username: user.username,
    fullName: user.full_name || user.fullName,
    full_name: user.full_name || user.fullName,
    email: user.email,
    phone: user.phone,
    avatar_url: user.avatar_url,
    email_verified_at: user.email_verified_at,
    status: user.status,
    membership_tier: user.membership_tier || payload.membership_tier || null,
    venue_memberships: user.venue_memberships || payload.venue_memberships || [],
    vip_subscription: user.vip_subscription || payload.vip_subscription || null,
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

function shouldClearAuthForError(error) {
  return [401, 419, 423].includes(Number(error?.status));
}

export function saveAuth(payload) {
  const authData = normalizeAuth(payload, getToken());

  localStorage.setItem(TOKEN_KEY, authData.token || '');
  localStorage.setItem(USER_KEY, JSON.stringify(authData.user || {}));
  localStorage.setItem(ROLES_KEY, JSON.stringify(authData.roles || []));
  localStorage.setItem(ROLE_GROUP_KEY, authData.role_group || 'user');
  localStorage.setItem(REDIRECT_KEY, authData.redirect_to || '/');
  localStorage.setItem(PERMISSIONS_KEY, JSON.stringify(authData.permissions || []));
  localStorage.setItem(VENUE_STAFF_PERMISSIONS_KEY, JSON.stringify(authData.venue_staff_permissions || {}));
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
    PERMISSIONS_KEY,
    VENUE_STAFF_PERMISSIONS_KEY,
    SELECTED_CLUSTER_KEY,
  ].forEach((key) => localStorage.removeItem(key));
  adminValidatedToken = null;
  adminRestorePromise = null;
  authRestorePromise = null;
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
    permissions: readJson(PERMISSIONS_KEY, []),
    venue_staff_permissions: readJson(VENUE_STAFF_PERMISSIONS_KEY, {}),
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

  // Deduplicate only concurrent validation requests. This is not response caching:
  // every completed validation is discarded and a later full app entry validates again.
  if (authRestorePromise) return authRestorePromise;

  authRestorePromise = (async () => {
    try {
      const payload = await authService.me();
      return saveAuth({ ...payload, token: currentToken });
    } catch (err) {
      if (shouldClearAuthForError(err)) {
        clearAuth();
      }
      return null;
    } finally {
      authRestorePromise = null;
    }
  })();

  return authRestorePromise;
}

export async function restoreAdminAuth() {
  const currentToken = getToken();
  if (!currentToken) return null;

  if (adminValidatedToken === currentToken) {
    return getAuth();
  }

  if (adminRestorePromise) return adminRestorePromise;

  adminRestorePromise = (async () => {
    try {
      const payload = await adminAuthService.me();
      const auth = saveAuth({ ...payload, token: currentToken });
      adminValidatedToken = currentToken;
      return auth;
    } catch (err) {
      if (shouldClearAuthForError(err)) {
        clearAuth();
      }
      return null;
    } finally {
      adminRestorePromise = null;
    }
  })();

  return adminRestorePromise;
}

export async function login(identifier, password) {
  const data = await authService.login(identifier, password);
  return saveAuth(data);
}

export async function adminLogin(identifier, password) {
  const data = await adminAuthService.login(identifier, password);
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

export async function adminLogout() {
  try {
    if (getToken()) {
      await adminAuthService.logout();
    }
  } finally {
    clearAuth();
  }
}

export function sendAdminForgotOtp(identifier) {
  return adminAuthService.sendForgotOtp(identifier);
}

export function verifyAdminForgotOtp(identifier, otp) {
  return adminAuthService.verifyForgotOtp(identifier, otp);
}

export function resetAdminPassword(identifier, otp, password, password_confirmation) {
  return adminAuthService.resetPassword(identifier, otp, password, password_confirmation);
}

export async function consumeGoogleCallback(query) {
  let payload = null;

  if (query.code) {
    payload = await authService.googleExchange(query.code);
  } else if (query.token) {
    payload = {
      token: query.token,
      role_group: query.role_group || 'user',
      redirect_to: query.redirect_to || '/',
      needs_password_setup: query.needs_password_setup || '0',
    };
  }

  if (!payload?.token) return null;

  if (payload.needs_password_setup === '1') {
    localStorage.setItem(PW_SETUP_KEY, '1');
  } else {
    localStorage.removeItem(PW_SETUP_KEY);
  }

  saveAuth({
    token: payload.token,
    user: {},
    roles: payload.roles || [],
    role_group: payload.role_group || 'user',
    redirect_to: payload.redirect_to || '/',
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
