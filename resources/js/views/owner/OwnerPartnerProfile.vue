<template>
  <div class="owner-profile-page">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h2>Hồ sơ đối tác & Hợp đồng</h2>
        <p class="muted">Thông tin đăng ký trở thành đối tác và các hợp đồng của bạn.</p>
      </div>
      <button class="btn primary" @click="openNewClusterModal()">
        <AppIcon name="plus" size="16" /> Đăng ký Cụm sân mới
      </button>
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
        <div style="margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
          <h2 style="margin: 0;">Cụm sân: {{ app.venue_name }}</h2>
          <button
            v-if="canRequestExpansion(app)"
            class="btn ghost"
            type="button"
            @click="openNewClusterModal(app)"
          >
            <AppIcon name="plus" size="16" /> Yêu cầu mở rộng
          </button>
        </div>
        
        <!-- Trạng thái chung -->
        <div class="card status-card">
          <div class="status-header">
            <h3>Trạng thái hồ sơ</h3>
            <span class="status-badge" :class="`status-${app.status}`">
              {{ statusLabel(app.status) }}
            </span>
          </div>
          <div class="muted" style="display: flex; gap: 16px; flex-wrap: wrap;">
            <span>Ngày gửi: {{ formatDate(app.submitted_at || app.created_at) }}</span>
            <span v-if="app.reviewed_at">Ngày duyệt: {{ formatDate(app.reviewed_at) }}</span>
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
                <div v-if="contract.owner_signed_at || contract.sportgo_signed_at" class="muted small" style="margin-top: 4px; font-size: 0.85em; color: #64748b;">
                  <div v-if="contract.owner_signed_at">Ngày đối tác ký: {{ formatDate(contract.owner_signed_at) }}</div>
                  <div v-if="contract.sportgo_signed_at">Ngày SportGo ký: {{ formatDate(contract.sportgo_signed_at) }}</div>
                </div>
              </div>
            </div>
            <div class="contract-actions">
              <button v-if="contract.generated_file_path" @click="viewFile(contract.generated_file_path)" type="button" class="btn ghost">
                <AppIcon name="eye" size="16" /> Xem Hợp đồng
              </button>
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
                  <AppIcon name="xCircle" size="16" /> {{ terminating ? 'Đang gửi...' : 'Yêu cầu kết thúc' }}
                </button>
                <span v-if="hasPendingTermination(contract)" class="status-badge status-reviewing">
                  Đang chờ duyệt kết thúc
                </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Termination Modal -->
      <div v-if="terminationModal.open" class="modal-backdrop" @click.self="closeTerminationModal">
        <div class="modal-content" style="max-width: 500px;">
          <div class="modal-header">
            <h3>Yêu cầu thanh lý hợp đồng</h3>
            <button class="close-btn" @click="closeTerminationModal">
              <AppIcon name="x" size="20" />
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Lý do thanh lý</label>
              <textarea 
                v-model="terminationModal.reason" 
                class="input" 
                rows="4" 
                placeholder="Nhập lý do thanh lý hợp đồng..."
              ></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn ghost" @click="closeTerminationModal">Hủy</button>
              <button class="btn danger" @click="submitTermination" :disabled="terminating">
                {{ terminating ? 'Đang gửi...' : 'Gửi yêu cầu' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- New Cluster Modal -->
      <div v-if="newClusterModal.open" class="modal-backdrop" @click.self="closeNewClusterModal">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Đăng ký Cụm sân mới</h3>
            <button class="close-btn" @click="closeNewClusterModal">
              <AppIcon name="x" size="20" />
            </button>
          </div>
          <form class="modal-body" @submit.prevent="submitNewCluster">
            <p class="muted" style="margin-bottom: 16px;">Thông tin pháp lý và kinh doanh sẽ được tự động lấy từ hồ sơ hiện tại của bạn.</p>
            <div class="form-group">
              <label>Tên cụm sân</label>
              <input v-model="newClusterForm.venue_name" class="input" required />
            </div>
            <div class="form-group">
              <label>Địa chỉ chi tiết</label>
              <input v-model="newClusterForm.venue_address" class="input" required />
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Tỉnh/Thành phố</label>
                <input v-model="newClusterForm.venue_province" class="input" required />
              </div>
              <div class="form-group">
                <label>Quận/Huyện</label>
                <input v-model="newClusterForm.venue_district" class="input" required />
              </div>
              <div class="form-group">
                <label>Phường/Xã</label>
                <input v-model="newClusterForm.venue_ward" class="input" required />
              </div>
              <div class="form-group">
                <label>Số lượng sân dự kiến</label>
                <input type="number" v-model="newClusterForm.court_count_total" class="input" required min="1" />
              </div>
              <div class="form-group">
                <label>Tọa độ Latitude</label>
                <input type="number" step="any" v-model="newClusterForm.venue_latitude" class="input" required />
              </div>
              <div class="form-group">
                <label>Tọa độ Longitude</label>
                <input type="number" step="any" v-model="newClusterForm.venue_longitude" class="input" required />
              </div>
            </div>
            <div class="form-group">
              <label>Link Google Maps</label>
              <input type="url" v-model="newClusterForm.venue_map_url" class="input" />
            </div>
            <div class="form-group">
              <label>Số điện thoại liên hệ cụm sân</label>
              <input v-model="newClusterForm.venue_phone" class="input" />
            </div>
            <div class="form-group">
              <label>Mô tả dịch vụ</label>
              <textarea v-model="newClusterForm.venue_description" class="input" rows="3"></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn ghost" @click="closeNewClusterModal">Hủy</button>
              <button type="submit" class="btn primary" :disabled="submittingCluster">
                {{ submittingCluster ? 'Đang gửi...' : 'Gửi yêu cầu mở rộng' }}
              </button>
            </div>
          </form>
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
          <button v-for="doc in app.documents" :key="doc.id" @click="viewFile(doc.file_path)" type="button" class="doc-item">
            <AppIcon name="paperclip" size="18" />
            <span>{{ documentTypeLabel(doc.type) }}</span>
          </button>
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
      terminating: false,
      terminationModal: { open: false, contractId: null, reason: '' },
      newClusterModal: { open: false },
      submittingCluster: false,
      newClusterForm: {
        venue_name: '',
        venue_address: '',
        venue_province: '',
        venue_district: '',
        venue_ward: '',
        court_count_total: 1,
        venue_latitude: '',
        venue_longitude: '',
        venue_map_url: '',
        venue_phone: '',
        venue_description: '',
      }
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
    canRequestExpansion(app) {
      return ['approved', 'completed'].includes(app?.status) || Boolean(app?.approved_venue_cluster_id);
    },
    openNewClusterModal(baseApplication = null) {
      this.newClusterForm = {
        venue_name: '',
        venue_address: baseApplication?.venue_address || '',
        venue_province: baseApplication?.venue_province || '',
        venue_district: baseApplication?.venue_district || '',
        venue_ward: baseApplication?.venue_ward || '',
        court_count_total: 1,
        venue_latitude: baseApplication?.venue_latitude || '',
        venue_longitude: baseApplication?.venue_longitude || '',
        venue_map_url: baseApplication?.venue_map_url || '',
        venue_phone: baseApplication?.venue_phone || '',
        venue_description: baseApplication?.venue_description || '',
      };
      this.newClusterModal.open = true;
    },
    closeNewClusterModal() {
      this.newClusterModal.open = false;
    },
    async submitNewCluster() {
      if (!confirm('Bạn có chắc chắn muốn gửi yêu cầu đăng ký cụm sân mới?')) return;
      
      this.submittingCluster = true;
      try {
        await api('/api/owner/partner-applications/new-cluster', {
          method: 'POST',
          body: JSON.stringify(this.newClusterForm),
        });
        alert('Gửi yêu cầu thành công! Vui lòng chờ admin xét duyệt.');
        this.closeNewClusterModal();
        this.fetchApplications();
      } catch (err) {
        alert(err.message || 'Có lỗi xảy ra khi gửi yêu cầu.');
      } finally {
        this.submittingCluster = false;
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
    requestTermination(contractId) {
      this.terminationModal = {
        open: true,
        contractId: contractId,
        reason: ''
      };
    },
    closeTerminationModal() {
      this.terminationModal.open = false;
    },
    async submitTermination() {
      if (!this.terminationModal.reason) {
        alert('Vui lòng nhập lý do thanh lý hợp đồng.');
        return;
      }
      const type = confirm('Đây là thỏa thuận chấm dứt từ cả hai bên? (Chọn OK nếu đã thỏa thuận, chọn Cancel nếu đơn phương chấm dứt)') ? 'mutual' : 'unilateral_by_owner';

      this.terminating = true;
      try {
        await api(`/api/owner/contracts/${this.terminationModal.contractId}/request-termination`, {
          method: 'POST',
          body: JSON.stringify({ reason: this.terminationModal.reason, type }),
        });
        alert('Đã gửi yêu cầu thanh lý thành công.');
        this.closeTerminationModal();
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
    async viewFile(path) {
      if (!path) return;
      if (path.startsWith('http')) {
        window.open(path, '_blank');
        return;
      }
      try {
        const token = localStorage.getItem('auth_token') || JSON.parse(localStorage.getItem('sportgo_auth') || 'null')?.token;
        const headers = { Accept: 'application/json' };
        if (token) headers.Authorization = `Bearer ${token}`;
        const response = await fetch(`/api/auth/files/download?path=${encodeURIComponent(path)}`, { headers });
        if (!response.ok) {
          let serverMessage = '';
          try {
            const errorBody = await response.json();
            serverMessage = errorBody?.message || '';
          } catch {
            serverMessage = '';
          }
          throw new Error(serverMessage || 'Không thể tải file');
        }
        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        if (response.redirected || contentType.includes('text/html')) {
          const htmlBody = await response.text();
          const compactHtml = htmlBody.replace(/\s+/g, ' ').slice(0, 120);
          throw new Error(`File trả về không hợp lệ (${compactHtml || 'HTML response'})`);
        }
        const blob = await response.blob();
        const disposition = response.headers.get('content-disposition') || '';
        const filenameFromHeader = disposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i)?.[1];
        const fallbackName = decodeURIComponent(String(path).split('/').pop() || 'downloaded-file');
        const filename = decodeURIComponent((filenameFromHeader || fallbackName).replace(/"/g, ''));

        const canPreviewInBrowser =
          contentType.includes('pdf') ||
          contentType.startsWith('image/');

        const url = URL.createObjectURL(blob);
        if (canPreviewInBrowser) {
          const openedWindow = window.open(url, '_blank', 'noopener,noreferrer');

          // Fallback for browsers blocking async popup open.
          if (!openedWindow) {
            const tempLink = document.createElement('a');
            tempLink.href = url;
            tempLink.target = '_blank';
            tempLink.rel = 'noopener noreferrer';
            document.body.appendChild(tempLink);
            tempLink.click();
            document.body.removeChild(tempLink);
          }
        } else {
          const downloadLink = document.createElement('a');
          downloadLink.href = url;
          downloadLink.download = filename;
          document.body.appendChild(downloadLink);
          downloadLink.click();
          document.body.removeChild(downloadLink);
        }

        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } catch (err) {
        alert(err.message || 'Lỗi tải file');
      }
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
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
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
