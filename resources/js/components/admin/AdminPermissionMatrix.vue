<template>
  <section class="permission-matrix">

    <!-- Header Banner -->
    <div class="matrix-banner">
      <div class="banner-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <div class="banner-text">
        <h2>Ma trận phân quyền</h2>
        <p>Cấp hoặc thu hồi quyền trực tiếp trên lưới theo danh sách quyền thực tế trong hệ thống.</p>
      </div>
      <div class="banner-stats" v-if="!loading && roles.length">
        <div class="stat-item">
          <span class="stat-number">{{ roles.length }}</span>
          <span class="stat-label">Vai trò</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">{{ totalPermissions }}</span>
          <span class="stat-label">Quyền</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">{{ permissionGroups.length }}</span>
          <span class="stat-label">Nhóm</span>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="matrix-toolbar">
      <div class="search-wrapper">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input v-model.trim="keyword" class="search-input" placeholder="Tìm quyền, mã quyền, nhóm hoặc vai trò…" />
        <button v-if="keyword" class="search-clear" type="button" @click="keyword = ''">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <button class="btn-refresh" type="button" :disabled="loading" @click="loadMatrix">
        <svg :class="{ spin: loading }" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
          <path d="M21 3v5h-5"/>
        </svg>
        {{ loading ? 'Đang tải...' : 'Làm mới' }}
      </button>
    </div>

    <!-- Alerts -->
    <transition name="slide-alert">
      <div v-if="error" class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
        {{ error }}
      </div>
    </transition>
    <transition name="slide-alert">
      <div v-if="success" class="alert alert-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
        {{ success }}
      </div>
    </transition>

    <!-- Loading State -->
    <div v-if="loading" class="state-card">
      <div class="loader-ring"></div>
      <p>Đang tải ma trận phân quyền...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredGroups.length === 0 && !loading" class="state-card">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <p>Không có quyền nào phù hợp bộ lọc.</p>
    </div>

    <!-- Matrix Table -->
    <div v-else class="matrix-card">
      <div class="matrix-scroll">
        <table>
          <thead>
            <tr>
              <th class="sticky-col permission-col">
                <div class="perm-header-cell">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  Quyền hệ thống
                </div>
              </th>
              <th v-for="role in visibleRoles" :key="role.id" class="role-col">
                <div class="role-head">
                  <div class="role-avatar" :data-initial="(role.display_name || role.name).charAt(0).toUpperCase()"></div>
                  <strong>{{ role.display_name || role.name }}</strong>
                  <span class="role-badge" :class="role.is_system ? 'badge-system' : 'badge-custom'">
                    {{ role.is_system ? 'Hệ thống' : 'Tùy chỉnh' }}
                  </span>
                  <span v-if="!role.is_configurable" class="badge-locked">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Khóa
                  </span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in filteredGroups" :key="group.group_name">
              <!-- Group Row -->
              <tr class="group-row">
                <td class="sticky-col permission-col">
                  <div class="group-info">
                    <div class="group-dot"></div>
                    <div>
                      <strong>{{ group.module_label || group.group_name }}</strong>
                      <span class="group-count">{{ group.permissions.length }} quyền</span>
                    </div>
                  </div>
                </td>
                <td v-for="role in visibleRoles" :key="`${group.group_name}-${role.id}`" class="bulk-cell">
                  <div class="bulk-actions">
                    <button
                      class="bulk-btn bulk-grant"
                      type="button"
                      :disabled="!canBulkGrant(role, group)"
                      @click="toggleGroup(role, group, true)"
                      title="Bật toàn bộ nhóm"
                    >
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                      Bật
                    </button>
                    <button
                      class="bulk-btn bulk-revoke"
                      type="button"
                      :disabled="!canBulkRevoke(role, group)"
                      @click="toggleGroup(role, group, false)"
                      title="Tắt toàn bộ nhóm"
                    >
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6 6 18M6 6l12 12"/></svg>
                      Tắt
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Permission Rows -->
              <tr v-for="permission in group.permissions" :key="permission.id" class="perm-row">
                <td class="sticky-col permission-col">
                  <div class="permission-info">
                    <strong>{{ permission.label || permission.name }}</strong>
                    <code class="perm-code">{{ permission.code }}</code>
                    <span v-if="permission.risk_label" class="risk-badge" :class="riskClass(permission.risk_level)">
                      {{ permission.risk_label }}
                    </span>
                  </div>
                </td>
                <td v-for="role in visibleRoles" :key="`${role.id}-${permission.id}`" class="toggle-cell">
                  <button
                    class="cell-toggle"
                    :class="{
                      'toggle-on': hasPermission(role, permission.id),
                      'toggle-locked': !role.is_configurable,
                      'toggle-busy': isBusy(role.id, permission.id)
                    }"
                    type="button"
                    :disabled="!role.is_configurable || isBusy(role.id, permission.id)"
                    :aria-pressed="hasPermission(role, permission.id)"
                    @click="togglePermission(role, permission)"
                  >
                    <span class="toggle-track">
                      <span class="toggle-thumb"></span>
                    </span>
                    <span class="toggle-label">{{ hasPermission(role, permission.id) ? 'Đã cấp' : 'Chưa cấp' }}</span>
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
    totalPermissions() {
      return this.permissionGroups.reduce((sum, g) => sum + g.permissions.length, 0);
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
          ? `✓ Đã cấp quyền "${permission.label || permission.name}" cho ${role.display_name || role.name}.`
          : `✓ Đã thu hồi quyền "${permission.label || permission.name}" khỏi ${role.display_name || role.name}.`;
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
          ? `✓ Đã bật nhóm quyền "${group.module_label || group.group_name}" cho ${role.display_name || role.name}.`
          : `✓ Đã tắt nhóm quyền "${group.module_label || group.group_name}" cho ${role.display_name || role.name}.`;
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
      setTimeout(() => { this.success = ''; }, 3000);
    },
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

/* ─── Root ────────────────────────────────────────────── */
.permission-matrix {
  display: grid;
  gap: 16px;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ─── Banner ─────────────────────────────────────────── */
.matrix-banner {
  display: flex;
  align-items: center;
  gap: 18px;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border-radius: 16px;
  padding: 22px 28px;
  color: #fff;
  position: relative;
  overflow: hidden;
}

.matrix-banner::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at top right, rgba(99,102,241,.35) 0%, transparent 65%);
  pointer-events: none;
}

.banner-icon {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: rgba(99,102,241,.25);
  border: 1px solid rgba(99,102,241,.4);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #a5b4fc;
}

.banner-text {
  flex: 1;
}

.banner-text h2 {
  margin: 0 0 4px;
  font-size: 1.2rem;
  font-weight: 700;
  color: #f1f5f9;
  letter-spacing: -0.01em;
}

.banner-text p {
  margin: 0;
  font-size: 0.84rem;
  color: #94a3b8;
  line-height: 1.5;
}

.banner-stats {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-shrink: 0;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.stat-number {
  font-size: 1.5rem;
  font-weight: 800;
  color: #e2e8f0;
  line-height: 1;
}

.stat-label {
  font-size: 0.72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 600;
}

.stat-divider {
  width: 1px;
  height: 32px;
  background: rgba(255,255,255,.12);
}

/* ─── Toolbar ─────────────────────────────────────────── */
.matrix-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 16px;
}

.search-wrapper {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}

.search-input {
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 9px 36px 9px 38px;
  font: inherit;
  font-size: 0.875rem;
  color: #1e293b;
  background: #f8fafc;
  transition: border-color .2s, box-shadow .2s, background .2s;
  outline: none;
}

.search-input::placeholder {
  color: #94a3b8;
}

.search-input:focus {
  border-color: #6366f1;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}

.search-clear {
  position: absolute;
  right: 10px;
  background: #e2e8f0;
  border: 0;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #64748b;
  transition: background .15s;
}

.search-clear:hover {
  background: #cbd5e1;
}

.btn-refresh {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  color: #475569;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all .2s;
}

.btn-refresh:hover:not(:disabled) {
  border-color: #6366f1;
  color: #6366f1;
  background: #f5f3ff;
}

.btn-refresh:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.spin {
  animation: spin .8s linear infinite;
}

/* ─── Alerts ──────────────────────────────────────────── */
.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 0.875rem;
  font-weight: 600;
}

.alert-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.alert-success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.slide-alert-enter-active,
.slide-alert-leave-active {
  transition: all .25s ease;
}

.slide-alert-enter-from,
.slide-alert-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ─── State Cards ─────────────────────────────────────── */
.state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 56px 24px;
  color: #94a3b8;
  font-size: 0.9rem;
  font-weight: 500;
}

.loader-ring {
  width: 36px;
  height: 36px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}

/* ─── Matrix Card ─────────────────────────────────────── */
.matrix-card {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(15,23,42,.06);
}

.matrix-scroll {
  overflow: auto;
  max-height: 72vh;
}

/* ─── Table ───────────────────────────────────────────── */
table {
  width: max-content;
  min-width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

th,
td {
  border-right: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  padding: 0;
  vertical-align: middle;
}

th {
  position: sticky;
  top: 0;
  z-index: 3;
  background: #f8fafc;
  border-bottom-color: #e2e8f0;
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

/* ─── Permission Header ───────────────────────────────── */
.perm-header-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 18px;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #475569;
}

/* ─── Permission Column ───────────────────────────────── */
.permission-col {
  width: 300px;
  min-width: 300px;
}

/* ─── Role Column ─────────────────────────────────────── */
.role-col {
  width: 160px;
  min-width: 160px;
  text-align: center;
}

.role-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  padding: 14px 12px;
}

.role-avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 2px;
}

.role-avatar::after {
  content: attr(data-initial);
}

.role-head strong {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  text-align: center;
}

.role-badge {
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: .02em;
}

.badge-system {
  background: #ede9fe;
  color: #5b21b6;
}

.badge-custom {
  background: #e0f2fe;
  color: #0369a1;
}

.badge-locked {
  display: flex;
  align-items: center;
  gap: 4px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  padding: 2px 8px;
  font-size: 0.68rem;
  font-weight: 700;
}

/* ─── Group Row ───────────────────────────────────────── */
.group-row td {
  background: linear-gradient(to right, #f8fafc, #f1f5f9);
}

.group-row .sticky-col {
  background: #f8fafc;
}

.group-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 18px;
}

.group-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  flex-shrink: 0;
}

.group-info strong {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: #1e293b;
}

.group-count {
  font-size: 0.72rem;
  color: #94a3b8;
  font-weight: 500;
  margin-top: 1px;
  display: block;
}

/* ─── Bulk Actions ────────────────────────────────────── */
.bulk-cell {
  padding: 8px 12px;
  text-align: center;
  background: linear-gradient(to right, #f8fafc, #f1f5f9);
}

.bulk-actions {
  display: flex;
  justify-content: center;
  gap: 5px;
}

.bulk-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1.5px solid transparent;
  border-radius: 7px;
  padding: 4px 10px;
  font: inherit;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .15s;
}

.bulk-grant {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}

.bulk-grant:hover:not(:disabled) {
  background: #dcfce7;
  border-color: #86efac;
}

.bulk-revoke {
  background: #fef2f2;
  border-color: #fecaca;
  color: #991b1b;
}

.bulk-revoke:hover:not(:disabled) {
  background: #fee2e2;
  border-color: #fca5a5;
}

.bulk-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ─── Permission Row ──────────────────────────────────── */
.perm-row:hover td {
  background: #fafbff;
}

.perm-row:hover .sticky-col {
  background: #fafbff;
}

.permission-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px 18px;
}

.permission-info strong {
  font-size: 0.84rem;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.3;
}

.perm-code {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: 0.72rem;
  color: #6366f1;
  background: #f0f0ff;
  border-radius: 4px;
  padding: 1px 5px;
  width: fit-content;
}

/* ─── Risk Badges ─────────────────────────────────────── */
.risk-badge {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.7rem;
  font-weight: 700;
  margin-top: 2px;
}

.risk-sensitive,
.risk-account-lock {
  background: #fef2f2;
  color: #991b1b;
}

.risk-finance {
  background: #fefce8;
  color: #854d0e;
}

.risk-system {
  background: #eff6ff;
  color: #1d4ed8;
}

.risk-permission {
  background: #faf5ff;
  color: #6d28d9;
}

/* ─── Toggle Cell ─────────────────────────────────────── */
.toggle-cell {
  text-align: center;
  padding: 10px 14px;
}

.cell-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 0;
  border-radius: 9px;
  padding: 6px 10px 6px 6px;
  font: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  background: #f1f5f9;
  color: #64748b;
  transition: all .2s ease;
  white-space: nowrap;
  min-width: 100px;
}

.cell-toggle:not(:disabled):hover {
  transform: scale(1.03);
  box-shadow: 0 2px 8px rgba(0,0,0,.1);
}

.cell-toggle:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

/* Toggle Track */
.toggle-track {
  width: 28px;
  height: 16px;
  border-radius: 999px;
  background: #cbd5e1;
  position: relative;
  flex-shrink: 0;
  transition: background .2s;
}

.toggle-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,.25);
  transition: transform .2s ease;
}

/* ON State */
.toggle-on {
  background: #f0fdf4;
  color: #166534;
}

.toggle-on .toggle-track {
  background: #22c55e;
}

.toggle-on .toggle-thumb {
  transform: translateX(12px);
}

/* Locked State */
.toggle-locked {
  background: #f8fafc;
  color: #94a3b8;
}

/* Busy State */
.toggle-busy {
  opacity: 0.6;
}

.toggle-busy .toggle-thumb {
  animation: spin .6s linear infinite;
}

@media (max-width: 900px) {
  .matrix-banner {
    flex-wrap: wrap;
    gap: 12px;
  }

  .banner-stats {
    width: 100%;
    justify-content: flex-start;
  }

  .matrix-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-refresh {
    justify-content: center;
  }
}
</style>
