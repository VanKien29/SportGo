export function addAuditLog(action, entityType, entityId, oldValues = null, newValues = null, context = 'platform_fee') {
  return {
    id: null,
    actor_id: null,
    action,
    entity_type: entityType,
    entity_id: entityId,
    old_values: oldValues,
    new_values: newValues,
    context,
    created_at: new Date().toISOString(),
    persisted: false,
    message: 'Audit phí nền tảng không còn ghi local. Cần dùng audit_logs từ API DB nếu bật lại chức năng này.',
  };
}

export const auditService = { addAuditLog };
