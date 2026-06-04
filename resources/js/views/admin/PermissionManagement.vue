<template>
  <section class="permission-management">

    <!-- Toolbar -->
    <div class="toolbar">
      <div>
        <h2>Quản lý Phân quyền</h2>
        <p>Quản lý vai trò (Role) và quyền hạn (Permission) trong hệ thống.</p>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="['tab-btn', activeTab === 'roles' ? 'active' : '']" @click="activeTab = 'roles'">
        🛡️ Vai trò (Roles)
      </button>
      <button :class="['tab-btn', activeTab === 'permissions' ? 'active' : '']" @click="activeTab = 'permissions'">
        🔑 Quyền hạn (Permissions)
      </button>
    </div>

    <!-- ==================== TAB ROLES ==================== -->
    <div v-if="activeTab === 'roles'">
      <div class="section-header">
        <span class="section-count">{{ roles.length }} vai trò</span>
        <button class="btn sg-primary" @click="openCreateRoleModal">+ Thêm vai trò</button>
      </div>

      <div v-if="loadingRoles" class="loading-state">Đang tải...</div>
      <div v-else-if="roles.length === 0" class="empty-state">Chưa có vai trò nào.</div>

      <div v-else class="roles-grid">
        <div v-for="role in roles" :key="role.id" class="role-card">
          <div class="role-header">
            <div class="role-title-wrap">
              <span class="role-name">{{ role.display_name || role.name }}</span>
              <span class="role-slug">{{ role.name }}</span>
            </div>
            <span v-if="role.is_system" class="badge system">Hệ thống</span>
          </div>

          <p v-if="role.description" class="role-desc">{{ role.description }}</p>

          <div class="role-permissions">
            <span class="perm-count">{{ (role.permissions || []).length }} quyền</span>
            <div class="perm-tags">
              <span
                v-for="perm in (role.permissions || []).slice(0, 5)"
                :key="perm.id"
                class="perm-tag"
              >{{ perm.name }}</span>
              <span v-if="(role.permissions || []).length > 5" class="perm-tag more">
                +{{ (role.permissions || []).length - 5 }} khác
              </span>
            </div>
          </div>

          <div class="role-actions">
            <button class="btn btn-sm primary" @click="openEditRoleModal(role)" :disabled="role.is_system">
              Chỉnh sửa
            </button>
            <button class="btn btn-sm danger" @click="deleteRole(role)" :disabled="role.is_system">
              Xóa
            </button>
          </div>
          <p v-if="role.is_system" class="system-note">Vai trò hệ thống không thể chỉnh sửa</p>
        </div>
      </div>
    </div>

    <!-- ==================== TAB PERMISSIONS ==================== -->
    <div v-if="activeTab === 'permissions'">
      <div class="section-header">
        <span class="section-count">{{ allPermissions.length }} quyền</span>
        <button class="btn sg-primary" @click="openCreatePermModal">+ Thêm quyền</button>
      </div>

      <div v-if="loadingPerms" class="loading-state">Đang tải...</div>
      <div v-else-if="allPermissions.length === 0" class="empty-state">Chưa có quyền nào.</div>

      <div v-else>
        <div v-for="(perms, group) in permsByGroup" :key="group" class="perm-group">
          <div class="perm-group-header">
            <span class="group-name">{{ group || 'Chung' }}</span>
            <span class="group-count">{{ perms.length }} quyền</span>
          </div>
          <div class="perm-table">
            <div class="perm-row header">
              <span>Mã quyền (Code)</span>
              <span>Tên quyền</span>
              <span>Thao tác</span>
            </div>
            <div v-for="perm in perms" :key="perm.id" class="perm-row">
              <span class="perm-code">{{ perm.code }}</span>
              <span>{{ perm.name }}</span>
              <button class="btn btn-sm danger" @click="deletePermission(perm)">Xóa</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== MODAL: Tạo/Sửa Role ==================== -->
    <div v-if="showRoleModal" class="modal-backdrop" @click.self="closeRoleModal">
      <div class="modal large">
        <div class="modal-header">
          <h3>{{ isEditingRole ? 'Chỉnh sửa vai trò' : 'Thêm vai trò mới' }}</h3>
          <button class="btn-close" @click="closeRoleModal">&times;</button>
        </div>
        <form @submit.prevent="saveRole">
          <div class="form-grid">
            <div class="form-group" v-if="!isEditingRole">
              <label>Tên định danh (name) *</label>
              <input v-model="roleForm.name" type="text" placeholder="vd: moderator" required />
              <span class="hint">Chỉ dùng chữ thường, số và dấu gạch dưới</span>
            </div>
            <div class="form-group">
              <label>Tên hiển thị *</label>
              <input v-model="roleForm.display_name" type="text" placeholder="vd: Kiểm duyệt viên" required />
            </div>
            <div class="form-group full-width">
              <label>Mô tả</label>
              <textarea v-model="roleForm.description" rows="2" placeholder="Mô tả ngắn về vai trò này..."></textarea>
            </div>

            <div class="form-group full-width">
              <label>Phân quyền cho vai trò này</label>
              <div class="perm-selector">
                <div v-for="(perms, group) in permsByGroup" :key="group" class="selector-group">
                  <div class="selector-group-header">
                    <label class="checkbox-label">
                      <input
                        type="checkbox"
                        :checked="isGroupAllChecked(group, perms)"
                        :indeterminate.prop="isGroupIndeterminate(group, perms)"
                        @change="toggleGroup(group, perms, $event)"
                      />
                      <strong>{{ group || 'Chung' }}</strong>
                    </label>
                  </div>
                  <div class="selector-perms">
                    <label v-for="perm in perms" :key="perm.id" class="checkbox-label perm-check">
                      <input type="checkbox" :value="perm.id" v-model="roleForm.permissions" />
                      <span class="perm-check-name">{{ perm.name }}</span>
                      <span class="perm-check-code">{{ perm.code }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn secondary" @click="closeRoleModal">Hủy</button>
            <button type="submit" class="btn sg-primary" :disabled="savingRole">
              {{ savingRole ? 'Đang lưu...' : (isEditingRole ? 'Cập nhật' : 'Tạo vai trò') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ==================== MODAL: Thêm Permission ==================== -->
    <div v-if="showPermModal" class="modal-backdrop" @click.self="closePermModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Thêm quyền mới</h3>
          <button class="btn-close" @click="closePermModal">&times;</button>
        </div>
        <form @submit.prevent="savePermission">
          <div class="form-grid">
            <div class="form-group full-width">
              <label>Mã quyền (code) *</label>
              <input v-model="permForm.code" type="text" placeholder="vd: banner.create" required />
              <span class="hint">Dùng dấu chấm để phân nhóm: nhóm.hành_động</span>
            </div>
            <div class="form-group full-width">
              <label>Tên quyền *</label>
              <input v-model="permForm.name" type="text" placeholder="vd: Tạo banner mới" required />
            </div>
            <div class="form-group full-width">
              <label>Nhóm quyền *</label>
              <input
                v-model="permForm.group_name"
                type="text"
                list="group-list"
                placeholder="vd: Banner, Người dùng..."
                required
              />
              <datalist id="group-list">
                <option v-for="g in existingGroups" :key="g" :value="g" />
              </datalist>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn secondary" @click="closePermModal">Hủy</button>
            <button type="submit" class="btn sg-primary" :disabled="savingPerm">
              {{ savingPerm ? 'Đang lưu...' : 'Tạo quyền' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </section>
</template>

<script>
import { api } from '../../services/api.js';

export default {
  name: 'PermissionManagement',

  data() {
    return {
      activeTab: 'roles',

      // Roles
      roles: [],
      loadingRoles: false,
      showRoleModal: false,
      isEditingRole: false,
      savingRole: false,
      roleForm: { id: null, name: '', display_name: '', description: '', permissions: [] },

      // Permissions
      allPermissions: [],
      loadingPerms: false,
      showPermModal: false,
      savingPerm: false,
      permForm: { code: '', name: '', group_name: '' },

      error: '',
      success: '',
    };
  },

  computed: {
    permsByGroup() {
      const groups = {};
      for (const p of this.allPermissions) {
        const g = p.group_name || 'Chung';
        if (!groups[g]) groups[g] = [];
        groups[g].push(p);
      }
      return groups;
    },
    existingGroups() {
      return Object.keys(this.permsByGroup);
    },
  },

  mounted() {
    this.loadRoles();
    this.loadPermissions();
  },

  methods: {
    // ---- Fetch ----
    async loadRoles() {
      this.loadingRoles = true;
      try {
        const res = await api('/api/admin/permissions/roles');
        this.roles = res.data ?? res ?? [];
      } catch (e) {
        this.error = 'Không thể tải danh sách vai trò: ' + e.message;
      } finally {
        this.loadingRoles = false;
      }
    },

    async loadPermissions() {
      this.loadingPerms = true;
      try {
        const res = await api('/api/admin/permissions/list');
        this.allPermissions = res.data ?? res ?? [];
      } catch (e) {
        this.error = 'Không thể tải danh sách quyền: ' + e.message;
      } finally {
        this.loadingPerms = false;
      }
    },

    // ---- Role Modal ----
    openCreateRoleModal() {
      this.isEditingRole = false;
      this.roleForm = { id: null, name: '', display_name: '', description: '', permissions: [] };
      this.showRoleModal = true;
      this.clearAlerts();
    },

    openEditRoleModal(role) {
      this.isEditingRole = true;
      this.roleForm = {
        id: role.id,
        name: role.name,
        display_name: role.display_name || '',
        description: role.description || '',
        permissions: (role.permissions || []).map(p => p.id),
      };
      this.showRoleModal = true;
      this.clearAlerts();
    },

    closeRoleModal() {
      this.showRoleModal = false;
    },

    async saveRole() {
      this.savingRole = true;
      this.clearAlerts();
      try {
        if (this.isEditingRole) {
          await api(`/api/admin/permissions/roles/${this.roleForm.id}`, {
            method: 'PUT',
            body: JSON.stringify({
              display_name: this.roleForm.display_name,
              description: this.roleForm.description,
              permissions: this.roleForm.permissions,
            }),
          });
          this.success = 'Cập nhật vai trò thành công!';
        } else {
          await api('/api/admin/permissions/roles', {
            method: 'POST',
            body: JSON.stringify({
              name: this.roleForm.name,
              display_name: this.roleForm.display_name,
              description: this.roleForm.description,
              permissions: this.roleForm.permissions,
            }),
          });
          this.success = 'Tạo vai trò mới thành công!';
        }
        this.closeRoleModal();
        this.loadRoles();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.savingRole = false;
      }
    },

    async deleteRole(role) {
      if (!confirm(`Xóa vai trò "${role.display_name || role.name}"? Hành động này không thể hoàn tác.`)) return;
      this.clearAlerts();
      try {
        await api(`/api/admin/permissions/roles/${role.id}`, { method: 'DELETE' });
        this.success = 'Xóa vai trò thành công!';
        this.loadRoles();
      } catch (e) {
        this.error = e.message;
      }
    },

    // ---- Permission Modal ----
    openCreatePermModal() {
      this.permForm = { code: '', name: '', group_name: '' };
      this.showPermModal = true;
      this.clearAlerts();
    },

    closePermModal() {
      this.showPermModal = false;
    },

    async savePermission() {
      this.savingPerm = true;
      this.clearAlerts();
      try {
        await api('/api/admin/permissions', {
          method: 'POST',
          body: JSON.stringify(this.permForm),
        });
        this.success = 'Tạo quyền mới thành công!';
        this.closePermModal();
        this.loadPermissions();
        this.loadRoles(); // reload roles vì có thể roles hiển thị perm mới
      } catch (e) {
        this.error = e.message;
      } finally {
        this.savingPerm = false;
      }
    },

    async deletePermission(perm) {
      if (!confirm(`Xóa quyền "${perm.name}" (${perm.code})?`)) return;
      this.clearAlerts();
      try {
        await api(`/api/admin/permissions/${perm.id}`, { method: 'DELETE' });
        this.success = 'Xóa quyền thành công!';
        this.loadPermissions();
        this.loadRoles();
      } catch (e) {
        this.error = e.message;
      }
    },

    // ---- Checkbox group helpers ----
    isGroupAllChecked(group, perms) {
      return perms.every(p => this.roleForm.permissions.includes(p.id));
    },
    isGroupIndeterminate(group, perms) {
      const checked = perms.filter(p => this.roleForm.permissions.includes(p.id)).length;
      return checked > 0 && checked < perms.length;
    },
    toggleGroup(group, perms, event) {
      if (event.target.checked) {
        perms.forEach(p => {
          if (!this.roleForm.permissions.includes(p.id)) {
            this.roleForm.permissions.push(p.id);
          }
        });
      } else {
        this.roleForm.permissions = this.roleForm.permissions.filter(
          id => !perms.some(p => p.id === id)
        );
      }
    },

    clearAlerts() {
      this.error = '';
      this.success = '';
    },
  },
};
</script>

<style scoped>
.permission-management {
  padding: 2rem;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.toolbar h2 {
  margin: 0;
  font-size: 1.75rem;
  color: #333;
}

.toolbar p {
  margin: 0.4rem 0 0;
  color: #666;
}

/* Alerts */
.alert {
  padding: 0.9rem 1.2rem;
  border-radius: 6px;
  margin-bottom: 1rem;
  font-size: 0.95rem;
}
.alert.error   { background: #fff0f0; color: #c62828; border: 1px solid #ffcdd2; }
.alert.success { background: #f0fff4; color: #2e7d32; border: 1px solid #c8e6c9; }

/* Tabs */
.tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #e0e0e0;
  margin-bottom: 1.5rem;
}
.tab-btn {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  font-size: 0.95rem;
  cursor: pointer;
  color: #666;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  font-weight: 500;
  transition: all 0.2s;
}
.tab-btn:hover { color: #1976d2; }
.tab-btn.active { color: #1976d2; border-bottom-color: #1976d2; }

/* Section header */
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.2rem;
}
.section-count {
  font-size: 0.9rem;
  color: #888;
}

/* States */
.loading-state,
.empty-state {
  text-align: center;
  padding: 3rem;
  background: #fafafa;
  border-radius: 8px;
  color: #999;
  border: 1px dashed #ddd;
}

/* ---- ROLES GRID ---- */
.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.2rem;
}

.role-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  padding: 1.2rem;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  transition: box-shadow 0.2s;
}
.role-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); }

.role-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.5rem;
}
.role-title-wrap { display: flex; flex-direction: column; gap: 0.15rem; }
.role-name { font-weight: 600; font-size: 1rem; color: #222; }
.role-slug { font-size: 0.78rem; color: #999; font-family: monospace; }

.badge.system {
  font-size: 0.72rem;
  padding: 0.2rem 0.5rem;
  background: #e3f2fd;
  color: #1565c0;
  border-radius: 4px;
  font-weight: 600;
  white-space: nowrap;
}

.role-desc {
  font-size: 0.85rem;
  color: #666;
  margin: 0.5rem 0;
}

.role-permissions {
  margin: 0.75rem 0;
}
.perm-count { font-size: 0.8rem; color: #888; display: block; margin-bottom: 0.4rem; }
.perm-tags { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.perm-tag {
  font-size: 0.72rem;
  padding: 0.2rem 0.5rem;
  background: #f0f4ff;
  color: #3949ab;
  border-radius: 3px;
}
.perm-tag.more {
  background: #f5f5f5;
  color: #888;
}

.role-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}
.system-note {
  font-size: 0.75rem;
  color: #bbb;
  margin: 0.3rem 0 0;
  text-align: center;
}

/* ---- PERMISSIONS TABLE ---- */
.perm-group { margin-bottom: 1.5rem; }
.perm-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.6rem 1rem;
  background: #f5f7ff;
  border-radius: 6px 6px 0 0;
  border: 1px solid #e0e6ff;
}
.group-name { font-weight: 600; color: #3949ab; }
.group-count { font-size: 0.8rem; color: #888; }

.perm-table { border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 6px 6px; overflow: hidden; }
.perm-row {
  display: grid;
  grid-template-columns: 2fr 2fr 80px;
  align-items: center;
  padding: 0.65rem 1rem;
  border-bottom: 1px solid #f0f0f0;
  gap: 1rem;
}
.perm-row:last-child { border-bottom: none; }
.perm-row.header {
  background: #f9f9f9;
  font-weight: 600;
  font-size: 0.82rem;
  color: #666;
}
.perm-code { font-family: monospace; font-size: 0.85rem; color: #555; }

/* ---- BUTTONS ---- */
.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
  font-weight: 500;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.82rem; flex: 1; }
.btn.primary  { background: #1976d2; color: white; }
.btn.primary:hover:not(:disabled)  { background: #1565c0; }
.btn.danger   { background: #e53935; color: white; }
.btn.danger:hover:not(:disabled)   { background: #c62828; }
.btn.secondary { background: #f5f5f5; color: #333; border: 1px solid #ddd; }
.btn.secondary:hover { background: #eeeeee; }
.btn.sg-primary { background: #2196f3; color: white; padding: 0.65rem 1.4rem; }
.btn.sg-primary:hover:not(:disabled) { background: #1976d2; }

/* ---- MODAL ---- */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal {
  background: white;
  border-radius: 10px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.25);
  max-width: 560px;
  width: 94%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}
.modal.large { max-width: 720px; }
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #eee;
}
.modal-header h3 { margin: 0; font-size: 1.2rem; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #aaa; line-height: 1; }
.btn-close:hover { color: #555; }
.modal-footer {
  display: flex;
  gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #eee;
  justify-content: flex-end;
}

form { flex: 1; overflow-y: auto; padding: 1.5rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { font-weight: 500; color: #444; font-size: 0.9rem; }
.form-group input,
.form-group textarea,
.form-group select {
  padding: 0.65rem 0.9rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}
.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #1976d2;
  box-shadow: 0 0 0 3px rgba(25,118,210,0.1);
}
.hint { font-size: 0.78rem; color: #aaa; }

/* ---- PERMISSION SELECTOR ---- */
.perm-selector {
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  max-height: 280px;
  overflow-y: auto;
}
.selector-group { border-bottom: 1px solid #f0f0f0; }
.selector-group:last-child { border-bottom: none; }
.selector-group-header {
  padding: 0.6rem 1rem;
  background: #f8f9ff;
  position: sticky;
  top: 0;
  z-index: 1;
}
.selector-perms {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  padding: 0.25rem 0;
}
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  font-size: 0.88rem;
  color: #444;
}
.checkbox-label input[type=checkbox] { width: auto; }
.perm-check {
  padding: 0.35rem 1rem;
  border-radius: 4px;
  transition: background 0.15s;
}
.perm-check:hover { background: #f5f5f5; }
.perm-check-name { flex: 1; }
.perm-check-code { font-family: monospace; font-size: 0.75rem; color: #bbb; }
</style>
