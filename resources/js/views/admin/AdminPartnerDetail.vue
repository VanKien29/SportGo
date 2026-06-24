<template>
  <div class="partner-detail-page">
    <header class="page-header">
      <div class="breadcrumbs">
        <router-link to="/admin/partner-applications" class="back-link">
          <AppIcon name="arrowLeft" size="16" /> Quay lại danh sách
        </router-link>
      </div>
      <div>
        <h2>Chi tiết đối tác: {{ app?.user_info?.full_name || app?.business_info?.business_name || 'Đang tải...' }}</h2>
      </div>
    </header>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải dữ liệu...</p>
    </div>
    <div v-else-if="error" class="notice error">{{ error }}</div>
    
    <div v-else-if="app" class="detail-container">
      <div class="main-content">
        <!-- 1. Thông tin chung -->
        <div class="card section-card">
          <h3>Thông tin đăng ký</h3>
          <div class="detail-grid">
            <div class="detail-col">
              <span class="label">Loại hồ sơ</span>
              <span class="value">
                <span v-if="app.type === 'new_cluster'" class="badge cluster-badge">Đăng ký thêm cụm sân</span>
                <span v-else class="badge partner-badge">Đăng ký đối tác mới</span>
              </span>
            </div>
            <div class="detail-col">
              <span class="label">Đơn vị kinh doanh</span>
              <span class="value">{{ app.business_info?.business_name }}</span>
            </div>
            <div class="detail-col">
              <span class="label">Mã số thuế</span>
              <span class="value">{{ app.business_info?.tax_code || '-' }}</span>
            </div>
            <div class="detail-col">
              <span class="label">Người đại diện</span>
              <span class="value">{{ app.user_info?.full_name }}</span>
            </div>
            <div class="detail-col">
              <span class="label">Điện thoại / Email</span>
              <span class="value">{{ app.user_info?.phone }} / {{ app.user_info?.email }}</span>
            </div>
          </div>
        </div>

        <!-- 2. Cụm sân quản lý (Hồ sơ hiện tại) -->
        <div class="card section-card">
          <h3>Cụm sân trong hồ sơ này</h3>
          <div class="detail-grid">
            <div class="detail-col full">
              <span class="label">Tên cụm sân</span>
              <span class="value" style="font-weight: 600;">{{ app.venue_info?.venue_name || app.business_info?.venue_name }}</span>
            </div>
            <div class="detail-col full">
              <span class="label">Địa chỉ</span>
              <span class="value">{{ app.venue_info?.address }}</span>
              <a v-if="app.venue_info?.map_url" :href="app.venue_info?.map_url" target="_blank" class="link small">(Bản đồ)</a>
            </div>
          </div>

          <h4 v-if="app.courts?.length" style="margin-top: 16px;">Sân con dự kiến ({{ app.courts.length }})</h4>
          <div v-if="app.courts?.length" class="courts-list">
            <div v-for="court in app.courts" :key="court.id" class="court-card">
              <strong>{{ court.name }}</strong>
              <span class="muted small">{{ court.court_type?.name }}</span>
            </div>
          </div>
        </div>

        <!-- 3. Tài liệu & Phụ lục (Documents) -->
        <div class="card section-card">
          <h3>Tài liệu đính kèm</h3>
          <div v-if="app.documents?.length" class="docs-list">
            <button v-for="doc in app.documents" :key="doc.id" @click="viewFile(doc.file_path)" type="button" class="btn ghost doc-item">
              <AppIcon name="paperclip" size="18" />
              <span>{{ doc.type }}</span>
            </button>
          </div>
          <p v-else class="muted">Chưa có tài liệu đính kèm.</p>
        </div>

        <!-- 4. Hợp đồng -->
        <div class="card section-card">
          <h3>Hợp đồng hợp tác</h3>
          <div v-if="app.contracts?.length" class="contracts-list">
            <div v-for="contract in app.contracts" :key="contract.id" class="contract-item card-inner">
              <div class="contract-header">
                <AppIcon name="fileText" size="24" class="contract-icon" />
                <div class="info">
                  <strong>{{ contract.contract_number }}</strong>
                  <span class="status-badge" :class="`status-${contract.status}`">{{ contractStatusLabel(contract.status) }}</span>
                </div>
              </div>
              <div class="contract-dates muted small">
                <div v-if="contract.owner_signed_at">Đối tác ký: {{ formatDate(contract.owner_signed_at) }}</div>
                <div v-if="contract.sportgo_signed_at">SportGo ký: {{ formatDate(contract.sportgo_signed_at) }}</div>
              </div>
              <div class="contract-actions">
                <button v-if="contract.generated_file_path" @click="viewFile(contract.generated_file_path)" class="btn ghost small">
                  <AppIcon name="eye" size="16" /> Xem Hợp đồng
                </button>
                <div class="flex-actions" v-if="contract.status === 'signed_active'">
                  <!-- Nút đơn phương chấm dứt -->
                  <button class="btn danger small" @click="openTerminationModal(contract)">
                    <AppIcon name="xCircle" size="14" /> Đơn phương chấm dứt
                  </button>
                </div>
                <button v-if="contract.status === 'pending_sportgo_signature'" @click="approveSignature(contract.id)" class="btn primary small" :disabled="savingAction">
                  <AppIcon name="check" size="16" /> Ký duyệt Hợp đồng
                </button>
              </div>

              <!-- Danh sách yêu cầu thanh lý nếu có -->
              <div v-if="contract.terminations?.length" class="terminations-list">
                <div v-for="term in contract.terminations" :key="term.id" class="termination-box">
                  <div class="term-header">
                    <strong>Yêu cầu thanh lý ({{ term.termination_type === 'unilateral_by_sportgo' ? 'Từ SportGo' : 'Từ Đối tác' }})</strong>
                    <span class="badge">{{ term.status }}</span>
                  </div>
                  <p class="muted small">{{ term.reason }}</p>
                  <div class="actions" v-if="term.status === 'submitted' && term.termination_type !== 'unilateral_by_sportgo'">
                    <button class="btn danger small" @click="approveTermination(contract.id)" :disabled="savingAction">
                      Đồng ý thanh lý
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="muted">Chưa có hợp đồng nào được tạo.</p>
        </div>

        <!-- 5. Lịch sử đăng ký (Các hồ sơ khác) -->
        <div class="card section-card">
          <h3>Lịch sử đăng ký / Cụm sân khác của đối tác</h3>
          <div v-if="app.other_applications?.length">
            <table class="simple-table">
              <thead>
                <tr>
                  <th>Tên cụm sân</th>
                  <th>Loại</th>
                  <th>Trạng thái</th>
                  <th>Ngày nộp</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="other in app.other_applications" :key="other.id">
                  <td>
                    <router-link :to="`/admin/partners/${other.id}`" class="link">{{ other.venue_name }}</router-link>
                  </td>
                  <td>{{ other.type === 'new_cluster' ? 'Cụm mới' : 'Đối tác mới' }}</td>
                  <td><span class="status" :class="`status-${other.status}`">{{ statusLabel(other.status) }}</span></td>
                  <td>{{ formatDate(other.submitted_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="muted">Đây là hồ sơ duy nhất của đối tác này.</p>
        </div>
      </div>
      
      <!-- Sidebar -->
      <div class="side-content">
        <!-- Ngân hàng -->
        <div class="card section-card">
          <h3>Tài khoản thanh toán</h3>
          <div v-if="app.bank_accounts?.length" class="bank-list">
            <div v-for="bank in app.bank_accounts" :key="bank.id" class="bank-card">
              <div class="bank-info">
                <strong>{{ bank.bank_name }}</strong>
                <span v-if="bank.is_default" class="badge">Mặc định</span>
              </div>
              <div class="account-details">
                <span>STK: <strong>{{ bank.account_number }}</strong></span>
                <span>Chủ tài khoản: <strong>{{ bank.account_holder_name }}</strong></span>
                <span v-if="bank.branch_name">Chi nhánh: {{ bank.branch_name }}</span>
              </div>
            </div>
          </div>
          <p v-else class="muted small">Chưa khai báo tài khoản.</p>
        </div>

        <!-- Trạng thái xử lý -->
        <div class="card section-card">
          <h3>Trạng thái duyệt</h3>
          <div class="review-status">
            <div class="status-indicator" :class="`status-${app.status}`">
              <div class="dot"></div>
              <span>{{ statusLabel(app.status) }}</span>
            </div>
            
            <div v-if="app.review_info?.reviewed_at" class="review-details">
              <p><strong>Người duyệt:</strong> {{ app.review_info.reviewed_by?.full_name || '-' }}</p>
              <p><strong>Thời gian:</strong> {{ formatDate(app.review_info.reviewed_at) }}</p>
              <div v-if="app.review_info.status_reason" class="note-box">
                <strong>Ghi chú:</strong>
                <p>{{ app.review_info.status_reason }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal chấm dứt đơn phương -->
    <div v-if="terminationModal.open" class="modal-backdrop" @click.self="closeTerminationModal">
      <div class="modal small">
        <div class="modal-header">
          <h3>Chấm dứt hợp đồng đơn phương</h3>
          <button class="icon-btn" type="button" @click="closeTerminationModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <form class="modal-body" @submit.prevent="submitUnilateralTermination">
          <p class="muted" style="margin-bottom: 12px;">Hành động này sẽ gửi công văn chấm dứt hợp tác tới đối tác và chuyển hợp đồng sang trạng thái thanh lý.</p>
          <label class="field full">
            <span>Lý do chấm dứt (sẽ gửi cho đối tác)</span>
            <textarea v-model.trim="terminationModal.reason" rows="4" maxlength="1000" required placeholder="Nhập lý do vi phạm hoặc lý do chấm dứt..."></textarea>
          </label>
          <div class="modal-footer inner">
            <button class="btn ghost" type="button" @click="closeTerminationModal">Hủy</button>
            <button class="btn danger" type="submit" :disabled="savingAction">
              {{ savingAction ? 'Đang gửi...' : 'Gửi công văn chấm dứt' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';
import { api } from '../../services/api.js';

export default {
  name: 'AdminPartnerDetail',
  components: { AppIcon },
  data() {
    return {
      app: null,
      loading: true,
      error: '',
      savingAction: false,
      terminationModal: {
        open: false,
        contractId: null,
        reason: ''
      }
    };
  },
  created() {
    this.fetchData();
  },
  watch: {
    '$route.params.id': 'fetchData'
  },
  methods: {
    async fetchData() {
      if (!this.$route.params.id) return;
      this.loading = true;
      this.error = '';
      try {
        const res = await adminPartnerApplicationService.show(this.$route.params.id);
        this.app = res.data;
      } catch (err) {
        this.error = 'Không thể tải chi tiết đối tác: ' + err.message;
      } finally {
        this.loading = false;
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return '-';
      return new Date(dateStr).toLocaleString('vi-VN');
    },
    statusLabel(status) {
      const map = {
        pending: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        approved: 'Đã duyệt (Chờ tạo HĐ)',
        approved_pending_contract: 'Đã duyệt (Chờ tạo HĐ)',
        contract_pending_owner_signature: 'Chờ đối tác ký HĐ',
        contract_pending_sportgo_signature: 'Chờ SportGo ký HĐ',
        completed: 'Hoàn tất',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      };
      return map[status] || status;
    },
    contractStatusLabel(status) {
      const map = {
        draft: 'Bản nháp',
        pending_owner_signature: 'Chờ đối tác ký',
        pending_sportgo_signature: 'Chờ SportGo duyệt',
        signed_active: 'Đang hiệu lực',
        terminating: 'Đang thanh lý',
        terminated: 'Đã chấm dứt',
      };
      return map[status] || status;
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
    openTerminationModal(contract) {
      this.terminationModal = {
        open: true,
        contractId: contract.id,
        reason: ''
      };
    },
    closeTerminationModal() {
      this.terminationModal.open = false;
    },
    async submitUnilateralTermination() {
      if (!this.terminationModal.contractId || !this.terminationModal.reason) return;
      if (!confirm('Bạn có chắc chắn muốn đơn phương chấm dứt hợp đồng này?')) return;
      
      this.savingAction = true;
      try {
        await api(`/api/admin/contracts/${this.terminationModal.contractId}/terminate`, {
          method: 'POST',
          body: JSON.stringify({
            type: 'unilateral_by_admin',
            reason: this.terminationModal.reason
          })
        });
        this.closeTerminationModal();
        await this.fetchData();
        alert('Đã gửi công văn chấm dứt thành công!');
      } catch (err) {
        alert(err.message || 'Lỗi khi yêu cầu chấm dứt hợp đồng.');
      } finally {
        this.savingAction = false;
      }
    },
    async approveTermination(contractId) {
      if (!confirm('Xác nhận đồng ý thanh lý hợp đồng này theo yêu cầu của đối tác?')) return;
      this.savingAction = true;
      try {
        await api(`/api/admin/contracts/${contractId}/approve-termination`, { method: 'POST' });
        await this.fetchData();
        alert('Đã duyệt yêu cầu thanh lý!');
      } catch (err) {
        alert(err.message || 'Lỗi khi duyệt yêu cầu thanh lý.');
      } finally {
        this.savingAction = false;
      }
    },
    async approveSignature(contractId) {
      if (!confirm('Xác nhận ký phê duyệt và cấp quyền cho đối tác này?')) return;
      this.savingAction = true;
      try {
        await api(`/api/admin/contracts/${contractId}/approve-signature`, { method: 'POST' });
        await this.fetchData();
        alert('Đã ký phê duyệt hợp đồng thành công!');
      } catch (err) {
        alert(err.message || 'Lỗi khi ký phê duyệt hợp đồng.');
      } finally {
        this.savingAction = false;
      }
    }
  }
};
</script>

<style scoped>
.partner-detail-page {
  padding: 24px;
}
.page-header {
  margin-bottom: 24px;
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--primary);
  text-decoration: none;
  font-weight: 500;
  margin-bottom: 12px;
}
.detail-container {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  align-items: start;
}
@media (max-width: 900px) {
  .detail-container {
    grid-template-columns: 1fr;
  }
}
.section-card {
  padding: 20px;
  margin-bottom: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.section-card h3 {
  margin-top: 0;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
  font-size: 1.1rem;
}
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.detail-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.detail-col.full {
  grid-column: 1 / -1;
}
.label {
  font-size: 0.85rem;
  color: var(--admin-muted);
}
.value {
  font-weight: 500;
}
.courts-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
}
.court-card {
  padding: 12px;
  background: var(--admin-surface-muted);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  display: flex;
  flex-direction: column;
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
}
.contracts-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.card-inner {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 16px;
  background: var(--admin-surface-muted);
}
.contract-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.contract-icon {
  color: var(--primary);
}
.info {
  display: flex;
  flex-direction: column;
}
.contract-dates {
  margin-bottom: 12px;
}
.flex-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.terminations-list {
  margin-top: 16px;
  border-top: 1px dashed var(--border-color);
  padding-top: 12px;
}
.termination-box {
  background: #fff0f0;
  border: 1px solid #ffcdd2;
  border-radius: 6px;
  padding: 12px;
}
.term-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.bank-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.bank-card {
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 12px;
  background: var(--admin-surface-muted);
}
.bank-info {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.account-details {
  display: flex;
  flex-direction: column;
  font-size: 0.9rem;
}
.review-status {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.status-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
}
.status-indicator .dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: currentColor;
}
.note-box {
  background: var(--admin-surface-muted);
  padding: 12px;
  border-radius: 6px;
  margin-top: 8px;
  font-size: 0.9rem;
}
.simple-table {
  width: 100%;
  border-collapse: collapse;
}
.simple-table th, .simple-table td {
  padding: 8px;
  border-bottom: 1px solid var(--border-color);
  text-align: left;
}
</style>
