<template>
  <div class="owner-profile-page">
    <div class="page-header">
      <h2>Hồ sơ đối tác & Hợp đồng</h2>
      <p class="muted">Thông tin đăng ký trở thành đối tác và các hợp đồng của bạn.</p>
    </div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải thông tin...</p>
    </div>

    <div v-else-if="error" class="state-box card">
      <div class="notice error">{{ error }}</div>
    </div>

    <div v-else-if="applications.length > 0" class="applications-container">

      <div v-for="(app, index) in applications" :key="app.id" class="application-details" style="margin-bottom: 40px;">
        <h2 style="margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Cụm sân: {{ app.venue_name }}</h2>
        
        <!-- Trạng thái chung -->
        <div class="card status-card">
          <div class="status-header">
            <h3>Trạng thái hồ sơ</h3>
            <span class="status-badge" :class="`status-${app.status}`">
              {{ statusLabel(app.status) }}
            </span>
          </div>
          <p v-if="app.status === 'rejected'" class="error-text">
            <strong>Lý do từ chối:</strong> {{ app.status_reason }}
          </p>
          <p v-else-if="app.status === 'pending' || app.status === 'reviewing'" class="muted">
            Hồ sơ đang được ban quản trị xem xét. Vui lòng chờ phản hồi.
          </p>
        </div>

      <!-- Hợp đồng -->
      <div class="card section-card" v-if="app.contracts && app.contracts.length > 0">
        <h3>Hợp đồng của cụm sân này</h3>
        <div class="contracts-list">
          <div v-for="contract in app.contracts" :key="contract.id" class="contract-item">
            <div class="contract-info">
              <AppIcon name="fileText" size="24" class="contract-icon" />
              <div>
                <strong>{{ contract.contract_number }}</strong>
                <span class="status-badge" :class="`status-${contract.status}`">{{ contractStatusLabel(contract.status) }}</span>
              </div>
            </div>
            <div class="contract-actions">
              <a v-if="contract.generated_file_path" :href="getFileUrl(contract.generated_file_path)" target="_blank" class="btn ghost">
                <AppIcon name="eye" size="16" /> Xem Hợp đồng
              </a>
                <button 
                  v-if="contract.status === 'pending_owner_signature'" 
                  class="btn primary" 
                  @click="signContract(contract.id)"
                  :disabled="signing"
                >
                  <AppIcon name="edit2" size="16" /> {{ signing ? 'Đang xử lý...' : 'Ký Hợp đồng' }}
                </button>
                <button 
                  v-if="contract.status === 'signed_active' && !hasPendingTermination(contract)" 
                  class="btn danger" 
                  @click="requestTermination(contract.id)"
                  :disabled="terminating"
                >
                  <AppIcon name="xCircle" size="16" /> {{ terminating ? 'Đang gửi...' : 'Yêu cầu thanh lý' }}
                </button>
                <span v-if="hasPendingTermination(contract)" class="status-badge status-reviewing">
                  Đang chờ duyệt thanh lý
                </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Thông tin kinh doanh -->
      <div class="card section-card">
        <h3>Thông tin đơn vị</h3>
        <div class="info-grid">
          <div class="info-item">
            <span class="label">Tên đơn vị kinh doanh</span>
            <span class="value">{{ app.business_name }}</span>
          </div>
          <div class="info-item">
            <span class="label">Mã số thuế</span>
            <span class="value">{{ app.tax_code || 'Không có' }}</span>
          </div>
          <div class="info-item">
            <span class="label">Tên cụm sân</span>
            <span class="value">{{ app.venue_name }}</span>
          </div>
          <div class="info-item full">
            <span class="label">Địa chỉ</span>
            <span class="value">{{ app.venue_address }}</span>
          </div>
        </div>
      </div>

      <!-- Tài khoản ngân hàng -->
      <div class="card section-card" v-if="app.bank_accounts && app.bank_accounts.length > 0">
        <h3>Tài khoản nhận tiền</h3>
        <div class="info-grid">
          <div v-for="account in app.bank_accounts" :key="account.id" class="info-item full account-box">
            <div class="account-details">
              <strong>{{ account.bank_name }}</strong>
              <span class="muted">{{ account.account_number }} - {{ account.account_holder_name }}</span>
            </div>
            <span v-if="account.is_default" class="badge">Mặc định</span>
          </div>
        </div>
      </div>

      <!-- Tài liệu đính kèm -->
      <div class="card section-card" v-if="app.documents && app.documents.length > 0">
        <h3>Tài liệu đính kèm</h3>
        <div class="docs-list">
          <a v-for="doc in app.documents" :key="doc.id" :href="getFileUrl(doc.file_path)" target="_blank" class="doc-item">
            <AppIcon name="paperclip" size="18" />
            <span>{{ documentTypeLabel(doc.type) }}</span>
          </a>
        </div>
      </div>
      
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerPartnerProfile',
  components: { AppIcon },
  data() {
    return {
      applications: [],
      loading: true,
      error: '',
      signing: false,
      terminating: false
    };
  },
  mounted() {
    this.fetchApplications();
  },
  methods: {
    async fetchApplications() {
      this.loading = true;
      try {
        const response = await api('/api/owner/partner-applications');
        this.applications = response.data || [];
        if (this.applications.length === 0) {
          this.error = 'Bạn chưa có hồ sơ đăng ký đối tác nào.';
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải thông tin hồ sơ.';
      } finally {
        this.loading = false;
      }
    },
    async signContract(contractId) {
      if (!confirm('Bạn có chắc chắn muốn ký xác nhận hợp đồng này? Thao tác này tương đương với việc chấp thuận các điều khoản trong hợp đồng.')) {
        return;
      }
      
      this.signing = true;
      try {
        await api(`/api/owner/contracts/${contractId}/sign`, { method: 'POST' });
        alert('Đã ký hợp đồng thành công!');
        this.fetchApplications(); // Tải lại để cập nhật trạng thái
      } catch (err) {
        alert(err.message || 'Có lỗi xảy ra khi ký hợp đồng.');
      } finally {
        this.signing = false;
      }
    },
      async requestTermination(contractId) {
        const reason = prompt('Vui lòng nhập lý do yêu cầu thanh lý hợp đồng:');
        if (!reason) return;

        const type = confirm('Đây là thỏa thuận chấm dứt từ cả hai bên? (Chọn OK nếu đã thỏa thuận, chọn Cancel nếu đơn phương chấm dứt)') ? 'mutual' : 'unilateral_by_owner';

        this.terminating = true;
        try {
          await api(`/api/owner/contracts/${contractId}/request-termination`, {
            method: 'POST',
            body: JSON.stringify({ reason, type }),
            headers: { 'Content-Type': 'application/json' }
          });
          alert('Đã gửi yêu cầu thanh lý thành công! Vui lòng chờ phản hồi từ SportGo.');
          this.fetchApplications();
        } catch (err) {
          alert(err.message || 'Có lỗi xảy ra khi gửi yêu cầu thanh lý.');
        } finally {
          this.terminating = false;
        }
      },
      hasPendingTermination(contract) {
        if (!contract.terminations) return false;
        return contract.terminations.some(t => t.status === 'submitted');
      },
    getFileUrl(path) {
      if (!path) return '#';
      if (path.startsWith('http')) return path;
      return '/storage/' + path.replace('public/', '');
    },
    statusLabel(status) {
      const map = {
        pending: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      };
      return map[status] || status;
    },
    contractStatusLabel(status) {
        const map = {
          generated: 'Nháp',
          pending_owner_signature: 'Chờ đối tác ký',
          pending_sportgo_signature: 'Chờ SportGo ký',
          signed_active: 'Đang hiệu lực',
          terminated: 'Đã thanh lý',
        };
      return map[status] || status;
    },
    documentTypeLabel(type) {
      const map = {
        identity_card: 'CCCD/CMND',
        business_license: 'Giấy phép kinh doanh',
        venue_images: 'Hình ảnh sân',
        other: 'Khác'
      };
      return map[type] || type;
    }
  }
};
</script>

<style scoped>
.owner-profile-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1000px;
  margin: 0 auto;
  padding-bottom: 40px;
}

.page-header {
  margin-bottom: 8px;
}

.page-header h2 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 4px;
}

.card {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  padding: 24px;
}

.application-details {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.status-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #f8fafc;
}

.status-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.status-header h3 {
  margin: 0;
  font-size: 18px;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
}

.status-approved { background: #dcfce7; color: #166534; }
.status-pending, .status-reviewing { background: #fef08a; color: #854d0e; }
.status-rejected, .status-cancelled { background: #fee2e2; color: #991b1b; }
.status-waiting_signature { background: #e0e7ff; color: #3730a3; }
.status-completed, .status-signed { background: #dcfce7; color: #166534; }

.section-card h3 {
  margin-top: 0;
  margin-bottom: 16px;
  font-size: 16px;
  border-bottom: 1px solid var(--sg-border);
  padding-bottom: 12px;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-item.full {
  grid-column: 1 / -1;
}

.info-item .label {
  font-size: 12px;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 700;
}

.info-item .value {
  font-size: 15px;
  font-weight: 500;
  color: var(--sg-text);
}

.account-box {
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #f8fafc;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.badge {
  background: #cbd5e1;
  color: #334155;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 12px;
  font-weight: 700;
}

.docs-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.doc-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #f1f5f9;
  border-radius: 8px;
  color: var(--sg-text);
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: background 0.2s;
}

.doc-item:hover {
  background: #e2e8f0;
}

.contracts-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.contract-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.contract-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.contract-icon {
  color: #3b82f6;
  background: #eff6ff;
  padding: 8px;
  border-radius: 8px;
}

.contract-info > div {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}

.contract-actions {
  display: flex;
  gap: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s;
  text-decoration: none;
}

.btn.ghost {
  background: #fff;
  border-color: var(--sg-border);
  color: var(--sg-text);
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.notice {
  padding: 12px 16px;
  border-radius: 8px;
  font-weight: 600;
}

.notice.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.error-text {
  color: #dc2626;
}

.muted {
  color: #64748b;
}

/* Modal styles */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid var(--sg-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
}

.close-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
}

.modal-body {
  padding: 24px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  font-size: 14px;
}

.input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--sg-border);
  border-radius: 6px;
  font-family: inherit;
  font-size: 14px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.modal-footer {
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--sg-border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
