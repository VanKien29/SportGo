<template>
  <section class="policy-management">
    <div class="toolbar">
      <div>
        <h2>Quản lý chính sách</h2>
        <p>Tạo và quản lý các điều khoản, chính sách của hệ thống.</p>
      </div>
      <button class="btn sg-primary" @click="openCreateModal">
        Thêm chính sách mới
      </button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="policy-groups">
      <div v-if="loading" class="loading-state">Đang tải dữ liệu...</div>
      <div v-else-if="Object.keys(policies).length === 0" class="empty-state">
        Chưa có chính sách nào.
      </div>
      
      <div v-for="(group, key) in policies" :key="key" class="policy-card">
        <div class="policy-header">
          <div class="policy-info">
            <span class="policy-key">{{ key }}</span>
            <span class="policy-type-badge">{{ group[0]?.type }}</span>
          </div>
          <div class="policy-actions">
            <button class="btn-icon" title="Cập nhật phiên bản mới" @click="openEditModal(group[0])">
              <span class="material-icons-outlined">edit</span>
            </button>
          </div>
        </div>
        
        <div class="policy-body">
          <h3 class="policy-title">{{ group[0].title }}</h3>
          <div class="policy-meta">
            <span>Phiên bản hiện tại: <strong>v{{ group[0].version }}</strong></span>
            <span>Trạng thái: 
              <span :class="['status-dot', group[0].is_active ? 'active' : 'inactive']"></span>
              {{ group[0].is_active ? 'Đang hoạt động' : 'Tạm ngưng' }}
            </span>
          </div>
          <div class="policy-content-preview">
            {{ stripHtml(group[0].content).substring(0, 150) }}...
          </div>
        </div>

        <div class="policy-history">
          <details>
            <summary>Lịch sử phiên bản ({{ group.length }})</summary>
            <ul>
              <li v-for="v in group" :key="v.id">
                <span>v{{ v.version }} - {{ formatDate(v.created_at) }}</span>
                <button class="btn-text" @click="viewPolicy(v)">Xem</button>
              </li>
            </ul>
          </details>
        </div>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal large">
        <div class="modal-header">
          <h3>{{ isEditing ? 'Cập nhật chính sách' : 'Thêm chính sách mới' }}</h3>
          <button class="btn-close" @click="closeModal">&times;</button>
        </div>
        <form @submit.prevent="savePolicy">
          <div class="form-grid">
            <div class="form-group" v-if="!isEditing">
              <label>Mã chính sách (Key)</label>
              <input v-model="form.key" type="text" placeholder="Ví dụ: terms-of-service" required />
            </div>
            <div class="form-group">
              <label>Loại</label>
              <select v-model="form.type" :disabled="isEditing">
                <option value="general">Chung (General)</option>
                <option value="refund">Hoàn tiền (Refund)</option>
                <option value="booking">Đặt sân (Booking)</option>
                <option value="moderation">Kiểm duyệt (Moderation)</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label>Tiêu đề</label>
              <input v-model="form.title" type="text" placeholder="Nhập tiêu đề chính sách" required />
            </div>
            <div class="form-group full-width">
              <label>Nội dung (hỗ trợ HTML)</label>
              <textarea v-model="form.content" rows="12" placeholder="Nhập nội dung chính sách..." required></textarea>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_active" />
                Kích hoạt ngay
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn secondary" @click="closeModal">Hủy</button>
            <button type="submit" class="btn sg-primary" :disabled="saving">
              {{ saving ? 'Đang lưu...' : (isEditing ? 'Tạo phiên bản mới' : 'Lưu chính sách') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal View Detail -->
    <div v-if="viewingPolicy" class="modal-backdrop" @click.self="viewingPolicy = null">
      <div class="modal large">
        <div class="modal-header">
          <h3>Chi tiết: {{ viewingPolicy.title }} (v{{ viewingPolicy.version }})</h3>
          <button class="btn-close" @click="viewingPolicy = null">&times;</button>
        </div>
        <div class="policy-view-content" v-html="viewingPolicy.content"></div>
        <div class="modal-footer">
          <button class="btn secondary" @click="viewingPolicy = null">Đóng</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { systemPolicyService } from '../../services/systemPolicyService.js';

export default {
  name: 'PolicyManagement',
  data() {
    return {
      policies: {},
      loading: false,
      saving: false,
      showModal: false,
      isEditing: false,
      viewingPolicy: null,
      error: '',
      success: '',
      form: {
        id: null,
        key: '',
        title: '',
        content: '',
        type: 'general',
        is_active: true,
      }
    };
  },
  mounted() {
    this.fetchPolicies();
  },
  methods: {
    async fetchPolicies() {
      this.loading = true;
      try {
        const response = await systemPolicyService.adminList();
        this.policies = response.data;
      } catch (err) {
        this.error = 'Không thể tải danh sách chính sách.';
      } finally {
        this.loading = false;
      }
    },
    openCreateModal() {
      this.isEditing = false;
      this.form = {
        key: '',
        title: '',
        content: '',
        type: 'general',
        is_active: true,
      };
      this.showModal = true;
    },
    openEditModal(policy) {
      this.isEditing = true;
      this.form = {
        id: policy.id,
        key: policy.key,
        title: policy.title,
        content: policy.content,
        type: policy.type,
        is_active: policy.is_active,
      };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
    },
    async savePolicy() {
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        if (this.isEditing) {
          await systemPolicyService.adminUpdate(this.form.id, this.form);
          this.success = 'Đã tạo phiên bản chính sách mới thành công.';
        } else {
          await systemPolicyService.adminCreate(this.form);
          this.success = 'Đã tạo chính sách mới thành công.';
        }
        this.closeModal();
        this.fetchPolicies();
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra khi lưu chính sách.';
      } finally {
        this.saving = false;
      }
    },
    viewPolicy(policy) {
      this.viewingPolicy = policy;
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    stripHtml(html) {
      let tmp = document.createElement("DIV");
      tmp.innerHTML = html;
      return tmp.textContent || tmp.innerText || "";
    }
  }
};
</script>

<style scoped>
.policy-management {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.toolbar h2 { margin: 0; font-size: 24px; color: var(--sg-dark); }
.toolbar p { margin: 4px 0 0; color: var(--sg-text-muted); font-size: 14px; }

.policy-groups {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.policy-card {
  background: white;
  border-radius: var(--sg-radius);
  border: 1px solid var(--sg-border);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 15px;
  box-shadow: var(--sg-shadow);
  transition: var(--sg-transition);
}

.policy-card:hover {
  box-shadow: var(--sg-shadow-lg);
}

.policy-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.policy-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.policy-key {
  font-size: 12px;
  font-weight: 700;
  color: var(--sg-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.policy-type-badge {
  display: inline-block;
  padding: 2px 8px;
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  width: fit-content;
}

.policy-title {
  margin: 0;
  font-size: 18px;
  color: var(--sg-dark);
}

.policy-meta {
  display: flex;
  gap: 15px;
  font-size: 13px;
  color: var(--sg-text-muted);
}

.status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 4px;
}
.status-dot.active { background: var(--sg-green); }
.status-dot.inactive { background: var(--sg-danger); }

.policy-content-preview {
  font-size: 14px;
  line-height: 1.5;
  color: var(--sg-text);
  background: #f8fafc;
  padding: 10px;
  border-radius: 8px;
}

.policy-history summary {
  font-size: 13px;
  font-weight: 600;
  color: var(--sg-green-dark);
  cursor: pointer;
  margin-bottom: 8px;
}

.policy-history ul {
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 12px;
}

.policy-history li {
  display: flex;
  justify-content: space-between;
  padding: 5px 0;
  border-bottom: 1px dashed var(--sg-border);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.full-width { grid-column: span 2; }

.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 14px; font-weight: 600; }
.form-group input, .form-group select, .form-group textarea {
  padding: 10px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  font-family: inherit;
}

.checkbox-label {
  flex-direction: row !important;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal.large { max-width: 800px; }

.modal-header {
  padding: 15px 20px;
  border-bottom: 1px solid var(--sg-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 { margin: 0; font-size: 18px; }
.btn-close { font-size: 24px; cursor: pointer; color: var(--sg-text-muted); }

.modal-footer {
  padding: 15px 20px;
  border-top: 1px solid var(--sg-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

form { padding: 20px; overflow-y: auto; }

.policy-view-content {
  padding: 20px;
  overflow-y: auto;
  line-height: 1.6;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s;
}

.sg-primary { background: var(--sg-green); color: white; }
.sg-primary:hover { background: var(--sg-green-dark); }
.secondary { background: #f1f5f9; color: var(--sg-text); }

.btn-icon {
  background: none;
  border: none;
  color: var(--sg-text-muted);
  cursor: pointer;
  padding: 5px;
  display: flex;
  align-items: center;
  border-radius: 5px;
}
.btn-icon:hover { background: #f1f5f9; color: var(--sg-green-dark); }

.btn-text {
  background: none;
  border: none;
  color: var(--sg-green-dark);
  cursor: pointer;
  font-weight: 600;
}

.alert {
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
}
.error { background: #fee2e2; color: #991b1b; }
.success { background: #dcfce7; color: #166534; }

.loading-state, .empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 50px;
  color: var(--sg-text-muted);
}
</style>
