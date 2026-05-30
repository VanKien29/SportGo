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
        <div class="modal-header">
          <div>
            <h3>Khóa tài khoản</h3>
            <p class="muted">Chặn đăng nhập và thu hồi token hiện tại.</p>
          </div>
          <button type="button" class="icon-btn" @click="closeLockModal" aria-label="Đóng">×</button>
        </div>

        <div class="target-user">
          <div class="target-avatar">{{ lockTarget.full_name?.charAt(0)?.toUpperCase() || '?' }}</div>
          <div>
            <strong>{{ lockTarget.full_name }}</strong>
            <span>{{ lockTarget.username }} · {{ lockTarget.email || 'Chưa có email' }}</span>
          </div>
        </div>

        <div class="field">
          <span class="field-label">Loại khóa</span>
          <div class="segmented">
            <button
              v-for="type in lockTypes"
              :key="type.value"
              type="button"
              :class="{ active: lockForm.lock_type === type.value }"
              @click="lockForm.lock_type = type.value"
            >
              {{ type.label }}
            </button>
          </div>
        </div>

        <label>
          Lý do khóa
          <textarea v-model="lockForm.status_reason" rows="4" required placeholder="Nhập lý do khóa"></textarea>
        </label>

        <div v-if="lockForm.lock_type === 'temporary'" class="field">
          <span class="field-label">Thời hạn khóa</span>
          <div class="duration-grid">
            <button
              v-for="duration in lockDurations"
              :key="duration.value"
              type="button"
              :class="{ active: lockForm.lock_duration === duration.value }"
              @click="lockForm.lock_duration = duration.value"
            >
              {{ duration.label }}
            </button>
          </div>
          <div v-if="lockForm.lock_duration === 'custom'" class="custom-duration">
            <label>
              Số lượng
              <input
                v-model.number="lockForm.custom_amount"
                type="number"
                min="1"
                max="365"
                required
                placeholder="VD: 3"
              />
            </label>
            <label>
              Đơn vị
              <select v-model="lockForm.custom_unit">
                <option value="hours">Giờ</option>
                <option value="days">Ngày</option>
              </select>
            </label>
          </div>
          <p class="hint">{{ lockUntilPreview }}</p>
        </div>

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
        lock_duration: '1_day',
        status_reason: '',
        custom_amount: 1,
        custom_unit: 'days',
      },
      lockTypes: [
        { value: 'temporary', label: 'Tạm thời' },
        { value: 'permanent', label: 'Vĩnh viễn' },
        { value: 'auto', label: 'Tự động' },
      ],
      lockDurations: [
        { value: '1_hour', label: '1 giờ', minutes: 60 },
        { value: '1_day', label: '24 giờ', minutes: 1440 },
        { value: '7_days', label: '7 ngày', minutes: 10080 },
        { value: '30_days', label: '30 ngày', minutes: 43200 },
        { value: 'custom', label: 'Tùy chỉnh', minutes: null },
      ],
    };
  },
  computed: {
    lockUntilPreview() {
      if (this.lockForm.lock_duration === 'custom') {
        const amount = Number(this.lockForm.custom_amount || 0);
        const unitLabel = this.lockForm.custom_unit === 'hours' ? 'giờ' : 'ngày';

        if (amount < 1) {
          return 'Nhập số lượng lớn hơn 0 để tính thời hạn khóa.';
        }

        return `Khóa trong ${amount} ${unitLabel}, đến: ${this.formatDate(this.resolveLockedUntil())}`;
      }

      return `Khóa đến: ${this.formatDate(this.resolveLockedUntil())}`;
    },
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
        lock_duration: '1_day',
        status_reason: '',
        custom_amount: 1,
        custom_unit: 'days',
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
          locked_until: this.lockForm.lock_type === 'temporary' ? this.resolveLockedUntil() : null,
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
    resolveLockedUntil() {
      if (this.lockForm.lock_duration === 'custom') {
        const amount = Math.max(1, Number(this.lockForm.custom_amount || 1));
        const date = new Date();
        const minutes = this.lockForm.custom_unit === 'hours' ? amount * 60 : amount * 1440;
        date.setMinutes(date.getMinutes() + minutes);

        return this.formatDateTimeForApi(date);
      }

      const duration = this.lockDurations.find((item) => item.value === this.lockForm.lock_duration);
      const date = new Date();
      date.setMinutes(date.getMinutes() + (duration?.minutes || 1440));

      return this.formatDateTimeForApi(date);
    },
    formatDateTimeForApi(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return [
        date.getFullYear(),
        pad(date.getMonth() + 1),
        pad(date.getDate()),
      ].join('-') + ' ' + [
        pad(date.getHours()),
        pad(date.getMinutes()),
        '00',
      ].join(':');
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
  background: rgba(15, 23, 42, .56);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
}
.modal {
  width: min(560px, calc(100vw - 32px));
  background: #fff;
  border-radius: 14px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
}
.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}
.modal h3 {
  margin: 0;
  font-size: 22px;
  color: #0f172a;
}
.icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  font-size: 24px;
  line-height: 1;
}
.icon-btn:hover {
  background: #e2e8f0;
}
.target-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
}
.target-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #dcfce7;
  color: #166534;
  display: grid;
  place-items: center;
  font-weight: 800;
}
.target-user strong,
.target-user span {
  display: block;
}
.target-user span {
  margin-top: 3px;
  font-size: 13px;
  color: #64748b;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.field-label {
  font-weight: 700;
  font-size: 14px;
  color: #0f172a;
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
  outline: none;
  color: #0f172a !important;
  -webkit-text-fill-color: #0f172a !important;
  background: #fff;
}
.modal input:focus,
.modal select:focus,
.modal textarea:focus {
  border-color: #22c55e;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
}
.segmented,
.duration-grid {
  display: grid;
  gap: 8px;
}
.segmented {
  grid-template-columns: repeat(3, 1fr);
}
.duration-grid {
  grid-template-columns: repeat(5, 1fr);
}
.segmented button,
.duration-grid button {
  min-height: 40px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  color: #334155;
  font-weight: 700;
}
.segmented button:hover,
.duration-grid button:hover {
  border-color: #86efac;
  background: #f0fdf4;
}
.segmented button.active,
.duration-grid button.active {
  border-color: #22c55e;
  background: #dcfce7;
  color: #166534;
}
.hint {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}
.custom-duration {
  display: grid;
  grid-template-columns: 1fr 150px;
  gap: 10px;
}
.custom-duration label {
  gap: 6px;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 4px;
}
@media (max-width: 640px) {
  .segmented,
  .duration-grid,
  .custom-duration {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
