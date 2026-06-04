import { addAuditLog } from './audit.service.js';
import {
  notifyOwnerFeeDueSoon,
  notifyOwnerFeeDueToday,
  notifyOwnerFeeOverdueThreeDays,
} from './notification.service.js';
import { markLedgerOverdue } from './platformFeeLedger.service.js';
import { createId, cloneValue, platformFeeStore } from '../stores/platformFee.store.js';

function dateOnly(value) {
  return new Date(value).toISOString().slice(0, 10);
}

function diffDays(left, right) {
  const a = new Date(dateOnly(left));
  const b = new Date(dateOnly(right));
  return Math.round((a - b) / 86400000);
}

function findLedger(id) {
  return platformFeeStore.state.ledgers.find((ledger) => ledger.id === id);
}

function findVenue(venueId) {
  return platformFeeStore.state.venues.find((venue) => venue.id === venueId);
}

function reminderAction(type) {
  return {
    due_soon_7_days: 'platform_fee_reminder.email_sent_7_days_before',
    due_today: 'platform_fee_reminder.email_sent_due_today',
    overdue_3_days: 'platform_fee_reminder.email_sent_3_days_overdue',
  }[type] || 'platform_fee_reminder.email_failed';
}

export function getRemainingAmount(ledger) {
  return Math.max(0, Number(ledger.amount_due || 0) - Number(ledger.amount_paid || 0));
}

export function getReminderTypeForDate(ledger, today = new Date()) {
  if (!ledger || ledger.status === 'paid' || ledger.status === 'cancelled' || getRemainingAmount(ledger) === 0) return null;
  const daysUntilDue = diffDays(ledger.due_date, today);
  if (daysUntilDue === 7) return 'due_soon_7_days';
  if (daysUntilDue === 0) return 'due_today';
  if (daysUntilDue === -3) return 'overdue_3_days';
  return null;
}

export function shouldSendPlatformFeeReminder(ledger, reminderType) {
  if (!ledger || !reminderType) return false;
  if (ledger.status === 'paid' || ledger.status === 'cancelled' || getRemainingAmount(ledger) === 0) return false;
  return !platformFeeStore.state.emailLogs.some((log) =>
    log.ledger_id === ledger.id &&
    log.type === reminderType &&
    ['sent', 'queued'].includes(log.status));
}

export function queuePlatformFeeReminderEmail(ledger, reminderType) {
  const venue = findVenue(ledger.venue_cluster_id);
  const email = venue?.owner?.email || '';
  const log = {
    id: createId('email'),
    ledger_id: ledger.id,
    venue_cluster_id: ledger.venue_cluster_id,
    type: reminderType,
    email,
    subject: reminderSubject(reminderType),
    content: reminderContent(ledger, reminderType),
    status: 'queued',
    queued_at: new Date().toISOString(),
    sent_at: null,
    error_reason: '',
  };

  platformFeeStore.state.emailLogs.unshift(log);
  platformFeeStore.save();
  return log;
}

export function sendPlatformFeeReminderEmail(ledger, reminderType) {
  const existing = platformFeeStore.state.emailLogs.find((log) =>
    log.ledger_id === ledger.id &&
    log.type === reminderType &&
    ['sent', 'queued'].includes(log.status));

  if (existing?.status === 'sent') {
    addAuditLog('platform_fee_reminder.email_skipped_duplicate', 'venue_platform_fee_ledger', ledger.id, null, existing, 'platform_fee_reminder');
    return cloneValue(existing);
  }

  const log = existing || queuePlatformFeeReminderEmail(ledger, reminderType);
  const venue = findVenue(ledger.venue_cluster_id);

  if (!venue?.owner?.email) {
    log.status = 'failed';
    log.error_reason = 'Owner khong co email.';
    log.sent_at = new Date().toISOString();
    platformFeeStore.save();
    addAuditLog('platform_fee_reminder.email_failed', 'venue_platform_fee_ledger', ledger.id, null, log, 'platform_fee_reminder');
    return cloneValue(log);
  }

  log.email = venue.owner.email;
  log.status = 'sent';
  log.sent_at = new Date().toISOString();
  log.error_reason = '';
  platformFeeStore.save();

  if (reminderType === 'due_soon_7_days') notifyOwnerFeeDueSoon(ledger);
  if (reminderType === 'due_today') notifyOwnerFeeDueToday(ledger);
  if (reminderType === 'overdue_3_days') notifyOwnerFeeOverdueThreeDays(ledger);

  addAuditLog(reminderAction(reminderType), 'venue_platform_fee_ledger', ledger.id, null, log, 'platform_fee_reminder');
  return cloneValue(log);
}

export function processPlatformFeeReminders(today = new Date()) {
  const results = [];
  platformFeeStore.state.ledgers.forEach((ledger) => {
    if (ledger.status === 'pending' && diffDays(ledger.due_date, today) < 0 && platformFeeStore.state.settings.auto_mark_overdue) {
      markLedgerOverdue(ledger.id, 'Tu dong chuyen qua han theo ngay den han.');
    }

    const freshLedger = findLedger(ledger.id);
    const type = getReminderTypeForDate(freshLedger, today);
    if (!type) return;

    if (!shouldSendPlatformFeeReminder(freshLedger, type)) {
      addAuditLog('platform_fee_reminder.email_skipped_duplicate', 'venue_platform_fee_ledger', freshLedger.id, null, { type }, 'platform_fee_reminder');
      return;
    }

    results.push(sendPlatformFeeReminderEmail(freshLedger, type));
  });

  return Promise.resolve(results);
}

export function getEmailLogsByLedgerId(ledgerId) {
  return Promise.resolve(cloneValue(platformFeeStore.state.emailLogs.filter((log) => log.ledger_id === ledgerId)));
}

export function reminderSubject(type) {
  return {
    due_soon_7_days: 'Phi duy tri sap den han',
    due_today: 'Hom nay la han dong phi duy tri',
    overdue_3_days: 'Phi duy tri da qua han 3 ngay',
  }[type] || 'Thong bao phi duy tri';
}

export function reminderContent(ledger, type) {
  const venue = findVenue(ledger.venue_cluster_id);
  const remaining = getRemainingAmount(ledger).toLocaleString('vi-VN');
  const line = {
    due_soon_7_days: 'se den han sau 7 ngay',
    due_today: 'den han trong hom nay',
    overdue_3_days: 'da qua han 3 ngay va co the bi khoa',
  }[type] || 'can xu ly';
  return `Ky phi duy tri cua cum san ${venue?.name || ''} ${line}. So tien con lai: ${remaining} VND.`;
}

export const platformFeeReminderService = {
  getRemainingAmount,
  getReminderTypeForDate,
  shouldSendPlatformFeeReminder,
  queuePlatformFeeReminderEmail,
  sendPlatformFeeReminderEmail,
  processPlatformFeeReminders,
  getEmailLogsByLedgerId,
};
