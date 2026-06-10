export function processPlatformFeeReminders(today = new Date()) {
  return Promise.resolve([]);
}

export function getEmailLogsByLedgerId(ledgerId) {
  return Promise.resolve([]);
}

export function getReminderTypeForDate(ledger, today = new Date()) {
  return null;
}

export function sendPlatformFeeReminderEmail(ledger, reminderType) {
  return Promise.resolve(null);
}

export const platformFeeReminderService = {
  processPlatformFeeReminders,
  getEmailLogsByLedgerId,
  getReminderTypeForDate,
  sendPlatformFeeReminderEmail,
};
