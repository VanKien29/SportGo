import { api } from './api.js';

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined && value !== false) {
      search.set(key, value);
    }
  });
  return search.toString() ? `?${search.toString()}` : '';
}

function remainingAmount(ledger) {
  return Math.max(0, Number(ledger.amount_due || 0) - Number(ledger.amount_paid || 0));
}

export async function getLedgers(filters = {}) {
  const ledgers = await api(`/api/admin/platform-fee-ledgers${query(filters)}`);
  return Array.isArray(ledgers) ? ledgers : ledgers.data || [];
}

export async function getLedgerById(id) {
  return api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(id)}`);
}

export function getLedgersByVenue(venueClusterId) {
  return getLedgers({ venue_cluster_id: venueClusterId });
}

export async function getPlatformFeeDashboardMetrics() {
  const ledgers = await getLedgers();
  const now = new Date();
  const paidThisMonth = ledgers.filter((ledger) => {
    if (!ledger.paid_at) return false;
    const paid = new Date(ledger.paid_at);
    return paid.getMonth() === now.getMonth() && paid.getFullYear() === now.getFullYear();
  });

  return {
    pending: ledgers.filter((ledger) => ledger.status === 'pending').length,
    overdue: ledgers.filter((ledger) => ledger.status === 'overdue').length,
    pending_amount: ledgers
      .filter((ledger) => ledger.status === 'pending')
      .reduce((sum, ledger) => sum + remainingAmount(ledger), 0),
    overdue_amount: ledgers
      .filter((ledger) => ledger.status === 'overdue')
      .reduce((sum, ledger) => sum + remainingAmount(ledger), 0),
    paid_this_month: paidThisMonth.reduce((sum, ledger) => sum + Number(ledger.amount_paid || 0), 0),
    locked_venues: ledgers.filter((ledger) => ledger.venue?.status === 'locked').length,
    email_sent_today: 0,
    email_failed: ledgers.filter((ledger) => (ledger.email_logs || []).some((log) => log.status === 'failed')).length,
  };
}

export function checkLedgerPeriodOverlap(venueClusterId, periodStart, periodEnd, ledgers = []) {
  const start = new Date(periodStart);
  const end = new Date(periodEnd);
  const overlaps = ledgers.filter((ledger) => {
    if (ledger.venue_cluster_id !== venueClusterId || ledger.status === 'cancelled') return false;
    return new Date(ledger.period_start) <= end && start <= new Date(ledger.period_end);
  });

  return {
    hasOverlap: overlaps.length > 0,
    overlaps,
    warnings: [],
  };
}

export function calculateLedgerPreview(payload) {
  return api('/api/admin/platform-fee-ledgers/preview', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function createLedger(payload) {
  const response = await api('/api/admin/platform-fee-ledgers', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data || response;
}

export async function confirmLedgerPayment(id, payload = {}) {
  const response = await api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(id)}/pay`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
  return response.data || response;
}

export async function markLedgerOverdue(id, reason = 'Quá hạn thanh toán') {
  const response = await api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(id)}/overdue`, {
    method: 'PATCH',
    body: JSON.stringify({ reason }),
  });
  return response.data || response;
}

export async function cancelLedger(id, reason) {
  const response = await api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(id)}/cancel`, {
    method: 'PATCH',
    body: JSON.stringify({ reason }),
  });
  return response.data || response;
}

export async function lockVenueForOverdueLedger(id, reason = 'Quá hạn phí duy trì hệ thống') {
  const response = await api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(id)}/lock-venue`, {
    method: 'PATCH',
    body: JSON.stringify({ reason }),
  });
  return response.data || response;
}

export async function unlockVenueAfterPayment(id) {
  const response = await api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(id)}/unlock-venue`, {
    method: 'PATCH',
  });
  return response.data || response;
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
  getLedgersByVenue,
  checkLedgerPeriodOverlap,
  getPlatformFeeDashboardMetrics,
};
