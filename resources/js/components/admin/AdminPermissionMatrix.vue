<template>
  <div class="matrix-wrapper">
    <div v-if="loading" class="matrix-state">Đang tải cấu hình phân quyền...</div>
    <div v-else-if="error" class="matrix-state matrix-state-error">{{ error }}</div>
    <div v-else-if="!roles.length" class="matrix-state">Chưa có nhóm quyền để hiển thị.</div>
    <div v-else class="matrix-workspace">
      <header class="matrix-intro">
        <div>
          <p class="matrix-eyebrow">CẤU HÌNH TRUY CẬP</p>
          <h2>Ma trận phân quyền</h2>
          <p>So sánh quyền giữa các nhóm và cấp hoặc thu hồi quyền ngay trên từng ô.</p>
        </div>
        <div class="matrix-intro-meta">
          <span>{{ roles.length }} nhóm quyền</span>
          <span>{{ permissionCount }} quyền trong hệ thống</span>
        </div>
      </header>

      <div class="matrix-toolbar">
        <label class="matrix-search">
          <AppIcon name="search" size="17" />
          <input v-model.trim="permissionKeyword" type="search" placeholder="Tìm theo chức năng hoặc quyền..." />
        </label>
        <button class="matrix-text-action" type="button" @click="toggleAllGroups">
          {{ allGroupsExpanded ? 'Thu gọn nhóm' : 'Mở tất cả nhóm' }}
        </button>
      </div>

      <div v-if="!filteredPermissionGroups.length" class="matrix-state matrix-state-inline">
        Không tìm thấy quyền phù hợp với từ khóa hiện tại.
      </div>

      <div v-else class="matrix-table-shell">
        <div class="matrix-table-scroll">
          <table class="permission-matrix" aria-label="Ma trận phân quyền theo nhóm quyền">
            <thead>
              <tr>
                <th class="permission-column">Chức năng và quyền</th>
                <th v-for="role in roles" :key="role.id" class="role-column">
                  <div class="role-heading">
                    <span>{{ role.display_name || role.name }}</span>
                    <small>{{ permissionCountForRole(role) }} quyền đã cấp</small>
                    <small>{{ role.is_configurable ? 'Có thể chỉnh sửa' : 'Đang khóa chỉnh sửa' }}</small>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in filteredPermissionGroups" :key="group.group_name">
                <tr class="group-row">
                  <td class="group-name-cell">
                    <button
                      class="group-toggle"
                      type="button"
                      :aria-expanded="isGroupExpanded(group)"
                      @click="toggleGroup(group.group_name)"
                    >
                      <span class="group-toggle-copy">
                        <span class="group-index">{{ groupIndex(group) }}</span>
                        <span>
                          <span class="group-title">{{ group.module_label }}</span>
                          <span class="group-description">{{ group.module_description }}</span>
                        </span>
                      </span>
                      <AppIcon :name="isGroupExpanded(group) ? 'chevronUp' : 'chevronDown'" size="17" />
                    </button>
                  </td>
                  <td v-for="role in roles" :key="role.id" class="group-role-summary">
                    <span>{{ groupGrantedCount(group, role) }}/{{ groupPermissionCount(group) }}</span>
                    <small>quyền</small>
                  </td>
                </tr>

                <template v-if="isGroupExpanded(group)">
                  <tr v-for="row in group.rows" :key="row.key" class="permission-row">
                    <td class="permission-name-cell">
                      <span class="permission-row-label">{{ row.label }}</span>
                      <span class="permission-row-description">{{ row.description }}</span>
                      <span class="permission-row-count">{{ row.permissions.length }} quyền trong chức năng</span>
                    </td>
                    <td v-for="role in roles" :key="role.id" class="permission-cell">
                      <button
                        v-for="permission in row.permissions"
                        :key="permission.id"
                        class="permission-control"
                        :class="{ granted: hasPermission(role, permission.id) }"
                        type="button"
                        :disabled="isPermissionDisabled(role, permission.id)"
                        :title="permissionHint(role, permission.id)"
                        @click="togglePermission(role, permission.id, !hasPermission(role, permission.id))"
                      >
                        <AppIcon
                          :name="hasPermission(role, permission.id) ? 'check' : permissionDisabledReason(role, permission.id) ? 'lock' : 'plus'"
                          size="14"
                        />
                        <span>{{ permission.label }}</span>
                        <small>{{ permissionActionLabel(role, permission.id) }}</small>
                      </button>
                    </td>
                  </tr>
                </template>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { adminRoleService } from '../../services/adminRoles.js';

export default {
  name: 'AdminPermissionMatrix',
  components: { AppIcon },
  props: {
    editable: { type: Boolean, default: false },
  },
  data() {
    return {
      loading: true,
      error: '',
      roles: [],
      permissionGroups: [],
      permissionKeyword: '',
      expandedGroups: [],
      isToggling: null,
    };
  },
  computed: {
    permissionCount() {
      return this.permissionGroups.reduce((total, group) => total + (group.permissions || []).length, 0);
    },
    filteredPermissionGroups() {
      const keyword = this.permissionKeyword.toLowerCase();
      if (!keyword) return this.permissionGroups;

      return this.permissionGroups
        .map((group) => {
          const groupMatches = [group.module_label, group.module_description].filter(Boolean).join(' ').toLowerCase().includes(keyword);
          const rows = (group.rows || [])
            .map((row) => {
              const rowMatches = groupMatches || [row.label, row.description].filter(Boolean).join(' ').toLowerCase().includes(keyword);
              const permissions = rowMatches
                ? row.permissions || []
                : (row.permissions || []).filter((permission) => [permission.code, permission.name, permission.label, permission.description]
                  .filter(Boolean)
                  .join(' ')
                  .toLowerCase()
                  .includes(keyword));
              return { ...row, permissions };
            })
            .filter((row) => row.permissions.length);

          return { ...group, rows };
        })
        .filter((group) => group.rows.length);
    },
    allGroupsExpanded() {
      return this.permissionGroups.length > 0 && this.permissionGroups.every((group) => this.expandedGroups.includes(group.group_name));
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
        this.roles = response.data?.roles || [];
        this.permissionGroups = response.data?.permission_groups || [];
        this.expandedGroups = this.permissionGroups.slice(0, 1).map((group) => group.group_name);
      } catch (error) {
        this.error = error.message || 'Không thể tải cấu hình phân quyền.';
      } finally {
        this.loading = false;
      }
    },
    hasPermission(role, permissionId) {
      return Boolean(role?.permission_ids?.some((id) => Number(id) === Number(permissionId)));
    },
    permissionCountForRole(role) {
      return role?.permission_ids?.length || 0;
    },
    groupPermissionCount(group) {
      return (group.rows || []).reduce((total, row) => total + (row.permissions || []).length, 0);
    },
    groupGrantedCount(group, role) {
      return (group.rows || []).reduce((total, row) => total + (row.permissions || []).filter((permission) => this.hasPermission(role, permission.id)).length, 0);
    },
    groupIndex(group) {
      const index = this.permissionGroups.findIndex((item) => item.group_name === group.group_name);
      return String(index + 1).padStart(2, '0');
    },
    requiredAccessIds(permissionId) {
      for (const group of this.permissionGroups) {
        for (const row of group.rows || []) {
          const accessIds = row.actions?.access?.permission_ids || [];
          const dependentIds = Object.entries(row.actions || {})
            .filter(([key]) => key !== 'access')
            .flatMap(([, action]) => action.permission_ids || []);
          if (dependentIds.some((id) => Number(id) === Number(permissionId))) return accessIds.map(Number);
        }
      }
      return [];
    },
    permissionDisabledReason(role, permissionId) {
      if (!this.editable || !role?.is_configurable) return 'locked';
      if (this.isToggling === `${role.id}-${permissionId}`) return 'loading';
      if (this.hasPermission(role, permissionId)) return '';
      if (this.requiredAccessIds(permissionId).some((id) => !this.hasPermission(role, id))) return 'access';
      return '';
    },
    isPermissionDisabled(role, permissionId) {
      return Boolean(this.permissionDisabledReason(role, permissionId));
    },
    permissionHint(role, permissionId) {
      const reason = this.permissionDisabledReason(role, permissionId);
      if (reason === 'access') return 'Cần cấp quyền Truy cập trước.';
      if (reason === 'locked') return 'Nhóm quyền này đang bị khóa hoặc tài khoản chỉ có quyền xem.';
      if (reason === 'loading') return 'Đang cập nhật quyền.';
      return this.hasPermission(role, permissionId) ? 'Nhấn để thu hồi quyền.' : 'Nhấn để cấp quyền.';
    },
    permissionActionLabel(role, permissionId) {
      const reason = this.permissionDisabledReason(role, permissionId);
      if (reason === 'loading') return 'Đang cập nhật';
      if (this.hasPermission(role, permissionId)) return 'Đã cấp · Nhấn để thu hồi';
      if (reason === 'access') return 'Cần cấp Truy cập trước';
      if (reason === 'locked') return 'Chỉ xem';
      return 'Chưa cấp · Nhấn để cấp';
    },
    isGroupExpanded(group) {
      return Boolean(this.permissionKeyword) || this.expandedGroups.includes(group.group_name);
    },
    toggleGroup(groupName) {
      if (this.expandedGroups.includes(groupName)) {
        this.expandedGroups = this.expandedGroups.filter((name) => name !== groupName);
      } else {
        this.expandedGroups = [...this.expandedGroups, groupName];
      }
    },
    toggleAllGroups() {
      this.expandedGroups = this.allGroupsExpanded ? [] : this.permissionGroups.map((group) => group.group_name);
    },
    async togglePermission(role, permissionId, isChecked) {
      if (this.isPermissionDisabled(role, permissionId)) return;

      const toggleKey = `${role.id}-${permissionId}`;
      const previousIds = [...(role.permission_ids || [])];
      const nextIds = new Set(previousIds.map(Number));
      if (isChecked) nextIds.add(Number(permissionId));
      else nextIds.delete(Number(permissionId));

      role.permission_ids = Array.from(nextIds);
      this.isToggling = toggleKey;

      try {
        await adminRoleService.togglePermission(role.id, permissionId, isChecked ? 'grant' : 'revoke');
      } catch (error) {
        role.permission_ids = previousIds;
        this.error = error.message || 'Lỗi khi thay đổi quyền.';
      } finally {
        this.isToggling = null;
      }
    },
  },
};
</script>

<style scoped>
.matrix-wrapper {
  display: block;
  min-width: 0;
}

.matrix-state {
  padding: 36px 18px;
  color: #475569;
  text-align: center;
}

.matrix-state-error {
  color: #b91c1c;
  background: #fff7f7;
}

.matrix-workspace {
  display: grid;
  gap: 18px;
  min-width: 0;
}

.matrix-intro,
.matrix-toolbar,
.group-toggle,
.role-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.matrix-intro {
  align-items: flex-end;
  padding: 2px 0;
}

.matrix-eyebrow,
.group-index {
  margin: 0;
  color: #047857;
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.matrix-intro h2 {
  margin: 4px 0 0;
  color: #0f172a;
  font-size: 24px;
  font-weight: 400;
}

.matrix-intro p:not(.matrix-eyebrow) {
  margin: 5px 0 0;
  color: #475569;
  line-height: 1.45;
}

.matrix-intro-meta {
  display: grid;
  gap: 5px;
  color: #475569;
  font-size: 13px;
  text-align: right;
}

.matrix-toolbar {
  align-items: center;
}

.matrix-search {
  display: flex;
  align-items: center;
  gap: 9px;
  width: min(420px, 100%);
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  padding: 0 12px;
  background: #fff;
  color: #475569;
}

.matrix-search:focus-within {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.matrix-search > input[type="search"] {
  width: 100%;
  min-height: 42px;
  min-width: 0;
  border: 0 !important;
  border-radius: 0 !important;
  outline: 0 !important;
  box-shadow: none !important;
  color: #0f172a;
  font: inherit;
  background: transparent !important;
  padding: 0 !important;
  appearance: none;
}

.matrix-search > input[type="search"]:focus {
  border: 0 !important;
  outline: 0 !important;
  box-shadow: none !important;
}

.matrix-text-action {
  border: 0;
  padding: 8px 0;
  color: #047857;
  font: inherit;
  cursor: pointer;
}

.matrix-table-shell {
  min-width: 0;
  background: #fff;
}

.matrix-table-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-x: contain;
}

.permission-matrix {
  width: 100%;
  min-width: 980px;
  border-collapse: separate;
  border-spacing: 0 6px;
  table-layout: fixed;
}

.permission-matrix th,
.permission-matrix td {
  padding: 12px;
  text-align: left;
  vertical-align: middle;
}

.permission-matrix th {
  position: sticky;
  top: 0;
  z-index: 4;
  background: #f1f8f3;
  color: #334155;
  font-size: 12px;
  font-weight: 400;
  text-transform: uppercase;
}

.permission-matrix th:first-child,
.permission-matrix td:first-child {
  position: sticky;
  left: 0;
  z-index: 3;
  width: 32%;
  min-width: 300px;
  background: #fff;
  box-shadow: 8px 0 12px -12px rgba(15, 23, 42, 0.4);
}

.permission-matrix th:first-child {
  z-index: 6;
  background: #f1f8f3;
}

.permission-matrix th:not(:first-child),
.permission-matrix td:not(:first-child) {
  width: 17%;
  min-width: 145px;
}

.role-heading {
  display: grid;
  gap: 4px;
  min-height: 54px;
  align-content: center;
}

.role-heading > span {
  color: #0f172a;
  font-size: 14px;
  text-transform: none;
}

.role-heading small,
.group-role-summary small,
.permission-row-count,
.permission-control small {
  color: #475569;
  font-size: 11px;
  font-weight: 400;
  text-transform: none;
}

.group-row td {
  background: #eaf7ee;
  color: #166534;
}

.group-row td:first-child {
  background: #eaf7ee;
}

.group-name-cell {
  padding: 0 !important;
}

.group-toggle {
  width: 100%;
  min-height: 76px;
  align-items: center;
  border: 0;
  padding: 12px;
  background: transparent;
  color: #166534;
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.group-toggle:focus-visible {
  outline: 2px solid #16a34a;
  outline-offset: -3px;
}

.group-toggle-copy {
  display: flex;
  gap: 10px;
  min-width: 0;
}

.group-index {
  flex: 0 0 auto;
  padding-top: 2px;
}

.group-toggle-copy > span:last-child {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.group-title {
  color: #166534;
  font-size: 15px;
}

.group-description {
  color: #475569;
  font-size: 12px;
  line-height: 1.4;
  text-transform: none;
}

.group-role-summary {
  display: table-cell;
  color: #166534;
  text-align: center !important;
}

.group-role-summary span,
.group-role-summary small {
  display: block;
}

.permission-row td {
  background: #f8fafc;
}

.permission-row td:first-child {
  background: #f8fafc;
}

.permission-name-cell {
  display: table-cell;
}

.permission-row-label,
.permission-row-description,
.permission-row-count {
  display: block;
}

.permission-row-label {
  color: #0f172a;
  font-size: 14px;
}

.permission-row-description {
  margin-top: 4px;
  color: #475569;
  font-size: 12px;
  line-height: 1.45;
}

.permission-row-count {
  margin-top: 7px;
}

.permission-cell {
  display: table-cell;
  padding: 8px !important;
}

.permission-control {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  gap: 6px;
  width: 100%;
  min-height: 48px;
  margin: 3px 0;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  padding: 8px 9px;
  background: #fff;
  color: #334155;
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.permission-control > span {
  min-width: 0;
  color: #0f172a;
  font-size: 12px;
  line-height: 1.35;
}

.permission-control small {
  grid-column: 2;
  line-height: 1.25;
}

.permission-control.granted {
  border-color: #86efac;
  background: #f0fdf4;
}

.permission-control.granted > span {
  color: #166534;
}

.permission-control:hover:not(:disabled),
.permission-control:focus-visible:not(:disabled) {
  border-color: #16a34a;
  outline: none;
}

.permission-control:disabled {
  cursor: not-allowed;
  opacity: 0.68;
}

@media (max-width: 760px) {
  .matrix-intro,
  .matrix-toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .matrix-intro-meta {
    text-align: left;
  }

  .matrix-text-action {
    align-self: flex-start;
  }
}
</style>
