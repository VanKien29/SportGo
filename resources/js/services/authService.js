import { api } from './api.js';

export const authService = {
  login(login, password) {
    return api('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ login, password }),
    });
  },

  register(payload) {
    return api('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  verifyRegisterOtp(email, otp) {
    return api('/api/auth/register/verify-otp', {
      method: 'POST',
      body: JSON.stringify({ email, otp }),
    });
  },

  resendRegisterOtp(email) {
    return api('/api/auth/register/resend-otp', {
      method: 'POST',
      body: JSON.stringify({ email }),
    });
  },

  sendForgotOtp(identifier) {
    return api('/api/auth/forgot-password/send-otp', {
      method: 'POST',
      body: JSON.stringify({ identifier }),
    });
  },

  verifyForgotOtp(identifier, otp) {
    return api('/api/auth/forgot-password/verify-otp', {
      method: 'POST',
      body: JSON.stringify({ identifier, otp }),
    });
  },

  resetPassword(identifier, otp, password, password_confirmation) {
    return api('/api/auth/forgot-password/reset', {
      method: 'POST',
      body: JSON.stringify({ identifier, otp, password, password_confirmation }),
    });
  },

  me() {
    return api('/api/auth/me');
  },

  setPassword(password, password_confirmation) {
    return api('/api/auth/set-password', {
      method: 'POST',
      body: JSON.stringify({ password, password_confirmation }),
    });
  },

  logout() {
    return api('/api/auth/logout', { method: 'POST' });
  },
};
