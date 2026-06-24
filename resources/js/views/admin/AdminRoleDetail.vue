<template>
  <section class="admin-page">
    <BackButton to="/admin/roles" />

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="loading-card">Đang tải chi tiết nhóm quyền...</div>

    <template v-if="!loading && role">
      <header class="role-header">
        <div>
          <p class="eyebrow">{{ role.display_scope }}</p>
          <h2>{{ role.display_name || role.name }}</h2>
          <p>{{ role.description || 'Chưa có mô tả phạm vi sử dụng.' }}</p>
          <div class="tag-row">
            <span class="badge" :class="role.is_system ? 'badge-system' : 'badge-custom'">
              {{ role.is_system ? 'Nhóm hệ thống' : 'Nhóm tùy chỉnh' }}
            </span>
            <span v-if="!role.can_edit_permissions" class="badge badge-locked">
              Quyền đang khóa chỉnh sửa
            </span>
            <span class="role-code">Mã: <code>{{ role.name }}</code></span>
          </div>
        </div>
      </header>

      <nav class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'info'" class="panel">
        <div class="section-head">
          <h3>Thông tin nhóm quyền</h3>
          <p>Thông tin này giúp admin hiểu nhóm quyền dành cho nhân sự nào.</p>
        </div>

        <form class="detail-form" @submit.prevent="saveInfo">
          <label>
            Mã nhóm
            <input v-model.trim="form.name" :disabled="role.is_system" required />
            <small>Mã nhóm hệ thống không được đổi.</small>
          </label>
          <label>
            Tên hiển thị
            <input v-model.trim="form.display_name" required />
          </label>
          <label>
            Mô tả phạm vi
            <textarea v-model.trim="form.description" rows="4"></textarea>
          </label>

          <div class="info-grid">
            <article>
              <strong>{{ selectedPermissionIds.length }}</strong>
              <span>Quyền đang cấp</span>
            </article>
            <article>
              <strong>{{ users.length }}</strong>
              <span>Nhân sự thuộc nhóm</span>
            </article>
          </div>

          <div class="actions-right">
            <button class="btn primary" type="submit">Lưu thông tin</button>
          </div>
        </form>
      </section>

      <section v-if="activeTab === 'permissions'" class="panel permissions-panel">
        <div class="section-head with-search">
          <div>
            <h3>Quyền được cấp</h3>
            <p>Chọn quyền theo nghiệp vụ. Mã kỹ thuật chỉ để đối chiếu, không phải nội dung chính.</p>
          </div>
          <input v-model.trim="permissionKeyword" placeholder="Tìm quyền..." />
        </div>

        <div v-if="!role.can_edit_permissions" class="alert warning">
          Nhóm này bị khóa chỉnh sửa quyền để tránh mất quyền quản trị lõi.
        </div>

        <div v-if="filteredPermissionGroups.length === 0" class="empty-card">
          Không tìm thấy quyền phù hợp.
        </div>

        <article v-for="group in filteredPermissionGroups" :key="group.group_name" class="permission-group">
          <header class="group-head">
            <div>
              <h4>{{ group.module_label || getModuleMeta(group.group_name).label }}</h4>
              <p>{{ group.module_description || getModuleMeta(group.group_name).description }}</p>
            </div>
            <label class="select-all">
              <input
                type="checkbox"
                :disabled="!role.can_edit_permissions"
                :checked="isGroupChecked(group)"
                :indeterminate.prop="isGroupIndeterminate(group)"
                @change="toggleGroup(group, $event.target.checked)"
              />
              Chọn cả nhóm
            </label>
          </header>

          <div class="permission-list">
            <label
              v-for="permission in group.permissions"
              :key="permission.id"
              class="permission-item"
              :class="{ checked: selectedPermissionIds.includes(permission.id) }"
            >
              <input
                v-model="selectedPermissionIds"
                type="checkbox"
                :value="permission.id"
                :disabled="!role.can_edit_permissions"
              />
              <div>
                <div class="permission-title-row">
                  <strong>{{ permission.label || getPermissionMeta(permission).label }}</strong>
                  <span
                    v-if="permission.risk_label || getPermissionMeta(permission).riskLabel"
                    class="risk-badge"
                    :class="getPermissionMeta(permission).riskClass"
                  >
                    {{ permission.risk_label || getPermissionMeta(permission).riskLabel }}
                  </span>
                </div>
                <p>{{ permission.description || getPermissionMeta(permission).description }}</p>
                <small>Mã quyền: <code>{{ permission.code }}</code></small>
              </div>
            </label>
          </div>
        </article>

        <div v-if="hasPermissionChanges" class="save-bar">
          <span>Có thay đổi quyền chưa lưu cho nhóm {{ role.display_name }}.</span>
          <div>
            <button class="btn secondary" type="button" @click="resetPermissions">Hủy thay đổi</button>
            <button class="btn primary" type="button" @click="confirmSavePermsShow = true">
              Lưu quyền được cấp
            </button>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'users'" class="panel">
        <div class="section-head">
          <h3>Nhân sự thuộc nhóm</h3>
          <p>Danh sách tối đa 100 tài khoản đang được gán nhóm quyền này.</p>
        </div>

        <div v-if="users.length === 0" class="empty-card">
          Chưa có nhân sự thuộc nhóm quyền này.
        </div>

        <div v-for="user in users" :key="user.id" class="user-row">
          <div>
            <strong>{{ user.full_name || user.username }}</strong>
            <span>{{ user.username }} · {{ user.email || 'Chưa có email' }}</span>
          </div>
          <span class="badge" :class="user.status === 'active' ? 'badge-system' : 'badge-locked'">
            {{ user.status === 'active' ? 'Hoạt động' : user.status }}
          </span>
        </div>
      </section>

      <section v-if="activeTab === 'audit'" class="panel">
        <div class="section-head">
          <h3>Lịch sử thay đổi</h3>
          <p>Hiển thị theo dòng thời gian để dễ kiểm tra ai đã sửa nhóm quyền.</p>
        </div>

        <div v-if="auditLogs.length === 0" class="empty-card">
          Chưa có lịch sử thay đổi.
        </div>

        <div v-else class="timeline">
          <article v-for="log in auditLogs" :key="log.id" class="timeline-item">
            <div class="dot"></div>
            <div>
              <header>
                <strong>{{ log.human_message || getAuditActionLabel(log.action) }}</strong>
                <span>{{ formatDate(log.created_at) }}</span>
              </header>

              <div v-if="auditDiffs(log).length" class="diff-list">
                <div v-for="diff in auditDiffs(log)" :key="diff.field" class="diff-row">
                  <template v-if="diff.summary">
                    <strong>{{ diff.summary }}</strong>
                  </template>
                  <template v-else>
                    <strong>{{ diff.field_label || diff.fieldLabel }}</strong>
                    <span>{{ formatDisplayValue(diff.old || diff.oldLabel) }}</span>
                    <span>→</span>
                    <span>{{ formatDisplayValue(diff.new || diff.newLabel) }}</span>
                  </template>
                </div>
              </div>

              <details>
                <summary>Xem dữ liệu kỹ thuật</summary>
                <pre>{{ formatJson({ old: log.old_values, new: log.new_values }) }}</pre>
              </details>
            </div>
          </article>
        </div>
      </section>
    </template>

    <ConfirmModal
      v-model="confirmSavePermsShow"
      title="Lưu quyền được cấp"
      :message="`Bạn đang thay đổi quyền của nhóm ${role?.display_name || ''}.`"
      consequence="Nhân sự thuộc nhóm này sẽ bị ảnh hưởng ngay sau khi lưu. Hãy kiểm tra kỹ các quyền nhạy cảm."
      confirm-text="Lưu quyền"
      type="warning"
      @confirm="savePermissions"
    />
  </section>
</template>

<script>
import ConfirmModal from '../../components/ConfirmModal.vue';
import BackButton from '../../components/BackButton.vue';
import { adminRoleService } from '../../services/adminRoles.js';
import {
  buildAuditDiff,
  getAuditActionLabel,
  getModuleMeta,
  getPermissionMeta,
} from '../../utils/labelMaps.js';

export default {
  name: 'AdminRoleDetail',
  components: { ConfirmModal, BackButton },
  data() {
    return {
      role: null,
      users: [],
      auditLogs: [],
      permissionGroups: [],
      selectedPermissionIds: [],
      originalPermissionIds: [],
      form: { name: '', display_name: '', description: '' },
      permissionKeyword: '',
      activeTab: this.$route.query.tab || 'info',
      loading: false,
      error: '',
      success: '',
      confirmSavePermsShow: false,
      tabs: [
        { key: 'info', label: 'Thông tin nhóm quyền' },
        { key: 'permissions', label: 'Quyền được cấp' },
        { key: 'users', label: 'Nhân sự thuộc nhóm' },
        { key: 'audit', label: 'Lịch sử thay đổi' },
      ],
    };
  },
  computed: {
    hasPermissionChanges() {
      return this.sortedIds(this.selectedPermissionIds).join(',') !== this.sortedIds(this.originalPermissionIds).join(',');
    },
    filteredPermissionGroups() {
      const keyword = this.permissionKeyword.toLowerCase();
      if (!keyword) return this.permissionGroups;

      return this.permissionGroups
        .map((group) => ({
          ...group,
          permissions: group.permissions.filter((permission) => {
            const meta = getPermissionMeta(permission);
            return [
              permission.code,
              permission.name,
              permission.label,
              permission.description,
              meta.label,
              meta.description,
            ].filter(Boolean).join(' ').toLowerCase().includes(keyword);
          }),
        }))
        .filter((group) => group.permissions.length > 0);
    },
  },
  mounted() {
    this.loadDetail();
  },
  methods: {
    getAuditActionLabel,
    getModuleMeta,
    getPermissionMeta,
    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminRoleService.show(this.$route.params.id);
        const data = response.data || {};
        this.role = data.role;
        this.users = data.users || [];
        this.auditLogs = data.audit_logs || [];
        this.permissionGroups = data.permission_groups || [];
        this.selectedPermissionIds = (data.permissions || []).map((permission) => permission.id);
        this.originalPermissionIds = [...this.selectedPermissionIds];
        this.form = {
          name: this.role.name,
          display_name: this.role.display_name || '',
          description: this.role.description || '',
        };
      } catch (error) {
        this.error = error.message || 'Không tải được chi tiết nhóm quyền.';
      } finally {
        this.loading = false;
      }
    },
    async saveInfo() {
      await this.runAction(
        () => adminRoleService.update(this.role.id, this.form),
        'Đã cập nhật thông tin nhóm quyền.',
      );
    },
    async savePermissions() {
      await this.runAction(
        () => adminRoleService.updatePermissions(this.role.id, this.selectedPermissionIds),
        'Đã cập nhật quyền được cấp.',
      );
    },
    resetPermissions() {
      this.selectedPermissionIds = [...this.originalPermissionIds];
    },
    toggleGroup(group, checked) {
      if (!this.role.can_edit_permissions) return;

      const ids = group.permissions.map((permission) => permission.id);
      if (checked) {
        this.selectedPermissionIds = this.sortedIds([...new Set([...this.selectedPermissionIds, ...ids])]);
        return;
      }

      this.selectedPermissionIds = this.selectedPermissionIds.filter((id) => !ids.includes(id));
    },
    isGroupChecked(group) {
      return group.permissions.length > 0 && group.permissions.every((permission) => this.selectedPermissionIds.includes(permission.id));
    },
    isGroupIndeterminate(group) {
      const checked = group.permissions.filter((permission) => this.selectedPermissionIds.includes(permission.id)).length;
      return checked > 0 && checked < group.permissions.length;
    },
    async runAction(action, fallbackMessage) {
      this.error = '';
      this.success = '';
      try {
        const response = await action();
        this.success = response.message || fallbackMessage;
        await this.loadDetail();
        setTimeout(() => { this.success = ''; }, 3500);
      } catch (error) {
        this.error = error.message || 'Thao tác không thành công.';
      }
    },
    auditDiffs(log) {
      return log.changes_summary?.length ? log.changes_summary : buildAuditDiff(log.old_values, log.new_values);
    },
    sortedIds(ids) {
      return ids.map((id) => Number(id)).sort((a, b) => a - b);
    },
    formatJson(value) {
      return JSON.stringify(value, null, 2);
    },
    formatDisplayValue(value) {
      if (value === null || value === undefined || value === '') return '(trống)';
      if (typeof value === 'boolean') return value ? 'Có' : 'Không';
      if (Array.isArray(value) || typeof value === 'object') return 'Dữ liệu kỹ thuật đã thay đổi';
      return String(value);
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
</script>

<style scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.link-btn {
  align-self: flex-start;
  border: 0;
  background: transparent;
  color: #16a34a;
  font-weight: 800;
  cursor: pointer;
}

.role-header,
.panel,
.loading-card,
.empty-card {
  border: 1px solid var(--admin-border);
  border-radius: 10px;
  background: var(--admin-surface, #fff);
}

.role-header {
  padding: 22px;
}

.eyebrow {
  margin: 0 0 5px;
  color: #16a34a;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
}

h2,
h3,
h4,
p {
  margin: 0;
}

h2 {
  color: var(--admin-text);
  font-size: 24px;
}

.role-header p:not(.eyebrow),
.section-head p,
.group-head p,
.permission-item p {
  color: var(--admin-muted);
  line-height: 1.5;
}

.tag-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin-top: 14px;
}

.role-code {
  color: var(--admin-muted);
  font-size: 13px;
}

code {
  border-radius: 4px;
  background: var(--admin-surface-muted);
  padding: 1px 5px;
}

.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--admin-border);
  overflow-x: auto;
}

.tabs button {
  border: 0;
  border-bottom: 3px solid transparent;
  background: transparent;
  padding: 11px 14px;
  color: var(--admin-muted);
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
}

.tabs button.active {
  border-color: #16a34a;
  color: #16a34a;
}

.panel {
  padding: 22px;
}

.section-head {
  margin-bottom: 18px;
}

.with-search {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.with-search input {
  max-width: 320px;
}

.detail-form,
.permission-group,
.permission-list,
.timeline {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--admin-text);
  font-weight: 800;
}

input,
textarea {
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 10px 12px;
  color: var(--admin-text);
  font: inherit;
  background: var(--admin-surface, #fff);
}

input:focus,
textarea:focus {
  outline: none;
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}

input:disabled,
textarea:disabled {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
}

small {
  color: var(--admin-muted);
  font-weight: 400;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.info-grid article {
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 14px;
}

.info-grid strong {
  display: block;
  color: var(--admin-text);
  font-size: 26px;
}

.info-grid span {
  color: var(--admin-muted);
  font-size: 13px;
}

.actions-right {
  display: flex;
  justify-content: flex-end;
}

.permission-group {
  border: 1px solid var(--admin-border);
  border-radius: 10px;
  overflow: hidden;
}

.group-head {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
  padding: 16px 18px;
  background: var(--admin-surface-muted);
  border-bottom: 1px solid var(--admin-border);
}

.select-all {
  flex: 0 0 auto;
  flex-direction: row;
  align-items: center;
  white-space: nowrap;
}

.select-all input,
.permission-item input {
  width: auto;
}

.permission-list {
  gap: 0;
}

.permission-item {
  flex-direction: row;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
}

.permission-item:last-child {
  border-bottom: 0;
}

.permission-item.checked {
  background: #f0fdf4;
}

.permission-title-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.risk-badge,
.badge {
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 12px;
  font-weight: 800;
}

.risk-sensitive,
.risk-account-lock,
.badge-locked {
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

.badge-system {
  background: #dcfce7;
  color: #166534;
}

.badge-custom {
  background: #e0f2fe;
  color: #075985;
}

.user-row {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f1f5f9;
}

.user-row div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.user-row span {
  color: var(--admin-muted);
}

.timeline-item {
  display: grid;
  grid-template-columns: 14px 1fr;
  gap: 12px;
}

.dot {
  width: 12px;
  height: 12px;
  margin-top: 5px;
  border-radius: 999px;
  background: #16a34a;
}

.timeline-item header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: var(--admin-text);
}

.timeline-item header span {
  color: var(--admin-muted);
  font-size: 13px;
}

.diff-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
}

.diff-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  color: var(--admin-faint);
  font-size: 13px;
}

details {
  margin-top: 8px;
  color: var(--admin-muted);
}

summary {
  cursor: pointer;
  font-weight: 700;
}

pre {
  max-height: 260px;
  overflow: auto;
  border-radius: 8px;
  background: #0f172a;
  color: #e2e8f0;
  padding: 12px;
  font-size: 12px;
}

.save-bar {
  position: sticky;
  bottom: 12px;
  z-index: 20;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 12px;
  background: #f0fdf4;
  color: #166534;
}

.save-bar div {
  display: flex;
  gap: 8px;
}

.alert {
  border-radius: 8px;
  padding: 11px 13px;
  font-weight: 700;
}

.alert.error {
  background: #fef2f2;
  color: #991b1b;
}

.alert.success {
  background: #ecfdf5;
  color: #047857;
}

.alert.warning {
  background: #fffbeb;
  color: #92400e;
}

.loading-card,
.empty-card {
  padding: 28px;
  color: var(--admin-muted);
  text-align: center;
}

.btn {
  border: 0;
  border-radius: 8px;
  padding: 9px 13px;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.btn.primary {
  background: #16a34a;
  color: #fff;
}

.btn.secondary {
  background: var(--admin-border);
  color: var(--admin-text);
}

@media (max-width: 760px) {
  .with-search,
  .group-head,
  .save-bar,
  .save-bar div,
  .user-row,
  .timeline-item header {
    flex-direction: column;
    align-items: stretch;
  }

  .with-search input {
    max-width: none;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
