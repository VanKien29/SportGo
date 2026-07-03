import { api } from './api.js';
import { getLedgers, markLedgerOverdue } from './platformFeeLedger.service.js';

function dateOnly(value) {
  return new Date(value).toISOString().slice(0, 10);
}

function diffDays(left, right) {
  const a = new Date(dateOnly(left));
  const b = new Date(dateOnly(right));
  return Math.round((a - b) / 86400000);
}

export function getRemainingAmount(ledger) {
  return Math.max(0, Number(ledger.amount_due || 0) - Number(ledger.amount_paid || 0));
}

export function getReminderTypeForDate(ledger, today = new Date(), leadDays = 7) {
  if (!ledger || ledger.status === 'paid' || ledger.status === 'cancelled' || getRemainingAmount(ledger) === 0) return null;
  const daysUntilDue = diffDays(ledger.due_date, today);
  if (daysUntilDue === leadDays) return `due_soon_${leadDays}_days`;
  if (daysUntilDue === 0) return 'due_today';
  if (daysUntilDue === -3) return 'overdue_3_days';
  return null;
}

export function shouldSendPlatformFeeReminder(ledger, reminderType) {
  if (!ledger || !reminderType) return false;
  if (ledger.status === 'paid' || ledger.status === 'cancelled' || getRemainingAmount(ledger) === 0) return false;
  return !(ledger.email_logs || []).some((log) =>
    log.ledger_id === ledger.id &&
    log.type === reminderType &&
    ['sent', 'queued'].includes(log.status));
}

export function queuePlatformFeeReminderEmail(ledger, reminderType) {
  return sendPlatformFeeReminderEmail(ledger, reminderType);
}

export async function sendPlatformFeeReminderEmail(ledger, reminderType, options = {}) {
  const response = await api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(ledger.id)}/reminders`, {
    method: 'POST',
    body: JSON.stringify({
      type: reminderType,
      force: Boolean(options.force),
    }),
  });

  return response.data || response;
}

export async function processPlatformFeeReminders(today = new Date()) {
  const results = [];
  const [ledgers, settings] = await Promise.all([
    getLedgers(),
    api('/api/admin/platform-fee-settings'),
  ]);
  const leadDays = Number(settings.default_due_days || 7);

  for (const ledger of ledgers) {
    let freshLedger = ledger;
    if (ledger.status === 'pending' && diffDays(ledger.due_date, today) < 0) {
      freshLedger = await markLedgerOverdue(ledger.id, 'Tự động chuyển quá hạn theo ngày đến hạn.');
    }

    const type = getReminderTypeForDate(freshLedger, today, leadDays);
    if (!type) continue;

    if (!shouldSendPlatformFeeReminder(freshLedger, type)) {
      continue;
    }

    results.push(await sendPlatformFeeReminderEmail(freshLedger, type));
  }

  return results;
}

export function getEmailLogsByLedgerId(ledgerId) {
  return api(`/api/admin/platform-fee-ledgers/${encodeURIComponent(ledgerId)}/email-logs`);
}

export function reminderSubject(type) {
  if (String(type).startsWith('due_soon_')) return 'Phí duy trì sắp đến hạn';
  return {
    due_today: 'Hôm nay là hạn đóng phí duy trì',
    overdue_3_days: 'Phí duy trì đã quá hạn 3 ngày',
  }[type] || 'Thông báo phí duy trì';
}

export function reminderContent(ledger, type) {
  const remaining = getRemainingAmount(ledger).toLocaleString('vi-VN');
  const leadDays = String(type).match(/^due_soon_(\d+)_days$/)?.[1];
  const line = leadDays ? `sẽ đến hạn sau ${leadDays} ngày` : ({
    due_today: 'đến hạn trong hôm nay',
    overdue_3_days: 'đã quá hạn 3 ngày và có thể bị khóa',
  }[type] || 'cần xử lý');
  return `Kỳ phí duy trì của cụm sân ${ledger.venue?.name || ''} ${line}. Số tiền còn lại: ${remaining} VND.`;
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
