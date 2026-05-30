<template>
  <div class="policies-page">
    <section class="page-head">
      <div>
        <h1>Quản lý chính sách</h1>
        <p>Tạo và cập nhật các chính sách hệ thống đang có hiệu lực cho người dùng.</p>
      </div>
      <button class="primary-btn" type="button" @click="openCreate">Thêm chính sách</button>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <section class="table-card">
      <div v-if="loading" class="state-card">Đang tải chính sách...</div>
      <div v-else-if="policies.length === 0" class="state-card">Chưa có chính sách nào.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Tiêu đề</th>
            <th>Key</th>
            <th>Loại</th>
            <th>Version</th>
            <th>Trạng thái</th>
            <th>Ngày hiệu lực</th>
            <th>Người cập nhật</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="policy in policies" :key="policy.id">
            <td class="strong">{{ policy.title }}</td>
            <td>{{ policy.key }}</td>
            <td>{{ typeLabel(policy.type) }}</td>
            <td>v{{ policy.version }}</td>
            <td><span class="status-chip" :class="{ off: !policy.is_active }">{{ policy.is_active ? 'Active' : 'Inactive' }}</span></td>
            <td>{{ formatDate(policy.effective_from) }}</td>
            <td>{{ policy.updated_by?.full_name || policy.created_by?.full_name || '-' }}</td>
            <td class="actions">
              <button type="button" @click="openEdit(policy)">Sửa</button>
              <button type="button" class="danger" @click="deactivate(policy)">Tắt</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal-panel" @submit.prevent="savePolicy">
        <header>
          <h2>{{ editingId ? 'Cập nhật chính sách' : 'Thêm chính sách' }}</h2>
          <button type="button" @click="closeModal">Đóng</button>
        </header>

        <div v-if="modalError" class="alert error">{{ modalError }}</div>

        <div class="form-grid">
          <label>
            <span>Key</span>
            <input v-model.trim="form.key" type="text" placeholder="terms_of_service" required />
          </label>
          <label>
            <span>Version</span>
            <input v-model.number="form.version" type="number" min="1" required />
          </label>
          <label>
            <span>Loại chính sách</span>
            <select v-model="form.type" required>
              <option value="general">Chung</option>
              <option value="booking">Đặt sân</option>
              <option value="refund">Hoàn tiền</option>
              <option value="moderation">Kiểm duyệt</option>
            </select>
          </label>
          <label>
            <span>Ngày hiệu lực</span>
            <input v-model="form.effective_from" type="datetime-local" />
          </label>
        </div>

        <label>
          <span>Tiêu đề</span>
          <input v-model.trim="form.title" type="text" required />
        </label>

        <label>
          <span>Nội dung</span>
          <textarea v-model.trim="form.content" rows="10" required></textarea>
        </label>

        <label class="check-row">
          <input v-model="form.is_active" type="checkbox" />
          <span>Đang active</span>
        </label>

        <footer>
          <button type="button" class="ghost-btn" @click="closeModal">Hủy</button>
          <button class="primary-btn" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu chính sách' }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import { adminPolicyService } from '../../services/adminPolicies.js';

export default {
  name: 'AdminPolicies',
  data() {
    return {
      policies: [],
      loading: true,
      saving: false,
      error: '',
      notice: '',
      modalError: '',
      showModal: false,
      editingId: null,
      form: this.defaultForm(),
    };
  },
  async created() {
    await this.loadPolicies();
  },
  methods: {
    defaultForm() {
      return {
        key: '',
        version: 1,
        title: '',
        content: '',
        type: 'general',
        is_active: true,
        effective_from: '',
      };
    },
    async loadPolicies() {
      this.loading = true;
      this.error = '';

      try {
        const response = await adminPolicyService.list();
        this.policies = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải chính sách.';
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.editingId = null;
      this.modalError = '';
      this.form = this.defaultForm();
      this.showModal = true;
    },
    openEdit(policy) {
      this.editingId = policy.id;
      this.modalError = '';
      this.form = {
        key: policy.key,
        version: policy.version,
        title: policy.title,
        content: policy.content,
        type: policy.type,
        is_active: Boolean(policy.is_active),
        effective_from: this.toDatetimeLocal(policy.effective_from),
      };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.editingId = null;
      this.modalError = '';
    },
    async savePolicy() {
      this.saving = true;
      this.modalError = '';
      this.notice = '';
      const payload = {
        ...this.form,
        effective_from: this.form.effective_from || null,
      };

      try {
        if (this.editingId) {
          await adminPolicyService.update(this.editingId, payload);
          this.notice = 'Đã cập nhật chính sách.';
        } else {
          await adminPolicyService.create(payload);
          this.notice = 'Đã thêm chính sách.';
        }
        await this.loadPolicies();
        this.closeModal();
      } catch (error) {
        this.modalError = error.message || 'Không thể lưu chính sách.';
      } finally {
        this.saving = false;
      }
    },
    async deactivate(policy) {
      if (!confirm(`Tắt chính sách "${policy.title}"?`)) return;
      this.error = '';
      this.notice = '';

      try {
        await adminPolicyService.deactivate(policy.id);
        this.notice = 'Đã tắt chính sách.';
        await this.loadPolicies();
      } catch (error) {
        this.error = error.message || 'Không thể tắt chính sách.';
      }
    },
    typeLabel(type) {
      return {
        general: 'Chung',
        booking: 'Đặt sân',
        refund: 'Hoàn tiền',
        moderation: 'Kiểm duyệt',
      }[type] || type;
    },
    formatDate(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
    },
    toDatetimeLocal(value) {
      if (!value) return '';
      const date = new Date(value);
      const offset = date.getTimezoneOffset() * 60000;
      return new Date(date.getTime() - offset).toISOString().slice(0, 16);
    },
  },
};
</script>

<style scoped>
.policies-page {
  display: grid;
  gap: 18px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-head,
.table-card,
.state-card,
.modal-panel,
.alert {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.page-head {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: end;
  padding: 20px;
}

.page-head h1,
.modal-panel h2 {
  margin: 0;
  color: #0f172a;
  font-weight: 900;
}

.page-head p {
  margin: 6px 0 0;
  color: #64748b;
}

.alert {
  padding: 12px 14px;
  font-weight: 900;
}

.alert.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert.success {
  border-color: #bbf7d0;
  background: #dcfce7;
  color: #166534;
}

.state-card {
  padding: 42px;
  color: #64748b;
  text-align: center;
  font-weight: 900;
}

.table-card {
  overflow: auto;
}

table {
  width: 100%;
  min-width: 980px;
  border-collapse: collapse;
}

th,
td {
  padding: 13px 14px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  font-size: 14px;
}

th {
  background: #f8fafc;
  color: #334155;
  font-weight: 900;
}

.strong {
  color: #0f172a;
  font-weight: 900;
}

.status-chip {
  padding: 5px 9px;
  border-radius: 999px;
  background: #dcfce7;
  color: #166534;
  font-size: 12px;
  font-weight: 900;
}

.status-chip.off {
  background: #e5e7eb;
  color: #475569;
}

.actions {
  display: flex;
  gap: 8px;
}

.actions button {
  padding: 7px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  color: #334155;
  font-weight: 900;
}

.actions .danger {
  color: #b91c1c;
}

.primary-btn,
.ghost-btn {
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  border-radius: 8px;
  font-weight: 900;
}

.primary-btn {
  background: #16a34a;
  color: #fff;
}

.ghost-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.48);
}

.modal-panel {
  width: min(760px, 100%);
  max-height: calc(100vh - 48px);
  overflow: auto;
  display: grid;
  gap: 16px;
  padding: 20px;
}

.modal-panel header,
.modal-panel footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

label {
  display: grid;
  gap: 7px;
}

label span {
  color: #334155;
  font-size: 13px;
  font-weight: 900;
}

input,
select,
textarea {
  width: 100%;
  padding: 11px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font: inherit;
}

.check-row {
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 10px;
}

.check-row input {
  width: 18px;
  height: 18px;
  accent-color: #16a34a;
}

@media (max-width: 760px) {
  .page-head,
  .form-grid {
    display: grid;
    grid-template-columns: 1fr;
  }
}
</style>
