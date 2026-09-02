import { api, apiFormData } from './api.js';

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

  me(include = '', options = {}) {
    const query = include ? `?include=${encodeURIComponent(include)}` : '';
    return api(`/api/auth/me${query}`, options);
  },

  updateProfile(formData) {
    return apiFormData('/api/auth/profile', formData);
  },

  requestEmailChangeOtp({ stage = 'current', email = '', current_password = '' } = {}) {
    return api('/api/auth/profile/email/request-otp', {
      method: 'POST',
      body: JSON.stringify({ stage, ...(email ? { email } : {}), ...(current_password ? { current_password } : {}) }),
    });
  },

  verifyEmailChangeOtp({ stage = 'current', email = '', otp } = {}) {
    return api('/api/auth/profile/email/verify-otp', {
      method: 'POST',
      body: JSON.stringify({ stage, ...(email ? { email } : {}), otp }),
    });
  },

  changePhone(phone, current_password) {
    return api('/api/auth/profile/phone/change', {
      method: 'POST',
      body: JSON.stringify({ phone, current_password }),
    });
  },

  googleExchange(code) {
    return api('/api/auth/google/exchange', {
      method: 'POST',
      body: JSON.stringify({ code }),
    });
  },

  setPassword(password, password_confirmation) {
    return api('/api/auth/set-password', {
      method: 'POST',
      body: JSON.stringify({ password, password_confirmation }),
    });
  },

  logout() {
    return api('/api/auth/logout', {
      method: 'POST',
    });
  },

  changePassword(payload) {
    return api('/api/auth/change-password', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
};
