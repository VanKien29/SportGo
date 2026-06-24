<template>
  <div class="partner-app-page">
    <div class="tabs-container">
      <div class="tabs">
        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'danh-sach' }" 
          @click="setTab('danh-sach')"
        >
          Danh sách
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'cho-duyet' }" 
          @click="setTab('cho-duyet')"
        >
          Chờ duyệt
        </button>
        <button 
          class="tab-btn" 
          :class="{ active: activeTab === 'da-tu-choi' }" 
          @click="setTab('da-tu-choi')"
        >
          Đã từ chối
        </button>
      </div>
    </div>

    <div class="toolbar card">
      <div class="filters">
        <label class="field compact">
          <span>Tìm kiếm</span>
          <input
            v-model="filters.search"
            type="search"
            placeholder="Tên sân, người nộp, MST"
            @input="onFilterChange"
          />
        </label>
        <label class="field compact">
          <span>Từ ngày</span>
          <input v-model="filters.date_from" type="date" @change="loadApplications(1)" />
        </label>
        <label class="field compact">
          <span>Đến ngày</span>
          <input v-model="filters.date_to" type="date" :min="filters.date_from" @change="loadApplications(1)" />
        </label>
      </div>
    </div>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải đơn đăng kí...</p>
    </div>

    <div v-else-if="applications.length === 0" class="state-box card">
      <p>Không có đơn đăng kí phù hợp.</p>
    </div>

    <div v-else class="applications-table card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Hồ sơ</th>
              <th>Người nộp</th>
              <th>Sân con</th>
              <th>Ngày gửi</th>
              <th class="center">Trạng thái</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="application in applications" :key="application.id">
              <td>
                <div class="main-title">
                  {{ application.venue_name }}
                  <span v-if="application.type === 'new_cluster'" class="badge cluster-badge">Cụm mới</span>
                  <span v-else class="badge partner-badge">Đối tác mới</span>
                </div>
                <div class="muted">{{ application.business_name }}</div>
              </td>
              <td>
                <div class="main-title">{{ application.user?.full_name || application.user?.username || '-' }}</div>
                <div class="muted">{{ application.user?.email || application.user?.phone || '' }}</div>
              </td>
              <td>{{ application.courts_count || 0 }}</td>
              <td>{{ formatDate(application.submitted_at) }}</td>
              <td class="center">
                <span class="status" :class="`status-${application.status}`">
                  {{ statusLabel(application.status) }}
                </span>
              </td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Chi tiết" @click="openDetail(application)">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <button
                    v-if="isReviewable(application.status)"
                    class="icon-btn approve"
                    type="button"
                    title="Duyệt"
                    @click="openApprove(application)"
                  >
                    <AppIcon name="check" size="16" />
                  </button>
                  <button
                    v-if="isReviewable(application.status)"
                    class="icon-btn danger"
                    type="button"
                    title="Từ chối"
                    @click="openReject(application)"
                  >
                    <AppIcon name="x" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.last_page > 1" class="pagination">
        <button class="icon-btn" type="button" title="Trang trước" aria-label="Trang trước" :disabled="pagination.current_page <= 1" @click="loadApplications(pagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="17" />
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="icon-btn" type="button" title="Trang sau" aria-label="Trang sau" :disabled="pagination.current_page >= pagination.last_page" @click="loadApplications(pagination.current_page + 1)">
          <AppIcon name="chevronRight" size="17" />
        </button>
      </div>
    </div>

    <div v-if="approveModal.open" class="modal-backdrop" @click.self="closeApprove">
      <div class="modal">
        <div class="modal-header">
          <h3>Duyệt đơn đăng kí</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeApprove">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <form class="modal-body" @submit.prevent="submitApprove">
          <div v-if="activeApplication?.courts?.length" class="inline-summary">
            <span>{{ activeApplication.courts.length }} sân con sẽ được tạo theo hồ sơ</span>
          </div>

          <div v-else class="form-grid">
            <label class="field">
              <span>Tên sân con ban đầu</span>
              <input v-model.trim="approveForm.initial_court_name" type="text" maxlength="100" required />
            </label>
            <label class="field">
              <span>Loại sân</span>
              <select v-model="approveForm.court_type_id" required>
                <option value="">Chọn loại sân</option>
                <option v-for="courtType in courtTypes" :key="courtType.id" :value="courtType.id">
                  {{ courtType.name }}
                </option>
              </select>
            </label>
          </div>

          <div class="form-grid">
            <label class="field">
              <span>Tên tài khoản</span>
              <input v-model.trim="approveForm.bank_account_name" type="text" maxlength="150" disabled />
            </label>
            <label class="field">
              <span>Số tài khoản</span>
              <input v-model.trim="approveForm.bank_account_number" type="text" maxlength="50" disabled />
            </label>
            <label class="field">
              <span>Ngân hàng</span>
              <input v-model.trim="approveForm.bank_name" type="text" maxlength="100" disabled />
            </label>
            <label class="field">
              <span>Mã ngân hàng</span>
              <input v-model.trim="approveForm.bank_code" type="text" maxlength="50" disabled />
            </label>
          </div>

          <label class="field full">
            <span>Ghi chú</span>
            <textarea v-model.trim="approveForm.review_note" rows="4" maxlength="2000"></textarea>
          </label>

          <div class="modal-footer inner">
            <button class="btn ghost" type="button" @click="closeApprove">Hủy</button>
            <button class="btn primary" type="submit" :disabled="savingAction">
              <AppIcon name="check" size="16" />
              <span>{{ savingAction ? 'Đang duyệt...' : 'Duyệt đơn' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="rejectModal.open" class="modal-backdrop" @click.self="closeReject">
      <div class="modal small">
        <div class="modal-header">
          <h3>Từ chối đơn đăng kí</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeReject">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <form class="modal-body" @submit.prevent="submitReject">
          <label class="field full">
            <span>Lý do từ chối</span>
            <textarea v-model.trim="rejectForm.reason" rows="6" maxlength="2000" required></textarea>
          </label>

          <div class="modal-footer inner">
            <button class="btn ghost" type="button" @click="closeReject">Hủy</button>
            <button class="btn danger" type="submit" :disabled="savingAction">
              <AppIcon name="x" size="16" />
              <span>{{ savingAction ? 'Đang xử lý...' : 'Từ chối' }}</span>
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
  name: 'AdminPartnerApplications',
  components: { AppIcon },
  data() {
    return {
      applications: [],
      courtTypes: [],
      activeApplication: null,
      loading: true,
      detailLoading: false,
      savingAction: false,
      signingAction: false,
      error: '',
      message: '',
      filterTimer: null,
      activeTab: 'danh-sach',
      filters: {
        search: '',
        status: '',
        date_from: '',
        date_to: '',
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      approveModal: { open: false },
      rejectModal: { open: false },
      approveForm: this.emptyApproveForm(),
      rejectForm: { reason: '' },
      statusOptions: [
        { value: 'pending', label: 'Chờ duyệt' },
        { value: 'reviewing', label: 'Đang xem xét' },
        { value: 'approved', label: 'Đã duyệt' },
        { value: 'rejected', label: 'Từ chối' },
        { value: 'cancelled', label: 'Đã hủy' },
      ],
    };
  },
  mounted() {
    this.loadApplications();
    this.loadCourtTypes();
  },
  methods: {
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
    emptyApproveForm() {
      return {
        initial_court_name: '',
        court_type_id: '',
        bank_account_name: '',
        bank_account_number: '',
        bank_name: '',
        bank_code: '',
        review_note: '',
      };
    },
    async loadApplications(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPartnerApplicationService.list({
          ...this.filters,
          page,
        });
        const paginator = response.data || {};
        this.applications = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.applications.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được đơn đăng kí.';
      } finally {
        this.loading = false;
      }
    },
    async loadCourtTypes() {
      try {
        const response = await adminPartnerApplicationService.courtTypes();
        this.courtTypes = response.data || [];
      } catch {
        this.courtTypes = [];
      }
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadApplications(1), 300);
    },
    refresh() {
      this.loadApplications(this.pagination.current_page);
    },
    setTab(tab) {
      this.activeTab = tab;
      if (tab === 'danh-sach') {
        this.filters.status = ''; // Empty means all
      } else if (tab === 'cho-duyet') {
        // Bao gồm hồ sơ mới gửi, đang xem xét, và đang chờ ký hợp đồng (các loại)
        this.filters.status = 'pending,reviewing,approved_pending_contract,contract_pending_owner_signature,contract_pending_sportgo_signature';
      } else if (tab === 'da-tu-choi') {
        this.filters.status = 'rejected,cancelled';
      }
      this.loadApplications(1);
    },
    async fetchApplication(application) {
      this.detailLoading = true;
      try {
        const response = await adminPartnerApplicationService.show(application.id);
        this.activeApplication = response.data;
        return response.data;
      } catch (err) {
        this.error = err.message || 'Không tải được chi tiết đơn.';
        return null;
      } finally {
        this.detailLoading = false;
      }
    },
    openDetail(application) {
      this.$router.push(`/admin/partners/${application.id}`);
    },
    async openApprove(application) {
      this.clearAlerts();
      const detail = application.courts || application.bank_accounts
        ? application
        : await this.fetchApplication(application);
      if (!detail) return;

      this.activeApplication = detail;
      this.approveForm = this.emptyApproveForm();
      const bankAccount = detail.bank_accounts?.find((account) => account.is_default) || detail.bank_accounts?.[0];
      if (bankAccount) {
        this.approveForm.bank_account_name = bankAccount.account_holder_name || '';
        this.approveForm.bank_account_number = bankAccount.account_number || '';
        this.approveForm.bank_name = bankAccount.bank_name || '';
        this.approveForm.bank_code = bankAccount.bank_code || '';
      }
      this.approveModal.open = true;
    },
    closeApprove() {
      this.approveModal.open = false;
    },
    async openReject(application) {
      this.clearAlerts();
      const detail = application.status_reason !== undefined
        ? application
        : await this.fetchApplication(application);
      if (!detail) return;

      this.activeApplication = detail;
      this.rejectForm.reason = '';
      this.rejectModal.open = true;
    },
    closeReject() {
      this.rejectModal.open = false;
    },
    async submitApprove() {
      if (!this.activeApplication) return;

      this.savingAction = true;
      this.clearAlerts();
      try {
        const response = await adminPartnerApplicationService.approve(this.activeApplication.id, this.approveForm);
        this.message = response.message || 'Duyệt đơn thành công.';
        this.activeApplication = response.data || this.activeApplication;
        this.closeApprove();
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không duyệt được đơn.';
      } finally {
        this.savingAction = false;
      }
    },
    async submitReject() {
      if (!this.activeApplication) return;

      this.savingAction = true;
      this.clearAlerts();
      try {
        const response = await adminPartnerApplicationService.reject(this.activeApplication.id, this.rejectForm);
        this.message = response.message || 'Từ chối đơn thành công.';
        this.activeApplication = response.data || this.activeApplication;
        this.closeReject();
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra khi từ chối đơn.';
      } finally {
        this.savingAction = false;
      }
    },
    async approveSignature(contractId) {
      if (!confirm('Xác nhận ký phê duyệt và cấp quyền Chủ sân cho đối tác này?')) return;
      
      this.clearAlerts();
      this.signingAction = true;
      try {
        await api(`/api/admin/contracts/${contractId}/approve-signature`, { method: 'POST' });
        this.message = 'Phê duyệt hợp đồng thành công!';
        await this.fetchApplication(this.activeApplication);
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra khi phê duyệt hợp đồng.';
      } finally {
        this.signingAction = false;
      }
    },
    hasPendingTermination(contract) {
      if (!contract.terminations) return false;
      return contract.terminations.some(t => t.status === 'submitted');
    },
    async approveTermination(contractId) {
      if (!confirm('Xác nhận đồng ý thanh lý hợp đồng này theo yêu cầu của đối tác?')) return;
      
      this.clearAlerts();
      this.signingAction = true;
      try {
        await api(`/api/admin/contracts/${contractId}/approve-termination`, { method: 'POST' });
        this.message = 'Đã duyệt yêu cầu thanh lý thành công!';
        await this.fetchApplication(this.activeApplication);
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra khi duyệt thanh lý.';
      } finally {
        this.signingAction = false;
      }
    },
    async unilateralTerminate(contractId) {
      const reason = prompt('Vui lòng nhập lý do chấm dứt hợp đồng (Ví dụ: Vi phạm quy định chống bán phá giá):');
      if (!reason) return;

      this.clearAlerts();
      this.signingAction = true;
      try {
        await api(`/api/admin/contracts/${contractId}/terminate`, { 
          method: 'POST',
          body: JSON.stringify({ reason: reason, type: 'unilateral_by_admin' }),
          headers: { 'Content-Type': 'application/json' }
        });
        this.message = 'Đã đơn phương chấm dứt hợp đồng thành công!';
        await this.fetchApplication(this.activeApplication);
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra khi đơn phương chấm dứt hợp đồng.';
      } finally {
        this.signingAction = false;
      }
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    isReviewable(status) {
      return ['pending', 'reviewing'].includes(status);
    },
    statusLabel(status) {
      const labels = {
        pending: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        approved: 'Hoàn tất',
        approved_pending_contract: 'Chờ tạo HĐ',
        contract_pending_owner_signature: 'Chờ ĐT ký HĐ',
        contract_pending_sportgo_signature: 'Chờ SportGo ký HĐ',
        active: 'Đang hoạt động',
        completed: 'Hoàn tất',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      };
      return labels[status] || status || '-';
    },
    contractStatusLabel(status) {
      const labels = {
        generated: 'Nháp',
        pending_owner_signature: 'Chờ đối tác ký',
        pending_sportgo_signature: 'Chờ Admin duyệt (Ký SportGo)',
        signed_active: 'Đang hiệu lực',
        terminated: 'Đã thanh lý',
      };
      return labels[status] || status;
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
    },
  },
};
</script>

<style scoped>
.partner-app-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
}

.card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
}

.toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
}

.filters {
  width: 100%;
  display: grid;
  grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(150px, 1fr));
  gap: 12px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
  color: var(--admin-text);
}

.field.full {
  grid-column: 1 / -1;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  font-weight: 500;
  background: var(--admin-surface);
  color: var(--admin-text);
}

.field input:disabled,
.field select:disabled,
.field textarea:disabled {
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  cursor: not-allowed;
}

.field input,
.field select {
  height: 40px;
}

.field textarea {
  min-height: 110px;
  padding-top: 10px;
  resize: vertical;
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.18s, border-color 0.18s, color 0.18s;
}

.btn {
  height: 40px;
  padding: 0 14px;
  white-space: nowrap;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.ghost {
  background: var(--admin-surface);
  border-color: var(--sg-border);
  color: var(--admin-text);
}

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: var(--admin-surface-muted);
  border-color: var(--admin-border);
  color: var(--admin-text);
}

.icon-btn.approve {
  color: #15803d;
}

.icon-btn.danger {
  color: #dc2626;
}

.notice {
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 800;
}

.notice.success {
  background: #dcfce7;
  color: #166534;
}

.notice.error {
  background: #fee2e2;
  color: #991b1b;
}

.state-box,
.modal-state {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: rgba(15, 23, 42, 0.55);
}

.spinner {
  width: 34px;
  height: 34px;
  border: 3px solid rgba(15, 23, 42, 0.08);
  border-top-color: var(--admin-text);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.applications-table {
  overflow: hidden;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 980px;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
  vertical-align: middle;
}

th {
  background: var(--admin-surface-muted);
  font-size: 12px;
  font-weight: 900;
  color: var(--admin-faint);
  text-transform: uppercase;
}

.center {
  text-align: center;
}

.right {
  text-align: right;
}

.main-title {
  color: var(--admin-text);
  font-weight: 800;
}

.muted {
  color: rgba(15, 23, 42, 0.5);
  font-size: 13px;
}

.status {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
}

.status-pending,
.status-reviewing,
.status-approved_pending_contract,
.status-contract_pending_owner_signature,
.status-contract_pending_sportgo_signature {
  background: #fef3c7;
  color: #92400e;
}

.status-approved,
.status-active,
.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-rejected,
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.actions {
  display: inline-flex;
  gap: 8px;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 800;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.5);
}

.modal {
  width: min(760px, 100%);
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--admin-surface);
  border-radius: 8px;
  text-overflow: ellipsis;
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  margin-left: 8px;
  vertical-align: middle;
}

.cluster-badge {
  background: #e0e7ff;
  color: #3730a3;
}

.partner-badge {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
}

.amenities-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.amenity-tag {
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.modal.large {
  width: min(980px, 100%);
}

.modal.small {
  width: min(560px, 100%);
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--sg-border);
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--sg-border);
  border-bottom: 0;
}

.modal-footer.inner {
  margin: 4px -18px -18px;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
}

.modal-body {
  padding: 18px;
  overflow-y: auto;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.detail-section {
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 14px;
  min-width: 0;
}

.detail-section.full {
  grid-column: 1 / -1;
}

.detail-section h4 {
  margin: 0 0 12px;
  font-size: 14px;
  font-weight: 900;
  color: var(--admin-text);
}

dl {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 8px 12px;
  margin: 0;
}

dt {
  color: rgba(15, 23, 42, 0.5);
  font-size: 13px;
  font-weight: 800;
}

dd {
  min-width: 0;
  margin: 0;
  color: var(--admin-text);
  font-size: 14px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.mini-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mini-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--admin-surface-muted);
}

.mini-item.stacked {
  align-items: flex-start;
  flex-direction: column;
  gap: 4px;
}

.mini-item span {
  font-weight: 800;
}

.mini-item strong {
  color: rgba(15, 23, 42, 0.6);
  font-size: 13px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}

.inline-summary {
  padding: 12px;
  margin-bottom: 14px;
  border-radius: 8px;
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  font-weight: 800;
}

@media (max-width: 900px) {
  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .filters,
  .detail-grid,
  .form-grid {
    grid-template-columns: 1fr;
  }

  .detail-section.full {
    grid-column: auto;
  }

  dl {
    grid-template-columns: 1fr;
  }
}
</style>
