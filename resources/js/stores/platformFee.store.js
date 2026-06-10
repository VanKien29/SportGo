const STORAGE_KEY = 'sportgo_platform_fee_mock_store_v1';

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function addDays(dateValue, days) {
  const date = new Date(dateValue);
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
}

function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

function discountProfiles() {
  return [
    {
      id: 'discount-small',
      name: 'Ưu đãi cụm nhỏ',
      discount_1_month: 0,
      discount_3_months: 5,
      discount_6_months: 8,
      discount_9_months: 10,
      discount_12_months: 12,
    },
    {
      id: 'discount-standard',
      name: 'Ưu đãi tiêu chuẩn',
      discount_1_month: 0,
      discount_3_months: 5,
      discount_6_months: 10,
      discount_9_months: 12,
      discount_12_months: 14,
    },
    {
      id: 'discount-large',
      name: 'Ưu đãi cụm lớn',
      discount_1_month: 0,
      discount_3_months: 5,
      discount_6_months: 10,
      discount_9_months: 12,
      discount_12_months: 15,
    },
  ];
}

function initialState() {
  const now = new Date().toISOString();
  const currentMonthStart = new Date();
  currentMonthStart.setDate(1);
  const periodStart = currentMonthStart.toISOString().slice(0, 10);

  return {
    tiers: [
      {
        id: 'tier-small',
        name: 'Bậc 1 - Cụm nhỏ',
        min_courts: 1,
        max_courts: 3,
        price_per_court_month: 50000,
        discount_profile_id: 'discount-small',
        discount_1_month: 0,
        discount_3_months: 5,
        discount_6_months: 8,
        discount_9_months: 10,
        discount_12_months: 12,
        is_active: true,
        note: 'Áp dụng cho cụm sân nhỏ.',
        created_at: now,
        updated_at: now,
      },
      {
        id: 'tier-medium',
        name: 'Bậc 2 - Cụm trung bình',
        min_courts: 4,
        max_courts: 7,
        price_per_court_month: 45000,
        discount_profile_id: 'discount-standard',
        discount_1_month: 0,
        discount_3_months: 5,
        discount_6_months: 10,
        discount_9_months: 12,
        discount_12_months: 14,
        is_active: true,
        note: 'Áp dụng cho cụm sân trung bình.',
        created_at: now,
        updated_at: now,
      },
      {
        id: 'tier-large',
        name: 'Bậc 3 - Cụm lớn',
        min_courts: 8,
        max_courts: null,
        price_per_court_month: 40000,
        discount_profile_id: 'discount-large',
        discount_1_month: 0,
        discount_3_months: 5,
        discount_6_months: 10,
        discount_9_months: 12,
        discount_12_months: 15,
        is_active: true,
        note: 'Bậc cuối không giới hạn số sân.',
        created_at: now,
        updated_at: now,
      },
    ],
    discount_profiles: discountProfiles(),
    venues: [
      {
        id: 'venue-alpha',
        name: 'SportGo Alpha',
        address: '12 Nguyễn Trãi, Quận 1',
        status: 'active',
        status_reason: '',
        locked_at: null,
        locked_by: null,
        owner: { id: 'owner-alpha', full_name: 'Nguyễn Văn Chủ', email: 'owner.alpha@sportgo.test' },
        court_count: 3,
        courts: [
          { id: 'alpha-1', name: 'Sân 1', status: 'active' },
          { id: 'alpha-2', name: 'Sân 2', status: 'maintenance' },
          { id: 'alpha-3', name: 'Sân 3', status: 'active' },
        ],
      },
      {
        id: 'venue-beta',
        name: 'SportGo Beta',
        address: '45 Lê Lợi, Thủ Đức',
        status: 'active',
        status_reason: '',
        locked_at: null,
        locked_by: null,
        owner: { id: 'owner-beta', full_name: 'Trần Thị Sân', email: 'owner.beta@sportgo.test' },
        court_count: 5,
        courts: [
          { id: 'beta-1', name: 'Sân 1', status: 'active' },
          { id: 'beta-2', name: 'Sân 2', status: 'active' },
          { id: 'beta-3', name: 'Sân 3', status: 'inactive' },
          { id: 'beta-4', name: 'Sân 4', status: 'active' },
          { id: 'beta-5', name: 'Sân 5', status: 'maintenance' },
        ],
      },
      {
        id: 'venue-gamma',
        name: 'SportGo Gamma',
        address: '88 Cách Mạng Tháng 8, Quận 10',
        status: 'locked',
        status_reason: 'Quá hạn phí duy trì hệ thống',
        locked_at: addDays(todayIso(), -3),
        locked_by: 'admin-mock',
        owner: { id: 'owner-gamma', full_name: 'Lê Minh Quân', email: 'owner.gamma@sportgo.test' },
        court_count: 8,
        courts: Array.from({ length: 8 }, (_, index) => ({
          id: `gamma-${index + 1}`,
          name: `Sân ${index + 1}`,
          status: index === 7 ? 'inactive' : 'active',
        })),
      },
    ],
    ledgers: [
      {
        id: 'pf-1001',
        code: 'PF-2026-0001',
        venue_cluster_id: 'venue-alpha',
        tier_id: 'tier-small',
        tier_name: 'Bậc 1 - Cụm nhỏ',
        court_count: 3,
        period_months: 3,
        billing_cycle: '3_months',
        period_start: periodStart,
        period_end: addDays(periodStart, 89),
        due_date: addDays(todayIso(), 7),
        price_per_court_month: 50000,
        discount_percent: 5,
        base_amount: 450000,
        discount_amount: 22500,
        amount_due: 427500,
        amount_paid: 0,
        status: 'pending',
        paid_at: null,
        overdue_at: null,
        cancelled_reason: '',
        receipt_id: null,
        created_at: now,
        updated_at: now,
      },
      {
        id: 'pf-1002',
        code: 'PF-2026-0002',
        venue_cluster_id: 'venue-gamma',
        tier_id: 'tier-large',
        tier_name: 'Bậc 3 - Cụm lớn',
        court_count: 8,
        period_months: 1,
        billing_cycle: '1_month',
        period_start: addDays(periodStart, -35),
        period_end: addDays(periodStart, -5),
        due_date: addDays(todayIso(), -3),
        price_per_court_month: 40000,
        discount_percent: 0,
        base_amount: 320000,
        discount_amount: 0,
        amount_due: 320000,
        amount_paid: 0,
        status: 'overdue',
        paid_at: null,
        overdue_at: addDays(todayIso(), -2),
        cancelled_reason: '',
        receipt_id: null,
        created_at: now,
        updated_at: now,
      },
    ],
    emailLogs: [],
    notifications: [],
    receipts: [],
    auditLogs: [],
    settings: {
      default_due_days: 7,
      auto_mark_overdue: true,
      lock_reason: 'Quá hạn phí duy trì hệ thống',
    },
    loading: false,
    error: '',
    selectedTier: null,
    selectedLedger: null,
  };
}

function readStoredState() {
  if (typeof localStorage === 'undefined') return initialState();
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (!stored) return initialState();
    const defaults = initialState();
    const parsed = JSON.parse(stored);
    return {
      ...defaults,
      ...parsed,
      discount_profiles: parsed.discount_profiles?.length
        ? parsed.discount_profiles
        : defaults.discount_profiles,
    };
  } catch {
    return initialState();
  }
}

export const platformFeeStore = {
  state: readStoredState(),
  save() {
    if (typeof localStorage === 'undefined') return;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
  },
  reset() {
    this.state = initialState();
    this.save();
  },
  snapshot() {
    return clone(this.state);
  },
};

export function createId(prefix) {
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
}

export function cloneValue(value) {
  return clone(value);
}
