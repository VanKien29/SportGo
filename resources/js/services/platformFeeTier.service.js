import { addAuditLog } from './audit.service.js';
import { createId, cloneValue, platformFeeStore } from '../stores/platformFee.store.js';

export const ALLOWED_PERIOD_MONTHS = [1, 3, 6, 9, 12];

function toNumber(value, fallback = 0) {
  if (value === '' || value === null || value === undefined) return fallback;
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}

function normalizeTier(payload) {
  return {
    ...payload,
    name: String(payload.name || '').trim(),
    min_courts: Number.parseInt(payload.min_courts, 10),
    max_courts: payload.max_courts === '' || payload.max_courts === null || payload.max_courts === undefined
      ? null
      : Number.parseInt(payload.max_courts, 10),
    price_per_court_month: toNumber(payload.price_per_court_month),
    discount_1_month: toNumber(payload.discount_1_month),
    discount_3_months: toNumber(payload.discount_3_months),
    discount_6_months: toNumber(payload.discount_6_months),
    discount_9_months: toNumber(payload.discount_9_months),
    discount_12_months: toNumber(payload.discount_12_months),
    is_active: Boolean(payload.is_active),
    note: String(payload.note || '').trim(),
  };
}

function addFieldError(errors, field, message) {
  if (!errors[field]) errors[field] = [];
  errors[field].push(message);
}

function discountFields() {
  return ['discount_1_month', 'discount_3_months', 'discount_6_months', 'discount_9_months', 'discount_12_months'];
}

export function validateTier(payload, existingTiers = getTiers()) {
  const tier = normalizeTier(payload);
  const errors = {};
  const editingId = payload.id || null;

  if (!tier.name) addFieldError(errors, 'name', 'Vui long nhap ten bac phi.');
  if (tier.name && existingTiers.some((item) => item.id !== editingId && item.is_active && item.name.trim().toLowerCase() === tier.name.toLowerCase())) {
    addFieldError(errors, 'name', 'Ten bac phi dang trung voi bac active khac.');
  }

  if (!Number.isInteger(tier.min_courts)) addFieldError(errors, 'min_courts', 'Vui long nhap so san toi thieu.');
  if (Number.isInteger(tier.min_courts) && tier.min_courts < 1) {
    addFieldError(errors, 'min_courts', 'So san toi thieu phai lon hon hoac bang 1.');
  }

  if (tier.max_courts !== null && !Number.isInteger(tier.max_courts)) {
    addFieldError(errors, 'max_courts', 'So san toi da phai la so nguyen.');
  }
  if (Number.isInteger(tier.max_courts) && tier.max_courts < tier.min_courts) {
    addFieldError(errors, 'max_courts', 'So san toi da phai lon hon hoac bang so san toi thieu.');
  }

  if (!Number.isFinite(tier.price_per_court_month) || tier.price_per_court_month <= 0) {
    addFieldError(errors, 'price_per_court_month', 'Gia/san/thang phai lon hon 0.');
  }

  discountFields().forEach((field) => {
    if (!Number.isFinite(tier[field]) || tier[field] < 0 || tier[field] > 100) {
      addFieldError(errors, field, 'Giam gia phai nam trong khoang 0 - 100%.');
    }
  });

  const proposed = existingTiers.map((item) => (item.id === editingId ? { ...item, ...tier, id: editingId } : item));
  if (!editingId) proposed.push({ ...tier, id: '__new__' });
  const unlimitedActive = proposed.filter((item) => item.is_active && item.max_courts === null);
  if (tier.is_active && unlimitedActive.length > 1) {
    addFieldError(errors, 'max_courts', 'Chi duoc co mot bac khong gioi han.');
  }

  const coverage = validateTierCoverage(proposed);
  if (tier.is_active && !coverage.isValid) errors._coverage = coverage.errors;

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    normalized: tier,
    coverage,
  };
}

export function validateTierCoverage(tiers) {
  const activeTiers = cloneValue(tiers)
    .filter((tier) => tier.is_active)
    .sort((left, right) => left.min_courts - right.min_courts);
  const errors = [];
  const warnings = [];
  const missingRanges = [];
  const overlappingRanges = [];

  if (activeTiers.length === 0) {
    errors.push('Chua co bac phi active nao.');
    missingRanges.push({ from: 1, to: null });
    return { isValid: false, errors, warnings, missingRanges, overlappingRanges };
  }

  let expectedMin = 1;
  let hasUnlimited = false;

  activeTiers.forEach((tier, index) => {
    if (hasUnlimited) {
      errors.push('Bac khong gioi han phai la bac cuoi.');
      overlappingRanges.push({ from: tier.min_courts, to: tier.max_courts });
      return;
    }

    if (index === 0 && tier.min_courts !== 1) {
      errors.push('Bac active dau tien phai bat dau tu 1 san.');
      missingRanges.push({ from: 1, to: tier.min_courts - 1 });
    }

    if (tier.min_courts < expectedMin) {
      const previous = activeTiers[index - 1];
      errors.push(`Khoang ${tier.min_courts} - ${tier.max_courts || 'khong gioi han'} bi trung voi khoang ${previous?.min_courts} - ${previous?.max_courts || 'khong gioi han'}.`);
      overlappingRanges.push({ from: tier.min_courts, to: Math.min(tier.max_courts || expectedMin, expectedMin - 1) });
    }

    if (tier.min_courts > expectedMin) {
      errors.push(`Thieu bac phi cho cum co ${expectedMin} san.`);
      missingRanges.push({ from: expectedMin, to: tier.min_courts - 1 });
    }

    if (tier.max_courts === null) {
      hasUnlimited = true;
      return;
    }

    expectedMin = Math.max(expectedMin, tier.max_courts + 1);
  });

  if (!hasUnlimited) {
    errors.push('Thieu bac phi khong gioi han cho cac cum lon hon.');
    missingRanges.push({ from: expectedMin, to: null });
  }

  return {
    isValid: errors.length === 0,
    errors,
    warnings,
    missingRanges,
    overlappingRanges,
  };
}

export function getTiers() {
  return cloneValue(platformFeeStore.state.tiers);
}

export function getActiveTiers() {
  return getTiers().filter((tier) => tier.is_active);
}

export function getTierUsageCount(tierId) {
  return platformFeeStore.state.ledgers.filter((ledger) => ledger.tier_id === tierId).length;
}

export function createTier(payload) {
  const validation = validateTier(payload);
  if (!validation.isValid) return Promise.reject(Object.assign(new Error('Du lieu bac phi chua hop le.'), { validation }));

  const now = new Date().toISOString();
  const tier = {
    ...validation.normalized,
    id: createId('tier'),
    created_at: now,
    updated_at: now,
  };
  platformFeeStore.state.tiers.push(tier);
  platformFeeStore.save();
  addAuditLog('platform_fee_tier.created', 'platform_fee_tier', tier.id, null, tier, 'platform_fee_tier');
  return Promise.resolve(cloneValue(tier));
}

export function updateTier(id, payload) {
  const index = platformFeeStore.state.tiers.findIndex((tier) => tier.id === id);
  if (index === -1) return Promise.reject(new Error('Khong tim thay bac phi.'));

  const oldTier = cloneValue(platformFeeStore.state.tiers[index]);
  const validation = validateTier({ ...oldTier, ...payload, id });
  if (!validation.isValid) return Promise.reject(Object.assign(new Error('Du lieu bac phi chua hop le.'), { validation }));

  const updated = { ...oldTier, ...validation.normalized, id, updated_at: new Date().toISOString() };
  platformFeeStore.state.tiers.splice(index, 1, updated);
  platformFeeStore.save();
  addAuditLog('platform_fee_tier.updated', 'platform_fee_tier', id, oldTier, updated, 'platform_fee_tier');
  return Promise.resolve(cloneValue(updated));
}

export function deactivateTier(id, reason = 'Ngung su dung') {
  const tier = platformFeeStore.state.tiers.find((item) => item.id === id);
  if (!tier) return Promise.reject(new Error('Khong tim thay bac phi.'));

  const oldTier = cloneValue(tier);
  tier.is_active = false;
  tier.inactive_reason = reason;
  tier.updated_at = new Date().toISOString();
  const coverage = validateTierCoverage(platformFeeStore.state.tiers);
  if (!coverage.isValid) {
    Object.assign(tier, oldTier);
    return Promise.reject(Object.assign(new Error('Ngung dung bac phi se lam thieu coverage.'), { coverage }));
  }

  platformFeeStore.save();
  addAuditLog('platform_fee_tier.deactivated', 'platform_fee_tier', id, oldTier, tier, 'platform_fee_tier');
  return Promise.resolve(cloneValue(tier));
}

export function reactivateTier(id) {
  const tier = platformFeeStore.state.tiers.find((item) => item.id === id);
  if (!tier) return Promise.reject(new Error('Khong tim thay bac phi.'));

  const oldTier = cloneValue(tier);
  tier.is_active = true;
  tier.updated_at = new Date().toISOString();
  const coverage = validateTierCoverage(platformFeeStore.state.tiers);
  if (!coverage.isValid) {
    Object.assign(tier, oldTier);
    return Promise.reject(Object.assign(new Error('Kich hoat lai bac phi se lam coverage chua hop le.'), { coverage }));
  }

  platformFeeStore.save();
  addAuditLog('platform_fee_tier.reactivated', 'platform_fee_tier', id, oldTier, tier, 'platform_fee_tier');
  return Promise.resolve(cloneValue(tier));
}

export function deleteTier(id) {
  const usageCount = getTierUsageCount(id);
  if (usageCount > 0) return deactivateTier(id, 'Da co ledger su dung nen chi ngung dung.');

  const index = platformFeeStore.state.tiers.findIndex((tier) => tier.id === id);
  if (index === -1) return Promise.reject(new Error('Khong tim thay bac phi.'));
  const oldTier = platformFeeStore.state.tiers[index];
  platformFeeStore.state.tiers.splice(index, 1);
  platformFeeStore.save();
  addAuditLog('platform_fee_tier.deactivated', 'platform_fee_tier', id, oldTier, null, 'platform_fee_tier');
  return Promise.resolve({ deleted: true });
}

export function findTierForCourtCount(courtCount) {
  const count = Number.parseInt(courtCount, 10);
  const matches = getActiveTiers().filter((tier) => count >= tier.min_courts && (tier.max_courts === null || count <= tier.max_courts));

  if (matches.length === 0) {
    return {
      tier: null,
      matches,
      error: `Chua co bac phi phu hop cho cum co ${count} san. Vui long cau hinh bac phi truoc.`,
    };
  }

  if (matches.length > 1) {
    return {
      tier: null,
      matches,
      error: `Co nhieu bac phi cung ap dung cho ${count} san. Vui long kiem tra lai cau hinh.`,
    };
  }

  return { tier: matches[0], matches, error: '' };
}

export function calculatePlatformFee({ court_count, period_months, tier }) {
  const months = Number.parseInt(period_months, 10);
  if (!ALLOWED_PERIOD_MONTHS.includes(months)) throw new Error('Ky dong phi khong hop le.');
  if (!tier) throw new Error('Chua co bac phi de tinh phi.');

  const field = months === 1
    ? 'discount_1_month'
    : `discount_${months}_months`;
  const discountPercent = toNumber(tier[field]);
  const baseAmount = Number(court_count) * Number(tier.price_per_court_month) * months;
  const discountAmount = Math.round(baseAmount * discountPercent / 100);
  const amountDue = Math.max(0, Math.round(baseAmount - discountAmount));
  const warnings = [];

  if (discountPercent > 50) warnings.push('Muc giam gia cao, vui long kiem tra lai.');
  if (discountPercent === 100 || amountDue === 0) warnings.push('Giam gia 100%, so tien phai dong bang 0.');

  return {
    court_count: Number(court_count),
    period_months: months,
    tier,
    discount_percent: discountPercent,
    base_amount: baseAmount,
    discount_amount: discountAmount,
    amount_due: amountDue,
    warnings,
  };
}

export const platformFeeTierService = {
  getTiers,
  getActiveTiers,
  createTier,
  updateTier,
  deactivateTier,
  reactivateTier,
  deleteTier,
  validateTier,
  validateTierCoverage,
  findTierForCourtCount,
  calculatePlatformFee,
  getTierUsageCount,
};
