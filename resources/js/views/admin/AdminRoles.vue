<template>
  <section class="admin-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Phân quyền nội bộ</p>
        <h2>Quản lý nhóm quyền hệ thống</h2>
        <p>Phân quyền cho nhân sự quản trị SportGo.</p>
      </div>
      <button class="btn primary" type="button" @click="openCreateModal">
        <AppIcon name="plus" size="18" />
        <span>Tạo nhóm quyền</span>
      </button>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="filter-panel">
      <div class="filter-head">
        <strong>Bộ lọc</strong>
        <span>Lọc theo tên, loại nhóm và trạng thái chỉnh sửa.</span>
      </div>
      <div class="filter-bar">
        <label class="search-box">
          <AppIcon name="search" size="18" />
          <input
            v-model.trim="filters.keyword"
            placeholder="Tìm theo tên nhóm, mô tả hoặc mã nội bộ"
            @keyup.enter="loadRoles"
          />
        </label>
        <select v-model="filters.is_system" @change="loadRoles">
          <option value="">Tất cả nhóm</option>
          <option value="1">Nhóm hệ thống</option>
          <option value="0">Nhóm tùy chỉnh</option>
        </select>
        <select v-model="configFilter">
          <option value="">Tất cả mức quyền</option>
          <option value="configurable">Có thể chỉnh sửa</option>
          <option value="locked">Đang khóa chỉnh sửa</option>
        </select>
        <ActionIconButton icon="filter" label="Lọc danh sách" variant="primary" @click="loadRoles" />
        <ActionIconButton icon="refresh" label="Làm mới" variant="secondary" :disabled="loading" @click="resetFilters" />
      </div>
    </section>

    <section class="fixed-note">
      <AppIcon name="shield" size="18" />
      <span>Vai trò người dùng, chủ sân và nhân viên sân là vai trò nghiệp vụ cố định, không cấu hình tại màn này.</span>
    </section>

    <div class="table-card">
      <div v-if="loading" class="table-state">Đang tải nhóm quyền...</div>
      <div v-else-if="filteredRoles.length === 0" class="table-state">Chưa có nhóm quyền quản trị nào phù hợp.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>
                <button class="sort-btn" type="button" @click="sortBy('display_name')">
                  Nhóm quyền
                  <AppIcon :name="sortIcon('display_name')" size="14" />
                </button>
              </th>
              <th>Mô tả</th>
              <th>Loại nhóm</th>
              <th>Quyền</th>
              <th>Nhân sự</th>
              <th>Mức nhạy cảm</th>
              <th>Cập nhật</th>
              <th class="actions-col">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in sortedRoles" :key="role.id">
              <td class="main-cell">
                <strong>{{ role.display_name || role.name }}</strong>
                <span>{{ role.name }}</span>
              </td>
              <td class="desc-cell">{{ role.description || 'Chưa có mô tả phạm vi sử dụng.' }}</td>
              <td>
                <span class="type-badge" :class="role.is_system ? 'badge-system' : 'badge-custom'">
                  {{ role.is_system ? 'Hệ thống' : 'Tùy chỉnh' }}
                </span>
              </td>
              <td>
                <span class="count-pill">{{ role.permissions_count || 0 }}</span>
              </td>
              <td>
                <span class="count-pill">{{ role.users_count || 0 }}</span>
              </td>
              <td>
                <span class="risk-badge" :class="sensitivityClass(role)">
                  <AppIcon :name="sensitivityIcon(role)" size="14" />
                  {{ sensitivityLabel(role) }}
                </span>
              </td>
              <td class="date-cell">{{ formatDate(role.updated_at) }}</td>
              <td class="actions-col">
                <TableActionGroup>
                  <ActionIconButton icon="eye" label="Xem chi tiết" @click="goDetail(role, 'info')" />
                  <ActionIconButton icon="shieldCheck" label="Cấu hình quyền" @click="goDetail(role, 'permissions')" />
                  <ActionIconButton
                    icon="pencil"
                    label="Sửa thông tin"
                    :disabled="!role.is_configurable"
                    @click="role.is_configurable && openEditModal(role)"
                  />
                  <ActionIconButton icon="users" label="Xem nhân sự đang dùng" @click="goDetail(role, 'users')" />
                  <ActionIconButton
                    icon="trash"
                    :label="role.can_delete ? 'Xóa nhóm quyền' : deleteDisabledReason(role)"
                    variant="danger"
                    :disabled="!role.can_delete"
                    @click="role.can_delete && openDeleteConfirm(role)"
                  />
                </TableActionGroup>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="saveRole">
        <header class="modal-head">
          <div>
            <h3>{{ editingRole ? 'Sửa nhóm quyền' : 'Tạo nhóm quyền quản trị' }}</h3>
            <p v-if="!editingRole">Sau khi tạo, hệ thống sẽ mở màn cấp quyền cho nhóm mới.</p>
            <p v-else>Chỉ sửa tên hiển thị và mô tả. Mã nhóm hệ thống không được đổi.</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeModal" />
        </header>

        <div v-if="modalError" class="alert error">{{ modalError }}</div>

        <div class="form-body">
          <label>
            Mã nhóm
            <input
              v-model.trim="form.name"
              required
              :disabled="editingRole?.is_system"
              placeholder="vd: support_staff"
            />
            <small>Chỉ dùng chữ thường, số, dấu chấm và gạch dưới.</small>
          </label>
          <label>
            Tên hiển thị
            <input v-model.trim="form.display_name" required placeholder="vd: Nhân viên hỗ trợ" />
          </label>
          <label>
            Mô tả phạm vi
            <textarea
              v-model.trim="form.description"
              rows="3"
              placeholder="Mô tả nhóm này dùng cho nhân sự nào và được phép xử lý việc gì"
            ></textarea>
          </label>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu nhóm quyền' }}
          </button>
        </footer>
      </form>
    </div>

    <ConfirmModal
      v-model="confirmDelete.show"
      title="Xóa nhóm quyền"
      :message="`Bạn sắp xóa nhóm quyền ${confirmDelete.role?.display_name || ''}.`"
      consequence="Chỉ xóa được nhóm tùy chỉnh chưa có nhân sự sử dụng. Thao tác này không thể hoàn tác."
      confirm-text="Xóa nhóm quyền"
      type="danger"
      @confirm="deleteRole"
    />
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { adminRoleService } from '../../services/adminRoles.js';

export default {
  name: 'AdminRoles',
  components: { ActionIconButton, AppIcon, ConfirmModal, TableActionGroup },
  data() {
    return {
      roles: [],
      summary: {},
      filters: { keyword: '', is_system: '' },
      configFilter: '',
      form: this.defaultForm(),
      editingRole: null,
      loading: false,
      saving: false,
      showModal: false,
      modalError: '',
      error: '',
      success: '',
      sortKey: 'display_name',
      sortDir: 'asc',
      confirmDelete: { show: false, role: null },
    };
  },
  computed: {
    filteredRoles() {
      if (!this.configFilter) return this.roles;
      return this.roles.filter((role) => {
        if (this.configFilter === 'configurable') return !!role.is_configurable;
        if (this.configFilter === 'locked') return !role.is_configurable;
        return true;
      });
    },
    sortedRoles() {
      return [...this.filteredRoles].sort((a, b) => {
        const left = this.sortValue(a, this.sortKey);
        const right = this.sortValue(b, this.sortKey);
        const result = String(left).localeCompare(String(right), 'vi', { numeric: true, sensitivity: 'base' });
        return this.sortDir === 'asc' ? result : -result;
      });
    },
  },
  mounted() {
    this.loadRoles();
  },
  methods: {
    defaultForm() {
      return { name: '', display_name: '', description: '' };
    },
    formatDate(dateStr) {
      if (!dateStr) return '-';
      const d = new Date(dateStr);
      return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    async loadRoles() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminRoleService.list(this.filters);
        this.roles = response.data || [];
        this.summary = response.summary || {};
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách nhóm quyền.';
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { keyword: '', is_system: '' };
      this.configFilter = '';
      this.loadRoles();
    },
    sortBy(key) {
      if (this.sortKey === key) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        return;
      }
      this.sortKey = key;
      this.sortDir = 'asc';
    },
    sortIcon(key) {
      if (this.sortKey !== key) return 'chevronDown';
      return this.sortDir === 'asc' ? 'chevronUp' : 'chevronDown';
    },
    sortValue(role, key) {
      if (key === 'display_name') return role.display_name || role.name || '';
      return role[key] || '';
    },
    goDetail(role, tab = 'info') {
      this.$router.push({ name: 'admin-role-detail', params: { id: role.id }, query: { tab } });
    },
    openCreateModal() {
      this.editingRole = null;
      this.form = this.defaultForm();
      this.modalError = '';
      this.showModal = true;
    },
    openEditModal(role) {
      this.editingRole = role;
      this.form = {
        name: role.name,
        display_name: role.display_name || '',
        description: role.description || '',
      };
      this.modalError = '';
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.modalError = '';
    },
    async saveRole() {
      this.saving = true;
      this.modalError = '';
      try {
        const response = this.editingRole
          ? await adminRoleService.update(this.editingRole.id, this.form)
          : await adminRoleService.create(this.form);

        this.success = response.message || 'Đã lưu nhóm quyền.';
        this.closeModal();

        if (!this.editingRole && response.data?.id) {
          this.$router.push({ name: 'admin-role-detail', params: { id: response.data.id }, query: { tab: 'permissions' } });
          return;
        }

        await this.loadRoles();
        this.autoHide();
      } catch (error) {
        this.modalError = error.message || 'Không lưu được nhóm quyền.';
      } finally {
        this.saving = false;
      }
    },
    openDeleteConfirm(role) {
      this.confirmDelete = { show: true, role };
    },
    async deleteRole() {
      const role = this.confirmDelete.role;
      if (!role) return;

      this.error = '';
      this.success = '';
      try {
        const response = await adminRoleService.delete(role.id);
        this.success = response.message || 'Đã xóa nhóm quyền.';
        await this.loadRoles();
        this.autoHide();
      } catch (error) {
        this.error = error.message || 'Không xóa được nhóm quyền.';
      }
    },
    sensitivityLabel(role) {
      if (!role.is_configurable) return 'Khóa chỉnh sửa';
      if ((role.permissions_count || 0) >= 10) return 'Cao';
      if ((role.permissions_count || 0) >= 5) return 'Trung bình';
      return 'Cơ bản';
    },
    sensitivityClass(role) {
      if (!role.is_configurable) return 'risk-locked';
      if ((role.permissions_count || 0) >= 10) return 'risk-high';
      if ((role.permissions_count || 0) >= 5) return 'risk-medium';
      return 'risk-low';
    },
    sensitivityIcon(role) {
      if (!role.is_configurable) return 'lock';
      if ((role.permissions_count || 0) >= 10) return 'alert';
      return 'shieldCheck';
    },
    deleteDisabledReason(role) {
      if (role.is_system) return 'Không thể xóa nhóm hệ thống';
      if ((role.users_count || 0) > 0) return 'Không thể xóa nhóm đang có nhân sự';
      return 'Không thể xóa nhóm quyền này';
    },
    autoHide() {
      setTimeout(() => { this.success = ''; }, 3500);
    },
  },
};
</script>

<style scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-head {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: flex-start;
}

.eyebrow {
  margin: 0 0 4px;
  color: #16a34a;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
}

h2,
h3,
p {
  margin: 0;
}

.page-head h2 {
  color: #0f172a;
  font-size: 24px;
}

.page-head p:not(.eyebrow) {
  margin-top: 6px;
  color: #64748b;
  line-height: 1.55;
}

.filter-panel,
.fixed-note,
.table-card,
.modal {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
}

.filter-panel {
  padding: 14px;
}

.filter-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.filter-head strong {
  color: #0f172a;
}

.filter-head span {
  color: #64748b;
  font-size: 13px;
}

.filter-bar {
  display: grid;
  grid-template-columns: minmax(260px, 2fr) repeat(2, minmax(145px, 1fr)) 36px 36px;
  gap: 10px;
  align-items: center;
  min-width: 0;
}

.search-box {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  min-width: 0;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0 12px;
  color: #64748b;
  font-weight: normal;
}

.search-box input {
  border: 0;
  padding-left: 0;
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  color: #0f172a;
  font: inherit;
  background: #fff;
}

input:focus,
select:focus,
textarea:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.search-box:focus-within {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.search-box:focus-within input {
  box-shadow: none;
}

.fixed-note {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 12px 14px;
  color: #475569;
  background: #f8fafc;
}

.fixed-note strong {
  color: #0f172a;
}

.table-card {
  overflow: hidden;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1120px;
  border-collapse: collapse;
}

th,
td {
  border-bottom: 1px solid #e2e8f0;
  padding: 13px 14px;
  text-align: left;
  vertical-align: middle;
}

th {
  background: #f8fafc;
  color: #475569;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

tbody tr:hover {
  background: #f8fafc;
}

.main-cell {
  min-width: 220px;
}

.main-cell strong {
  display: block;
  color: #0f172a;
}

.main-cell span,
.desc-cell {
  color: #64748b;
  font-size: 13px;
}

.desc-cell {
  max-width: 320px;
  line-height: 1.45;
}

.count-pill {
  display: inline-grid;
  min-width: 32px;
  height: 28px;
  place-items: center;
  border-radius: 999px;
  background: #eef2f7;
  color: #334155;
  font-weight: 900;
}

.type-badge,
.risk-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 999px;
  padding: 5px 9px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.badge-system {
  background: #dcfce7;
  color: #166534;
}

.badge-custom {
  background: #e0f2fe;
  color: #075985;
}

.risk-low {
  background: #eef2f7;
  color: #334155;
}

.risk-medium {
  background: #fef3c7;
  color: #92400e;
}

.risk-high {
  background: #fee2e2;
  color: #991b1b;
}

.risk-locked {
  background: #f1f5f9;
  color: #475569;
}

.actions-col {
  text-align: right;
}

.sort-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  text-transform: inherit;
}

.table-state {
  padding: 36px;
  color: #64748b;
  text-align: center;
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

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 0;
  border-radius: 8px;
  padding: 10px 14px;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.btn.primary {
  background: #16a34a;
  color: #fff;
}

.btn.secondary {
  background: #e2e8f0;
  color: #334155;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: .55;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 900;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.55);
}

.modal {
  width: min(560px, calc(100vw - 32px));
  overflow: hidden;
}

.modal-head,
.modal-actions {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 22px;
}

.modal-head {
  border-bottom: 1px solid #e2e8f0;
}

.modal-head p {
  margin-top: 4px;
  color: #64748b;
}

.form-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 22px;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: #334155;
  font-weight: 800;
}

small {
  color: #64748b;
  font-weight: 400;
}

.modal-actions {
  justify-content: flex-end;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

@media (max-width: 1120px) {
  .filter-bar {
    grid-template-columns: minmax(240px, 1fr) minmax(135px, 1fr) minmax(135px, 1fr) 36px 36px;
  }
}

@media (max-width: 760px) {
  .page-head,
  .fixed-note {
    flex-direction: column;
    align-items: flex-start;
  }

  .filter-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .search-box {
    grid-column: 1 / -1;
  }
}

@media (max-width: 520px) {
  .filter-bar {
    grid-template-columns: 1fr;
  }
}
</style>
