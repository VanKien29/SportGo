<template>
  <section class="permission-matrix">
    <header class="matrix-toolbar">
      <div>
        <h3>Ma trận phân quyền</h3>
        <p>Cấp hoặc thu hồi quyền trực tiếp trên lưới theo danh sách quyền thực tế trong hệ thống.</p>
      </div>
      <div class="toolbar-actions">
        <label class="search-box">
          <span>Tìm</span>
          <input v-model.trim="keyword" placeholder="Tên quyền, mã quyền, nhóm hoặc vai trò" />
        </label>
        <button class="btn secondary" type="button" :disabled="loading" @click="loadMatrix">Làm mới</button>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state">Đang tải ma trận phân quyền...</div>
    <div v-else-if="filteredGroups.length === 0" class="state">Không có quyền nào phù hợp bộ lọc.</div>

    <div v-else class="matrix-card">
      <div class="matrix-scroll">
        <table>
          <thead>
            <tr>
              <th class="sticky-col permission-col">Quyền hệ thống</th>
              <th v-for="role in visibleRoles" :key="role.id" class="role-col">
                <div class="role-head">
                  <strong>{{ role.display_name || role.name }}</strong>
                  <small>{{ role.is_system ? 'Hệ thống' : 'Tùy chỉnh' }}</small>
                  <span v-if="!role.is_configurable" class="locked">Khóa chỉnh sửa</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in filteredGroups" :key="group.group_name">
              <tr class="group-row">
                <td class="sticky-col permission-col">
                  <strong>{{ group.module_label || group.group_name }}</strong>
                  <span>{{ group.permissions.length }} quyền</span>
                </td>
                <td v-for="role in visibleRoles" :key="`${group.group_name}-${role.id}`" class="bulk-cell">
                  <div class="bulk-actions">
                    <button class="mini-btn grant" type="button" :disabled="!canBulkGrant(role, group)" @click="toggleGroup(role, group, true)">Bật nhóm</button>
                    <button class="mini-btn revoke" type="button" :disabled="!canBulkRevoke(role, group)" @click="toggleGroup(role, group, false)">Tắt nhóm</button>
                  </div>
                </td>
              </tr>
              <tr v-for="permission in group.permissions" :key="permission.id">
                <td class="sticky-col permission-col">
                  <div class="permission-info">
                    <strong>{{ permission.label || permission.name }}</strong>
                    <span>{{ permission.code }}</span>
                    <small v-if="permission.risk_label" class="risk" :class="riskClass(permission.risk_level)">
                      {{ permission.risk_label }}
                    </small>
                  </div>
                </td>
                <td v-for="role in visibleRoles" :key="`${role.id}-${permission.id}`" class="toggle-cell">
                  <button
                    class="cell-toggle"
                    :class="{ on: hasPermission(role, permission.id), locked: !role.is_configurable }"
                    type="button"
                    :disabled="!role.is_configurable || isBusy(role.id, permission.id)"
                    :aria-pressed="hasPermission(role, permission.id)"
                    @click="togglePermission(role, permission)"
                  >
                    <span>{{ hasPermission(role, permission.id) ? 'Đã cấp' : 'Chưa cấp' }}</span>
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script>
import { adminRoleService } from '../../services/adminRoles.js';

export default {
  name: 'AdminPermissionMatrix',
  data() {
    return {
      loading: false,
      error: '',
      success: '',
      keyword: '',
      roles: [],
      permissionGroups: [],
      toggling: new Set(),
    };
  },
  computed: {
    normalizedKeyword() {
      return this.keyword.toLowerCase();
    },
    visibleRoles() {
      if (!this.normalizedKeyword) return this.roles;
      const roleMatches = this.roles.filter((role) => [
        role.name,
        role.display_name,
        role.description,
      ].filter(Boolean).join(' ').toLowerCase().includes(this.normalizedKeyword));

      return roleMatches.length ? roleMatches : this.roles;
    },
    filteredGroups() {
      const keyword = this.normalizedKeyword;
      if (!keyword) return this.permissionGroups;

      return this.permissionGroups
        .map((group) => {
          const groupMatches = [group.group_name, group.module_label, group.module_description]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(keyword);
          const permissions = group.permissions.filter((permission) => {
            if (groupMatches) return true;
            return [
              permission.code,
              permission.name,
              permission.label,
              permission.description,
              permission.group_name,
            ].filter(Boolean).join(' ').toLowerCase().includes(keyword);
          });

          return { ...group, permissions };
        })
        .filter((group) => group.permissions.length > 0);
    },
  },
  mounted() {
    this.loadMatrix();
  },
  methods: {
    async loadMatrix() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminRoleService.matrix();
        const data = response.data || {};
        this.roles = (data.roles || []).map((role) => ({
          ...role,
          permission_ids: (role.permission_ids || []).map((id) => Number(id)),
        }));
        this.permissionGroups = data.permission_groups || [];
      } catch (error) {
        this.error = error.message || 'Không tải được ma trận phân quyền.';
      } finally {
        this.loading = false;
      }
    },
    hasPermission(role, permissionId) {
      return (role.permission_ids || []).includes(Number(permissionId));
    },
    isBusy(roleId, permissionId) {
      return this.toggling.has(`${roleId}-${permissionId}`);
    },
    async togglePermission(role, permission) {
      if (!role.is_configurable) return;
      const permissionId = Number(permission.id);
      const action = this.hasPermission(role, permissionId) ? 'revoke' : 'grant';
      const key = `${role.id}-${permissionId}`;
      this.toggling.add(key);
      this.error = '';
      this.success = '';

      try {
        await adminRoleService.togglePermission(role.id, permissionId, action);
        this.applyLocalToggle(role, permissionId, action === 'grant');
        this.success = action === 'grant'
          ? `Đã cấp quyền ${permission.label || permission.name} cho ${role.display_name || role.name}.`
          : `Đã thu hồi quyền ${permission.label || permission.name} khỏi ${role.display_name || role.name}.`;
        this.autoHide();
      } catch (error) {
        this.error = error.message || 'Không cập nhật được quyền.';
      } finally {
        this.toggling.delete(key);
      }
    },
    async toggleGroup(role, group, shouldGrant) {
      if (!role.is_configurable) return;
      const targets = group.permissions
        .map((permission) => Number(permission.id))
        .filter((permissionId) => shouldGrant ? !this.hasPermission(role, permissionId) : this.hasPermission(role, permissionId));

      if (!targets.length) return;
      this.error = '';
      this.success = '';

      for (const permissionId of targets) {
        const key = `${role.id}-${permissionId}`;
        this.toggling.add(key);
        try {
          await adminRoleService.togglePermission(role.id, permissionId, shouldGrant ? 'grant' : 'revoke');
          this.applyLocalToggle(role, permissionId, shouldGrant);
        } catch (error) {
          this.error = error.message || 'Không cập nhật được một số quyền trong nhóm.';
          break;
        } finally {
          this.toggling.delete(key);
        }
      }

      if (!this.error) {
        this.success = shouldGrant
          ? `Đã bật nhóm quyền ${group.module_label || group.group_name} cho ${role.display_name || role.name}.`
          : `Đã tắt nhóm quyền ${group.module_label || group.group_name} cho ${role.display_name || role.name}.`;
        this.autoHide();
      }
    },
    applyLocalToggle(role, permissionId, shouldGrant) {
      const ids = new Set((role.permission_ids || []).map((id) => Number(id)));
      if (shouldGrant) ids.add(permissionId);
      else ids.delete(permissionId);
      role.permission_ids = [...ids].sort((a, b) => a - b);
    },
    canBulkGrant(role, group) {
      return role.is_configurable && group.permissions.some((permission) => !this.hasPermission(role, permission.id));
    },
    canBulkRevoke(role, group) {
      return role.is_configurable && group.permissions.some((permission) => this.hasPermission(role, permission.id));
    },
    riskClass(level) {
      return {
        finance: 'risk-finance',
        system: 'risk-system',
        permission: 'risk-permission',
        account_lock: 'risk-account-lock',
        sensitive: 'risk-sensitive',
      }[level] || '';
    },
    autoHide() {
      setTimeout(() => { this.success = ''; }, 2800);
    },
  },
};
</script>

<style scoped>
.permission-matrix {
  display: grid;
  gap: 14px;
}

.matrix-toolbar,
.toolbar-actions {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.matrix-toolbar {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  padding: 16px;
}

.matrix-toolbar h3,
.matrix-toolbar p {
  margin: 0;
}

.matrix-toolbar p {
  margin-top: 5px;
  color: #64748b;
}

.toolbar-actions {
  align-items: end;
}

.search-box {
  display: grid;
  gap: 6px;
  min-width: 320px;
  color: #334155;
  font-weight: 800;
}

input {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  font: inherit;
}

.matrix-card {
  border: 1px solid #dbe3ef;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}

.matrix-scroll {
  overflow: auto;
  max-height: 72vh;
}

table {
  width: max-content;
  min-width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

th,
td {
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  padding: 10px;
  vertical-align: middle;
}

th {
  position: sticky;
  top: 0;
  z-index: 3;
  background: #f8fafc;
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 2;
  background: #fff;
}

th.sticky-col {
  z-index: 4;
  background: #f8fafc;
}

.permission-col {
  width: 320px;
  min-width: 320px;
}

.role-col {
  width: 170px;
  min-width: 170px;
  text-align: center;
}

.role-head {
  display: grid;
  gap: 4px;
  justify-items: center;
}

.role-head strong {
  color: #0f172a;
}

.role-head small {
  color: #64748b;
}

.locked {
  border-radius: 999px;
  background: #e2e8f0;
  color: #475569;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 900;
}

.group-row td {
  background: #f1f5f9;
}

.group-row .sticky-col {
  background: #f1f5f9;
}

.group-row strong,
.group-row span,
.permission-info strong,
.permission-info span {
  display: block;
}

.group-row span,
.permission-info span {
  margin-top: 3px;
  color: #64748b;
  font-size: 12px;
}

.bulk-actions {
  display: flex;
  justify-content: center;
  gap: 6px;
}

.mini-btn,
.cell-toggle,
.btn {
  border: 0;
  border-radius: 8px;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.mini-btn {
  padding: 6px 8px;
  font-size: 12px;
}

.mini-btn.grant {
  background: #dcfce7;
  color: #166534;
}

.mini-btn.revoke {
  background: #fee2e2;
  color: #991b1b;
}

.mini-btn:disabled,
.cell-toggle:disabled,
.btn:disabled {
  cursor: not-allowed;
  opacity: .55;
}

.toggle-cell {
  text-align: center;
}

.cell-toggle {
  min-width: 96px;
  padding: 8px 10px;
  background: #f1f5f9;
  color: #475569;
}

.cell-toggle.on {
  background: #dcfce7;
  color: #166534;
}

.cell-toggle.locked {
  background: #e2e8f0;
  color: #64748b;
}

.risk {
  display: inline-flex;
  width: fit-content;
  margin-top: 6px;
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 900;
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

.alert,
.state {
  border-radius: 8px;
  padding: 12px;
  font-weight: 700;
}

.state {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  text-align: center;
}

.error {
  background: #fef2f2;
  color: #991b1b;
}

.success {
  background: #ecfdf5;
  color: #047857;
}

.btn {
  padding: 10px 14px;
}

.btn.secondary {
  background: #e2e8f0;
  color: #334155;
}

@media (max-width: 900px) {
  .matrix-toolbar,
  .toolbar-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    min-width: 0;
  }
}
</style>
