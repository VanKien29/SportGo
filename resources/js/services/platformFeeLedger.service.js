import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      search.set(key, value);
    }
  });
  return search.toString() ? `?${search.toString()}` : '';
}

export function getLedgers(filters = {}) {
  return api(`/api/admin/platform-fee-ledgers${query(filters)}`).then(res => res.data || []);
}

export function getLedgerById(id) {
  return api(`/api/admin/platform-fee-ledgers/${id}`).then(res => res.data);
}

export function createLedger(payload) {
  return api(`/api/admin/platform-fee-ledgers`, {
    method: 'POST',
    body: JSON.stringify(payload),
  }).then(res => res.data);
}

export function calculateLedgerPreview(payload) {
  return api(`/api/admin/platform-fee-ledgers/preview`, {
    method: 'POST',
    body: JSON.stringify(payload),
  }).then(res => res.data);
}

export function confirmLedgerPayment(id, payload = {}) {
  return api(`/api/admin/platform-fee-ledgers/${id}/confirm-payment`, {
    method: 'POST',
    body: JSON.stringify(payload),
  }).then(res => res.data);
}

export function markLedgerOverdue(id, reason = 'Quá hạn thanh toán') {
  return api(`/api/admin/platform-fee-ledgers/${id}/mark-overdue`, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  }).then(res => res.data);
}

export function cancelLedger(id, reason) {
  return api(`/api/admin/platform-fee-ledgers/${id}/cancel`, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  }).then(res => res.data);
}

export function lockVenueForOverdueLedger(id, reason = 'Quá hạn phí duy trì hệ thống') {
  return api(`/api/admin/platform-fee-ledgers/${id}/lock-venue`, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  }).then(res => res.data);
}

export function unlockVenueAfterPayment(id) {
  return api(`/api/admin/platform-fee-ledgers/${id}/unlock-venue`, {
    method: 'POST',
  }).then(res => res.data);
}

export function getPlatformFeeDashboardMetrics() {
  return api(`/api/admin/platform-fee-ledgers/metrics`).then(res => res.data || {
    pending: 0,
    overdue: 0,
    pending_amount: 0,
    overdue_amount: 0,
  });
}

export function rejectPlatformFeePaymentProof(id, payload = {}) {
  return api(`/api/admin/platform-fee-ledgers/${id}/reject-payment`, {
    method: 'POST',
    body: JSON.stringify({ reason: payload.reason }),
  }).then(res => res.data);
}

export const platformFeeLedgerService = {
  getLedgers,
  getLedgerById,
  createLedger,
  calculateLedgerPreview,
  confirmLedgerPayment,
  markLedgerOverdue,
  cancelLedger,
  lockVenueForOverdueLedger,
  unlockVenueAfterPayment,
  getPlatformFeeDashboardMetrics,
  rejectPlatformFeePaymentProof,
};
