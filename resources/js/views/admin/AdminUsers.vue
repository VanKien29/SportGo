<template>
  <section class="admin-users">
    <header class="page-head">
      <div>
        <h2>Quản lý tài khoản</h2>
        <p>Theo dõi trạng thái, cảnh báo, vai trò và các thao tác nhạy cảm của tài khoản.</p>
      </div>
      <button class="btn secondary" :disabled="loading" @click="loadUsers">Tải lại</button>
    </header>

    <nav class="tabs" aria-label="Lọc nhanh tài khoản">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        :class="{ active: filters.status === tab.value }"
        type="button"
        @click="setStatus(tab.value)"
      >
        {{ tab.label }}
      </button>
    </nav>

    <section class="filters">
      <label>
        <span>Tìm kiếm</span>
        <input
          v-model.trim="filters.keyword"
          placeholder="Tên, username, email hoặc số điện thoại"
          @input="scheduleSearch"
          @keyup.enter="loadUsers"
        />
      </label>
      <label>
        <span>Vai trò</span>
        <select v-model="filters.role" @change="reloadFromFirstPage">
          <option value="">Tất cả vai trò</option>
          <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
        </select>
      </label>
      <label v-if="filters.status === 'warning'">
        <span>Mức cảnh báo</span>
        <select v-model="filters.warning_level" @change="reloadFromFirstPage">
          <option value="">Tất cả cảnh báo</option>
          <option value="watch">Cần theo dõi</option>
          <option value="near_lock">Gần ngưỡng khóa</option>
          <option value="lock_suggested">Đề xuất khóa</option>
        </select>
      </label>
      <button class="btn secondary" type="button" @click="resetFilters">Xóa lọc</button>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">Đang tải danh sách tài khoản...</div>
      <table v-else>
        <thead>
          <tr>
            <th>Họ tên</th>
            <th>Username</th>
            <th>Email/SĐT</th>
            <th>Vai trò chính</th>
            <th>Trạng thái</th>
            <th>Cảnh báo</th>
            <th>Report/khiếu nại</th>
            <th>Số dư ví</th>
            <th>Ngày tạo</th>
            <th class="actions-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="users.length === 0">
            <td colspan="10" class="state">Không có tài khoản phù hợp với bộ lọc hiện tại.</td>
          </tr>
          <tr v-for="user in users" :key="user.id">
            <td>
              <strong>{{ user.full_name || '-' }}</strong>
              <small>{{ user.warning_summary?.message }}</small>
            </td>
            <td>{{ user.username }}</td>
            <td>{{ user.email || user.phone || '-' }}</td>
            <td>{{ user.primary_role_label || '-' }}</td>
            <td>
              <span class="status" :class="user.status">{{ user.status_label || statusLabel(user.status) }}</span>
            </td>
            <td>
              <span class="warning" :class="user.warning_summary?.level || 'normal'">
                {{ user.warning_summary?.label || 'Bình thường' }}
              </span>
            </td>
            <td>{{ user.reports_count_recent || 0 }} / {{ user.complaints_count_recent || 0 }}</td>
            <td>{{ money(user.wallet_balance) }}</td>
            <td>{{ date(user.created_at) }}</td>
            <td class="actions-col">
              <RouterLink class="icon-btn" :to="{ name: 'admin-user-detail', params: { id: user.id } }" title="Xem chi tiết">
                Chi tiết
              </RouterLink>
              <button
                v-if="user.status === 'locked'"
                class="icon-btn"
                type="button"
                title="Mở khóa tài khoản"
                @click="openUnlockModal(user)"
              >
                Mở khóa
              </button>
              <button
                v-else
                class="icon-btn danger"
                type="button"
                title="Khóa tài khoản"
                @click="openLockModal(user)"
              >
                Khóa
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <footer class="pagination" v-if="meta.total > 0">
      <span>Hiển thị {{ users.length }} / {{ meta.total }} tài khoản</span>
      <div>
        <button class="btn secondary" type="button" :disabled="meta.current_page <= 1 || loading" @click="goPage(meta.current_page - 1)">
          Trước
        </button>
        <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn secondary" type="button" :disabled="meta.current_page >= meta.last_page || loading" @click="goPage(meta.current_page + 1)">
          Sau
        </button>
      </div>
    </footer>

    <div v-if="actionTarget" class="modal-backdrop" @click.self="closeActionModal">
      <form class="modal" @submit.prevent="submitAccountAction">
        <h3>{{ actionType === 'lock' ? 'Khóa tài khoản' : 'Mở khóa tài khoản' }}</h3>
        <p class="muted">
          {{ actionTarget.full_name || actionTarget.username }} · {{ actionTarget.primary_role_label }}
        </p>

        <template v-if="actionType === 'lock'">
          <div class="segmented">
            <button
              v-for="type in lockTypes"
              :key="type.value"
              type="button"
              :class="{ active: actionForm.lock_type === type.value }"
              @click="actionForm.lock_type = type.value"
            >
              {{ type.label }}
            </button>
          </div>
          <label v-if="actionForm.lock_type === 'temporary'">
            <span>Khóa đến</span>
            <input v-model="actionForm.locked_until" type="datetime-local" required />
          </label>
        </template>

        <label>
          <span>{{ actionType === 'lock' ? 'Lý do khóa' : 'Lý do mở khóa' }}</span>
          <textarea v-model.trim="actionForm.reason" rows="4" required placeholder="Nhập lý do để lưu audit log"></textarea>
        </label>

        <footer>
          <button type="button" class="btn secondary" @click="closeActionModal">Hủy</button>
          <button type="submit" class="btn" :class="{ danger: actionType === 'lock' }" :disabled="saving">
            {{ actionType === 'lock' ? 'Xác nhận khóa' : 'Xác nhận mở khóa' }}
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminUsers',
  data() {
    return {
      users: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      loading: false,
      saving: false,
      error: '',
      success: '',
      searchTimer: null,
      filters: {
        keyword: '',
        status: '',
        role: '',
        warning_level: '',
        page: 1,
        per_page: 15,
      },
      actionTarget: null,
      actionType: 'lock',
      actionForm: {
        lock_type: 'temporary',
        locked_until: '',
        reason: '',
      },
      tabs: [
        { value: '', label: 'Tất cả tài khoản' },
        { value: 'active', label: 'Đang hoạt động' },
        { value: 'warning', label: 'Tài khoản cảnh báo' },
        { value: 'locked', label: 'Tài khoản đã khóa' },
        { value: 'pending_verify', label: 'Chờ xác thực' },
      ],
      roleOptions: [
        { value: 'super_admin', label: 'Super admin' },
        { value: 'admin', label: 'Quản trị viên' },
        { value: 'system_staff', label: 'Nhân viên hệ thống' },
        { value: 'venue_owner', label: 'Chủ sân' },
        { value: 'venue_staff', label: 'Nhân viên sân' },
        { value: 'user', label: 'Người dùng' },
      ],
      lockTypes: [
        { value: 'temporary', label: 'Tạm thời' },
        { value: 'permanent', label: 'Vĩnh viễn' },
        { value: 'auto', label: 'Tự động' },
      ],
    };
  },
  mounted() {
    this.loadUsers();
  },
  beforeUnmount() {
    clearTimeout(this.searchTimer);
  },
  methods: {
    setStatus(status) {
      this.filters.status = status;
      if (status !== 'warning') this.filters.warning_level = '';
      this.reloadFromFirstPage();
    },
    scheduleSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.reloadFromFirstPage(), 350);
    },
    reloadFromFirstPage() {
      this.filters.page = 1;
      this.loadUsers();
    },
    resetFilters() {
      this.filters = {
        keyword: '',
        status: '',
        role: '',
        warning_level: '',
        page: 1,
        per_page: 15,
      };
      this.loadUsers();
    },
    goPage(page) {
      this.filters.page = page;
      this.loadUsers();
    },
    async loadUsers() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.list(this.filters);
        this.users = response.data || [];
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    openLockModal(user) {
      this.actionTarget = user;
      this.actionType = 'lock';
      this.actionForm = {
        lock_type: 'temporary',
        locked_until: this.inputDate(new Date(Date.now() + 24 * 60 * 60 * 1000)),
        reason: '',
      };
    },
    openUnlockModal(user) {
      this.actionTarget = user;
      this.actionType = 'unlock';
      this.actionForm = {
        lock_type: 'temporary',
        locked_until: '',
        reason: '',
      };
    },
    closeActionModal() {
      this.actionTarget = null;
      this.error = '';
    },
    async submitAccountAction() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.actionType === 'lock'
          ? await adminUserService.lock(this.actionTarget.id, {
              lock_type: this.actionForm.lock_type,
              status_reason: this.actionForm.reason,
              locked_until: this.actionForm.lock_type === 'temporary' ? this.actionForm.locked_until : null,
            })
          : await adminUserService.unlock(this.actionTarget.id, { reason: this.actionForm.reason });
        this.success = response.message;
        this.closeActionModal();
        await this.loadUsers();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái tài khoản.';
      } finally {
        this.saving = false;
      }
    },
    statusLabel(status) {
      return {
        active: 'Đang hoạt động',
        locked: 'Đã khóa',
        pending_verify: 'Chờ xác thực',
        deactivated: 'Đã vô hiệu hóa',
      }[status] || 'Không xác định';
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    inputDate(value) {
      const date = new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
  },
};
</script>

<style scoped>
.admin-users {
  display: grid;
  gap: 16px;
}

.page-head,
.filters,
.pagination {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.page-head h2 {
  margin: 0 0 6px;
}

.page-head p,
.muted,
small {
  margin: 0;
  color: #64748b;
}

.tabs,
.segmented {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tabs button,
.segmented button {
  border: 1px solid #dbe3ef;
  background: #fff;
  border-radius: 8px;
  padding: 10px 14px;
  font-weight: 800;
  cursor: pointer;
}

.tabs button.active,
.segmented button.active {
  background: #dcfce7;
  border-color: #22c55e;
  color: #166534;
}

.filters {
  justify-content: flex-start;
  flex-wrap: wrap;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px;
}

label {
  display: grid;
  gap: 6px;
  font-weight: 800;
  color: #334155;
}

label span {
  font-size: 13px;
}

input,
select,
textarea {
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  padding: 10px;
  font: inherit;
  min-width: 220px;
  background: #fff;
}

textarea {
  min-width: 100%;
  resize: vertical;
}

.table-card,
.modal {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.table-card {
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1120px;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
}

td:first-child {
  display: grid;
  gap: 4px;
}

.actions-col {
  position: sticky;
  right: 0;
  background: #fff;
  white-space: nowrap;
}

.state {
  padding: 20px;
  color: #64748b;
  text-align: center;
}

.btn,
.icon-btn {
  border: 0;
  border-radius: 8px;
  font-weight: 800;
  cursor: pointer;
  text-decoration: none;
}

.btn {
  padding: 10px 14px;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 7px 10px;
  margin-right: 6px;
  background: #f1f5f9;
  color: #0f172a;
}

.secondary {
  background: #f1f5f9;
  color: #0f172a;
}

.danger,
.icon-btn.danger {
  background: #fee2e2;
  color: #b91c1c;
}

.status,
.warning {
  display: inline-flex;
  border-radius: 999px;
  padding: 5px 9px;
  font-size: 12px;
  font-weight: 800;
  background: #e2e8f0;
}

.status.active,
.warning.normal {
  background: #dcfce7;
  color: #166534;
}

.status.locked,
.status.deactivated,
.warning.lock_suggested {
  background: #fee2e2;
  color: #b91c1c;
}

.status.pending_verify,
.warning.watch,
.warning.near_lock {
  background: #fef3c7;
  color: #92400e;
}

.alert {
  padding: 12px;
  border-radius: 10px;
  font-weight: 700;
}

.error {
  background: #fee2e2;
  color: #b91c1c;
}

.success {
  background: #dcfce7;
  color: #166534;
}

.pagination {
  align-items: center;
  color: #64748b;
}

.pagination div {
  display: flex;
  gap: 10px;
  align-items: center;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.56);
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
}

.modal {
  width: min(640px, calc(100vw - 32px));
  padding: 22px;
  display: grid;
  gap: 16px;
}

.modal h3 {
  margin: 0;
}

.modal footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 720px) {
  .page-head,
  .filters,
  .pagination,
  .pagination div {
    flex-direction: column;
    align-items: stretch;
  }

  input,
  select {
    min-width: 0;
    width: 100%;
  }
}
</style>
