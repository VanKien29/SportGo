import { addAuditLog } from './audit.service.js';
import { createId, cloneValue, platformFeeStore } from '../stores/platformFee.store.js';

export const ALLOWED_PERIOD_MONTHS = [1, 3, 6, 9, 12];
export const DISCOUNT_FIELDS = [
  'discount_1_month',
  'discount_3_months',
  'discount_6_months',
  'discount_9_months',
  'discount_12_months',
];

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
    discount_profile_id: payload.discount_profile_id || null,
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

function sortedActiveTiers(tiers) {
  return tiers
    .filter((tier) => tier.is_active)
    .sort((left, right) => left.min_courts - right.min_courts);
}

function rangeLabel(tier) {
  return tier.max_courts === null
    ? `từ ${tier.min_courts} sân trở lên`
    : `${tier.min_courts} - ${tier.max_courts} sân`;
}

function rebalanceTierRanges(tiers) {
  const activeTiers = sortedActiveTiers(tiers);
  const adjustments = [];
  if (!activeTiers.length) return adjustments;

  activeTiers.forEach((tier, index) => {
    const oldMin = tier.min_courts;
    const oldMax = tier.max_courts;
    if (index === 0) tier.min_courts = 1;
    tier.max_courts = index === activeTiers.length - 1
      ? null
      : activeTiers[index + 1].min_courts - 1;

    if (oldMin !== tier.min_courts || oldMax !== tier.max_courts) {
      tier.updated_at = new Date().toISOString();
      adjustments.push(`${tier.name}: ${rangeLabel(tier)}`);
    }
  });

  return adjustments;
}

function proposedRebalancedTiers(tiers) {
  const proposed = cloneValue(tiers);
  rebalanceTierRanges(proposed);
  return proposed;
}

export function validateDiscountSchedule(payload) {
  const errors = {};
  const values = DISCOUNT_FIELDS.map((field) => toNumber(payload[field], Number.NaN));

  DISCOUNT_FIELDS.forEach((field, index) => {
    const value = values[index];
    if (!Number.isFinite(value) || value < 0 || value > 100) {
      addFieldError(errors, field, 'Giảm giá phải nằm trong khoảng 0 - 100%.');
    }
    if (index > 0 && Number.isFinite(value) && Number.isFinite(values[index - 1]) && value <= values[index - 1]) {
      addFieldError(
        errors,
        field,
        `Giảm kỳ ${ALLOWED_PERIOD_MONTHS[index]} tháng phải lớn hơn kỳ ${ALLOWED_PERIOD_MONTHS[index - 1]} tháng.`,
      );
    }
  });

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    normalized: Object.fromEntries(DISCOUNT_FIELDS.map((field, index) => [field, values[index]])),
  };
}

export function validateTier(payload, existingTiers = getTiers(), options = {}) {
  const tier = normalizeTier(payload);
  const errors = {};
  const editingId = payload.id || null;
  const skipCoverage = Boolean(options.skipCoverage);

  if (!tier.name) addFieldError(errors, 'name', 'Vui lòng nhập tên bậc phí.');
  if (tier.name && existingTiers.some((item) => item.id !== editingId && item.is_active && item.name.trim().toLowerCase() === tier.name.toLowerCase())) {
    addFieldError(errors, 'name', 'Tên bậc phí đang trùng với bậc đang dùng khác.');
  }

  if (!Number.isInteger(tier.min_courts)) addFieldError(errors, 'min_courts', 'Vui lòng nhập số sân tối thiểu.');
  if (Number.isInteger(tier.min_courts) && tier.min_courts < 1) {
    addFieldError(errors, 'min_courts', 'Số sân tối thiểu phải lớn hơn hoặc bằng 1.');
  }

  if (tier.max_courts !== null && !Number.isInteger(tier.max_courts)) {
    addFieldError(errors, 'max_courts', 'Số sân tối đa phải là số nguyên.');
  }
  if (Number.isInteger(tier.max_courts) && tier.max_courts < tier.min_courts) {
    addFieldError(errors, 'max_courts', 'Số sân tối đa phải lớn hơn hoặc bằng số sân tối thiểu.');
  }

  if (!Number.isFinite(tier.price_per_court_month) || tier.price_per_court_month <= 0) {
    addFieldError(errors, 'price_per_court_month', 'Giá/sân/tháng phải lớn hơn 0.');
  }

  const discountValidation = validateDiscountSchedule(tier);
  Object.entries(discountValidation.errors).forEach(([field, messages]) => {
    messages.forEach((message) => addFieldError(errors, field, message));
  });

  const proposed = existingTiers.map((item) => (item.id === editingId ? { ...item, ...tier, id: editingId } : item));
  if (!editingId) proposed.push({ ...tier, id: '__new__' });
  const unlimitedActive = proposed.filter((item) => item.is_active && item.max_courts === null);
  if (tier.is_active && unlimitedActive.length > 1 && !skipCoverage) {
    addFieldError(errors, 'max_courts', 'Chỉ được có một bậc không giới hạn.');
  }

  const initialCoverage = validateTierCoverage(proposed);
  if (tier.is_active && !initialCoverage.isValid && !skipCoverage) errors._coverage = initialCoverage.errors;
  const otherActiveTiers = existingTiers.filter((item) => item.id !== editingId && item.is_active);

  if (tier.is_active && otherActiveTiers.some((item) => item.min_courts === tier.min_courts)) {
    addFieldError(errors, 'min_courts', 'Số sân tối thiểu đang trùng với một bậc khác.');
  }

  if (!editingId && tier.is_active && otherActiveTiers.length) {
    const lastTier = sortedActiveTiers(otherActiveTiers).at(-1);
    if (tier.min_courts <= lastTier.min_courts) {
      addFieldError(
        errors,
        'min_courts',
        `Bậc mới phải bắt đầu từ số sân lớn hơn ${lastTier.min_courts}.`,
      );
    }
  }

  const rebalancedCoverage = validateTierCoverage(proposedRebalancedTiers(proposed));
  if (tier.is_active && !rebalancedCoverage.isValid) errors._coverage = rebalancedCoverage.errors;

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    normalized: tier,
    coverage: rebalancedCoverage,
  };
}

export function autoAdjustTierRanges(tiers, editedId) {
  const adjusted = cloneValue(tiers);
  const adjustments = [];
  const editedIndex = adjusted.findIndex((tier) => tier.id === editedId);

  if (editedIndex === -1 || !adjusted[editedIndex].is_active) {
    return { tiers: adjusted, adjustments };
  }

  const now = new Date().toISOString();
  const edited = adjusted[editedIndex];

  const previousActive = adjusted
    .slice(0, editedIndex)
    .filter((tier) => tier.is_active)
    .at(-1);

  if (previousActive?.max_courts !== null && previousActive?.max_courts !== undefined) {
    const nextMin = previousActive.max_courts + 1;
    if (edited.min_courts !== nextMin) {
      adjustments.push(`${edited.name}: tự chuyển số sân tối thiểu từ ${edited.min_courts} sang ${nextMin}.`);
      edited.min_courts = nextMin;
      edited.updated_at = now;
    }
  }

  if (edited.max_courts === null) {
    adjusted.slice(editedIndex + 1).forEach((tier) => {
      if (!tier.is_active) return;
      tier.is_active = false;
      tier.inactive_reason = `Tự ngưng dùng vì ${edited.name} đã là bậc không giới hạn.`;
      tier.updated_at = now;
      adjustments.push(`${tier.name}: tự ngưng dùng vì bậc trước đã bao phủ từ ${edited.min_courts} sân trở lên.`);
    });
    return { tiers: adjusted, adjustments };
  }

  let expectedMin = edited.max_courts + 1;

  for (let index = editedIndex + 1; index < adjusted.length; index += 1) {
    const tier = adjusted[index];
    if (!tier.is_active) continue;

    if (tier.max_courts !== null && tier.max_courts < expectedMin) {
      tier.is_active = false;
      tier.inactive_reason = 'Tự ngưng dùng vì khoảng sân đã được bậc trước bao phủ.';
      tier.updated_at = now;
      adjustments.push(`${tier.name}: tự ngưng dùng vì khoảng ${tier.min_courts} - ${tier.max_courts} đã bị bậc trước bao phủ.`);
      continue;
    }

    if (tier.min_courts !== expectedMin) {
      adjustments.push(`${tier.name}: tự chuyển số sân tối thiểu từ ${tier.min_courts} sang ${expectedMin}.`);
      tier.min_courts = expectedMin;
      tier.updated_at = now;
    }

    if (tier.max_courts === null) break;
    expectedMin = tier.max_courts + 1;
  }

  return { tiers: adjusted, adjustments };
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
    errors.push('Chưa có bậc phí đang dùng nào.');
    missingRanges.push({ from: 1, to: null });
    return { isValid: false, errors, warnings, missingRanges, overlappingRanges };
  }

  let expectedMin = 1;
  let hasUnlimited = false;

  activeTiers.forEach((tier, index) => {
    if (hasUnlimited) {
      errors.push('Bậc không giới hạn phải là bậc cuối.');
      overlappingRanges.push({ from: tier.min_courts, to: tier.max_courts });
      return;
    }

    if (index === 0 && tier.min_courts !== 1) {
      errors.push('Bậc đang dùng đầu tiên phải bắt đầu từ 1 sân.');
      missingRanges.push({ from: 1, to: tier.min_courts - 1 });
    }

    if (tier.min_courts < expectedMin) {
      const previous = activeTiers[index - 1];
      errors.push(`Khoảng ${tier.min_courts} - ${tier.max_courts || 'không giới hạn'} bị trùng với khoảng ${previous?.min_courts} - ${previous?.max_courts || 'không giới hạn'}.`);
      overlappingRanges.push({ from: tier.min_courts, to: Math.min(tier.max_courts || expectedMin, expectedMin - 1) });
    }

    if (tier.min_courts > expectedMin) {
      errors.push(`Thiếu bậc phí cho cụm có ${expectedMin} sân.`);
      missingRanges.push({ from: expectedMin, to: tier.min_courts - 1 });
    }

    if (tier.max_courts === null) {
      hasUnlimited = true;
      return;
    }

    expectedMin = Math.max(expectedMin, tier.max_courts + 1);
  });

  if (!hasUnlimited) {
    errors.push('Thiếu bậc phí không giới hạn cho các cụm lớn hơn.');
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

export function getDiscountProfiles() {
  return cloneValue(platformFeeStore.state.discount_profiles || []);
}

export function createDiscountProfile(payload) {
  const name = String(payload.name || '').trim();
  const validation = validateDiscountSchedule(payload);
  if (!name) addFieldError(validation.errors, 'name', 'Vui lòng nhập tên mẫu giảm giá.');
  if (platformFeeStore.state.discount_profiles.some((profile) => profile.name.toLowerCase() === name.toLowerCase())) {
    addFieldError(validation.errors, 'name', 'Tên mẫu giảm giá đã tồn tại.');
  }
  validation.isValid = Object.keys(validation.errors).length === 0;
  if (!validation.isValid) {
    return Promise.reject(Object.assign(new Error('Mẫu giảm giá chưa hợp lệ.'), { validation }));
  }

  const profile = {
    id: createId('discount'),
    name,
    ...validation.normalized,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };
  platformFeeStore.state.discount_profiles.push(profile);
  platformFeeStore.save();
  addAuditLog('platform_fee_discount_profile.created', 'platform_fee_discount_profile', profile.id, null, profile, 'platform_fee_tier');
  return Promise.resolve(cloneValue(profile));
}

export function updateDiscountProfile(id, payload) {
  const profile = platformFeeStore.state.discount_profiles.find((item) => item.id === id);
  if (!profile) return Promise.reject(new Error('Không tìm thấy mẫu giảm giá.'));

  const name = String(payload.name || '').trim();
  const validation = validateDiscountSchedule(payload);
  if (!name) addFieldError(validation.errors, 'name', 'Vui lòng nhập tên mẫu giảm giá.');
  if (platformFeeStore.state.discount_profiles.some((item) => item.id !== id && item.name.toLowerCase() === name.toLowerCase())) {
    addFieldError(validation.errors, 'name', 'Tên mẫu giảm giá đã tồn tại.');
  }
  validation.isValid = Object.keys(validation.errors).length === 0;
  if (!validation.isValid) {
    return Promise.reject(Object.assign(new Error('Mẫu giảm giá chưa hợp lệ.'), { validation }));
  }

  const oldProfile = cloneValue(profile);
  Object.assign(profile, {
    name,
    ...validation.normalized,
    updated_at: new Date().toISOString(),
  });
  platformFeeStore.state.tiers
    .filter((tier) => tier.discount_profile_id === id)
    .forEach((tier) => {
      Object.assign(tier, validation.normalized, { updated_at: new Date().toISOString() });
    });
  platformFeeStore.save();
  addAuditLog('platform_fee_discount_profile.updated', 'platform_fee_discount_profile', id, oldProfile, profile, 'platform_fee_tier');
  return Promise.resolve(cloneValue(profile));
}

export function deleteDiscountProfile(id) {
  if (platformFeeStore.state.tiers.some((tier) => tier.discount_profile_id === id)) {
    return Promise.reject(new Error('Mẫu giảm giá đang được bậc phí sử dụng.'));
  }
  const index = platformFeeStore.state.discount_profiles.findIndex((profile) => profile.id === id);
  if (index === -1) return Promise.reject(new Error('Không tìm thấy mẫu giảm giá.'));
  const [profile] = platformFeeStore.state.discount_profiles.splice(index, 1);
  platformFeeStore.save();
  addAuditLog('platform_fee_discount_profile.deleted', 'platform_fee_discount_profile', id, profile, null, 'platform_fee_tier');
  return Promise.resolve({ deleted: true });
}

export function createTier(payload) {
  const validation = validateTier(payload, getTiers(), { skipCoverage: true });
  if (!validation.isValid) return Promise.reject(Object.assign(new Error('Dữ liệu bậc phí chưa hợp lệ.'), { validation }));

  const now = new Date().toISOString();
  const tier = {
    ...validation.normalized,
    id: createId('tier'),
    created_at: now,
    updated_at: now,
  };
  const proposed = [...cloneValue(platformFeeStore.state.tiers), tier];
  const rangeAdjustments = rebalanceTierRanges(proposed);
  const coverage = validateTierCoverage(proposed);

  if (tier.is_active && !coverage.isValid) {
    return Promise.reject(Object.assign(new Error('Cấu hình bậc phí sau khi tự cân khoảng chưa hợp lệ.'), {
      validation: { isValid: false, errors: { _coverage: coverage.errors }, normalized: tier, coverage },
    }));
  }

  platformFeeStore.state.tiers = proposed;
  platformFeeStore.save();
  addAuditLog('platform_fee_tier.created', 'platform_fee_tier', tier.id, null, tier, 'platform_fee_tier');
  return Promise.resolve(cloneValue({ ...tier, range_adjustments: rangeAdjustments }));
}

export function updateTier(id, payload) {
  const index = platformFeeStore.state.tiers.findIndex((tier) => tier.id === id);
  if (index === -1) return Promise.reject(new Error('Không tìm thấy bậc phí.'));

  const oldTiers = cloneValue(platformFeeStore.state.tiers);
  const oldTier = cloneValue(platformFeeStore.state.tiers[index]);
  const validation = validateTier({ ...oldTier, ...payload, id }, platformFeeStore.state.tiers, { skipCoverage: true });
  if (!validation.isValid) return Promise.reject(Object.assign(new Error('Dữ liệu bậc phí chưa hợp lệ.'), { validation }));

  const updated = { ...oldTier, ...validation.normalized, id, updated_at: new Date().toISOString() };
  const proposed = cloneValue(platformFeeStore.state.tiers);
  proposed.splice(index, 1, updated);
  const adjusted = autoAdjustTierRanges(proposed, id);
  const coverage = validateTierCoverage(adjusted.tiers);

  if (updated.is_active && !coverage.isValid) {
    return Promise.reject(Object.assign(new Error('Cấu hình bậc phí sau khi tự căn chỉnh vẫn chưa hợp lệ.'), {
      validation: { isValid: false, errors: { _coverage: coverage.errors }, normalized: updated, coverage },
    }));
  }

  platformFeeStore.state.tiers = adjusted.tiers;
  platformFeeStore.save();
  const finalTier = platformFeeStore.state.tiers.find((tier) => tier.id === id);
  addAuditLog('platform_fee_tier.updated', 'platform_fee_tier', id, oldTiers, platformFeeStore.state.tiers, 'platform_fee_tier');
  return Promise.resolve({ ...cloneValue(finalTier), range_adjustments: adjusted.adjustments });
  platformFeeStore.state.tiers.splice(index, 1, updated);
  const rangeAdjustments = rebalanceTierRanges(platformFeeStore.state.tiers);
  platformFeeStore.save();
  addAuditLog('platform_fee_tier.updated', 'platform_fee_tier', id, oldTier, updated, 'platform_fee_tier');
  return Promise.resolve(cloneValue({ ...updated, range_adjustments: rangeAdjustments }));
}

export function deactivateTier(id, reason = 'Ngừng sử dụng') {
  const tier = platformFeeStore.state.tiers.find((item) => item.id === id);
  if (!tier) return Promise.reject(new Error('Không tìm thấy bậc phí.'));

  const oldTier = cloneValue(tier);
  tier.is_active = false;
  tier.inactive_reason = reason;
  tier.updated_at = new Date().toISOString();
  const rangeAdjustments = rebalanceTierRanges(platformFeeStore.state.tiers);
  const coverage = validateTierCoverage(platformFeeStore.state.tiers);
  if (!coverage.isValid) {
    Object.assign(tier, oldTier);
    return Promise.reject(Object.assign(new Error('Ngừng dùng bậc phí sẽ làm thiếu khoảng áp dụng.'), { coverage }));
  }

  platformFeeStore.save();
  addAuditLog('platform_fee_tier.deactivated', 'platform_fee_tier', id, oldTier, tier, 'platform_fee_tier');
  return Promise.resolve(cloneValue({ ...tier, range_adjustments: rangeAdjustments }));
}

export function reactivateTier(id) {
  const tier = platformFeeStore.state.tiers.find((item) => item.id === id);
  if (!tier) return Promise.reject(new Error('Không tìm thấy bậc phí.'));

  const oldTier = cloneValue(tier);
  tier.is_active = true;
  tier.updated_at = new Date().toISOString();
  const activeWithSameMinimum = platformFeeStore.state.tiers.some(
    (item) => item.id !== id && item.is_active && item.min_courts === tier.min_courts,
  );
  if (activeWithSameMinimum) {
    Object.assign(tier, oldTier);
    return Promise.reject(new Error('Không thể bật vì số sân tối thiểu đang trùng với bậc khác.'));
  }
  const rangeAdjustments = rebalanceTierRanges(platformFeeStore.state.tiers);
  const coverage = validateTierCoverage(platformFeeStore.state.tiers);
  if (!coverage.isValid) {
    Object.assign(tier, oldTier);
    return Promise.reject(Object.assign(new Error('Kích hoạt lại bậc phí sẽ làm khoảng áp dụng chưa hợp lệ.'), { coverage }));
  }

  platformFeeStore.save();
  addAuditLog('platform_fee_tier.reactivated', 'platform_fee_tier', id, oldTier, tier, 'platform_fee_tier');
  return Promise.resolve(cloneValue({ ...tier, range_adjustments: rangeAdjustments }));
}

export function deleteTier(id) {
  const usageCount = getTierUsageCount(id);
  if (usageCount > 0) return deactivateTier(id, 'Đã có ledger sử dụng nên chỉ ngừng dùng.');

  const index = platformFeeStore.state.tiers.findIndex((tier) => tier.id === id);
  if (index === -1) return Promise.reject(new Error('Không tìm thấy bậc phí.'));
  const oldTier = platformFeeStore.state.tiers[index];
  platformFeeStore.state.tiers.splice(index, 1);
  rebalanceTierRanges(platformFeeStore.state.tiers);
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
      error: `Chưa có bậc phí phù hợp cho cụm có ${count} sân. Vui lòng cấu hình bậc phí trước.`,
    };
  }

  if (matches.length > 1) {
    return {
      tier: null,
      matches,
      error: `Có nhiều bậc phí cùng áp dụng cho ${count} sân. Vui lòng kiểm tra lại cấu hình.`,
    };
  }

  return { tier: matches[0], matches, error: '' };
}

export function calculatePlatformFee({ court_count, period_months, tier }) {
  const months = Number.parseInt(period_months, 10);
  if (!ALLOWED_PERIOD_MONTHS.includes(months)) throw new Error('Kỳ đóng phí không hợp lệ.');
  if (!tier) throw new Error('Chưa có bậc phí để tính phí.');

  const field = months === 1
    ? 'discount_1_month'
    : `discount_${months}_months`;
  const discountPercent = toNumber(tier[field]);
  const baseAmount = Number(court_count) * Number(tier.price_per_court_month) * months;
  const discountAmount = Math.round(baseAmount * discountPercent / 100);
  const amountDue = Math.max(0, Math.round(baseAmount - discountAmount));
  const warnings = [];

  if (discountPercent > 50) warnings.push('Mức giảm giá cao, vui lòng kiểm tra lại.');
  if (discountPercent === 100 || amountDue === 0) warnings.push('Giảm giá 100%, số tiền phải đóng bằng 0.');

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
  autoAdjustTierRanges,
  findTierForCourtCount,
  calculatePlatformFee,
  getTierUsageCount,
  getDiscountProfiles,
  createDiscountProfile,
  updateDiscountProfile,
  deleteDiscountProfile,
  validateDiscountSchedule,
};
