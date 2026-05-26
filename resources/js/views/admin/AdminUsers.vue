<template>
  <section class="admin-users">
    <div class="toolbar">
      <div>
        <h2>Quản lý tài khoản</h2>
        <p>Khóa, mở khóa và kiểm tra trạng thái đăng nhập người dùng.</p>
      </div>
      <button class="btn secondary" :disabled="loading" @click="loadUsers">Tải lại</button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Họ tên</th>
            <th>Username</th>
            <th>Email</th>
            <th>SĐT</th>
            <th>Role/group</th>
            <th>Trạng thái</th>
            <th>Lý do khóa</th>
            <th>Thời hạn khóa</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="9" class="empty">Đang tải...</td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="9" class="empty">Chưa có tài khoản.</td>
          </tr>
          <tr v-for="user in users" :key="user.id">
            <td>{{ user.full_name }}</td>
            <td>{{ user.username }}</td>
            <td>{{ user.email || '-' }}</td>
            <td>{{ user.phone || '-' }}</td>
            <td>
              <div class="roles">{{ (user.roles || []).join(', ') || '-' }}</div>
              <span class="muted">{{ user.role_group }}</span>
            </td>
            <td><span class="status" :class="user.status">{{ user.status }}</span></td>
            <td>{{ user.status_reason || '-' }}</td>
            <td>{{ formatDate(user.locked_until) }}</td>
            <td>
              <button v-if="user.status === 'locked'" class="btn secondary" @click="unlockUser(user)">
                Mở khóa
              </button>
              <button v-else class="btn danger" @click="openLockModal(user)">
                Khóa
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lockTarget" class="modal-backdrop" @click.self="closeLockModal">
      <form class="modal" @submit.prevent="lockUser">
        <h3>Khóa tài khoản</h3>
        <p class="muted">{{ lockTarget.full_name }} - {{ lockTarget.username }}</p>

        <label>
          Loại khóa
          <select v-model="lockForm.lock_type">
            <option value="temporary">temporary</option>
            <option value="permanent">permanent</option>
            <option value="auto">auto</option>
          </select>
        </label>

        <label>
          Lý do khóa
          <textarea v-model="lockForm.status_reason" rows="4" required placeholder="Nhập lý do khóa"></textarea>
        </label>

        <label v-if="lockForm.lock_type === 'temporary'">
          Khóa đến
          <input v-model="lockForm.locked_until" type="datetime-local" required />
        </label>

        <div class="modal-actions">
          <button type="button" class="btn secondary" @click="closeLockModal">Hủy</button>
          <button type="submit" class="btn danger" :disabled="saving">Xác nhận khóa</button>
        </div>
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
      loading: false,
      saving: false,
      error: '',
      success: '',
      lockTarget: null,
      lockForm: {
        lock_type: 'temporary',
        status_reason: '',
        locked_until: '',
      },
    };
  },
  mounted() {
    this.loadUsers();
  },
  methods: {
    async loadUsers() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.list();
        this.users = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    openLockModal(user) {
      this.lockTarget = user;
      this.lockForm = {
        lock_type: 'temporary',
        status_reason: '',
        locked_until: '',
      };
      this.error = '';
      this.success = '';
    },
    closeLockModal() {
      this.lockTarget = null;
    },
    async lockUser() {
      if (!this.lockTarget) return;
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        const payload = {
          lock_type: this.lockForm.lock_type,
          status_reason: this.lockForm.status_reason,
          locked_until: this.lockForm.lock_type === 'temporary' ? this.lockForm.locked_until : null,
        };
        const response = await adminUserService.lock(this.lockTarget.id, payload);
        this.success = response.message;
        this.closeLockModal();
        await this.loadUsers();
      } catch (error) {
        this.error = error.message || 'Khóa tài khoản không thành công.';
      } finally {
        this.saving = false;
      }
    },
    async unlockUser(user) {
      if (!confirm(`Mở khóa tài khoản ${user.username}?`)) return;
      this.error = '';
      this.success = '';
      try {
        const response = await adminUserService.unlock(user.id);
        this.success = response.message;
        await this.loadUsers();
      } catch (error) {
        this.error = error.message || 'Mở khóa tài khoản không thành công.';
      }
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleString('vi-VN');
    },
  },
};
</script>

<style scoped>
.admin-users {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.toolbar {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
}
.toolbar h2 {
  font-size: 22px;
  margin: 0 0 4px;
}
.toolbar p,
.muted {
  color: var(--sg-text-muted);
  font-size: 13px;
}
.alert {
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 14px;
}
.alert.error {
  background: #fef2f2;
  color: #b91c1c;
}
.alert.success {
  background: #ecfdf5;
  color: #047857;
}
.table-wrap {
  overflow: auto;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #fff;
}
table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1080px;
}
th,
td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
  font-size: 14px;
  vertical-align: top;
}
th {
  background: #f9fafb;
  font-weight: 700;
}
.empty {
  text-align: center;
  color: var(--sg-text-muted);
}
.roles {
  font-weight: 600;
}
.status {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 999px;
  background: #e5e7eb;
  font-size: 12px;
  font-weight: 700;
}
.status.active {
  background: #dcfce7;
  color: #166534;
}
.status.locked {
  background: #fee2e2;
  color: #991b1b;
}
.btn {
  border: 0;
  border-radius: 8px;
  padding: 9px 12px;
  font-weight: 700;
  cursor: pointer;
}
.btn.secondary {
  background: #f3f4f6;
  color: #111827;
}
.btn.danger {
  background: #dc2626;
  color: #fff;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, .48);
  display: grid;
  place-items: center;
  z-index: 500;
}
.modal {
  width: min(460px, calc(100vw - 32px));
  background: #fff;
  border-radius: 8px;
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.modal h3 {
  margin: 0;
}
.modal label {
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-weight: 700;
  font-size: 14px;
}
.modal input,
.modal select,
.modal textarea {
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 10px 12px;
  font: inherit;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>



