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

  withdrawals(params = {}) {
    return api(`/api/admin/finance/withdrawals${query(params)}`);
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
};
