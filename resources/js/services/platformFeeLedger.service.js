import { addAuditLog } from './audit.service.js';
import { generatePlatformFeeReceipt } from './financeReceipt.service.js';
import {
  notifyOwnerPlatformFeeCreated,
  notifyOwnerPlatformFeeOverdue,
  notifyOwnerPlatformFeePaid,
  notifyOwnerVenueLocked,
  notifyOwnerVenueUnlocked,
} from './notification.service.js';
import { calculatePlatformFee, findTierForCourtCount, validateTierCoverage } from './platformFeeTier.service.js';
import { createId, cloneValue, platformFeeStore } from '../stores/platformFee.store.js';

function addMonths(dateValue, months) {
  const date = new Date(dateValue);
  date.setMonth(date.getMonth() + Number(months));
  date.setDate(date.getDate() - 1);
  return date.toISOString().slice(0, 10);
}

function remainingAmount(ledger) {
  return Math.max(0, Number(ledger.amount_due || 0) - Number(ledger.amount_paid || 0));
}

function findVenue(venueId) {
  return platformFeeStore.state.venues.find((venue) => venue.id === venueId);
}

function findLedger(id) {
  return platformFeeStore.state.ledgers.find((ledger) => ledger.id === id);
}

function decorateLedger(ledger) {
  const venue = findVenue(ledger.venue_cluster_id);
  return {
    ...cloneValue(ledger),
    venue,
    owner: venue?.owner || null,
    remaining_amount: remainingAmount(ledger),
    email_logs: platformFeeStore.state.emailLogs.filter((log) => log.ledger_id === ledger.id),
    receipt: ledger.receipt_id ? platformFeeStore.state.receipts.find((receipt) => receipt.id === ledger.receipt_id) : null,
  };
}

function dateOnly(value) {
  if (!value) return '';
  return new Date(value).toISOString().slice(0, 10);
}

function rangesOverlap(startA, endA, startB, endB) {
  return new Date(startA) <= new Date(endB) && new Date(startB) <= new Date(endA);
}

export function getLedgers(filters = {}) {
  let list = platformFeeStore.state.ledgers.map(decorateLedger);

  if (filters.status) list = list.filter((ledger) => ledger.status === filters.status);
  if (filters.venue_cluster_id) list = list.filter((ledger) => ledger.venue_cluster_id === filters.venue_cluster_id);
  if (filters.owner_id) list = list.filter((ledger) => ledger.owner?.id === filters.owner_id);
  if (filters.period_months) list = list.filter((ledger) => Number(ledger.period_months) === Number(filters.period_months));
  if (filters.overdue_only) list = list.filter((ledger) => ledger.status === 'overdue' || (ledger.status === 'pending' && new Date(ledger.due_date) < new Date()));
  if (filters.period_start) list = list.filter((ledger) => dateOnly(ledger.period_start) >= filters.period_start);
  if (filters.period_end) list = list.filter((ledger) => dateOnly(ledger.period_end) <= filters.period_end);
  if (filters.due_date) list = list.filter((ledger) => dateOnly(ledger.due_date) === filters.due_date);
  if (filters.email_status) {
    list = list.filter((ledger) => {
      const logs = ledger.email_logs || [];
      if (filters.email_status === 'not_sent') return logs.length === 0;
      if (filters.email_status === 'failed') return logs.some((log) => log.status === 'failed');
      if (filters.email_status === 'sent_today') return logs.some((log) => log.status === 'sent' && dateOnly(log.sent_at) === dateOnly(new Date()));
      return logs.some((log) => log.type === filters.email_status);
    });
  }
  if (filters.range === 'this_month') {
    const now = new Date();
    list = list.filter((ledger) => {
      if (!ledger.paid_at) return false;
      const paid = new Date(ledger.paid_at);
      return paid.getMonth() === now.getMonth() && paid.getFullYear() === now.getFullYear();
    });
  }
  if (filters.keyword) {
    const q = String(filters.keyword).trim().toLowerCase();
    list = list.filter((ledger) =>
      ledger.code.toLowerCase().includes(q) ||
      (ledger.venue?.name || '').toLowerCase().includes(q) ||
      (ledger.owner?.full_name || '').toLowerCase().includes(q));
  }

  return Promise.resolve(list);
}

export function getLedgerById(id) {
  const ledger = platformFeeStore.state.ledgers.find((item) => item.id === id);
  return Promise.resolve(ledger ? decorateLedger(ledger) : null);
}

export function getLedgersByVenue(venueClusterId) {
  return getLedgers({ venue_cluster_id: venueClusterId });
}

export function checkLedgerPeriodOverlap(venueClusterId, periodStart, periodEnd) {
  const overlaps = platformFeeStore.state.ledgers.filter((ledger) =>
    ledger.venue_cluster_id === venueClusterId &&
    ledger.status !== 'cancelled' &&
    rangesOverlap(ledger.period_start, ledger.period_end, periodStart, periodEnd));
  const cancelledOverlaps = platformFeeStore.state.ledgers.filter((ledger) =>
    ledger.venue_cluster_id === venueClusterId &&
    ledger.status === 'cancelled' &&
    rangesOverlap(ledger.period_start, ledger.period_end, periodStart, periodEnd));

  return {
    hasOverlap: overlaps.length > 0,
    overlaps: cloneValue(overlaps),
    warnings: cancelledOverlaps.length ? ['Ky phi da huy trung thoi gian, co the tao lai neu can.'] : [],
  };
}

export function calculateLedgerPreview(payload) {
  const venue = findVenue(payload.venue_cluster_id);
  if (!venue) return { isValid: false, error: 'Khong tim thay cum san.', warnings: [] };

  const coverage = validateTierCoverage(platformFeeStore.state.tiers);
  if (!coverage.isValid) {
    return {
      isValid: false,
      error: 'Cau hinh bac phi hien chua hop le, vui long sua truoc khi tao ky phi.',
      coverage,
      warnings: [],
    };
  }

  const courtCount = Number(payload.court_count || venue.court_count || venue.courts?.filter((court) => court.status !== 'deleted').length || 0);
  const found = findTierForCourtCount(courtCount);
  if (!found.tier) return { isValid: false, error: found.error, warnings: [] };

  const periodMonths = Number(payload.period_months || 1);
  const periodStart = payload.period_start || new Date().toISOString().slice(0, 10);
  const periodEnd = payload.period_end || addMonths(periodStart, periodMonths);
  const dueDate = payload.due_date || periodEnd;
  const fee = calculatePlatformFee({ court_count: courtCount, period_months: periodMonths, tier: found.tier });
  const overlap = checkLedgerPeriodOverlap(venue.id, periodStart, periodEnd);

  if (overlap.hasOverlap) {
    return {
      isValid: false,
      error: 'Da co ky phi trung thoi gian cho cum san nay.',
      venue,
      tier: found.tier,
      fee,
      period_start: periodStart,
      period_end: periodEnd,
      due_date: dueDate,
      warnings: overlap.warnings,
    };
  }

  return {
    isValid: true,
    venue,
    tier: found.tier,
    fee,
    court_count: courtCount,
    period_months: periodMonths,
    period_start: periodStart,
    period_end: periodEnd,
    due_date: dueDate,
    warnings: [...fee.warnings, ...overlap.warnings],
  };
}

export function createLedger(payload) {
  const preview = calculateLedgerPreview(payload);
  if (!preview.isValid) return Promise.reject(Object.assign(new Error(preview.error), { preview }));

  const now = new Date().toISOString();
  const ledger = {
    id: createId('ledger'),
    code: `PF-${new Date().getFullYear()}-${String(platformFeeStore.state.ledgers.length + 1).padStart(4, '0')}`,
    venue_cluster_id: preview.venue.id,
    tier_id: preview.tier.id,
    tier_name: preview.tier.name,
    court_count: preview.court_count,
    period_months: preview.period_months,
    billing_cycle: `${preview.period_months}_months`,
    period_start: preview.period_start,
    period_end: preview.period_end,
    due_date: preview.due_date,
    price_per_court_month: preview.tier.price_per_court_month,
    discount_percent: preview.fee.discount_percent,
    base_amount: preview.fee.base_amount,
    discount_amount: preview.fee.discount_amount,
    amount_due: preview.fee.amount_due,
    amount_paid: 0,
    status: 'pending',
    paid_at: null,
    overdue_at: null,
    cancelled_reason: '',
    receipt_id: null,
    created_at: now,
    updated_at: now,
  };

  platformFeeStore.state.ledgers.unshift(ledger);
  platformFeeStore.save();
  notifyOwnerPlatformFeeCreated(ledger);
  addAuditLog('platform_fee_ledger.created', 'venue_platform_fee_ledger', ledger.id, null, ledger, 'platform_fee_ledger');
  return Promise.resolve(decorateLedger(ledger));
}

export function confirmLedgerPayment(id, payload = {}) {
  const ledger = findLedger(id);
  if (!ledger) return Promise.reject(new Error('Khong tim thay ky phi.'));
  if (ledger.status === 'cancelled') return Promise.reject(new Error('Ky phi da huy khong the thanh toan.'));

  const oldLedger = cloneValue(ledger);
  const amount = Number(payload.amount || remainingAmount(ledger));
  if (!Number.isFinite(amount) || amount <= 0) return Promise.reject(new Error('So tien thanh toan phai lon hon 0.'));

  ledger.amount_paid = Math.min(Number(ledger.amount_due), Number(ledger.amount_paid || 0) + amount);
  ledger.updated_at = new Date().toISOString();

  if (remainingAmount(ledger) === 0) {
    ledger.status = 'paid';
    ledger.paid_at = payload.paid_at || new Date().toISOString();
    const receipt = generatePlatformFeeReceipt(ledger);
    ledger.receipt_id = receipt.id;
    notifyOwnerPlatformFeePaid(ledger);
    addAuditLog('platform_fee_ledger.paid', 'venue_platform_fee_ledger', id, oldLedger, ledger, 'platform_fee_ledger');
  } else {
    addAuditLog('platform_fee_ledger.partial_paid', 'venue_platform_fee_ledger', id, oldLedger, ledger, 'platform_fee_ledger');
  }

  platformFeeStore.save();
  return Promise.resolve(decorateLedger(ledger));
}

export function markLedgerOverdue(id, reason = 'Qua han thanh toan') {
  const ledger = findLedger(id);
  if (!ledger) return Promise.reject(new Error('Khong tim thay ky phi.'));
  if (ledger.status === 'paid' || ledger.status === 'cancelled') return Promise.reject(new Error('Ky phi da ket thuc khong the danh dau qua han.'));

  const oldLedger = cloneValue(ledger);
  ledger.status = 'overdue';
  ledger.overdue_at = new Date().toISOString();
  ledger.overdue_reason = reason;
  ledger.updated_at = new Date().toISOString();
  platformFeeStore.save();
  notifyOwnerPlatformFeeOverdue(ledger);
  addAuditLog('platform_fee_ledger.overdue', 'venue_platform_fee_ledger', id, oldLedger, ledger, 'platform_fee_ledger');
  return Promise.resolve(decorateLedger(ledger));
}

export function cancelLedger(id, reason) {
  if (!String(reason || '').trim()) return Promise.reject(new Error('Vui long nhap ly do huy ky phi.'));
  const ledger = findLedger(id);
  if (!ledger) return Promise.reject(new Error('Khong tim thay ky phi.'));
  if (ledger.status === 'paid') return Promise.reject(new Error('Ky phi da thanh toan khong duoc huy.'));

  const oldLedger = cloneValue(ledger);
  ledger.status = 'cancelled';
  ledger.cancelled_reason = String(reason).trim();
  ledger.updated_at = new Date().toISOString();
  platformFeeStore.save();
  addAuditLog('platform_fee_ledger.cancelled', 'venue_platform_fee_ledger', id, oldLedger, ledger, 'platform_fee_ledger');
  return Promise.resolve(decorateLedger(ledger));
}

export function lockVenueForOverdueLedger(id, reason = platformFeeStore.state.settings.lock_reason) {
  const ledger = findLedger(id);
  if (!ledger) return Promise.reject(new Error('Khong tim thay ky phi.'));
  if (ledger.status !== 'overdue') return Promise.reject(new Error('Chi khoa cum khi ky phi da qua han.'));
  if (!String(reason || '').trim()) return Promise.reject(new Error('Vui long nhap ly do khoa cum san.'));

  const venue = findVenue(ledger.venue_cluster_id);
  if (!venue) return Promise.reject(new Error('Khong tim thay cum san.'));

  const oldVenue = cloneValue(venue);
  venue.status = 'locked';
  venue.status_reason = reason;
  venue.locked_at = new Date().toISOString();
  venue.locked_by = 'admin-mock';
  platformFeeStore.save();
  notifyOwnerVenueLocked(venue);
  addAuditLog('venue.locked_by_platform_fee_overdue', 'venue_cluster', venue.id, oldVenue, venue, 'platform_fee_ledger');
  return Promise.resolve(cloneValue(venue));
}

export function unlockVenueAfterPayment(id) {
  const ledger = findLedger(id);
  if (!ledger) return Promise.reject(new Error('Khong tim thay ky phi.'));
  if (ledger.status !== 'paid') return Promise.reject(new Error('Chi mo khoa sau khi ky phi da thanh toan du.'));

  const venue = findVenue(ledger.venue_cluster_id);
  if (!venue) return Promise.reject(new Error('Khong tim thay cum san.'));

  const oldVenue = cloneValue(venue);
  venue.status = 'active';
  venue.status_reason = '';
  venue.locked_at = null;
  venue.locked_by = null;
  platformFeeStore.save();
  notifyOwnerVenueUnlocked(venue);
  addAuditLog('venue.unlocked_after_platform_fee_paid', 'venue_cluster', venue.id, oldVenue, venue, 'platform_fee_ledger');
  return Promise.resolve(cloneValue(venue));
}

export function getPlatformFeeDashboardMetrics() {
  const ledgers = platformFeeStore.state.ledgers;
  const now = new Date();
  const paidThisMonth = ledgers.filter((ledger) => {
    if (!ledger.paid_at) return false;
    const paid = new Date(ledger.paid_at);
    return paid.getMonth() === now.getMonth() && paid.getFullYear() === now.getFullYear();
  });
  const emailSentToday = platformFeeStore.state.emailLogs.filter((log) => log.status === 'sent' && dateOnly(log.sent_at) === dateOnly(now));

  return {
    pending: ledgers.filter((ledger) => ledger.status === 'pending').length,
    overdue: ledgers.filter((ledger) => ledger.status === 'overdue').length,
    pending_amount: ledgers.filter((ledger) => ledger.status === 'pending').reduce((sum, ledger) => sum + remainingAmount(ledger), 0),
    overdue_amount: ledgers.filter((ledger) => ledger.status === 'overdue').reduce((sum, ledger) => sum + remainingAmount(ledger), 0),
    paid_this_month: paidThisMonth.reduce((sum, ledger) => sum + Number(ledger.amount_paid || 0), 0),
    locked_venues: platformFeeStore.state.venues.filter((venue) => venue.status === 'locked' && venue.status_reason?.toLowerCase().includes('phi')).length,
    email_sent_today: emailSentToday.length,
    email_failed: platformFeeStore.state.emailLogs.filter((log) => log.status === 'failed').length,
  };
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
