import { createId, cloneValue, platformFeeStore } from '../stores/platformFee.store.js';

export function addAuditLog(action, entityType, entityId, oldValues = null, newValues = null, context = 'platform_fee') {
  const log = {
    id: createId('audit'),
    actor_id: 'admin-mock',
    action,
    entity_type: entityType,
    entity_id: entityId,
    old_values: cloneValue(oldValues),
    new_values: cloneValue(newValues),
    context,
    created_at: new Date().toISOString(),
  };

  platformFeeStore.state.auditLogs.unshift(log);
  platformFeeStore.save();
  return log;
}

export const auditService = { addAuditLog };
