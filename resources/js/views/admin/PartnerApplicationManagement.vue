<template>
  <section class="partner-application-management">
    <div class="toolbar">
      <div>
        <h2>Quản lý đơn đăng kí</h2>
        <p>Duyệt và quản lý các đơn đăng kí làm chủ sân.</p>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <!-- Filters -->
    <div class="filters">
      <select v-model="filterStatus" @change="loadApplications">
        <option value="">-- Tất cả trạng thái --</option>
        <option value="pending">Chờ duyệt</option>
        <option value="approved">Đã duyệt</option>
        <option value="rejected">Từ chối</option>
      </select>
      <input 
        v-model="searchVenue" 
        type="text" 
        placeholder="Tìm theo tên sân..."
        @keyup.debounce="loadApplications"
      />
    </div>

    <!-- Applications List -->
    <div class="applications-section">
      <div v-if="loading" class="loading-state">Đang tải dữ liệu...</div>
      <div v-else-if="applications.length === 0" class="empty-state">
        Không có đơn đăng kí nào.
      </div>
      
      <table v-else class="applications-table">
        <thead>
          <tr>
            <th>Tên sân</th>
            <th>Người nộp</th>
            <th>Ngày nộp</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="app in applications" :key="app.id">
            <td>{{ app.venue_name }}</td>
            <td>{{ app.user?.full_name }}</td>
            <td>{{ formatDate(app.submitted_at) }}</td>
            <td>
              <span :class="['status-badge', 'status-' + app.status]">
                {{ formatStatus(app.status) }}
              </span>
            </td>
            <td>
              <button class="btn-icon" title="Xem chi tiết" @click="openDetailModal(app)">
                <span>👁️</span>
              </button>
              <button 
                v-if="app.status === 'pending'"
                class="btn-icon approve"
                title="Duyệt"
                @click="openApproveModal(app)"
              >
                <span>✓</span>
              </button>
              <button 
                v-if="app.status === 'pending'"
                class="btn-icon reject"
                title="Từ chối"
                @click="openRejectModal(app)"
              >
                <span>✕</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detail Modal -->
    <div v-if="detailModal.show" class="modal-backdrop" @click.self="detailModal.show = false">
      <div class="modal large">
        <div class="modal-header">
          <h3>Chi tiết đơn đăng kí</h3>
          <button class="btn-close" @click="detailModal.show = false">&times;</button>
        </div>
        <div class="modal-body" v-if="detailModal.data">
          <div class="detail-section">
            <h4>Thông tin người nộp</h4>
            <p><strong>Tên:</strong> {{ detailModal.data.user?.full_name }}</p>
            <p><strong>Email:</strong> {{ detailModal.data.user?.email }}</p>
            <p><strong>Điện thoại:</strong> {{ detailModal.data.user?.phone }}</p>
          </div>

          <div class="detail-section">
            <h4>Thông tin kinh doanh</h4>
            <p><strong>Tên doanh nghiệp:</strong> {{ detailModal.data.business_name }}</p>
            <p><strong>Mã số thuế:</strong> {{ detailModal.data.tax_code }}</p>
          </div>

          <div class="detail-section">
            <h4>Thông tin sân</h4>
            <p><strong>Tên sân:</strong> {{ detailModal.data.venue_name }}</p>
            <p><strong>Địa chỉ:</strong> {{ detailModal.data.venue_address }}</p>
            <p v-if="detailModal.data.venue_map_url">
              <strong>Bản đồ:</strong> <a :href="detailModal.data.venue_map_url" target="_blank">Xem bản đồ</a>
            </p>
            <p>
              <strong>Tọa độ:</strong> {{ detailModal.data.venue_latitude }}, {{ detailModal.data.venue_longitude }}
            </p>
          </div>

          <div class="detail-section">
            <h4>Thông tin duyệt</h4>
            <p><strong>Trạng thái:</strong> <span :class="['status-badge', 'status-' + detailModal.data.status]">{{ formatStatus(detailModal.data.status) }}</span></p>
            <p v-if="detailModal.data.reviewed_by">
              <strong>Duyệt bởi:</strong> {{ detailModal.data.reviewedBy?.full_name }}
            </p>
            <p v-if="detailModal.data.reviewed_at">
              <strong>Ngày duyệt:</strong> {{ formatDate(detailModal.data.reviewed_at) }}
            </p>
            <p v-if="detailModal.data.status_reason">
              <strong>Lý do:</strong> {{ detailModal.data.status_reason }}
            </p>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn secondary" @click="detailModal.show = false">Đóng</button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="approveModal.show" class="modal-backdrop" @click.self="approveModal.show = false">
      <div class="modal">
        <div class="modal-header">
          <h3>Duyệt đơn đăng kí</h3>
          <button class="btn-close" @click="approveModal.show = false">&times;</button>
        </div>
        <form @submit.prevent="submitApprove">
          <div class="form-grid">
            <div class="form-group full-width">
              <label>Tên sân con ban đầu *</label>
              <input v-model="approveForm.initial_court_name" type="text" placeholder="Ví dụ: Sân bóng 1" required />
            </div>

            <div class="form-group">
              <label>Loại sân *</label>
              <select v-model="approveForm.court_type_id" required>
                <option value="">-- Chọn loại sân --</option>
                <option v-for="type in courtTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
            </div>

            <div class="form-group full-width">
              <label>Tên tài khoản ngân hàng *</label>
              <input v-model="approveForm.bank_account_name" type="text" required />
            </div>

            <div class="form-group">
              <label>Số tài khoản *</label>
              <input v-model="approveForm.bank_account_number" type="text" required />
            </div>

            <div class="form-group">
              <label>Tên ngân hàng *</label>
              <input v-model="approveForm.bank_name" type="text" required />
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn secondary" @click="approveModal.show = false">Hủy</button>
            <button type="submit" class="btn sg-primary" :disabled="approveModal.saving">
              {{ approveModal.saving ? 'Đang xử lý...' : 'Duyệt' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejectModal.show" class="modal-backdrop" @click.self="rejectModal.show = false">
      <div class="modal">
        <div class="modal-header">
          <h3>Từ chối đơn đăng kí</h3>
          <button class="btn-close" @click="rejectModal.show = false">&times;</button>
        </div>
        <form @submit.prevent="submitReject">
          <div class="form-group full-width">
            <label>Lý do từ chối *</label>
            <textarea v-model="rejectForm.reason" rows="6" placeholder="Nhập lý do từ chối..." required></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn secondary" @click="rejectModal.show = false">Hủy</button>
            <button type="submit" class="btn danger" :disabled="rejectModal.saving">
              {{ rejectModal.saving ? 'Đang xử lý...' : 'Từ chối' }}
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
  name: 'PartnerApplicationManagement',
  data() {
    return {
      applications: [],
      courtTypes: [],
      loading: false,
      error: '',
      success: '',
      filterStatus: '',
      searchVenue: '',
      detailModal: {
        show: false,
        data: null,
      },
      approveModal: {
        show: false,
        saving: false,
        data: null,
      },
      rejectModal: {
        show: false,
        saving: false,
        data: null,
      },
      approveForm: {
        initial_court_name: '',
        court_type_id: '',
        bank_account_name: '',
        bank_account_number: '',
        bank_name: '',
      },
      rejectForm: {
        reason: '',
      }
    };
  },
  mounted() {
    this.loadApplications();
    this.loadCourtTypes();
  },
  methods: {
    async loadApplications() {
      this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.filterStatus) params.append('status', this.filterStatus);
        if (this.searchVenue) params.append('venue_name', this.searchVenue);
        
        const response = await api(`/api/admin/partner-applications?${params}`);
        this.applications = response.data?.data ?? response.data ?? [];
      } catch (e) {
        this.error = 'Lỗi khi tải danh sách: ' + e.message;
      } finally {
        this.loading = false;
      }
    },
    async loadCourtTypes() {
      try {
        const response = await api('/api/admin/court-types');
        this.courtTypes = response.data ?? [];
      } catch (e) {
        console.error('Lỗi tải loại sân:', e);
        this.courtTypes = [];
      }
    },
    openDetailModal(app) {
      this.detailModal.data = app;
      this.detailModal.show = true;
    },
    openApproveModal(app) {
      this.approveModal.data = app;
      this.approveForm = {
        initial_court_name: '',
        court_type_id: '',
        bank_account_name: '',
        bank_account_number: '',
        bank_name: '',
      };
      this.approveModal.show = true;
    },
    openRejectModal(app) {
      this.rejectModal.data = app;
      this.rejectForm.reason = '';
      this.rejectModal.show = true;
    },
    async submitApprove() {
      this.approveModal.saving = true;
      this.error = '';
      this.success = '';
      try {
        const response = await api(`/api/admin/partner-applications/${this.approveModal.data.id}/approve`, {
          method: 'POST',
          body: JSON.stringify(this.approveForm),
        });
        this.success = response.message || 'Duyệt đơn thành công!';
        this.approveModal.show = false;
        this.loadApplications();
      } catch (e) {
        this.error = 'Lỗi khi duyệt: ' + e.message;
      } finally {
        this.approveModal.saving = false;
      }
    },
    async submitReject() {
      this.rejectModal.saving = true;
      this.error = '';
      this.success = '';
      try {
        const response = await api(`/api/admin/partner-applications/${this.rejectModal.data.id}/reject`, {
          method: 'POST',
          body: JSON.stringify(this.rejectForm),
        });
        this.success = response.message || 'Từ chối đơn thành công!';
        this.rejectModal.show = false;
        this.loadApplications();
      } catch (e) {
        this.error = 'Lỗi khi từ chối: ' + e.message;
      } finally {
        this.rejectModal.saving = false;
      }
    },
    formatStatus(status) {
      const map = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
      };
      return map[status] || status;
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
  }
};
</script>

<style scoped>
.partner-application-management {
  padding: 2rem;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  gap: 2rem;
}

.toolbar h2 {
  margin: 0;
  font-size: 1.75rem;
  color: #333;
}

.toolbar p {
  margin: 0.5rem 0 0 0;
  color: #666;
}

.alert {
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1rem;
}

.alert.error {
  background: #fee;
  color: #c33;
  border: 1px solid #fcc;
}

.alert.success {
  background: #efe;
  color: #3c3;
  border: 1px solid #cfc;
}

.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
}

.filters select,
.filters input {
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.95rem;
}

.filters input {
  flex: 1;
}

.applications-section {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 2rem;
  color: #666;
}

.applications-table {
  width: 100%;
  border-collapse: collapse;
}

.applications-table th {
  background: #f5f5f5;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #ddd;
}

.applications-table td {
  padding: 1rem;
  border-bottom: 1px solid #eee;
}

.applications-table tbody tr:hover {
  background: #f9f9f9;
}

.status-badge {
  display: inline-block;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 600;
}

.status-pending {
  background: #fff3cd;
  color: #856404;
}

.status-approved {
  background: #d4edda;
  color: #155724;
}

.status-rejected {
  background: #f8d7da;
  color: #721c24;
}

.btn-icon {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  padding: 0.5rem;
  margin: 0 0.25rem;
  transition: transform 0.2s;
}

.btn-icon:hover {
  transform: scale(1.2);
}

.btn-icon.approve {
  color: #28a745;
}

.btn-icon.reject {
  color: #dc3545;
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal.large {
  max-width: 700px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #eee;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.3rem;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #999;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

.detail-section {
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #eee;
}

.detail-section:last-child {
  border-bottom: none;
}

.detail-section h4 {
  margin: 0 0 1rem 0;
  font-size: 1.1rem;
  color: #333;
}

.detail-section p {
  margin: 0.5rem 0;
  color: #666;
}

.detail-section a {
  color: #1976d2;
  text-decoration: none;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #eee;
  justify-content: flex-end;
}

form {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
}

.btn.secondary {
  background: #e0e0e0;
  color: #333;
}

.btn.secondary:hover {
  background: #d0d0d0;
}

.btn.danger {
  background: #dc3545;
  color: white;
}

.btn.danger:hover:not(:disabled) {
  background: #c82333;
}

.btn.sg-primary {
  background: #2196f3;
  color: white;
}

.btn.sg-primary:hover:not(:disabled) {
  background: #1976d2;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
