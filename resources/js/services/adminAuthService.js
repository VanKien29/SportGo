import { api } from './api.js';

export const adminAuthService = {
  login(login, password) {
    return api('/api/admin/auth/login', {
      method: 'POST',
      body: JSON.stringify({ login, password }),
    });
  },

  me() {
    return api('/api/admin/auth/me');
  },

  logout() {
    return api('/api/admin/auth/logout', { method: 'POST' });
  },

  sendForgotOtp(identifier) {
    return api('/api/admin/auth/forgot-password/send-otp', {
      method: 'POST',
      body: JSON.stringify({ identifier }),
    });
  },

  verifyForgotOtp(identifier, otp) {
    return api('/api/admin/auth/forgot-password/verify-otp', {
      method: 'POST',
      body: JSON.stringify({ identifier, otp }),
    });
  },

  resetPassword(identifier, otp, password, password_confirmation) {
    return api('/api/admin/auth/forgot-password/reset', {
      method: 'POST',
      body: JSON.stringify({ identifier, otp, password, password_confirmation }),
    });
  },
};
