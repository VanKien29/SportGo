<template>
  <div class="owner-profile-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">Đối tác SportGo</p>
        <h1>Hồ sơ đối tác</h1>
        <p class="muted">Theo dõi hồ sơ, văn bản cần ký và trạng thái hợp tác của từng cụm sân.</p>
      </div>
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
      <label v-if="applications.length > 1" class="card selector">
        <span>Hồ sơ đang xem</span>
        <select v-model="activeApplicationId">
          <option v-for="application in applications" :key="application.id" :value="application.id">
            {{ application.venue_name }} - {{ statusLabel(application.status) }}
          </option>
        </select>
      </label>

      <section class="card profile-overview" :class="{ archived: isArchivedApplication }">
        <div class="profile-main">
          <div class="profile-title-row">
            <div>
              <p class="eyebrow">Cụm sân</p>
              <h2>{{ activeApplication.venue_name }}</h2>
            </div>
            <span class="status" :class="`status-${activeApplication.status}`">{{ statusLabel(activeApplication.status) }}</span>
          </div>
          <p class="profile-address">{{ activeApplication.venue_address || activeApplication.business_name || '-' }}</p>
          <div class="profile-facts">
            <span><strong>{{ activeDocuments.length }}</strong> văn bản</span>
            <span><strong>{{ activeContract ? 'Có' : 'Chưa' }}</strong> hợp đồng hiệu lực</span>
            <span><strong>{{ settledRequests.length }}</strong> quyết toán</span>
          </div>
        </div>
        <div class="profile-actions">
          <button v-if="nextAction" class="btn primary" type="button" @click="runNextAction">
            <AppIcon :name="nextAction.icon" size="16" /> {{ nextAction.label }}
          </button>
          <button class="btn ghost" type="button" @click="activeTab = 'documents'">
            <AppIcon name="fileText" size="16" /> Văn bản
          </button>
        </div>
      </section>

      <section v-if="nextAction" class="next-task" aria-live="polite">
        <AppIcon name="checkCircle" size="20" />
        <div>
          <strong>{{ nextAction.title }}</strong>
          <p>{{ nextAction.hint }}</p>
        </div>
        <button class="btn primary small" type="button" @click="runNextAction">Xử lý ngay</button>
      </section>

      <nav class="tabs" aria-label="Nội dung hồ sơ đối tác">
        <button v-for="tab in tabs" :key="tab.value" class="tab-btn" :class="{ active: activeTab === tab.value }" type="button" @click="activeTab = tab.value">
          {{ tab.label }}
          <span v-if="tab.value === 'documents' && signableDocumentCount" class="tab-count">{{ signableDocumentCount }}</span>
        </button>
      </nav>

      <section v-if="activeTab === 'application'" class="card section-card">
        <div class="section-head">
          <div>
            <p class="eyebrow">Thông tin hồ sơ</p>
            <h3>{{ statusLabel(activeApplication.status) }}</h3>
          </div>
          <span class="submitted-at">Nộp {{ formatDate(activeApplication.submitted_at) }}</span>
        </div>
        <div class="info-grid compact">
          <div class="info-item"><span class="label">Tên cụm sân</span><span>{{ activeApplication.venue_name }}</span></div>
          <div class="info-item"><span class="label">Đơn vị vận hành</span><span>{{ activeApplication.business_name || '-' }}</span></div>
          <div class="info-item full"><span class="label">Địa chỉ</span><span>{{ activeApplication.venue_address || '-' }}</span></div>
          <div v-if="activeApplication.status === 'rejected'" class="rejection full">
            <strong>Lý do từ chối:</strong> {{ activeApplication.status_reason || 'Chưa có lý do.' }}
          </div>
        </div>
        <details v-if="(activeApplication.status_histories || []).length" class="history-details">
          <summary>Xem lịch sử xử lý ({{ activeApplication.status_histories.length }})</summary>
          <div class="timeline">
            <div v-for="item in activeApplication.status_histories" :key="`${item.new_status}-${item.created_at}`" class="timeline-item">
              <span class="dot"></span>
              <div><strong>{{ statusLabel(item.new_status) }}</strong><p>{{ formatDate(item.created_at) }} · {{ item.reason || 'Không có ghi chú' }}</p></div>
            </div>
          </div>
        </details>
      </section>

      <section v-if="activeTab === 'documents'" class="card section-card">
        <div class="section-head">
          <div><p class="eyebrow">Hồ sơ điện tử</p><h3>Hợp đồng & văn bản</h3></div>
          <span class="submitted-at">{{ activeDocuments.length }} văn bản</span>
        </div>
        <div class="doc-list">
          <article v-for="document in activeDocuments" :key="document.id" class="doc-row">
            <div class="doc-copy">
              <strong>{{ document.title || documentTypeLabel(document.document_type) }}</strong>
              <p>{{ document.document_code || 'Chưa cấp mã' }} · {{ documentStatusLabel(document.status) }}</p>
              <small>{{ signatureSummary(document.signatures) }}</small>
            </div>
            <div class="doc-actions">
              <button class="btn small" :class="canSignDocument(document) ? 'primary' : 'ghost'" type="button" @click="viewDocument(document)">
                <AppIcon :name="canSignDocument(document) ? 'pencil' : 'eye'" size="15" />
                {{ canSignDocument(document) ? 'Xem và ký' : 'Xem văn bản' }}
              </button>
              <button class="icon-btn" type="button" title="Tải văn bản" aria-label="Tải văn bản" @click="downloadDocument(document.id)">
                <AppIcon name="download" size="15" />
              </button>
            </div>
          </article>
          <p v-if="activeDocuments.length === 0" class="empty-copy">Chưa có văn bản nào trong hồ sơ này.</p>
        </div>
      </section>

      <section v-if="activeTab === 'termination'" class="card section-card">
        <div class="section-head">
          <div><p class="eyebrow">Chấm dứt hợp tác</p><h3>Hồ sơ & quyết toán</h3></div>
          <button v-if="activeContract && !pendingTermination && !isArchivedApplication" class="btn danger small" type="button" :disabled="!activeVenueClusterId" @click="openTerminationFlow">
            Tạo yêu cầu chấm dứt
          </button>
        </div>
        <div v-if="pendingTermination" class="notice warning">
          <strong>{{ terminationStatusLabel(pendingTermination.status) }}</strong>
          <span>{{ pendingTermination.reason || 'Hồ sơ đang được xử lý.' }}</span>
          <button class="btn ghost small" type="button" @click="openTerminationFlow">Mở hồ sơ</button>
        </div>
        <div class="doc-list">
          <article v-for="request in activeApplication.termination_requests || []" :key="request.id" class="doc-row">
            <div class="doc-copy">
              <strong>{{ request.termination_code || 'Hồ sơ chấm dứt' }}</strong>
              <p>{{ terminationStatusLabel(request.status) }} · {{ request.reason || 'Không có ghi chú' }}</p>
              <small>Thu hồi quyền: {{ formatDate(request.transition_end_at) }}</small>
            </div>
            <button v-if="pendingTermination?.id === request.id" class="btn ghost small" type="button" @click="openTerminationFlow">Chi tiết</button>
          </article>
          <p v-if="!(activeApplication.termination_requests || []).length" class="empty-copy">Chưa có yêu cầu chấm dứt.</p>
        </div>
        <details v-if="settledRequests.length" class="history-details settlement-details">
          <summary>Xem quyết toán đã lưu ({{ settledRequests.length }})</summary>
          <div v-for="request in settledRequests" :key="request.id" class="settlement-box">
            <div class="info-grid compact">
              <div class="info-item"><span class="label">Mã hồ sơ</span><span>{{ request.termination_code }}</span></div>
              <div class="info-item"><span class="label">Thu hồi quyền</span><span>{{ formatDate(request.transition_end_at) }}</span></div>
              <div class="info-item"><span class="label">Hoàn phí nền tảng</span><span>{{ money(request.settlement?.platform_fee_remaining_refund_amount) }}</span></div>
              <div class="info-item"><span class="label">Trạng thái</span><span>{{ terminationStatusLabel(request.status) }}</span></div>
            </div>
          </div>
        </details>
      </section>
    </template>

    <DocumentViewerModal :show="viewerModal.open" :document="viewerModal.document" @close="closeViewerModal" />
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
      error: '',
      tabs: [
        { value: 'application', label: 'Tổng quan' },
        { value: 'documents', label: 'Văn bản' },
        { value: 'termination', label: 'Chấm dứt & quyết toán' },
      ],
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
    signableDocumentCount() {
      return this.activeDocuments.filter((document) => this.canSignDocument(document)).length;
    },
    nextAction() {
      const document = this.activeDocuments.find((item) => this.canSignDocument(item));
      if (document) {
        return {
          type: 'document',
          document,
          icon: 'pencil',
          label: 'Xem và ký',
          title: document.title || this.documentTypeLabel(document.document_type),
          hint: 'Kiểm tra toàn bộ file trước khi ký. Chữ ký được thực hiện ngay bên cạnh bản xem trước.',
        };
      }
      if (this.pendingTermination) {
        return {
          type: 'termination',
          icon: 'arrowRight',
          label: 'Tiếp tục xử lý',
          title: this.terminationStatusLabel(this.pendingTermination.status),
          hint: 'Mở hồ sơ chấm dứt để xử lý booking, văn bản hoặc quyết toán đang chờ.',
        };
      }
      return null;
    },
    pendingOwnerContract() {
      return this.activeApplication.contracts?.find((contract) => contract.status === 'pending_owner_signature') || null;
    },
    activeContract() {
      return this.activeApplication.contracts?.find((contract) => contract.status === 'signed_active') || null;
    },
    activeVenueClusterId() {
      return this.activeApplication.approved_venue_cluster_id
        || this.activeApplication.approved_venue_cluster?.id
        || this.activeApplication.approvedVenueCluster?.id
        || this.activeContract?.venue_cluster_id
        || this.activeDocuments.find((document) => document.venue_cluster_id)?.venue_cluster_id
        || null;
    },
    pendingTermination() {
      const activeStatuses = [
        'draft',
        'draft_preview',
        'submitted',
        'reviewing',
        'approved',
        'pending_signature',
        'settlement_processing',
        'transition_period',
        'cancellation_in_progress',
        'future_bookings_processing',
        'waiting_final_settlement',
        'waiting_final_document_signature',
        'terminating',
      ];

      return this.activeApplication.termination_requests?.find((request) => activeStatuses.includes(request.status)) || null;
    },
    settledRequests() {
      return (this.activeApplication.termination_requests || []).filter((request) => request.settlement);
    },
    isArchivedApplication() {
      return !this.pendingTermination && (this.activeApplication.termination_requests || [])
        .some((request) => ['completed', 'terminated'].includes(request.status));
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

        const requestedDocument = this.documents.find((document) => String(document.id) === String(this.$route.query.document || ''));
        const requestedApplicationId = this.$route.query.application || requestedDocument?.partner_application_id;
        const selectedClusterId = localStorage.getItem('selected_cluster');
        const selectedByCluster = this.applications.find((application) => [
          application.approved_venue_cluster_id,
          application.approved_venue_cluster?.id,
          application.approvedVenueCluster?.id,
        ].some((id) => id && String(id) === String(selectedClusterId)));
        const currentApplication = this.applications.find((application) => String(application.id) === String(this.activeApplicationId));
        const requestedApplication = this.applications.find((application) => String(application.id) === String(requestedApplicationId));

        this.activeApplicationId = (requestedApplication || currentApplication || selectedByCluster || this.applications[0])?.id || '';

        if (requestedDocument) {
          this.activeTab = 'documents';
          this.$nextTick(() => this.viewDocument(requestedDocument));
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải hồ sơ đối tác.';
      } finally {
        this.loading = false;
      }
    },
    viewDocument(doc) {
      if (this.canSignDocument(doc)) {
        this.openOwnerDocument(doc);
        return;
      }

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
    canSignDocument(doc) {
      const signableTypes = [
        'partner_application_form',
        'partner_contract',
        'venue_scale_request',
        'venue_location_change_request',
        'venue_scale_appendix',
        'venue_location_appendix',
      ];

      return doc?.status === 'pending_owner_signature'
        && signableTypes.includes(doc.document_type)
        && !(doc.signatures || []).some((signature) => signature.signer_side === 'owner' && signature.status === 'signed');
    },
    openOwnerDocument(doc, from = 'owner-profile') {
      const applicationId = doc?.partner_application_id || this.activeApplication.id;
      if (!applicationId || !doc?.id) {
        this.error = 'Không tìm thấy hồ sơ liên kết với văn bản cần ký.';
        return;
      }

      this.$router.push({
        name: 'owner-partner-document',
        params: { id: applicationId, documentId: doc.id },
        query: { from },
      });
    },
    contractDocument(contract) {
      const generated = contract?.generated_document || contract?.generatedDocument;
      if (generated?.id) return generated;

      return this.activeDocuments.find((document) => (
        document.partner_contract_id === contract?.id
        || (document.document_type === 'partner_contract' && document.status === 'pending_owner_signature')
      )) || null;
    },
    closeViewerModal() {
      this.viewerModal.open = false;
    },
    runNextAction() {
      if (!this.nextAction) return;
      if (this.nextAction.type === 'document') {
        this.openOwnerDocument(this.nextAction.document);
        return;
      }
      this.openTerminationFlow();
    },
    openSignContract() {
      const document = this.contractDocument(this.pendingOwnerContract);
      if (!document) {
        this.error = 'Không tìm thấy file hợp đồng cần ký.';
        return;
      }

      this.openOwnerDocument(document);
    },
    closeSignContract() {
      this.signModal.open = false;
    },
    openTerminationFlow() {
      if (!this.activeVenueClusterId) {
        this.error = 'Không tìm thấy cụm sân đang hoạt động để tạo yêu cầu chấm dứt.';
        return;
      }

      this.$router.push({
        name: 'owner-partner-termination',
        params: { id: this.activeVenueClusterId },
      });
    },
    submitSignContract() {
      this.closeSignContract();
      this.openSignContract();
    },
    openTermination() {
      this.terminationForm.reason = '';
      this.terminationModal.open = true;
    },
    closeTermination() {
      this.terminationModal.open = false;
    },
    submitTermination() {
      this.closeTermination();
      this.openTerminationFlow();
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
      return signatures.map((signature) => `${this.signerSideLabel(signature.signer_side)}: ${this.formatDate(signature.signed_at)}`).join(' · ');
    },
    signerSideLabel(side) {
      return {
        owner: 'Chủ sân',
        sportgo: 'SportGo',
        admin: 'SportGo',
      }[side] || side || '-';
    },
    statusLabel(status) {
      return {
        pending: 'Chờ duyệt',
        submitted: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        need_supplement: 'Cần bổ sung',
        approved_pending_contract: 'Đã duyệt, chờ hợp đồng',
        contract_pending_owner_signature: 'Chờ ký hợp đồng',
        contract_pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Đang hoạt động',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
      }[status] || status || '-';
    },
    documentTypeLabel(type) {
      if (type === 'venue_scale_appendix') return 'Phụ lục thay đổi quy mô sân';
      if (type === 'venue_location_appendix') return 'Phụ lục thay đổi vị trí cụm sân';

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
        draft: 'Bản nháp',
        draft_preview: 'Đã tạo bản xem trước',
        submitted: 'Chờ xác nhận',
        reviewing: 'Đang xem xét',
        approved: 'Đã duyệt',
        pending_signature: 'Chờ ký',
        cancellation_in_progress: 'Đang xử lý hủy hợp tác',
        future_bookings_processing: 'Đang xử lý lịch đặt tương lai',
        waiting_final_settlement: 'Chờ quyết toán cuối',
        waiting_final_document_signature: 'Chờ ký biên bản cuối',
        terminating: 'Đang thu hồi quyền',
        transition_period: 'Giai đoạn chuyển tiếp',
        settlement_processing: 'Đang quyết toán',
        settlement_completed: 'Đã quyết toán',
        completed: 'Đã thu hồi quyền',
        rejected: 'Từ chối',
        cancelled: 'Đã hủy',
        terminated: 'Đã chấm dứt',
        owner_cancelled_request: 'Chủ sân đã hủy yêu cầu',
        admin_rejected: 'Admin từ chối',
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
  width: 100%;
  max-width: 1180px;
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
.profile-overview,
.section-head,
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
.profile-overview,
.selector,
.section-card,
.work-queue {
  padding: 18px;
}

.summary h3,
.profile-overview h3,
.work-queue h3,
.section-card h3,
.page-header h1,
.profile-overview h2 {
  margin: 0;
}

.page-header h1 {
  color: var(--admin-text);
  font-size: 28px;
  line-height: 1.2;
}

.profile-overview {
  align-items: stretch;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
}

.profile-main {
  min-width: 0;
}

.profile-main h3 {
  color: var(--admin-text);
  font-size: 22px;
}

.profile-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.profile-title-row h2 {
  color: var(--admin-text);
  font-size: 24px;
  line-height: 1.25;
}

.profile-address {
  margin: 10px 0 14px;
  color: var(--admin-muted);
  line-height: 1.5;
}

.profile-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  color: var(--admin-muted);
  font-size: 13px;
}

.profile-facts strong {
  color: var(--admin-text);
}

.profile-overview.archived {
  background: #f3f5f3;
  filter: grayscale(0.35);
}

.eyebrow {
  margin: 0 0 4px;
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0;
  text-transform: uppercase;
}

.profile-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}

.selector {
  display: grid;
  grid-template-columns: auto minmax(240px, 420px);
  align-items: center;
  justify-content: start;
  gap: 14px;
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 800;
}

.selector select {
  min-height: 40px;
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface);
  color: var(--admin-text);
  padding: 0 12px;
}

.next-task {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  border-left: 4px solid #16844a;
  background: #edf8f0;
  color: #14532d;
  padding: 14px 16px;
}

.next-task p {
  margin: 3px 0 0;
  color: #3f6750;
  font-size: 13px;
}

.profile-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.metric-card {
  display: grid;
  gap: 4px;
  min-height: 104px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface);
  padding: 14px;
}

.metric-card span {
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.metric-card strong {
  color: var(--admin-text);
  font-size: 26px;
  line-height: 1;
}

.metric-card small,
.work-item small {
  color: var(--admin-muted);
  font-size: 12px;
}

.work-queue {
  display: grid;
  gap: 10px;
}

.section-head {
  padding: 0;
}

.work-item {
  display: grid;
  gap: 4px;
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface);
  color: var(--admin-text);
  cursor: pointer;
  padding: 12px;
  text-align: left;
}

.work-item:hover {
  border-color: #0f172a;
}

.work-item span {
  font-weight: 900;
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
  background: #16844a;
  border-color: #16844a;
  color: #fff;
}

.tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  margin-left: 6px;
  border-radius: 50%;
  background: #dc2626;
  color: #fff;
  font-size: 11px;
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

.info-grid.compact {
  margin-top: 18px;
  gap: 16px 24px;
}

.submitted-at {
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 700;
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

.history-details {
  margin-top: 18px;
  border-top: 1px solid var(--admin-border);
  padding-top: 14px;
}

.history-details summary {
  color: var(--admin-text);
  cursor: pointer;
  font-size: 13px;
  font-weight: 800;
}

.settlement-details .settlement-box {
  margin-top: 12px;
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

.doc-copy {
  min-width: 0;
}

.doc-copy strong,
.doc-copy p,
.doc-copy small {
  overflow-wrap: anywhere;
}

.doc-copy p {
  margin: 4px 0;
  color: var(--admin-muted);
  font-size: 13px;
}

.doc-copy small {
  color: var(--admin-muted);
}

.doc-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.empty-copy {
  margin: 16px 0 4px;
  color: var(--admin-muted);
  text-align: center;
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
  background: #16844a;
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

.termination-note {
  border: 1px solid #fde68a;
  border-radius: 8px;
  background: #fffbeb;
  color: #92400e;
  padding: 12px;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.5;
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
  .profile-overview,
  .doc-row,
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .profile-overview,
  .profile-metrics {
    grid-template-columns: 1fr;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .info-item.full,
  .rejection.full {
    grid-column: auto;
  }

  .selector,
  .next-task {
    grid-template-columns: 1fr;
  }

  .profile-title-row,
  .doc-actions {
    width: 100%;
  }

  .doc-actions .btn {
    flex: 1;
  }

  .tabs {
    flex-wrap: nowrap;
    overflow-x: auto;
    padding-bottom: 4px;
  }

  .tab-btn {
    flex: 0 0 auto;
  }
}
</style>
