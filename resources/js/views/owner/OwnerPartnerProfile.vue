<template>
  <div class="owner-profile-page">
    <header class="page-header">
      <div>
        <h2>Hồ sơ đối tác của tôi</h2>
        <p class="muted">Theo dõi hồ sơ đăng ký, hợp đồng, yêu cầu chấm dứt và quyết toán.</p>
      </div>
      <button class="icon-btn" type="button" title="Làm mới" @click="fetchData"><AppIcon name="refresh" size="16" /></button>
    </header>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải hồ sơ...</p>
    </div>

    <div v-else-if="error" class="notice error">{{ error }}</div>

    <div v-else-if="applications.length === 0" class="state-box card">
      <p>Bạn chưa có hồ sơ đăng ký đối tác nào.</p>
    </div>

    <template v-else>
      <div v-if="applications.length > 1" class="card selector">
        <label class="field">
          <span>Cụm sân</span>
          <select v-model="activeApplicationId">
            <option v-for="application in applications" :key="application.id" :value="application.id">
              {{ application.venue_name }} - {{ statusLabel(application.status) }}
            </option>
          </select>
        </label>
      </div>

      <div class="card summary">
        <div>
          <h3>{{ activeApplication.venue_name }}</h3>
          <p class="muted">{{ activeApplication.business_name }}</p>
        </div>
        <span class="status" :class="`status-${activeApplication.status}`">{{ statusLabel(activeApplication.status) }}</span>
      </div>

      <div class="tabs">
        <button v-for="tab in tabs" :key="tab.value" class="tab-btn" :class="{ active: activeTab === tab.value }" type="button" @click="activeTab = tab.value">
          {{ tab.label }}
        </button>
      </div>

      <section v-if="activeTab === 'application'" class="card section-card">
        <h3>Hồ sơ đăng ký</h3>
        <div class="info-grid">
          <div class="info-item"><span class="label">Tên cụm sân</span><span>{{ activeApplication.venue_name }}</span></div>
          <div class="info-item"><span class="label">Ngày nộp</span><span>{{ formatDate(activeApplication.submitted_at) }}</span></div>
          <div class="info-item full"><span class="label">Địa chỉ</span><span>{{ activeApplication.venue_address }}</span></div>
          <div v-if="activeApplication.status === 'rejected'" class="rejection full">
            <strong>Lý do từ chối:</strong> {{ activeApplication.status_reason || 'Chưa có lý do.' }}
          </div>
        </div>
        <div class="timeline">
          <div v-for="item in activeApplication.status_histories || []" :key="`${item.new_status}-${item.created_at}`" class="timeline-item">
            <span class="dot"></span>
            <div>
              <strong>{{ statusLabel(item.new_status) }}</strong>
              <p>{{ formatDate(item.created_at) }} · {{ item.reason || '-' }}</p>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'documents'" class="card section-card">
        <h3>Hợp đồng & văn bản</h3>
        <div class="doc-list">
          <div v-for="document in activeDocuments" :key="document.id" class="doc-row">
            <div>
              <strong>{{ document.title || documentTypeLabel(document.document_type) }}</strong>
              <p class="muted">{{ document.document_code }} · {{ documentStatusLabel(document.status) }}</p>
              <p class="muted">{{ signatureSummary(document.signatures) }}</p>
            </div>
            <div style="display: flex; gap: 8px;">
              <button class="btn ghost small" type="button" @click="viewDocument(document)">
                <AppIcon name="eye" size="15" /> Xem
              </button>
              <button class="btn ghost small" type="button" @click="downloadDocument(document.id)">
                <AppIcon name="download" size="15" /> Tải xuống
              </button>
            </div>
          </div>
          <p v-if="activeDocuments.length === 0" class="muted">Chưa có văn bản nào.</p>
        </div>
        <button v-if="pendingOwnerContract" class="btn primary" type="button" @click="openSignContract">
          <AppIcon name="edit2" size="16" /> Ký điện tử hợp đồng
        </button>
      </section>

      <section v-if="activeTab === 'termination'" class="card section-card">
        <h3>Yêu cầu chấm dứt</h3>
        <button v-if="activeContract && !pendingTermination" class="btn danger" type="button" @click="openTermination">
          Gửi yêu cầu chấm dứt hợp tác
        </button>
        <div v-if="pendingTermination" class="notice warning">
          Yêu cầu chấm dứt đang được xử lý: {{ pendingTermination.reason }}
        </div>
        <div class="doc-list">
          <div v-for="request in activeApplication.termination_requests || []" :key="request.id" class="doc-row">
            <div>
              <strong>{{ request.termination_code }}</strong>
              <p class="muted">{{ terminationStatusLabel(request.status) }} · {{ request.reason }}</p>
              <p class="muted">Thu hồi quyền: {{ formatDate(request.transition_end_at) }}</p>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'settlement'" class="card section-card">
        <h3>Quyết toán</h3>
        <div v-for="request in settledRequests" :key="request.id" class="settlement-box">
          <div class="info-grid">
            <div class="info-item"><span class="label">Mã yêu cầu</span><span>{{ request.termination_code }}</span></div>
            <div class="info-item"><span class="label">Ngày thu hồi quyền</span><span>{{ formatDate(request.transition_end_at) }}</span></div>
            <div class="info-item"><span class="label">Hoàn phí nền tảng</span><span>{{ money(request.settlement?.platform_fee_remaining_refund_amount) }}</span></div>
            <div class="info-item"><span class="label">Trạng thái</span><span>{{ terminationStatusLabel(request.status) }}</span></div>
          </div>
        </div>
        <p v-if="settledRequests.length === 0" class="muted">Chưa có quyết toán.</p>
      </section>
    </template>

    <div v-if="signModal.open" class="modal-backdrop" @click.self="closeSignContract">
      <form class="modal" @submit.prevent="submitSignContract">
        <div class="modal-header">
          <h3>Ký hợp đồng hợp tác</h3>
          <button class="icon-btn" type="button" @click="closeSignContract"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <div class="contract-preview">
            {{ pendingOwnerContract?.contract_title || 'Hợp đồng hợp tác đối tác SportGo' }}
          </div>
          <canvas ref="signatureCanvas" class="signature-pad" width="620" height="190" @pointerdown="startDraw" @pointermove="draw" @pointerup="stopDraw" @pointerleave="stopDraw"></canvas>
          <button class="btn ghost small" type="button" @click="clearSignature">Xóa chữ ký</button>
          <label class="check-line">
            <input v-model="signModal.accepted" type="checkbox" />
            <span>Tôi đã đọc và đồng ý với toàn bộ nội dung hợp đồng</span>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeSignContract">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving || !signModal.accepted">Xác nhận ký</button>
        </div>
      </form>
    </div>

    <div v-if="terminationModal.open" class="modal-backdrop" @click.self="closeTermination">
      <form class="modal small" @submit.prevent="submitTermination">
        <div class="modal-header">
          <h3>Gửi yêu cầu chấm dứt</h3>
          <button class="icon-btn" type="button" @click="closeTermination"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <label class="field">
            <span>Lý do chấm dứt</span>
            <textarea v-model.trim="terminationForm.reason" rows="5" required></textarea>
          </label>
          <canvas ref="terminationCanvas" class="signature-pad" width="620" height="190" @pointerdown="startDraw" @pointermove="draw" @pointerup="stopDraw" @pointerleave="stopDraw"></canvas>
          <button class="btn ghost small" type="button" @click="clearSignature">Xóa chữ ký</button>
        </div>
        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeTermination">Hủy</button>
          <button class="btn danger" type="submit" :disabled="saving">Gửi yêu cầu</button>
        </div>
      </form>
    </div>

    <DocumentViewerModal
      :show="viewerModal.open"
      :document="viewerModal.document"
      @close="closeViewerModal"
    />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import { api, apiDownload } from '../../services/api.js';

export default {
  name: 'OwnerPartnerProfile',
  components: { AppIcon, DocumentViewerModal },
  data() {
    return {
      applications: [],
      documents: [],
      activeApplicationId: '',
      activeTab: 'application',
      loading: true,
      saving: false,
      drawing: false,
      error: '',
      tabs: [
        { value: 'application', label: 'Hồ sơ đăng ký' },
        { value: 'documents', label: 'Hợp đồng & văn bản' },
        { value: 'termination', label: 'Yêu cầu chấm dứt' },
        { value: 'settlement', label: 'Quyết toán' },
      ],
      signModal: { open: false, accepted: false },
      terminationModal: { open: false },
      terminationForm: { reason: '' },
      viewerModal: { open: false, document: null },
    };
  },
  computed: {
    activeApplication() {
      return this.applications.find((item) => item.id === this.activeApplicationId) || this.applications[0] || {};
    },
    activeDocuments() {
      return this.documents.filter((document) => document.partner_application_id === this.activeApplication.id);
    },
    pendingOwnerContract() {
      return this.activeApplication.contracts?.find((contract) => contract.status === 'pending_owner_signature') || null;
    },
    activeContract() {
      return this.activeApplication.contracts?.find((contract) => contract.status === 'signed_active') || null;
    },
    pendingTermination() {
      return this.activeApplication.termination_requests?.find((request) => ['submitted', 'reviewing', 'transition_period'].includes(request.status)) || null;
    },
    settledRequests() {
      return (this.activeApplication.termination_requests || []).filter((request) => request.settlement);
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      this.error = '';
      try {
        const [applicationsResponse, documentsResponse] = await Promise.all([
          api('/api/owner/partner-applications'),
          api('/api/owner/my-partner-profile/documents'),
        ]);
        this.applications = applicationsResponse.data || [];
        this.documents = documentsResponse.data || [];
        this.activeApplicationId = this.activeApplicationId || this.applications[0]?.id || '';
      } catch (err) {
        this.error = err.message || 'Không thể tải hồ sơ đối tác.';
      } finally {
        this.loading = false;
      }
    },
    viewDocument(doc) {
      this.viewerModal = {
        open: true,
        document: {
          title: doc.title || this.documentTypeLabel(doc.document_type),
          file_type: doc.file_extension || 'docx',
          download_url: `/api/files/documents/${doc.id}/download`,
          signatures: doc.signatures || []
        }
      };
    },
    closeViewerModal() {
      this.viewerModal.open = false;
    },
    openSignContract() {
      this.signModal = { open: true, accepted: false };
      this.$nextTick(() => this.prepareCanvas(this.$refs.signatureCanvas));
    },
    closeSignContract() {
      this.signModal.open = false;
    },
    async submitSignContract() {
      this.saving = true;
      try {
        await api('/api/user/partner-application/sign-contract', {
          method: 'POST',
          body: JSON.stringify({
            contract_id: this.pendingOwnerContract.id,
            signature_image: this.signatureData(this.$refs.signatureCanvas),
          }),
        });
        this.closeSignContract();
        await this.fetchData();
      } catch (err) {
        this.error = err.message || 'Không ký được hợp đồng.';
      } finally {
        this.saving = false;
      }
    },
    openTermination() {
      this.terminationForm.reason = '';
      this.terminationModal.open = true;
      this.$nextTick(() => this.prepareCanvas(this.$refs.terminationCanvas));
    },
    closeTermination() {
      this.terminationModal.open = false;
    },
    async submitTermination() {
      this.saving = true;
      try {
        await api(`/api/owner/contracts/${this.activeContract.id}/request-termination`, {
          method: 'POST',
          body: JSON.stringify({
            reason: this.terminationForm.reason,
            signature_image: this.signatureData(this.$refs.terminationCanvas),
          }),
        });
        this.closeTermination();
        await this.fetchData();
      } catch (err) {
        this.error = err.message || 'Không gửi được yêu cầu chấm dứt.';
      } finally {
        this.saving = false;
      }
    },
    async downloadDocument(id) {
      try {
        await apiDownload(`/api/files/documents/${id}/download`);
      } catch (err) {
        this.error = err.message || 'Không tải được văn bản.';
      }
    },
    prepareCanvas(canvas) {
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.strokeStyle = '#0f172a';
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
    },
    pointerPosition(event) {
      const canvas = event.currentTarget;
      const rect = canvas.getBoundingClientRect();
      return {
        canvas,
        x: ((event.clientX - rect.left) / rect.width) * canvas.width,
        y: ((event.clientY - rect.top) / rect.height) * canvas.height,
      };
    },
    startDraw(event) {
      this.drawing = true;
      const point = this.pointerPosition(event);
      const ctx = point.canvas.getContext('2d');
      ctx.beginPath();
      ctx.moveTo(point.x, point.y);
    },
    draw(event) {
      if (!this.drawing) return;
      const point = this.pointerPosition(event);
      const ctx = point.canvas.getContext('2d');
      ctx.lineTo(point.x, point.y);
      ctx.stroke();
    },
    stopDraw() {
      this.drawing = false;
    },
    clearSignature() {
      this.prepareCanvas(this.signModal.open ? this.$refs.signatureCanvas : this.$refs.terminationCanvas);
    },
    signatureData(canvas) {
      return canvas?.toDataURL('image/png') || null;
    },
    signatureSummary(signatures = []) {
      if (!signatures.length) return 'Chưa có chữ ký';
      return signatures.map((signature) => `${signature.signer_side}: ${this.formatDate(signature.signed_at)}`).join(' · ');
    },
    statusLabel(status) {
      return {
        pending: 'Chờ duyệt',
        submitted: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        need_supplement: 'Cần bổ sung',
        contract_pending_owner_signature: 'Chờ ký hợp đồng',
        contract_pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Đang hoạt động',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      }[status] || status || '-';
    },
    documentTypeLabel(type) {
      return {
        partner_application_form: 'Đơn đăng ký đối tác',
        partner_contract: 'Hợp đồng hợp tác',
        termination_request: 'Đơn yêu cầu chấm dứt',
        mutual_liquidation_minutes: 'Biên bản thanh lý',
        unilateral_termination_notice: 'Công văn chấm dứt',
        settlement_minutes: 'Biên bản quyết toán',
      }[type] || type;
    },
    documentStatusLabel(status) {
      return {
        generated: 'Đã sinh',
        pending_owner_signature: 'Chờ chủ sân ký',
        pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Hoàn thành',
      }[status] || status;
    },
    terminationStatusLabel(status) {
      return {
        submitted: 'Chờ xác nhận',
        reviewing: 'Đang xem xét',
        transition_period: 'Giai đoạn chuyển tiếp',
        completed: 'Đã thu hồi quyền',
      }[status] || status;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    },
  },
};
</script>

<style scoped>
.owner-profile-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1100px;
  margin: 0 auto;
  padding-bottom: 40px;
}

.card,
.modal {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
}

.summary,
.page-header,
.doc-row,
.modal-header,
.modal-footer,
.tabs {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.summary,
.selector,
.section-card {
  padding: 18px;
}

.summary h3,
.section-card h3,
.page-header h2 {
  margin: 0;
}

.muted {
  color: var(--admin-muted);
  font-size: 13px;
}

.state-box {
  min-height: 220px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 12px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.tabs {
  justify-content: flex-start;
  flex-wrap: wrap;
}

.tab-btn {
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-weight: 800;
  cursor: pointer;
}

.tab-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-weight: 800;
}

.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 10px 12px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-weight: 800;
}

.info-item.full,
.rejection.full {
  grid-column: 1 / -1;
}

.label {
  color: var(--admin-muted);
  font-size: 12px;
  text-transform: uppercase;
}

.rejection,
.notice.error {
  padding: 12px;
  border-radius: 8px;
  background: #fee2e2;
  color: #991b1b;
}

.notice.warning {
  margin: 12px 0;
  padding: 12px;
  border-radius: 8px;
  background: #fef3c7;
  color: #92400e;
  font-weight: 800;
}

.timeline {
  margin-top: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.timeline-item {
  display: grid;
  grid-template-columns: 16px 1fr;
  gap: 10px;
}

.dot {
  width: 10px;
  height: 10px;
  margin-top: 5px;
  border-radius: 50%;
  background: #0f172a;
}

.doc-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 12px 0;
}

.doc-row,
.settlement-box {
  padding: 12px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
}

.status {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  background: var(--admin-border);
  color: var(--admin-text);
}

.status-submitted,
.status-reviewing,
.status-contract_pending_owner_signature,
.status-contract_pending_sportgo_signature {
  background: #fef3c7;
  color: #92400e;
}

.status-completed {
  background: #dcfce7;
  color: #166534;
}

.status-rejected {
  background: #fee2e2;
  color: #991b1b;
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 900;
  cursor: pointer;
}

.btn {
  min-height: 40px;
  padding: 0 14px;
}

.btn.small {
  min-height: 34px;
  padding: 0 10px;
  font-size: 13px;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn.ghost,
.icon-btn {
  background: var(--admin-surface);
  border-color: var(--sg-border);
  color: var(--admin-text);
}

.icon-btn {
  width: 34px;
  height: 34px;
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
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal.small {
  width: min(560px, 100%);
}

.modal-header,
.modal-footer {
  padding: 14px 18px;
  border-bottom: 1px solid var(--admin-border);
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--admin-border);
  border-bottom: 0;
}

.modal-body {
  padding: 18px;
  overflow-y: auto;
}

.contract-preview {
  max-height: 160px;
  overflow: auto;
  padding: 12px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface-muted);
  margin-bottom: 12px;
  font-weight: 800;
}

.signature-pad {
  width: 100%;
  max-width: 620px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  touch-action: none;
  display: block;
  margin-bottom: 10px;
}

.check-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  font-weight: 800;
}

@media (max-width: 800px) {
  .summary,
  .doc-row,
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .info-item.full,
  .rejection.full {
    grid-column: auto;
  }
}
</style>
