export const POLICY_TYPE_LABELS = {
  general: 'Chung',
  booking: 'Đặt sân',
  refund: 'Hoàn tiền',
  moderation: 'Kiểm duyệt và báo cáo',
  account: 'Tài khoản',
  platform_fee: 'Phí duy trì cụm sân',
  terms: 'Điều khoản sử dụng',
};

export const STATUS_LABELS = {
  draft: 'Bản nháp',
  active: 'Đang áp dụng',
  inactive: 'Tạm ngưng',
  archived: 'Đã lưu trữ',
  pending: 'Chờ xử lý',
  approved: 'Đã duyệt',
  rejected: 'Đã từ chối',
  locked: 'Đã khóa',
};

export const STATUS_BADGE_CLASS = {
  draft: 'status-draft',
  active: 'status-active',
  inactive: 'status-inactive',
  archived: 'status-archived',
  pending: 'status-pending',
  approved: 'status-active',
  rejected: 'status-archived',
  locked: 'status-archived',
};

export const ACTION_LABELS = {
  'booking.cancel': 'Hủy lịch đặt sân',
  'booking.create': 'Tạo lịch đặt sân',
  'booking.confirm': 'Xác nhận lịch đặt sân',
  'refund.request': 'Khách yêu cầu hoàn tiền',
  'refund.owner_confirm': 'Chủ sân xác nhận hoàn tiền',
  'refund.admin_confirm': 'Admin xác nhận hoàn tiền',
  'report.create': 'Người dùng báo cáo vi phạm',
  'report.resolve': 'Xử lý báo cáo vi phạm',
  'complaint.create': 'Tạo khiếu nại',
  'complaint.resolve': 'Xử lý khiếu nại',
  'account.lock': 'Khóa tài khoản',
  'account.unlock': 'Mở khóa tài khoản',
  'venue.lock': 'Khóa cụm sân',
  'venue.lock_due_fee': 'Khóa cụm sân do quá hạn phí',
  'first_login.accept_policy': 'Bắt buộc đồng ý điều khoản',
};

export const RULE_TYPE_LABELS = {
  refund_by_cancel_time: 'Hoàn tiền theo thời điểm hủy',
  refund_time_window: 'Hoàn tiền theo khung thời gian',
  report_auto_lock: 'Gợi ý khóa tài khoản theo số báo cáo',
  report_threshold: 'Ngưỡng xử lý báo cáo vi phạm',
  platform_fee_overdue: 'Xử lý cụm sân quá hạn phí duy trì',
  account_lock_manual: 'Khóa tài khoản thủ công',
  first_login_accept_required: 'Bắt buộc đồng ý chính sách',
  booking_auto_cancel: 'Tự hủy booking chưa thanh toán',
};

export const MODULE_META = {
  dashboard: {
    label: 'Tổng quan hệ thống',
    description: 'Xem dashboard và số liệu vận hành chung.',
  },
  profile: {
    label: 'Hồ sơ cá nhân',
    description: 'Xem và cập nhật hồ sơ của chính nhân sự đăng nhập.',
  },
  user: {
    label: 'Tài khoản',
    description: 'Xem, khóa và mở khóa tài khoản trong hệ thống.',
  },
  staff: {
    label: 'Tài khoản nhân sự',
    description: 'Tạo, khóa và gán nhóm quyền cho nhân sự hệ thống.',
  },
  role: {
    label: 'Nhóm quyền',
    description: 'Tạo, sửa và phân quyền cho nhóm nhân sự hệ thống.',
  },
  policy: {
    label: 'Chính sách',
    description: 'Quản lý văn bản chính sách và quy tắc xử lý tự động.',
  },
  moderation: {
    label: 'Bài viết và kiểm duyệt',
    description: 'Duyệt, từ chối hoặc xử lý nội dung vi phạm.',
  },
  report: {
    label: 'Báo cáo vi phạm',
    description: 'Xem và xử lý báo cáo vi phạm từ người dùng.',
  },
  complaint: {
    label: 'Khiếu nại',
    description: 'Xem, tiếp nhận và xử lý khiếu nại.',
  },
  partner: {
    label: 'Đối tác',
    description: 'Xem và duyệt hồ sơ đăng ký chủ sân.',
  },
  venue: {
    label: 'Đối tác và sân',
    description: 'Quản lý hồ sơ đối tác, cụm sân và sân con.',
  },
  booking: {
    label: 'Đặt sân',
    description: 'Xem và xử lý lịch đặt sân.',
  },
  pricing: {
    label: 'Bảng giá',
    description: 'Quản lý giá sân và khung giờ.',
  },
  finance: {
    label: 'Tài chính và đối soát',
    description: 'Xem thanh toán, hoàn tiền và nghiệp vụ đối soát.',
  },
  audit: {
    label: 'Nhật ký hệ thống',
    description: 'Xem lịch sử thao tác nhạy cảm.',
  },
};

const FALLBACK_ACTIONS = {
  view: 'Xem',
  create: 'Tạo',
  update: 'Sửa',
  delete: 'Xóa',
  manage: 'Quản lý',
  approve: 'Duyệt',
  reject: 'Từ chối',
  lock: 'Khóa',
  unlock: 'Mở khóa',
  publish: 'Kích hoạt',
  resolve: 'Xử lý',
};

export function getPolicyTypeLabel(type) {
  return POLICY_TYPE_LABELS[type] || type || 'Không xác định';
}

export function getStatusLabel(status) {
  return STATUS_LABELS[status] || status || 'Không xác định';
}

export function getStatusBadgeClass(status) {
  return STATUS_BADGE_CLASS[status] || 'status-default';
}

export function getActionLabel(code) {
  if (!code) return 'Không xác định';
  return ACTION_LABELS[code] || code.replaceAll('.', ' / ').replaceAll('_', ' ');
}

export function getRuleTypeLabel(type) {
  if (!type) return 'Không xác định';
  return RULE_TYPE_LABELS[type] || type.replaceAll('_', ' ');
}

export function getModuleMeta(moduleKey) {
  return MODULE_META[moduleKey] || {
    label: moduleKey ? moduleKey.replaceAll('_', ' ') : 'Khác',
    description: '',
  };
}

export function getPermissionMeta(permission) {
  const code = typeof permission === 'string' ? permission : permission?.code;

  if (!code) {
    return {
      label: 'Không xác định',
      description: '',
      riskLabel: '',
      riskClass: '',
      moduleKey: 'other',
    };
  }

  const riskLevel = permission?.risk_level || resolveRiskLevel(code);
  const moduleKey = permission?.module_key || code.split('.')[0] || 'other';

  return {
    label: permission?.label || buildPermissionLabel(code),
    description: permission?.description || `Quyền thao tác trong module ${getModuleMeta(moduleKey).label}.`,
    riskLabel: permission?.risk_label || getRiskLabel(riskLevel),
    riskClass: getRiskClass(riskLevel),
    moduleKey,
  };
}

export function getRiskLabel(riskLevel) {
  return {
    finance: 'Tài chính',
    system: 'Hệ thống',
    permission: 'Phân quyền',
    account_lock: 'Khóa tài khoản',
    sensitive: 'Nhạy cảm',
    normal: '',
  }[riskLevel] || '';
}

export function getRiskClass(riskLevel) {
  return {
    finance: 'risk-finance',
    system: 'risk-system',
    permission: 'risk-permission',
    account_lock: 'risk-account-lock',
    sensitive: 'risk-sensitive',
  }[riskLevel] || '';
}

export function getAuditActionLabel(action) {
  return {
    'role.created': 'Tạo nhóm quyền',
    'role.updated': 'Cập nhật nhóm quyền',
    'role.deleted': 'Xóa nhóm quyền',
    'role.permissions_updated': 'Cập nhật quyền được cấp',
    'policy.created': 'Tạo chính sách',
    'policy.updated': 'Cập nhật chính sách',
    'policy.cloned': 'Tạo phiên bản mới',
    'policy.published': 'Kích hoạt chính sách',
    'policy.status_changed': 'Đổi trạng thái chính sách',
    'policy.binding_saved': 'Cấu hình thao tác áp dụng',
    'policy.binding_disabled': 'Tắt thao tác áp dụng',
    'policy.rule_created': 'Tạo quy tắc xử lý',
    'policy.rule_updated': 'Cập nhật quy tắc xử lý',
    'policy.rule_toggled': 'Bật hoặc tắt quy tắc xử lý',
  }[action] || action || 'Thao tác';
}

export function getRuleSummary(rule) {
  if (!rule) return '';
  if (rule.business_summary) return rule.business_summary;

  const condition = rule.condition_json || {};
  const result = rule.result_json || {};

  if (['refund_by_cancel_time', 'refund_time_window'].includes(rule.rule_type)) {
    const hours = condition.hours_before_start?.gte ?? condition.hours_before_start ?? '?';
    const percent = result.refund_percent ?? '?';
    return `Nếu khách hủy trước ít nhất ${hours} giờ, hệ thống hoàn ${percent}% tiền.`;
  }

  if (['report_auto_lock', 'report_threshold'].includes(rule.rule_type)) {
    const count = condition.report_count?.gte ?? condition.report_count ?? '?';
    const unique = condition.unique_reporters?.gte ?? condition.unique_reporters ?? '?';
    const days = condition.window_days ?? '?';
    return `Nếu có ${count} báo cáo từ ${unique} người trong ${days} ngày, hệ thống xử lý theo cấu hình.`;
  }

  if (rule.rule_type === 'platform_fee_overdue') {
    const days = condition.overdue_days?.gte ?? condition.overdue_days ?? '?';
    return `Nếu cụm sân quá hạn phí ${days} ngày, hệ thống xử lý theo cấu hình.`;
  }

  if (rule.rule_type === 'first_login_accept_required') {
    return 'Người dùng phải đồng ý phiên bản chính sách mới nhất trước khi tiếp tục sử dụng.';
  }

  return rule.rule_name || getRuleTypeLabel(rule.rule_type);
}

export function buildAuditDiff(oldValues, newValues) {
  const oldData = oldValues || {};
  const newData = newValues || {};
  const fieldLabels = {
    title: 'Tiêu đề',
    content: 'Nội dung',
    status: 'Trạng thái',
    policy_type: 'Loại chính sách',
    version: 'Phiên bản',
    priority: 'Thứ tự ưu tiên',
    is_overridable: 'Cho sân chỉnh riêng',
    require_reaccept: 'Bắt buộc đồng ý lại',
    display_name: 'Tên hiển thị',
    description: 'Mô tả',
    name: 'Mã',
  };

  return Array.from(new Set([...Object.keys(oldData), ...Object.keys(newData)]))
    .filter((field) => fieldLabels[field])
    .filter((field) => JSON.stringify(oldData[field]) !== JSON.stringify(newData[field]))
    .map((field) => ({
      field,
      fieldLabel: fieldLabels[field] || field,
      oldLabel: formatValue(field, oldData[field]),
      newLabel: formatValue(field, newData[field]),
    }));
}

export function formatValue(field, value) {
  if (value === null || value === undefined || value === '') return 'Trống';
  if (typeof value === 'boolean') return value ? 'Có' : 'Không';
  if (field === 'status') return getStatusLabel(value);
  if (field === 'policy_type') return getPolicyTypeLabel(value);
  if (Array.isArray(value) || (typeof value === 'object' && value !== null)) {
    if (['rules', 'condition_json', 'result_json'].includes(field)) return 'Quy tắc xử lý đã thay đổi';
    if (field === 'action_bindings') return 'Thao tác áp dụng đã thay đổi';
    return 'Dữ liệu kỹ thuật đã thay đổi';
  }

  const text = typeof value === 'object'
    ? JSON.stringify(value)
    : String(value);

  return text.length > 90 ? `${text.slice(0, 90)}...` : text;
}

function buildPermissionLabel(code) {
  const parts = code.split('.');
  const action = parts.pop();
  const moduleKey = parts[0] || 'other';
  const actionLabel = FALLBACK_ACTIONS[action] || action;

  return `${actionLabel} ${getModuleMeta(moduleKey).label}`.trim();
}

function resolveRiskLevel(code) {
  if (code.includes('payment') || code.includes('refund')) return 'finance';
  if (code.includes('role') || code.includes('permission')) return 'permission';
  if (code.includes('lock') || code.includes('unlock')) return 'account_lock';
  if (code.includes('publish') || code.includes('policy.rule')) return 'system';
  if (code.includes('delete') || code.includes('manage')) return 'sensitive';
  return 'normal';
}
