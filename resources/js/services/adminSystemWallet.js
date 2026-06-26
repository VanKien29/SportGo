import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) search.set(key, value);
  });
  return search.toString() ? `?${search.toString()}` : '';
}

export const adminSystemWalletService = {
  show(params = {}) {
    return api(`/api/admin/finance/system-wallet${query(params)}`);
  },

  sync() {
    return api('/api/admin/finance/system-wallet/sync', {
      method: 'POST',
    });
  },

  updateSettings(payload) {
    return api('/api/admin/finance/system-wallet/settings', {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
};
