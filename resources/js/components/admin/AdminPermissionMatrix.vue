<template>
  <div class="matrix-wrapper">
    <div v-if="loading" class="empty-state">Đang tải cấu hình phân quyền...</div>
    <div v-else-if="error" class="alert error">{{ error }}</div>
    <div v-else class="matrix-card">
      <div class="matrix-table-container">
        <table>
          <thead>
            <tr>
              <th class="sticky-col group-col">Nhóm tính năng</th>
              <th class="sticky-col name-col">Tên quyền</th>
              <th v-for="role in roles" :key="role.id" class="role-col">
                <div class="role-header">
                  <strong>{{ role.display_name }}</strong>
                  <span class="role-type">{{ role.is_system ? 'Hệ thống' : 'Tùy chỉnh' }}</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in permissionGroups" :key="group.group_name">
              <tr class="group-header-row">
                <td :colspan="2 + roles.length">
                  <strong>{{ group.module_label }}</strong>
                  <span>{{ group.module_description }}</span>
                </td>
              </tr>
              <tr v-for="perm in group.permissions" :key="perm.id">
                <td class="sticky-col group-col"></td>
                <td class="sticky-col name-col">
                  <div class="perm-info">
                    <span>{{ perm.label }}</span>
                    <span v-if="perm.risk_label" class="risk-badge" :class="riskClass(perm.risk_level)">
                      {{ perm.risk_label }}
                    </span>
                  </div>
                </td>
                <td v-for="role in roles" :key="role.id" class="toggle-cell">
                  <label class="switch" :class="{ disabled: !role.is_configurable }">
                    <input
                      type="checkbox"
                      :checked="hasPermission(role, perm.id)"
                      :disabled="!role.is_configurable || isToggling === `${role.id}-${perm.id}`"
                      @change="togglePermission(role, perm.id, $event.target.checked)"
                    />
                    <span class="slider round"></span>
                  </label>
                  <div v-if="isToggling === `${role.id}-${perm.id}`" class="spinner"></div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { adminRoleService } from '../../services/adminRoles.js';

export default {
  name: 'AdminPermissionMatrix',
  data() {
    return {
      loading: true,
      error: '',
      roles: [],
      permissionGroups: [],
      isToggling: null,
    };
  },
  mounted() {
    this.loadMatrix();
  },
  methods: {
    async loadMatrix() {
      this.loading = true;
      this.error = '';
      try {
        const res = await adminRoleService.matrix();
        if (res.data) {
          this.roles = res.data.roles || [];
          this.permissionGroups = res.data.permission_groups || [];
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải cấu hình phân quyền.';
      } finally {
        this.loading = false;
      }
    },
    hasPermission(role, permissionId) {
      return role.permission_ids && role.permission_ids.includes(permissionId);
    },
    async togglePermission(role, permissionId, isChecked) {
      if (!role.is_configurable) return;
      
      const toggleKey = `${role.id}-${permissionId}`;
      this.isToggling = toggleKey;
      
      try {
        const action = isChecked ? 'grant' : 'revoke';
        await adminRoleService.togglePermission(role.id, permissionId, action);
        
        // Cập nhật state local
        if (isChecked) {
          role.permission_ids.push(permissionId);
        } else {
          role.permission_ids = role.permission_ids.filter(id => id !== permissionId);
        }
      } catch (err) {
        alert(err.message || 'Lỗi khi thay đổi quyền.');
        // Giao diện tự rollback do bind với computed / method
      } finally {
        this.isToggling = null;
      }
    },
    riskClass(level) {
      if (level === 'finance') return 'risk-finance';
      if (level === 'system') return 'risk-system';
      if (level === 'permission') return 'risk-permission';
      if (level === 'account_lock') return 'risk-account-lock';
      if (level === 'sensitive') return 'risk-sensitive';
      return '';
    }
  }
};
</script>

<style scoped>
.matrix-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.matrix-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.matrix-table-container {
  overflow-x: auto;
  max-height: 75vh;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 800px;
}

th, td {
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #f1f5f9;
  vertical-align: middle;
}

th {
  background: #f8fafc;
  position: sticky;
  top: 0;
  z-index: 10;
  border-bottom: 2px solid #e2e8f0;
}

.sticky-col {
  position: sticky;
  left: 0;
  background: #fff;
  z-index: 11;
}

th.sticky-col {
  background: #f8fafc;
  z-index: 12;
}

.group-col {
  width: 40px;
}

.name-col {
  left: 0;
  min-width: 280px;
  border-right: 2px solid #e2e8f0;
}

.role-col {
  min-width: 160px;
  text-align: center;
}

.role-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.role-header strong {
  color: #0f172a;
  font-size: 14px;
}

.role-type {
  font-size: 11px;
  color: #64748b;
  background: #e2e8f0;
  padding: 2px 6px;
  border-radius: 4px;
}

.group-header-row td {
  background: #f1f5f9;
  padding: 14px 16px;
}

.group-header-row strong {
  color: #0f172a;
  font-size: 15px;
  margin-right: 12px;
}

.group-header-row span {
  color: #64748b;
  font-size: 13px;
}

.perm-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.perm-info span:first-child {
  color: #334155;
  font-weight: 600;
  font-size: 14px;
}

.toggle-cell {
  text-align: center;
  position: relative;
}

.switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}

.switch input { 
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
}

input:checked + .slider {
  background-color: #10b981;
}

input:focus + .slider {
  box-shadow: 0 0 1px #10b981;
}

input:checked + .slider:before {
  transform: translateX(20px);
}

.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

.switch.disabled .slider {
  background-color: #e2e8f0;
  cursor: not-allowed;
  opacity: 0.7;
}

.switch.disabled input:checked + .slider {
  background-color: #94a3b8;
}

.spinner {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 16px;
  height: 16px;
  border: 2px solid rgba(0,0,0,0.1);
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  z-index: 2;
}

@keyframes spin {
  to { transform: translate(-50%, -50%) rotate(360deg); }
}

.risk-badge {
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.risk-sensitive,
.risk-account-lock {
  background: #fee2e2;
  color: #991b1b;
}

.risk-finance {
  background: #fef3c7;
  color: #92400e;
}

.risk-system {
  background: #dbeafe;
  color: #1d4ed8;
}

.risk-permission {
  background: #ede9fe;
  color: #5b21b6;
}

.alert {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  color: #991b1b;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #64748b;
}
</style>
