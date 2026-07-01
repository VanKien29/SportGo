import { api } from './api.js';

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
    name: String(payload.name || '').trim(),
    min_courts: Number.parseInt(payload.min_courts, 10),
    price_per_court_month: toNumber(payload.price_per_court_month),
    annual_discount_percent: toNumber(payload.annual_discount_percent ?? payload.discount_12_months),
    is_active: Boolean(payload.is_active),
  };
}

function query(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined && value !== false) {
      search.set(key, value);
    }
  });
  return search.toString() ? `?${search.toString()}` : '';
}

function addFieldError(errors, field, message) {
  if (!errors[field]) errors[field] = [];
  errors[field].push(message);
}

function decorateTier(tier) {
  const annualDiscount = Number(tier.annual_discount_percent || tier.discount_12_months || 0);
  return {
    ...tier,
    discount_profile_id: tier.discount_profile_id || 'db-annual',
    discount_1_month: Number(tier.discount_1_month || 0),
    discount_3_months: Number(tier.discount_3_months || 0),
    discount_6_months: Number(tier.discount_6_months || 0),
    discount_9_months: Number(tier.discount_9_months || 0),
    discount_12_months: annualDiscount,
    annual_discount_percent: annualDiscount,
    usage_count: Number(tier.usage_count || 0),
  };
}

export async function getTiers(filters = {}) {
  const response = await api(`/api/admin/platform-fee-tiers${query(filters)}`);
  const tiers = Array.isArray(response) ? response : response.data || [];
  return tiers.map(decorateTier);
}

export async function getActiveTiers() {
  const tiers = await getTiers({ status: 'active' });
  return tiers.filter((tier) => tier.is_active);
}

export function getTierUsageCount(tierId, tiers = []) {
  return Number(tiers.find((tier) => tier.id === tierId)?.usage_count || 0);
}

export function getDiscountProfiles() {
  return Promise.resolve([
    {
      id: 'db-annual',
      name: 'Giảm theo DB hiện tại',
      discount_1_month: 0,
      discount_3_months: 0,
      discount_6_months: 0,
      discount_9_months: 0,
      discount_12_months: 0,
      readonly: true,
    },
  ]);
}

export function createDiscountProfile() {
  return Promise.reject(new Error('Mẫu giảm kỳ chưa có bảng DB riêng. Hiện chỉ lưu giảm 12 tháng trực tiếp trên bậc phí.'));
}

export function updateDiscountProfile() {
  return createDiscountProfile();
}

export function deleteDiscountProfile() {
  return createDiscountProfile();
}

export function validateDiscountSchedule(payload) {
  const errors = {};
  const annualDiscount = toNumber(payload.annual_discount_percent ?? payload.discount_12_months);
  if (annualDiscount < 0 || annualDiscount > 100) {
    addFieldError(errors, 'discount_12_months', 'Giảm kỳ 12 tháng phải nằm trong khoảng 0 - 100%.');
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    normalized: {
      discount_1_month: 0,
      discount_3_months: 0,
      discount_6_months: 0,
      discount_9_months: 0,
      discount_12_months: annualDiscount,
    },
  };
}

export function validateTier(payload, existingTiers = []) {
  const tier = normalizeTier(payload);
  const errors = {};
  const editingId = payload.id || null;

  if (!tier.name) addFieldError(errors, 'name', 'Vui lòng nhập tên bậc phí.');
  if (tier.name && existingTiers.some((item) => item.id !== editingId && item.name.trim().toLowerCase() === tier.name.toLowerCase())) {
    addFieldError(errors, 'name', 'Tên bậc phí đang trùng với bậc khác.');
  }
  if (!Number.isInteger(tier.min_courts) || tier.min_courts < 1) {
    addFieldError(errors, 'min_courts', 'Số sân tối thiểu phải lớn hơn hoặc bằng 1.');
  }
  if (!Number.isInteger(tier.price_per_court_month) || tier.price_per_court_month <= 0) {
    addFieldError(errors, 'price_per_court_month', 'Giá/sân/tháng phải là số nguyên VND lớn hơn 0.');
  } else if (tier.price_per_court_month > 9999999999) {
    addFieldError(errors, 'price_per_court_month', 'Giá/sân/tháng vượt quá giới hạn cho phép.');
  }
  if (tier.annual_discount_percent < 0 || tier.annual_discount_percent > 100) {
    addFieldError(errors, 'discount_12_months', 'Giảm kỳ 12 tháng phải nằm trong khoảng 0 - 100%.');
  }
  if (existingTiers.some((item) => item.id !== editingId && Number(item.min_courts) === tier.min_courts)) {
    addFieldError(errors, 'min_courts', 'Số sân tối thiểu đang trùng với một bậc phí khác.');
  }

  const proposedActiveTiers = existingTiers
    .filter((item) => item.id !== editingId && item.is_active)
    .concat(tier.is_active ? [tier] : [])
    .sort((left, right) => Number(left.min_courts) - Number(right.min_courts));

  if (proposedActiveTiers.length && Number(proposedActiveTiers[0].min_courts) !== 1) {
    addFieldError(errors, 'min_courts', 'Bậc phí đang dùng đầu tiên phải bắt đầu từ 1 sân.');
  }

  if (tier.is_active && Number.isInteger(tier.price_per_court_month) && tier.price_per_court_month > 0) {
    const tierIndex = proposedActiveTiers.indexOf(tier);
    const previousTier = proposedActiveTiers[tierIndex - 1];
    const nextTier = proposedActiveTiers[tierIndex + 1];

    if (previousTier && tier.price_per_court_month >= Number(previousTier.price_per_court_month)) {
      addFieldError(
        errors,
        'price_per_court_month',
        `Giá bậc này phải thấp hơn giá của bậc ít sân hơn (${Number(previousTier.price_per_court_month).toLocaleString('vi-VN')} đ).`,
      );
    }
    if (nextTier && tier.price_per_court_month <= Number(nextTier.price_per_court_month)) {
      addFieldError(
        errors,
        'price_per_court_month',
        `Giá bậc này phải cao hơn giá của bậc nhiều sân hơn (${Number(nextTier.price_per_court_month).toLocaleString('vi-VN')} đ).`,
      );
    }
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    normalized: tier,
  };
}

export function validateTierCoverage(tiers) {
  const activeTiers = [...tiers]
    .filter((tier) => tier.is_active)
    .sort((left, right) => Number(left.min_courts) - Number(right.min_courts));
  const errors = [];
  const missingRanges = [];
  const overlappingRanges = [];

  if (!activeTiers.length) {
    errors.push('Chưa có bậc phí đang dùng nào.');
    missingRanges.push({ from: 1, to: null });
    return { isValid: false, errors, warnings: [], missingRanges, overlappingRanges };
  }

  if (Number(activeTiers[0].min_courts) !== 1) {
    errors.push('Bậc đang dùng đầu tiên nên bắt đầu từ 1 sân.');
    missingRanges.push({ from: 1, to: Number(activeTiers[0].min_courts) - 1 });
  }

  activeTiers.forEach((tier, index) => {
    const next = activeTiers[index + 1];
    if (next && Number(next.min_courts) <= Number(tier.min_courts)) {
      errors.push(`Bậc ${next.name} đang trùng khoảng bắt đầu với bậc trước.`);
      overlappingRanges.push({ from: Number(next.min_courts), to: Number(tier.min_courts) });
    }
  });

  return {
    isValid: errors.length === 0,
    errors,
    warnings: [],
    missingRanges,
    overlappingRanges,
  };
}

export async function createTier(payload, existingTiers = []) {
  const validation = validateTier(payload, existingTiers);
  if (!validation.isValid) return Promise.reject(Object.assign(new Error('Dữ liệu bậc phí chưa hợp lệ.'), { validation }));
  const response = await api('/api/admin/platform-fee-tiers', {
    method: 'POST',
    body: JSON.stringify(validation.normalized),
  });
  return decorateTier(response.data || response);
}

export async function updateTier(id, payload, existingTiers = []) {
  const validation = validateTier({ ...payload, id }, existingTiers);
  if (!validation.isValid) return Promise.reject(Object.assign(new Error('Dữ liệu bậc phí chưa hợp lệ.'), { validation }));
  const response = await api(`/api/admin/platform-fee-tiers/${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: JSON.stringify(validation.normalized),
  });
  return decorateTier(response.data || response);
}

export async function deactivateTier(id, reason = 'Ngừng sử dụng') {
  const response = await api(`/api/admin/platform-fee-tiers/${encodeURIComponent(id)}/deactivate`, {
    method: 'PATCH',
    body: JSON.stringify({ reason }),
  });
  return decorateTier(response.data || response);
}

export async function reactivateTier(id) {
  const response = await api(`/api/admin/platform-fee-tiers/${encodeURIComponent(id)}/reactivate`, {
    method: 'PATCH',
  });
  return decorateTier(response.data || response);
}

export async function deleteTier(id) {
  return api(`/api/admin/platform-fee-tiers/${encodeURIComponent(id)}`, {
    method: 'DELETE',
  });
}

export function findTierForCourtCount(courtCount, tiers = []) {
  const count = Number.parseInt(courtCount, 10);
  const matches = tiers
    .filter((tier) => tier.is_active)
    .filter((tier) => count >= Number(tier.min_courts) && (tier.max_courts === null || count <= Number(tier.max_courts)));

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

  const courtCount = Number(court_count);
  const monthlyPrice = Number(tier.price_per_court_month);
  if (!Number.isInteger(courtCount) || courtCount < 1) {
    throw new Error('Số sân phải là số nguyên lớn hơn hoặc bằng 1.');
  }
  if (!Number.isFinite(monthlyPrice) || monthlyPrice <= 0) {
    throw new Error('Giá phí theo sân mỗi tháng phải lớn hơn 0.');
  }

  const discountPercent = months === 12 ? toNumber(tier.annual_discount_percent ?? tier.discount_12_months) : 0;
  const baseAmount = courtCount * monthlyPrice * months;
  const discountAmount = Math.round((baseAmount * discountPercent) / 100);
  const amountDue = Math.max(0, Math.round(baseAmount - discountAmount));
  const warnings = [];

  if (months !== 12) warnings.push('DB hiện chỉ cấu hình giảm kỳ 12 tháng; kỳ này không áp dụng giảm.');
  if (discountPercent > 50) warnings.push('Mức giảm giá cao, vui lòng kiểm tra lại.');
  if (discountPercent === 100 || amountDue === 0) warnings.push('Giảm giá 100%, số tiền phải đóng bằng 0.');

  return {
    court_count: courtCount,
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
  getDiscountProfiles,
  createDiscountProfile,
  updateDiscountProfile,
  deleteDiscountProfile,
  validateDiscountSchedule,
};
