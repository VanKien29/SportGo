export const POLICY_TYPE_LABELS = {
  terms: 'Điều khoản sử dụng',
  booking_cancellation: 'Hủy booking',
  refund: 'Hoàn tiền',
  platform_fee: 'Phí nền tảng',
  venue_policy: 'Chính sách sân',
  moderation: 'Kiểm duyệt & báo cáo',
  partner_contract: 'Đối tác & hợp đồng',
  general: 'Chung',
  booking: 'Đặt sân',
  account: 'Tài khoản',
};

export const STATUS_LABELS = {
  draft: 'Bản nháp',
  active: 'Đang áp dụng',
  inactive: 'Ngưng áp dụng',
  archived: 'Lưu trữ',
  pending_review: 'Chờ duyệt',
  rejected: 'Bị từ chối',
  pending_owner_confirmation: 'Chờ chủ sân xác nhận',
  owner_confirmed: 'Chủ sân đã xác nhận',
  admin_processing: 'Admin đang xử lý',
  completed: 'Hoàn tất',
  owner_rejected: 'Chủ sân từ chối',
  pending_owner_signature: 'Chờ chủ sân ký',
  pending_sportgo_signature: 'Chờ SportGo ký',
  signed_active: 'Đã ký và có hiệu lực',
  terminated: 'Đã chấm dứt',
  pending: 'Chờ xử lý',
  approved: 'Đã duyệt',
  locked: 'Đã khóa',
};

export const STATUS_BADGE_CLASS = {
  draft: 'status-draft',
  active: 'status-active',
  inactive: 'status-inactive',
  archived: 'status-archived',
  pending_review: 'status-pending',
  rejected: 'status-rejected',
  pending_owner_confirmation: 'status-pending',
  owner_confirmed: 'status-active',
  admin_processing: 'status-pending',
  completed: 'status-active',
  owner_rejected: 'status-rejected',
  pending_owner_signature: 'status-pending',
  pending_sportgo_signature: 'status-pending',
  signed_active: 'status-active',
  terminated: 'status-archived',
  pending: 'status-pending',
  approved: 'status-active',
  locked: 'status-rejected',
};

export const ACTION_LABELS = {
  'partner_application.approve': 'Admin duyệt hồ sơ đối tác',
  'partner_contract.generate': 'Hệ thống sinh hợp đồng đối tác',
  'partner_contract.sign': 'Chủ sân và SportGo ký hợp đồng',
  'partner_termination.approve': 'Admin duyệt yêu cầu chấm dứt hợp tác',
  'refund.request': 'Khách gửi yêu cầu hoàn tiền',
  'refund.owner_confirm': 'Chủ sân xác nhận yêu cầu hoàn tiền',
  'refund.admin_complete': 'Admin xác nhận hoàn tất hoàn tiền',
  'booking.cancel_by_customer': 'Khách hủy booking',
  'booking.cancel_by_owner': 'Chủ sân hủy booking',
  'booking.expire_unpaid': 'Hệ thống hủy booking do quá hạn thanh toán',
  'venue.platform_fee_due': 'Sắp đến hạn hoặc quá hạn phí nền tảng',
  'venue.lock_due_fee': 'Khóa/giới hạn cụm sân do quá hạn phí nền tảng',
  'owner.access_limited_due_fee': 'Giới hạn quyền chủ sân do quá hạn phí',
  'post.report': 'Người dùng báo cáo nội dung',
  'post.hide': 'Ẩn nội dung vi phạm',
  'first_login.accept_policy': 'Người dùng/chủ sân chấp nhận chính sách',
  'venue_policy.submit': 'Chủ sân gửi chính sách riêng để duyệt',
  'venue_policy.activate': 'Kích hoạt chính sách sân hợp lệ',
};

export const RULE_TYPE_LABELS = {
  terms_acceptance_required: 'Bắt buộc chấp nhận điều khoản trước khi sử dụng',
  cancel_before_hours: 'Quy định mốc thời gian được hủy booking',
  refund_percent_by_cancel_time: 'Tính phần trăm hoàn tiền theo thời gian hủy',
  owner_confirm_required_before_admin_transfer: 'Bắt buộc chủ sân xác nhận trước khi admin hoàn tiền',
  platform_fee_overdue_warning: 'Nhắc chủ sân khi sắp/quá hạn phí nền tảng',
  platform_fee_overdue_lock: 'Giới hạn hoặc khóa cụm sân khi quá hạn phí nền tảng',
  venue_policy_override_limit: 'Giới hạn chính sách riêng của sân theo khung hệ thống',
  report_threshold_requires_review: 'Đưa nội dung vào chờ kiểm duyệt khi có nhiều báo cáo',
  contract_signing_required: 'Hợp đồng phải có đủ chữ ký mới có hiệu lực',
  partner_termination_transition_30_days: 'Thu quyền chủ sân sau thời gian chuyển tiếp khi chấm dứt hợp đồng',
  partner_application_approve_requires_contract: 'Duyệt hồ sơ đối tác xong phải sinh hợp đồng',
};

export const MODULE_META = {
  dashboard: { label: 'Tổng quan hệ thống', description: 'Xem dashboard và số liệu vận hành chung.' },
  profile: { label: 'Hồ sơ cá nhân', description: 'Xem và cập nhật hồ sơ của nhân sự đăng nhập.' },
  user: { label: 'Tài khoản', description: 'Xem, khóa và mở khóa tài khoản trong hệ thống.' },
  staff: { label: 'Tài khoản nhân sự', description: 'Tạo, khóa và gán nhóm quyền cho nhân sự hệ thống.' },
  role: { label: 'Nhóm quyền', description: 'Tạo, sửa và phân quyền cho nhóm nhân sự hệ thống.' },
  policy: { label: 'Chính sách', description: 'Quản lý văn bản chính sách và quy tắc xử lý tự động.' },
  moderation: { label: 'Kiểm duyệt', description: 'Duyệt, từ chối hoặc xử lý nội dung vi phạm.' },
  report: { label: 'Báo cáo vi phạm', description: 'Xem và xử lý báo cáo vi phạm từ người dùng.' },
  complaint: { label: 'Khiếu nại', description: 'Xem, tiếp nhận và xử lý khiếu nại.' },
  partner: { label: 'Đối tác & hợp đồng', description: 'Xem hồ sơ đối tác, hợp đồng và chấm dứt hợp tác.' },
  venue: { label: 'Cụm sân', description: 'Quản lý cụm sân, sân con và trạng thái vận hành.' },
  owner: { label: 'Quyền chủ sân', description: 'Quản lý quyền thao tác của owner.' },
  booking: { label: 'Đặt sân', description: 'Xem và xử lý lịch đặt sân.' },
  pricing: { label: 'Bảng giá', description: 'Quản lý giá sân và khung giờ.' },
  finance: { label: 'Tài chính & đối soát', description: 'Xem thanh toán, hoàn tiền và nghiệp vụ đối soát.' },
  refund: { label: 'Hoàn tiền', description: 'Xử lý yêu cầu hoàn tiền.' },
  audit: { label: 'Nhật ký hệ thống', description: 'Xem lịch sử thao tác nhạy cảm.' },
  auth: { label: 'Xác nhận chính sách', description: 'Kiểm tra việc chấp nhận điều khoản/chính sách.' },
  venue_policy: { label: 'Chính sách sân', description: 'Chính sách riêng do chủ sân đề xuất.' },
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
  if (!status) return 'Không xác định';
  return STATUS_LABELS[String(status).toLowerCase()] || status;
}

export function getStatusBadgeClass(status) {
  return STATUS_BADGE_CLASS[String(status || '').toLowerCase()] || 'status-default';
}

export function getActionLabel(code) {
  if (!code) return 'Không xác định';
  return ACTION_LABELS[code] || code;
}

export function getRuleTypeLabel(type) {
  if (!type) return 'Không xác định';
  return RULE_TYPE_LABELS[type] || type;
}

export function getModuleMeta(moduleKey) {
  return MODULE_META[moduleKey] || {
    label: moduleKey || 'Khác',
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
  if (rule.business_summary_vi || rule.business_summary) return rule.business_summary_vi || rule.business_summary;

  const condition = rule.condition_json || {};
  const result = rule.result_json || {};
  const hours = condition.hours_before_start?.gte ?? condition.hours_before_start ?? '?';
  const days = condition.overdue_days?.gte ?? condition.overdue_days ?? '?';

  if (rule.rule_type === 'refund_percent_by_cancel_time') {
    return `Nếu khách hủy booking trước giờ chơi ít nhất ${hours} giờ, hệ thống đề xuất hoàn tối thiểu ${result.refund_percent ?? '?'}% số tiền đã thanh toán.`;
  }

  if (rule.rule_type === 'owner_confirm_required_before_admin_transfer') {
    return 'Nếu yêu cầu hoàn tiền chưa được chủ sân xác nhận, admin không được chuyển tiền và không được chuyển yêu cầu sang hoàn tất.';
  }

  if (rule.rule_type === 'platform_fee_overdue_lock') {
    return `Nếu cụm sân quá hạn phí nền tảng ${days} ngày, hệ thống chuyển cụm sân sang trạng thái bị giới hạn quyền.`;
  }

  if (rule.rule_type === 'report_threshold_requires_review') {
    const count = condition.report_count?.gte ?? '?';
    const unique = condition.unique_reporters?.gte ?? '?';
    const windowDays = condition.window_days ?? '?';
    return `Nếu một nội dung nhận từ ${count} báo cáo hợp lệ bởi ít nhất ${unique} người khác nhau trong ${windowDays} ngày, hệ thống đưa nội dung vào chờ kiểm duyệt.`;
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
    policy_type: 'Nhóm chính sách',
    version: 'Phiên bản',
    priority: 'Thứ tự ưu tiên',
    is_overridable: 'Cho sân cấu hình riêng',
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
  if (value === null || value === undefined || value === '') return '(trống)';
  if (typeof value === 'boolean') return value ? 'Có' : 'Không';
  if (field === 'status') return getStatusLabel(value);
  if (field === 'policy_type') return getPolicyTypeLabel(value);
  if (Array.isArray(value) || (typeof value === 'object' && value !== null)) {
    if (['rules', 'condition_json', 'result_json'].includes(field)) return 'Quy tắc xử lý đã thay đổi';
    if (field === 'action_bindings') return 'Thao tác áp dụng đã thay đổi';
    return 'Dữ liệu kỹ thuật đã thay đổi';
  }

  const text = String(value);
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
