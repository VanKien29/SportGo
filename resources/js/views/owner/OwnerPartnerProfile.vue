<template>
  <div class="owner-partner-profile-container">

    <!-- Loading State -->
    <div v-if="loading" class="state-box">
      <div class="spinner"></div>
      <p>Đang tải dữ liệu hồ sơ đối tác...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="state-box error">
      <AppIcon name="alert" size="24" class="error-icon" />
      <div class="state-copy">
        <h3>Không thể tải hồ sơ</h3>
        <p>{{ error }}</p>
      </div>
      <button class="btn btn-outline btn-sm" type="button" @click="fetchData">Thử lại</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="applications.length === 0" class="state-box empty">
      <AppIcon name="layers" size="32" class="empty-icon" />
      <h3>Chưa có hồ sơ đối tác</h3>
      <p>Bạn chưa thực hiện nộp hồ sơ đối tác nào trên hệ thống SportGo.</p>
    </div>

    <!-- MAIN PROFILE WORKSPACE -->
    <div v-else class="owner-partner-profile-workspace">
      <!-- Unified Hero Integrated Navtabs (Đồng bộ 1:1 chuẩn toàn hệ thống) -->
      <div class="hero-integrated-tabs">
        <AppTabs :tabs="tabs" :model-value="activeTab" @update:model-value="activeTab = $event" />
      </div>

      <!-- Content Surface (Seamless White Block connected below Tabs) -->
      <div class="profile-content-surface">
        
        <!-- Urgent Task Action Notice (if signature or termination is pending) -->
        <div v-if="nextAction" class="urgent-action-notice">
          <AppIcon name="alertCircle" size="20" class="notice-icon" />
          <div class="notice-body">
            <strong>{{ nextAction.title }}</strong>
            <p>{{ nextAction.hint }}</p>
          </div>
          <button class="btn btn-accent btn-sm" type="button" @click="runNextAction">
            <span>Xử lý ngay</span>
            <AppIcon name="arrowRight" size="14" />
          </button>
        </div>

        <!-- TAB 1: TỔNG QUAN HỒ SƠ & LỊCH SỬ -->
        <div v-if="activeTab === 'application'" class="tab-pane-flow">
          
          <!-- Rejection Notice Banner -->
          <div v-if="activeApplication.status === 'rejected'" class="rejection-banner">
            <AppIcon name="alert" size="20" class="alert-icon" />
            <div>
              <strong>Hồ sơ đã bị từ chối xét duyệt</strong>
              <p>Lý do: {{ activeApplication.status_reason || 'Không có ghi chú thêm từ Admin.' }}</p>
            </div>
          </div>

          <!-- Section 1: Thông tin đăng ký đối tác (Standard Form Grid layout) -->
          <div class="profile-section-card">
            <div class="tab-section-header">
              <div>
                <h2>Thông tin đăng ký đối tác</h2>
                <p class="section-subtitle">Các chi tiết đăng ký đã gửi hệ thống kiểm duyệt (Nộp lúc: {{ formatDate(activeApplication.submitted_at) }})</p>
              </div>
            </div>

            <div class="meta-info-grid">
              <div class="meta-info-item">
                <span class="meta-info-label">Tên cụm sân đăng ký</span>
                <span class="meta-info-value highlight">{{ activeApplication.venue_name }}</span>
              </div>

              <div class="meta-info-item">
                <span class="meta-info-label">Số điện thoại liên hệ</span>
                <span class="meta-info-value">{{ activeApplication.phone_contact || activeApplication.contact_phone || 'Chưa cung cấp' }}</span>
              </div>

              <div class="meta-info-item">
                <span class="meta-info-label">Đơn vị / Cá nhân vận hành</span>
                <span class="meta-info-value">{{ activeApplication.business_name || 'Chưa cung cấp' }}</span>
              </div>

              <div class="meta-info-item">
                <span class="meta-info-label">Trạng thái hồ sơ</span>
                <span class="meta-info-value">{{ statusLabel(activeApplication.status) }}</span>
              </div>

              <div class="meta-info-item full-width">
                <span class="meta-info-label">Địa chỉ đăng ký</span>
                <span class="meta-info-value">{{ activeApplication.venue_address || 'Chưa có thông tin địa chỉ' }}</span>
              </div>
            </div>
          </div>

          <!-- Section 2: Lịch sử xử lý hồ sơ -->
          <div v-if="(activeApplication.status_histories || []).length > 0" class="profile-section-card">
            <div class="tab-section-header">
              <div>
                <h2>Lịch sử xử lý hồ sơ</h2>
                <p class="section-subtitle">Các giai đoạn cập nhật trạng thái hồ sơ của đối tác ({{ activeApplication.status_histories.length }} sự kiện)</p>
              </div>
            </div>

            <div class="audit-history-list">
              <div
                v-for="(item, idx) in activeApplication.status_histories"
                :key="`${item.new_status}-${item.created_at}-${idx}`"
                class="history-audit-item"
              >
                <div class="audit-axis-col">
                  <div class="audit-status-dot" :class="`dot--${item.new_status}`"></div>
                  <div v-if="idx < activeApplication.status_histories.length - 1" class="audit-status-line"></div>
                </div>

                <div class="audit-item-body">
                  <div class="audit-item-header">
                    <span class="audit-status-title">{{ statusLabel(item.new_status) }}</span>
                    <time class="audit-timestamp">{{ formatDate(item.created_at) }}</time>
                  </div>
                  <p class="audit-note-text">{{ item.reason || 'Cập nhật trạng thái tự động' }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: HỒ SƠ ĐIỆN TỬ & VĂN BẢN -->
        <div v-if="activeTab === 'documents'" class="tab-pane-flow">
          <div class="profile-section-card">
            <div class="tab-section-header">
              <div>
                <h2>Danh sách hợp đồng & văn bản điện tử</h2>
                <p class="section-subtitle">Tất cả văn bản, đơn từ và phụ lục pháp lý đính kèm hồ sơ ({{ activeDocuments.length }} văn bản)</p>
              </div>
            </div>

            <div class="doc-list-rows" v-if="activeDocuments.length > 0">
              <article
                v-for="document in activeDocuments"
                :key="document.id"
                class="doc-row-item"
                :class="{ 'needs-sign': canSignDocument(document) }"
              >
                <div class="doc-type-icon">
                  <AppIcon :name="canSignDocument(document) ? 'pencil' : 'fileText'" size="18" />
                </div>

                <div class="doc-details">
                  <div class="doc-head-line">
                    <h4 class="doc-name">{{ document.title || documentTypeLabel(document.document_type) }}</h4>
                    <span class="doc-badge" :class="`doc-status--${document.status}`">
                      {{ documentStatusLabel(document.status) }}
                    </span>
                  </div>

                  <div class="doc-sub-line">
                    <span>{{ document.document_code || 'Mã: Tự động khởi tạo' }}</span>
                    <span class="dot-sep">•</span>
                    <span>{{ signatureSummary(document.signatures) }}</span>
                  </div>
                </div>

                <div class="doc-actions">
                  <button
                    class="btn btn-sm"
                    :class="canSignDocument(document) ? 'btn-primary' : 'btn-outline'"
                    type="button"
                    @click="viewDocument(document)"
                  >
                    <AppIcon :name="canSignDocument(document) ? 'pencil' : 'eye'" size="14" />
                    <span>{{ canSignDocument(document) ? 'Xem & ký ngay' : 'Xem văn bản' }}</span>
                  </button>

                  <button
                    class="btn-icon-square"
                    type="button"
                    title="Tải văn bản về máy"
                    aria-label="Tải văn bản"
                    @click="downloadDocument(document.id)"
                  >
                    <AppIcon name="download" size="14" />
                  </button>
                </div>
              </article>
            </div>

            <div v-else class="empty-state-copy">
              <AppIcon name="fileText" size="32" class="faint-icon" />
              <p>Chưa có văn bản điện tử nào trong hồ sơ này.</p>
            </div>
          </div>
        </div>

        <!-- TAB 3: CHẤM DỨT & QUYẾT TOÁN -->
        <div v-if="activeTab === 'termination'" class="tab-pane-flow">
          <div class="profile-section-card">
            <div class="tab-section-header">
              <div>
                <h2>Thủ tục chấm dứt hợp tác & Quyết toán</h2>
                <p class="section-subtitle">Quản lý hồ sơ chấm dứt hợp tác và thanh toán quyết toán còn lại</p>
              </div>

              <button
                v-if="activeContract && !pendingTermination && !isArchivedApplication"
                class="btn btn-danger-soft btn-sm"
                type="button"
                :disabled="!activeVenueClusterId"
                @click="openTerminationFlow"
              >
                <AppIcon name="alertCircle" size="14" />
                <span>Yêu cầu chấm dứt</span>
              </button>
            </div>

            <!-- Pending Termination Banner -->
            <div v-if="pendingTermination" class="termination-banner">
              <AppIcon name="alertCircle" size="20" class="notice-icon" />
              <div class="notice-copy">
                <strong>Hồ sơ chấm dứt: {{ terminationStatusLabel(pendingTermination.status) }}</strong>
                <p>{{ pendingTermination.reason || 'Hồ sơ đang trong quá trình xem xét và quyết toán.' }}</p>
              </div>
              <button class="btn btn-outline btn-sm" type="button" @click="openTerminationFlow">
                Chi tiết hồ sơ
              </button>
            </div>

            <!-- Termination Requests List -->
            <div class="termination-list" v-if="(activeApplication.termination_requests || []).length > 0">
              <article
                v-for="request in activeApplication.termination_requests || []"
                :key="request.id"
                class="term-row"
              >
                <div class="term-main-info">
                  <div class="term-title-line">
                    <strong>{{ request.termination_code || 'Hồ sơ chấm dứt' }}</strong>
                    <span class="term-badge">{{ terminationStatusLabel(request.status) }}</span>
                  </div>
                  <p class="term-reason">Lý do: {{ request.reason || 'Không có ghi chú' }}</p>
                  <p class="term-date">Ngày thu hồi quyền: {{ formatDate(request.transition_end_at) }}</p>
                </div>

                <button
                  v-if="pendingTermination?.id === request.id"
                  class="btn btn-outline btn-sm"
                  type="button"
                  @click="openTerminationFlow"
                >
                  Mở chi tiết
                </button>
              </article>
            </div>

            <div v-else class="empty-state-copy">
              <AppIcon name="checkCircle" size="32" class="faint-icon" />
              <p>Cụm sân đang vận hành bình thường, chưa có yêu cầu chấm dứt hợp tác nào.</p>
            </div>

            <!-- Settled Requests Financial Breakdown -->
            <div v-if="settledRequests.length > 0" class="settlements-box">
              <h3>Lịch sử quyết toán đã hoàn tất ({{ settledRequests.length }})</h3>
              <div class="settlement-cards-grid">
                <div v-for="request in settledRequests" :key="request.id" class="settlement-card">
                  <div class="s-row"><span class="s-label">Mã hồ sơ:</span><span class="s-val">{{ request.termination_code }}</span></div>
                  <div class="s-row"><span class="s-label">Ngày thu hồi:</span><span class="s-val">{{ formatDate(request.transition_end_at) }}</span></div>
                  <div class="s-row"><span class="s-label">Hoàn phí nền tảng:</span><span class="s-val green">{{ money(request.settlement?.platform_fee_remaining_refund_amount) }}</span></div>
                  <div class="s-row"><span class="s-label">Trạng thái:</span><span class="s-val">{{ terminationStatusLabel(request.status) }}</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Document Viewer Modal -->
    <DocumentViewerModal
      :show="viewerModal.open"
      :document="viewerModal.document"
      @close="closeViewerModal"
    />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import { api, apiDownload } from '../../services/api.js';

export default {
  name: 'OwnerPartnerProfile',
  components: { AppIcon, AppTabs, DocumentViewerModal },
  data() {
    return {
      applications: [],
      documents: [],
      activeApplicationId: '',
      activeTab: 'application',
      loading: true,
      error: '',
      tabs: [
        { key: 'application', label: 'Thông tin hồ sơ' },
        { key: 'documents', label: 'Hồ sơ điện tử & Văn bản' },
        { key: 'termination', label: 'Chấm dứt & Quyết toán' },
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
          label: 'Xem & ký ngay',
          title: document.title || this.documentTypeLabel(document.document_type),
          hint: 'Hợp đồng hoặc phụ lục mới đã sẵn sàng. Vui lòng kiểm tra và hoàn thành chữ ký điện tử.',
        };
      }
      if (this.pendingTermination) {
        return {
          type: 'termination',
          icon: 'arrowRight',
          label: 'Tiếp tục xử lý',
          title: this.terminationStatusLabel(this.pendingTermination.status),
          hint: 'Mở hồ sơ chấm dứt để tiếp tục xử lý lịch đặt sân, văn bản hoặc quyết toán tài chính.',
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
    async downloadDocument(id) {
      try {
        await apiDownload(`/api/files/documents/${id}/download`);
      } catch (err) {
        this.error = err.message || 'Không tải được văn bản.';
      }
    },
    signatureSummary(signatures = []) {
      if (!signatures.length) return 'Chưa có chữ ký điện tử';
      return signatures.map((signature) => `${this.signerSideLabel(signature.signer_side)}: ${this.formatDate(signature.signed_at)}`).join(' • ');
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
        generated: 'Bản thảo',
        pending_owner_signature: 'Chờ chủ sân ký',
        pending_sportgo_signature: 'Chờ SportGo ký',
        completed: 'Hoàn tất chữ ký',
      }[status] || status;
    },
    terminationStatusLabel(status) {
      return {
        draft: 'Bản nháp',
        draft_preview: 'Bản xem trước',
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
        owner_cancelled_request: 'Chủ sân đã hủy',
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
.owner-partner-profile-container {
  display: flex;
  flex-direction: column;
  gap: 0;
  width: 100%;
}

.profile-master-workspace {
  display: flex;
  flex-direction: column;
  background: transparent;
  border: none;
  padding: 0;
  gap: 0;
}

/* Header Tabs Surface */
.profile-header-tabs-surface {
  background: var(--admin-surface, #ffffff);
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.application-selector-bar {
  display: flex;
  align-items: center;
  gap: 10px;
}

.selector-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-muted, #64748b);
  white-space: nowrap;
}

.select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.custom-select {
  appearance: none;
  background: transparent;
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 6px;
  padding: 4px 28px 4px 10px;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
  cursor: pointer;
  outline: none;
}

.select-arrow {
  position: absolute;
  right: 8px;
  pointer-events: none;
  color: var(--admin-muted, #64748b);
}

.hero-integrated-tabs {
  padding-bottom: 0;
}

/* Content Surface */
.profile-content-surface {
  display: flex;
  flex-direction: column;
  background: transparent;
  border-radius: 0;
  overflow: visible;
  padding: 0;
}

.tab-pane-flow {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.profile-section-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 10px;
  background: var(--admin-surface, #ffffff);
}

.tab-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.tab-section-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 400;
  color: var(--admin-text, #0f172a);
}

.section-subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

/* Clean Flat Meta Info Grid (Read-only view without text input boxes) */
.meta-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px 32px;
}

.meta-info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.meta-info-item.full-width {
  grid-column: span 2;
}

.meta-info-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--admin-muted, #64748b);
}

.meta-info-value {
  font-size: 14px;
  font-weight: 400;
  color: var(--admin-text, #0f172a);
}

.meta-info-value.highlight {
  font-weight: 500;
  color: var(--admin-primary, #3b82f6);
}

/* Urgent Action Notice Banner */
.urgent-action-notice {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  margin-bottom: 24px;
  border-radius: 8px;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  color: var(--admin-text, #0f172a);
}

.notice-icon {
  color: #d97706;
  flex-shrink: 0;
}

.notice-body {
  flex: 1;
}

.notice-body strong {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.notice-body p {
  margin: 2px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

.rejection-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert-icon {
  color: #dc2626;
  flex-shrink: 0;
  margin-top: 2px;
}

.rejection-banner strong {
  color: #dc2626;
  font-weight: 500;
}

.rejection-banner p {
  margin: 2px 0 0;
  font-size: 13px;
  color: var(--admin-text, #0f172a);
}

/* Scoped Audit History Timeline (Isolated from admin.css global classes) */
.audit-history-list {
  display: flex;
  flex-direction: column;
  padding-left: 4px;
}

.history-audit-item {
  display: flex;
  gap: 16px;
  position: relative;
  border-bottom: none !important;
  background: transparent !important;
}

.audit-axis-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 16px;
  flex-shrink: 0;
  background: transparent !important;
  border: none !important;
}

.audit-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--admin-muted, #94a3b8);
  margin-top: 5px;
  z-index: 2;
  flex-shrink: 0;
}

.audit-status-dot.dot--completed,
.audit-status-dot.dot--signed_active { background: #16a34a; }
.audit-status-dot.dot--pending,
.audit-status-dot.dot--submitted,
.audit-status-dot.dot--reviewing { background: #d97706; }
.audit-status-dot.dot--rejected { background: #dc2626; }

.audit-status-line {
  flex: 1;
  width: 2px;
  background: var(--admin-border-soft, #e2e8f0);
  margin-top: 4px;
  margin-bottom: -4px;
}

.audit-item-body {
  flex: 1;
  padding-bottom: 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.audit-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.audit-status-title {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.audit-timestamp {
  font-size: 12px;
  color: var(--admin-muted, #94a3b8);
}

.audit-note-text {
  margin: 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

/* Document Rows */
.doc-list-rows {
  display: flex;
  flex-direction: column;
}

.doc-row-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid var(--admin-border-soft, #f1f5f9);
}

.doc-row-item:last-child {
  border-bottom: none;
}

.doc-type-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-primary, #3b82f6);
  flex-shrink: 0;
}

.doc-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.doc-head-line {
  display: flex;
  align-items: center;
  gap: 10px;
}

.doc-name {
  margin: 0;
  font-size: 14px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.doc-badge {
  font-size: 11.5px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 4px;
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-muted, #64748b);
}

.doc-status--pending_owner_signature {
  background: rgba(59, 130, 246, 0.1);
  color: #2563eb;
}

.doc-status--completed {
  background: rgba(34, 197, 94, 0.1);
  color: #16a34a;
}

.doc-sub-line {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--admin-muted, #64748b);
}

.dot-sep {
  color: var(--admin-muted, #cbd5e1);
}

.doc-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-icon-square {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-muted, #64748b);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-icon-square:hover {
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-text, #0f172a);
}

/* Termination & Settlements */
.termination-banner {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 8px;
}

.termination-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.term-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  background: var(--admin-hover, #f8fafc);
  border-radius: 8px;
}

.term-title-line {
  display: flex;
  align-items: center;
  gap: 10px;
}

.term-badge {
  font-size: 11.5px;
  padding: 2px 8px;
  border-radius: 4px;
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #334155);
}

.term-reason, .term-date {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--admin-muted, #64748b);
}

.settlements-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.settlements-box h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.settlement-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.settlement-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px;
  background: var(--admin-hover, #f8fafc);
  border-radius: 8px;
}

.s-row {
  display: flex;
  justify-content: space-between;
  font-size: 12.5px;
}

.s-label {
  color: var(--admin-muted, #64748b);
}

.s-val {
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.s-val.green {
  color: #16a34a;
}

/* Empty State Copy */
.empty-state-copy {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 32px 16px;
  color: var(--admin-muted, #64748b);
  font-size: 13.5px;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  border: 1px solid transparent;
  white-space: nowrap;
}

.btn-sm {
  height: 32px;
  padding: 0 12px;
  font-size: 12.5px;
}

.btn-primary {
  background: var(--admin-primary, #3b82f6);
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background: var(--admin-primary, #3b82f6);
  box-shadow: none;
  transform: none;
}

.btn-outline {
  background: transparent;
  border-color: var(--admin-border-soft, #cbd5e1);
  color: var(--admin-text, #334155);
}

.btn-outline:hover {
  background: var(--admin-hover, #f1f5f9);
}

.btn-accent {
  background: #d97706;
  color: #ffffff;
}

.btn-accent:hover {
  background: #b45309;
}

.btn-danger-soft {
  background: rgba(239, 68, 68, 0.08);
  color: #dc2626;
  border-color: rgba(239, 68, 68, 0.2);
}

.btn-danger-soft:hover {
  background: rgba(239, 68, 68, 0.15);
}

/* Base State Boxes */
.state-box {
  background: var(--admin-surface, #ffffff);
  border-radius: 12px;
  padding: 40px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  text-align: center;
  color: var(--admin-muted, #64748b);
}

.faint-icon {
  opacity: 0.5;
  color: var(--admin-muted, #94a3b8);
}

.state-box.error {
  border: 1px solid rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.03);
  color: var(--admin-text, #0f172a);
}

.error-icon {
  color: #ef4444;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(59, 130, 246, 0.2);
  border-top-color: var(--admin-primary, #3b82f6);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .doc-row-item {
    flex-direction: column;
    align-items: flex-start;
  }
  .doc-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
