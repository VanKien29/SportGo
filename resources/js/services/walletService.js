import { api } from './api.js';

export const walletService = {
  show(params = {}) {
    const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    return api(`/api/user/wallet${query.toString() ? `?${query}` : ''}`);
  },
  listPayoutAccounts() {
    return api('/api/user/wallet/payout-accounts');
  },
  savePayoutAccount(payload) {
    return api('/api/user/wallet/payout-accounts', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
  deletePayoutAccount(id) {
    return api(`/api/user/wallet/payout-accounts/${id}`, { method: 'DELETE' });
  },
  requestWithdrawal(payload) {
    return api('/api/user/wallet/withdrawals', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },
  cancelWithdrawal(id) {
    return api(`/api/user/wallet/withdrawals/${id}/cancel`, { method: 'POST' });
  },
};
