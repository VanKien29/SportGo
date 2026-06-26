import { api, apiDownload } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) search.set(key, value);
  });
  return search.toString() ? `?${search.toString()}` : '';
}

export const adminFinanceOperationsService = {
  refunds(params = {}) {
    return api(`/api/admin/finance/refunds${query(params)}`);
  },

  updateRefund(id, payload) {
    return api(`/api/admin/finance/refunds/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  exportRefunds(ids) {
    return apiDownload('/api/admin/finance/refunds/export', {
      method: 'POST',
      body: JSON.stringify({ ids }),
    });
  },

  refundPayoutQr(id) {
    return api(`/api/admin/finance/refunds/${id}/payout-qr`, {
      method: 'POST',
    });
  },

  checkRefundPayout(id) {
    return api(`/api/admin/finance/refunds/${id}/payout-check`, {
      method: 'POST',
    });
  },

  withdrawals(params = {}) {
    return api(`/api/admin/finance/withdrawals${query(params)}`);
  },

  userWithdrawals(params = {}) {
    return api(`/api/admin/finance/user-withdrawals${query(params)}`);
  },

  payUserWithdrawal(id, payload) {
    return api(`/api/admin/finance/user-withdrawals/${id}/pay`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  userWithdrawalPayoutQr(id) {
    return api(`/api/admin/finance/user-withdrawals/${id}/payout-qr`, {
      method: 'POST',
    });
  },

  checkUserWithdrawalPayout(id) {
    return api(`/api/admin/finance/user-withdrawals/${id}/payout-check`, {
      method: 'POST',
    });
  },

  updateWithdrawal(id, payload) {
    return api(`/api/admin/finance/withdrawals/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });
  },

  exportWithdrawals(ids) {
    return apiDownload('/api/admin/finance/withdrawals/export', {
      method: 'POST',
      body: JSON.stringify({ ids }),
    });
  },

  withdrawalPayoutQr(id) {
    return api(`/api/admin/finance/withdrawals/${id}/payout-qr`, {
      method: 'POST',
    });
  },

  checkWithdrawalPayout(id) {
    return api(`/api/admin/finance/withdrawals/${id}/payout-check`, {
      method: 'POST',
    });
  },
};
