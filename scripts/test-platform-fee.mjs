import assert from 'node:assert/strict';
import { platformFeeStore } from '../resources/js/stores/platformFee.store.js';
import {
  calculatePlatformFee,
  findTierForCourtCount,
  getTiers,
  updateTier,
  validateTier,
  validateTierCoverage,
} from '../resources/js/services/platformFeeTier.service.js';
import {
  cancelLedger,
  checkLedgerPeriodOverlap,
  confirmLedgerPayment,
  createLedger,
  getLedgers,
  getPlatformFeeDashboardMetrics,
  lockVenueForOverdueLedger,
  markLedgerOverdue,
  unlockVenueAfterPayment,
} from '../resources/js/services/platformFeeLedger.service.js';
import {
  getReminderTypeForDate,
  processPlatformFeeReminders,
} from '../resources/js/services/platformFeeReminder.service.js';

const results = [];

function pass(name) {
  results.push({ name, status: 'PASS' });
}

function fail(name, error) {
  results.push({ name, status: 'FAIL', error: error.message });
}

async function runCase(name, fn) {
  try {
    platformFeeStore.reset();
    await fn();
    pass(name);
  } catch (error) {
    fail(name, error);
  }
}

const validTiers = [
  { id: 'a', name: 'A', min_courts: 1, max_courts: 3, price_per_court_month: 50000, is_active: true },
  { id: 'b', name: 'B', min_courts: 4, max_courts: 7, price_per_court_month: 45000, is_active: true },
  { id: 'c', name: 'C', min_courts: 8, max_courts: null, price_per_court_month: 40000, is_active: true },
].map((tier) => ({
  discount_1_month: 0,
  discount_3_months: 5,
  discount_6_months: 10,
  discount_9_months: 12,
  discount_12_months: 15,
  note: '',
  ...tier,
}));

await runCase('A. Validate bac phi co ban', () => {
  assert.equal(validateTier({ ...validTiers[0], name: '' }, validTiers).isValid, false);
  assert.equal(validateTier({ ...validTiers[0], min_courts: 0 }, validTiers).isValid, false);
  assert.equal(validateTier({ ...validTiers[0], max_courts: 0 }, validTiers).isValid, false);
  assert.equal(validateTier({ ...validTiers[0], price_per_court_month: 0 }, validTiers).isValid, false);
  assert.equal(validateTier({ ...validTiers[0], discount_3_months: 120 }, validTiers).isValid, false);
  assert.equal(validateTierCoverage([...validTiers, { ...validTiers[2], id: 'd', name: 'D' }]).isValid, false);
});

await runCase('B. Validate overlap va ho khoang', () => {
  assert.equal(validateTierCoverage([{ ...validTiers[0] }, { ...validTiers[1], min_courts: 3 }, validTiers[2]]).isValid, false);
  assert.equal(validateTierCoverage([{ ...validTiers[0] }, { ...validTiers[1], min_courts: 5 }, validTiers[2]]).isValid, false);
  assert.equal(validateTierCoverage(validTiers).isValid, true);
  assert.equal(validateTierCoverage([{ ...validTiers[0], min_courts: 2 }, validTiers[1], validTiers[2]]).isValid, false);
  assert.equal(validateTierCoverage([validTiers[0], { ...validTiers[2], min_courts: 4 }, { ...validTiers[1], min_courts: 9, max_courts: 12 }]).isValid, false);
});

await runCase('C. Tim bac theo so san', () => {
  platformFeeStore.state.tiers = validTiers;
  assert.equal(findTierForCourtCount(2).tier.id, 'a');
  assert.equal(findTierForCourtCount(5).tier.id, 'b');
  assert.equal(findTierForCourtCount(10).tier.id, 'c');
  platformFeeStore.state.tiers = [validTiers[0]];
  assert.equal(findTierForCourtCount(5).tier, null);
  platformFeeStore.state.tiers = [validTiers[0], { ...validTiers[0], id: 'x', name: 'X', min_courts: 2, max_courts: 4 }];
  assert.equal(findTierForCourtCount(2).tier, null);
});

await runCase('D. Tinh phi', () => {
  assert.equal(calculatePlatformFee({ court_count: 3, period_months: 1, tier: validTiers[0] }).amount_due, 150000);
  assert.equal(calculatePlatformFee({ court_count: 3, period_months: 3, tier: validTiers[0] }).amount_due, 427500);
  assert.equal(calculatePlatformFee({ court_count: 5, period_months: 6, tier: validTiers[1] }).amount_due, 1215000);
  assert.equal(calculatePlatformFee({ court_count: 8, period_months: 12, tier: validTiers[2] }).amount_due, 3264000);
  assert.ok(calculatePlatformFee({ court_count: 1, period_months: 1, tier: { ...validTiers[0], discount_1_month: 60 } }).warnings.length);
  assert.equal(calculatePlatformFee({ court_count: 1, period_months: 1, tier: { ...validTiers[0], discount_1_month: 100 } }).amount_due, 0);
});

await runCase('E. Tao ledger', async () => {
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 3, period_start: '2026-08-01', due_date: '2026-08-31' });
  assert.equal(ledger.status, 'pending');
  assert.equal(ledger.court_count, 5);
  assert.equal(ledger.tier_id, 'tier-medium');
  assert.ok(platformFeeStore.state.notifications.length);
  assert.ok(platformFeeStore.state.auditLogs.some((log) => log.action === 'platform_fee_ledger.created'));
});

await runCase('F. Chan ledger trung ky', async () => {
  await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-06-01', period_end: '2026-06-30', due_date: '2026-06-30' });
  assert.equal(checkLedgerPeriodOverlap('venue-beta', '2026-06-15', '2026-07-15').hasOverlap, true);
  assert.equal(checkLedgerPeriodOverlap('venue-beta', '2026-07-01', '2026-07-31').hasOverlap, false);
  const ledger = (await getLedgers({ venue_cluster_id: 'venue-beta' }))[0];
  await cancelLedger(ledger.id, 'Tao lai ky phi');
  assert.equal(checkLedgerPeriodOverlap('venue-beta', '2026-06-15', '2026-07-15').warnings.length, 1);
});

await runCase('G. Snapshot', async () => {
  const ledger = await createLedger({ venue_cluster_id: 'venue-alpha', period_months: 1, period_start: '2026-09-01' });
  const tier = platformFeeStore.state.tiers.find((item) => item.id === ledger.tier_id);
  tier.price_per_court_month = 60000;
  const oldLedger = (await getLedgers({ venue_cluster_id: 'venue-alpha' })).find((item) => item.id === ledger.id);
  assert.equal(oldLedger.price_per_court_month, 50000);
});

await runCase('H. Them san con', async () => {
  platformFeeStore.state.venues.find((item) => item.id === 'venue-beta').court_count = 7;
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-10-01' });
  platformFeeStore.state.venues.find((item) => item.id === 'venue-beta').court_count = 8;
  const next = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-11-01' });
  assert.equal(ledger.court_count, 7);
  assert.equal(next.court_count, 8);
  assert.equal(next.tier_id, 'tier-large');
});

await runCase('I. Thanh toan phi', async () => {
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-12-01' });
  let paid = await confirmLedgerPayment(ledger.id, { amount: 100000 });
  assert.equal(paid.status, 'pending');
  paid = await confirmLedgerPayment(ledger.id, { amount: paid.remaining_amount });
  assert.equal(paid.status, 'paid');
  assert.ok(paid.paid_at);
  assert.ok(paid.receipt);
});

await runCase('J. Qua han va khoa cum', async () => {
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-01-01', due_date: '2026-01-31' });
  await markLedgerOverdue(ledger.id, 'Qua han');
  await lockVenueForOverdueLedger(ledger.id, 'Qua han phi duy tri he thong');
  assert.equal(platformFeeStore.state.venues.find((venue) => venue.id === 'venue-beta').status, 'locked');
});

await runCase('K. Mo khoa sau thanh toan', async () => {
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-02-01' });
  await markLedgerOverdue(ledger.id, 'Qua han');
  await lockVenueForOverdueLedger(ledger.id, 'Qua han phi duy tri he thong');
  await confirmLedgerPayment(ledger.id, { amount: ledger.amount_due });
  await unlockVenueAfterPayment(ledger.id);
  assert.equal(platformFeeStore.state.venues.find((venue) => venue.id === 'venue-beta').status, 'active');
});

await runCase('L. Huy ky phi', async () => {
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-03-01' });
  await assert.rejects(() => cancelLedger(ledger.id, ''));
  const cancelled = await cancelLedger(ledger.id, 'Nhap sai ky');
  assert.equal(cancelled.status, 'cancelled');
  const paid = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-04-01' });
  await confirmLedgerPayment(paid.id, { amount: paid.amount_due });
  await assert.rejects(() => cancelLedger(paid.id, 'Khong huy paid'));
});

await runCase('M. Dashboard sync', async () => {
  const before = getPlatformFeeDashboardMetrics();
  const ledger = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2026-05-01' });
  assert.equal(getPlatformFeeDashboardMetrics().pending, before.pending + 1);
  await markLedgerOverdue(ledger.id, 'Qua han');
  assert.equal(getPlatformFeeDashboardMetrics().overdue, before.overdue + 1);
  await lockVenueForOverdueLedger(ledger.id, 'Qua han phi duy tri he thong');
  assert.ok(getPlatformFeeDashboardMetrics().locked_venues >= before.locked_venues);
});

await runCase('N. Email nhac dong phi', async () => {
  const dueSoon = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2027-06-01', due_date: '2027-06-15' });
  assert.equal(getReminderTypeForDate(dueSoon, '2027-06-08'), 'due_soon_7_days');
  let logs = await processPlatformFeeReminders('2027-06-08');
  assert.equal(logs.length, 1);
  logs = await processPlatformFeeReminders('2027-06-08');
  assert.equal(logs.length, 0);

  const dueToday = await createLedger({ venue_cluster_id: 'venue-alpha', period_months: 1, period_start: '2027-07-01', due_date: '2027-07-15' });
  logs = await processPlatformFeeReminders('2027-07-15');
  assert.ok(logs.some((log) => log.ledger_id === dueToday.id && log.type === 'due_today'));

  const overdue = await createLedger({ venue_cluster_id: 'venue-beta', period_months: 1, period_start: '2027-08-01', due_date: '2027-08-15' });
  logs = await processPlatformFeeReminders('2027-08-18');
  assert.ok(logs.some((log) => log.ledger_id === overdue.id && log.type === 'overdue_3_days'));
});

await runCase('O. Tu can lai khoang bac phi sau khi sua bac truoc', async () => {
  const saved = await updateTier('tier-small', { max_courts: 5 });
  const tiers = getTiers();
  const small = tiers.find((tier) => tier.id === 'tier-small');
  const medium = tiers.find((tier) => tier.id === 'tier-medium');
  const large = tiers.find((tier) => tier.id === 'tier-large');

  assert.equal(saved.max_courts, 5);
  assert.equal(small.min_courts, 1);
  assert.equal(small.max_courts, 5);
  assert.equal(medium.min_courts, 6);
  assert.equal(medium.max_courts, 7);
  assert.equal(medium.is_active, true);
  assert.equal(large.min_courts, 8);
  assert.equal(large.is_active, true);
  assert.ok(saved.range_adjustments.some((message) => message.includes(medium.name)));
  assert.equal(validateTierCoverage(tiers).isValid, true);
});

await runCase('P. Tu vo hieu hoa bac sau khi bac truoc khong gioi han', async () => {
  const saved = await updateTier('tier-medium', { max_courts: '' });
  const tiers = getTiers();
  const medium = tiers.find((tier) => tier.id === 'tier-medium');
  const large = tiers.find((tier) => tier.id === 'tier-large');

  assert.equal(saved.max_courts, null);
  assert.equal(medium.min_courts, 4);
  assert.equal(medium.max_courts, null);
  assert.equal(large.is_active, false);
  assert.ok(large.inactive_reason);
  assert.ok(saved.range_adjustments.some((message) => message.includes(large.name)));
  assert.equal(validateTierCoverage(tiers).isValid, true);
});

const failed = results.filter((result) => result.status === 'FAIL');
results.forEach((result) => {
  console.log(`${result.status} ${result.name}${result.error ? ` - ${result.error}` : ''}`);
});

if (failed.length) process.exit(1);
