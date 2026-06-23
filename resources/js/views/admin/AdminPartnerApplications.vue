<template>
  <div class="partner-page">
    <header class="page-header">
      <div>
        <h2>Quản lý hồ sơ đối tác</h2>
        <p>Theo dõi hồ sơ, hợp đồng, chữ ký điện tử và chấm dứt hợp tác của chủ sân.</p>
      </div>
      <button class="icon-btn" type="button" title="Làm mới" @click="refresh">
        <AppIcon name="refresh" size="16" />
      </button>
    </header>

    <div class="tabs">
      <button
        v-for="tab in listTabs"
        :key="tab.value"
        class="tab-btn"
        :class="{ active: filters.tab === tab.value }"
        type="button"
        @click="selectListTab(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="toolbar card">
      <label class="field">
        <span>Tìm kiếm</span>
        <input v-model.trim="filters.search" type="search" placeholder="Tên sân, chủ sân, email, MST" @input="onFilterChange" />
      </label>
      <label class="field">
        <span>Trạng thái</span>
        <select v-model="filters.status" @change="loadApplications(1)">
          <option value="">Tất cả</option>
          <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>
    </div>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải hồ sơ...</p>
    </div>

    <div v-else-if="applications.length === 0" class="state-box card">
      <p>Không có hồ sơ phù hợp.</p>
    </div>

    <div v-else class="table-card card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Hồ sơ</th>
              <th>Người nộp</th>
              <th>Sân</th>
              <th>Ngày gửi</th>
              <th class="center">Trạng thái</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="application in applications" :key="application.id">
              <td>
                <div class="strong">{{ application.venue_name }}</div>
                <div class="muted">{{ application.business_name }}</div>
              </td>
              <td>
                <div class="strong">{{ application.user?.full_name || application.user?.username || '-' }}</div>
                <div class="muted">{{ application.user?.email || application.user?.phone || '-' }}</div>
              </td>
              <td>{{ application.courts_count || 0 }}</td>
              <td>{{ formatDate(application.submitted_at) }}</td>
              <td class="center">
                <span class="status" :class="`status-${application.status}`">{{ statusLabel(application.status) }}</span>
              </td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Chi tiết" @click="openDetail(application)">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <button v-if="isReviewable(application.status)" class="icon-btn approve" type="button" title="Duyệt" @click="openApprove(application)">
                    <AppIcon name="check" size="16" />
                  </button>
                  <button v-if="isReviewable(application.status)" class="icon-btn danger" type="button" title="Từ chối" @click="openReject(application)">
                    <AppIcon name="x" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.last_page > 1" class="pagination">
        <button class="icon-btn" type="button" :disabled="pagination.current_page <= 1" @click="loadApplications(pagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="16" />
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="icon-btn" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadApplications(pagination.current_page + 1)">
          <AppIcon name="chevronRight" size="16" />
        </button>
      </div>
    </div>

    <div v-if="detailModal.open" class="modal-backdrop" @click.self="closeDetail">
      <div class="modal large">
        <div class="modal-header">
          <h3>{{ activeApplication?.venue_name || 'Chi tiết hồ sơ' }}</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeDetail"><AppIcon name="x" size="18" /></button>
        </div>

        <div v-if="detailLoading" class="state-box modal-state">
          <div class="spinner"></div>
        </div>

        <template v-else-if="activeApplication">
          <div class="detail-tabs">
            <button v-for="tab in detailTabs" :key="tab.value" class="tab-btn" :class="{ active: detailTab === tab.value }" type="button" @click="detailTab = tab.value">
              {{ tab.label }}
            </button>
          </div>

          <div class="modal-body">
            <section v-if="detailTab === 'overview'" class="detail-grid">
              <div class="panel">
                <h4>Người đăng ký</h4>
                <dl>
                  <dt>Họ tên</dt><dd>{{ activeApplication.applicant_full_name || activeApplication.user?.full_name || '-' }}</dd>
                  <dt>Email</dt><dd>{{ activeApplication.applicant_email || activeApplication.user?.email || '-' }}</dd>
                  <dt>Điện thoại</dt><dd>{{ activeApplication.applicant_phone || activeApplication.user?.phone || '-' }}</dd>
                  <dt>Ngày sinh</dt><dd>{{ dateOnly(activeApplication.applicant_birth_date) }}</dd>
                  <dt>Địa chỉ liên hệ</dt><dd>{{ activeApplication.applicant_address || '-' }}</dd>
                </dl>
              </div>
              <div class="panel">
                <h4>Đại diện pháp lý</h4>
                <dl>
                  <dt>Người đại diện</dt><dd>{{ activeApplication.representative_name || '-' }}</dd>
                  <dt>Loại giấy tờ</dt><dd>{{ activeApplication.representative_identity_type || '-' }}</dd>
                  <dt>Số giấy tờ</dt><dd>{{ activeApplication.representative_identity_number || '-' }}</dd>
                  <dt>Ngày cấp</dt><dd>{{ dateOnly(activeApplication.representative_identity_issued_date) }}</dd>
                  <dt>Nơi cấp</dt><dd>{{ activeApplication.representative_identity_issued_place || '-' }}</dd>
                  <dt>Chức vụ</dt><dd>{{ activeApplication.representative_position || '-' }}</dd>
                </dl>
              </div>
              <div class="panel">
                <h4>Đơn vị kinh doanh</h4>
                <dl>
                  <dt>Tên đơn vị</dt><dd>{{ activeApplication.business_name || '-' }}</dd>
                  <dt>Mã số thuế</dt><dd>{{ activeApplication.tax_code || '-' }}</dd>
                  <dt>Mã kinh doanh</dt><dd>{{ activeApplication.business_code || '-' }}</dd>
                  <dt>Số giấy phép</dt><dd>{{ activeApplication.business_license_number || '-' }}</dd>
                  <dt>Địa chỉ pháp lý</dt><dd>{{ activeApplication.business_address || '-' }}</dd>
                </dl>
              </div>
              <div class="panel">
                <h4>Ngân hàng nhận tiền</h4>
                <dl>
                  <dt>Ngân hàng</dt><dd>{{ activeApplication.bank_name || '-' }}</dd>
                  <dt>Mã ngân hàng</dt><dd>{{ activeApplication.bank_code || '-' }}</dd>
                  <dt>Số tài khoản</dt><dd>{{ activeApplication.account_number || '-' }}</dd>
                  <dt>Chủ tài khoản</dt><dd>{{ activeApplication.account_holder_name || '-' }}</dd>
                  <dt>Trạng thái</dt><dd>{{ bankVerificationLabel(activeApplication.bank_verification_status) }}</dd>
                  <dt>Xác minh lúc</dt><dd>{{ formatDate(activeApplication.bank_verified_at) }}</dd>
                </dl>
              </div>
              <div class="panel full">
                <h4>Cụm sân đăng ký</h4>
                <dl>
                  <dt>Tên cụm</dt><dd>{{ activeApplication.venue_name }}</dd>
                  <dt>Địa chỉ</dt><dd>{{ activeApplication.venue_address }}</dd>
                  <dt>Tỉnh/Thành phố</dt><dd>{{ activeApplication.venue_province || '-' }}</dd>
                  <dt>Phường/Xã</dt><dd>{{ activeApplication.venue_ward || '-' }}</dd>
                  <dt>Tọa độ</dt><dd>{{ activeApplication.venue_latitude }}, {{ activeApplication.venue_longitude }}</dd>
                  <dt>Google Maps</dt>
                  <dd>
                    <a v-if="activeApplication.venue_map_url" :href="activeApplication.venue_map_url" target="_blank" rel="noopener">Mở vị trí</a>
                    <span v-else>-</span>
                  </dd>
                  <dt>Liên hệ sân</dt><dd>{{ activeApplication.venue_phone || '-' }} · {{ activeApplication.venue_email || '-' }}</dd>
                  <dt>Giờ mở cửa</dt><dd>{{ activeApplication.expected_opening_hours || '-' }}</dd>
                  <dt>Bãi xe/phụ trợ</dt><dd>{{ activeApplication.parking_info || '-' }}</dd>
                  <dt>Số sân con</dt><dd>{{ activeApplication.court_count_total || activeApplication.courts_count || 0 }}</dd>
                  <dt>Giá cơ bản</dt><dd>{{ money(activeApplication.base_price_per_hour) }}</dd>
                  <dt>Tiện ích</dt><dd>{{ compactList(activeApplication.amenities) }}</dd>
                  <dt>Trạng thái</dt><dd><span class="status" :class="`status-${activeApplication.status}`">{{ statusLabel(activeApplication.status) }}</span></dd>
                  <dt v-if="activeApplication.status_reason">Ghi chú</dt><dd v-if="activeApplication.status_reason">{{ activeApplication.status_reason }}</dd>
                </dl>
              </div>
            </section>

            <section v-if="detailTab === 'courts'" class="doc-list">
              <div v-if="activeApplication.approved_venue_cluster" class="panel">
                <h4>Cụm sân đã tạo</h4>
                <dl>
                  <dt>Tên cụm</dt><dd>{{ activeApplication.approved_venue_cluster.name }}</dd>
                  <dt>Trạng thái</dt><dd>{{ activeApplication.approved_venue_cluster.status }}</dd>
                  <dt>Địa chỉ</dt><dd>{{ activeApplication.approved_venue_cluster.address || activeApplication.venue_address || '-' }}</dd>
                </dl>
              </div>

              <div v-for="court in activeApplication.courts || []" :key="court.id || court.name" class="doc-row">
                <div>
                  <div class="strong">{{ court.name }}</div>
                  <div class="muted">{{ courtTypeName(court) }} · Số lượng dự kiến: {{ court.expected_court_count || 1 }}</div>
                  <div v-if="court.note" class="signature-line">{{ court.note }}</div>
                </div>
              </div>
              <p v-if="!activeApplication.courts?.length" class="muted">Chưa có sân con nào trong hồ sơ.</p>
            </section>

            <section v-if="detailTab === 'documents'" class="doc-list">
              <h4 class="doc-section-title">Hồ sơ gốc người đăng ký tải lên</h4>
              <div v-for="document in activeApplication.uploaded_documents || []" :key="`uploaded-${document.id}`" class="doc-row">
                <div>
                  <div class="strong">{{ document.title || uploadedDocumentTypeLabel(document.document_type) }}</div>
                  <div class="muted">{{ document.file_name || uploadedDocumentTypeLabel(document.document_type) }} · {{ formatFileSize(document.file_size) }} · {{ formatDate(document.uploaded_at) }}</div>
                  <div v-if="document.description" class="signature-line">{{ document.description }}</div>
                </div>
                <button class="btn ghost small" type="button" @click="downloadUploadedDocument(document.id)">
                  <AppIcon name="download" size="15" /> Tải hồ sơ
                </button>
              </div>
              <p v-if="!activeApplication.uploaded_documents?.length" class="muted">Chưa có hồ sơ gốc nào.</p>

              <h4 class="doc-section-title">Văn bản hệ thống sinh ra</h4>
              <div v-for="document in activeApplication.documents || []" :key="document.id" class="doc-row">
                <div>
                  <div class="strong">{{ document.title || documentTypeLabel(document.document_type) }}</div>
                  <div class="muted">{{ document.document_code }} · {{ documentStatusLabel(document.status) }} · {{ formatDate(document.generated_at) }}</div>
                  <div class="signature-line">{{ signatureSummary(document.signatures) }}</div>
                </div>
                <div class="flex gap-2">
                  <button class="btn primary small" type="button" @click="viewDocument(document)">
                    <AppIcon name="eye" size="15" /> Xem
                  </button>
                  <button class="btn ghost small" type="button" @click="downloadDocument(document.id)">
                    <AppIcon name="download" size="15" />
                  </button>
                </div>
              </div>
              <p v-if="!activeApplication.documents?.length" class="muted">Chưa có văn bản nào.</p>
            </section>

            <section v-if="detailTab === 'history'" class="timeline">
              <div v-for="item in activeApplication.status_histories || []" :key="`${item.partner_application_id}-${item.created_at}`" class="timeline-item">
                <span class="dot"></span>
                <div>
                  <div class="strong">{{ statusLabel(item.new_status) }}</div>
                  <div class="muted">{{ formatDate(item.created_at) }} · {{ item.changed_by?.full_name || item.actor_type }}</div>
                  <p v-if="item.reason">{{ item.reason }}</p>
                </div>
              </div>
              <p v-if="!activeApplication.status_histories?.length" class="muted">Chưa có lịch sử xử lý.</p>
            </section>

            <section v-if="detailTab === 'settlement'" class="doc-list">
              <div v-for="request in activeApplication.termination_requests || []" :key="request.id" class="doc-row">
                <div>
                  <div class="strong">{{ request.termination_code }}</div>
                  <div class="muted">{{ terminationStatusLabel(request.status) }} · {{ request.reason }}</div>
                  <div v-if="request.settlement" class="signature-line">
                    Hoàn phí: {{ money(request.settlement.platform_fee_remaining_refund_amount) }} · Thu hồi quyền: {{ formatDate(request.transition_end_at) }}
                  </div>
                </div>
                <button v-if="['submitted', 'reviewing'].includes(request.status)" class="btn primary small" type="button" @click="confirmTermination(request.id)">
                  Xác nhận chấm dứt
                </button>
              </div>
              <p v-if="!activeApplication.termination_requests?.length" class="muted">Chưa có yêu cầu chấm dứt.</p>
            </section>
          </div>

          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeDetail">Đóng</button>
            <button v-if="isReviewable(activeApplication.status)" class="btn danger" type="button" @click="openReject(activeApplication)">Từ chối</button>
            <button v-if="isReviewable(activeApplication.status)" class="btn primary" type="button" @click="openApprove(activeApplication)">Duyệt hồ sơ</button>
            <button v-if="pendingSportgoContract" class="btn primary" type="button" @click="openSign(activeApplication)">Ký xác nhận</button>
            <button v-if="activeContract" class="btn danger" type="button" @click="openTerminate(activeApplication)">Chấm dứt đơn phương</button>
          </div>
        </template>
      </div>
    </div>

    <div v-if="approveModal.open" class="modal-backdrop" @click.self="closeApprove">
      <form class="modal" @submit.prevent="submitApprove">
        <div class="modal-header">
          <h3>Duyệt hồ sơ</h3>
          <button class="icon-btn" type="button" @click="closeApprove"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body form-grid">
          <label v-if="!activeApplication?.courts?.length" class="field">
            <span>Tên sân con ban đầu</span>
            <input v-model.trim="approveForm.initial_court_name" type="text" required />
          </label>
          <label v-if="!activeApplication?.courts?.length" class="field">
            <span>Loại sân</span>
            <select v-model="approveForm.court_type_id" required>
              <option value="">Chọn loại sân</option>
              <option v-for="courtType in courtTypes" :key="courtType.id" :value="courtType.id">{{ courtType.name }}</option>
            </select>
          </label>
          <label class="field full">
            <span>Ghi chú duyệt</span>
            <textarea v-model.trim="approveForm.review_note" rows="4"></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeApprove">Hủy</button>
          <button class="btn primary" type="submit" :disabled="savingAction">{{ savingAction ? 'Đang duyệt...' : 'Duyệt & tạo hợp đồng' }}</button>
        </div>
      </form>
    </div>

    <div v-if="rejectModal.open" class="modal-backdrop" @click.self="closeReject">
      <form class="modal small" @submit.prevent="submitReject">
        <div class="modal-header">
          <h3>Từ chối hồ sơ</h3>
          <button class="icon-btn" type="button" @click="closeReject"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <label class="field full">
            <span>Lý do từ chối</span>
            <textarea v-model.trim="rejectForm.reason" rows="6" required></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeReject">Hủy</button>
          <button class="btn danger" type="submit" :disabled="savingAction">Từ chối</button>
        </div>
      </form>
    </div>

    <div v-if="signModal.open" class="modal-backdrop" @click.self="closeSign">
      <form class="modal" @submit.prevent="submitSign">
        <div class="modal-header">
          <h3>Ký xác nhận SportGo</h3>
          <button class="icon-btn" type="button" @click="closeSign"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <canvas ref="signatureCanvas" class="signature-pad" width="620" height="190" @pointerdown="startDraw" @pointermove="draw" @pointerup="stopDraw" @pointerleave="stopDraw"></canvas>
          <button class="btn ghost small" type="button" @click="clearSignature">Xóa chữ ký</button>
        </div>
        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeSign">Hủy</button>
          <button class="btn primary" type="submit" :disabled="savingAction">Xác nhận ký</button>
        </div>
      </form>
    </div>

    <div v-if="terminateModal.open" class="modal-backdrop" @click.self="closeTerminate">
      <form class="modal small" @submit.prevent="submitTerminate">
        <div class="modal-header">
          <h3>Chấm dứt hợp tác đơn phương</h3>
          <button class="icon-btn" type="button" @click="closeTerminate"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <label class="field full">
            <span>Lý do chấm dứt</span>
            <textarea v-model.trim="terminateForm.reason" rows="6" required></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeTerminate">Hủy</button>
          <button class="btn danger" type="submit" :disabled="savingAction">Khởi tạo chấm dứt</button>
        </div>
      </form>
    </div>

    <DocumentViewerModal
      :show="showDocumentViewer"
      :document="viewingDocument"
      @close="closeDocumentViewer"
    />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import DocumentViewerModal from '../../components/DocumentViewerModal.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';

export default {
  name: 'AdminPartnerApplications',
  components: { AppIcon, DocumentViewerModal },
  data() {
    return {
      applications: [],
      activeApplication: null,
      courtTypes: [],
      loading: true,
      detailLoading: false,
      savingAction: false,
      drawing: false,
      error: '',
      message: '',
      filterTimer: null,
      filters: { tab: 'pending', search: '', status: '' },
      pagination: { current_page: 1, last_page: 1, total: 0 },
      detailTab: 'overview',
      showDocumentViewer: false,
      viewingDocument: null,
      detailModal: { open: false },
      approveModal: { open: false },
      rejectModal: { open: false },
      signModal: { open: false },
      terminateModal: { open: false },
      approveForm: { initial_court_name: '', court_type_id: '', review_note: '' },
      rejectForm: { reason: '' },
      terminateForm: { reason: '' },
      listTabs: [
        { value: 'pending', label: 'Chờ xử lý' },
        { value: 'active', label: 'Hợp đồng & hoạt động' },
        { value: 'terminating', label: 'Đang chấm dứt' },
      ],
      detailTabs: [
        { value: 'overview', label: 'Tổng quan' },
        { value: 'courts', label: 'Sân quản lý' },
        { value: 'documents', label: 'Tài liệu & văn bản' },
        { value: 'history', label: 'Lịch sử' },
        { value: 'settlement', label: 'Quyết toán' },
      ],
      statusOptions: [
        { value: 'submitted', label: 'Chờ duyệt' },
        { value: 'reviewing', label: 'Đang xem xét' },
        { value: 'contract_pending_owner_signature', label: 'Chờ chủ sân ký' },
        { value: 'contract_pending_sportgo_signature', label: 'Chờ SportGo ký' },
        { value: 'completed', label: 'Đang hoạt động' },
        { value: 'rejected', label: 'Từ chối' },
      ],
    };
  },
  computed: {
    pendingSportgoContract() {
      return this.activeApplication?.contracts?.find((contract) => contract.status === 'pending_sportgo_signature') || null;
    },
    activeContract() {
      return this.activeApplication?.contracts?.find((contract) => contract.status === 'signed_active') || null;
    },
  },
  mounted() {
    this.loadApplications();
    this.loadCourtTypes();
  },
  methods: {
    async loadApplications(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPartnerApplicationService.list({ ...this.filters, page });
        const paginator = response.data || {};
        this.applications = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.applications.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được hồ sơ đối tác.';
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
    async fetchApplication(application) {
      this.detailLoading = true;
      try {
        const response = await adminPartnerApplicationService.show(application.id);
        this.activeApplication = response.data;
        return response.data;
      } catch (err) {
        this.error = err.message || 'Không tải được chi tiết hồ sơ.';
        return null;
      } finally {
        this.detailLoading = false;
      }
    },
    selectListTab(tab) {
      this.filters.tab = tab;
      this.filters.status = '';
      this.loadApplications(1);
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadApplications(1), 300);
    },
    refresh() {
      this.loadApplications(this.pagination.current_page);
    },
    async openDetail(application) {
      this.clearAlerts();
      this.detailTab = 'overview';
      this.detailModal.open = true;
      this.activeApplication = application;
      await this.fetchApplication(application);
    },
    closeDetail() {
      this.detailModal.open = false;
    },
    async openApprove(application) {
      this.clearAlerts();
      const detail = application.courts ? application : await this.fetchApplication(application);
      if (!detail) return;
      this.activeApplication = detail;
      this.approveForm = { initial_court_name: '', court_type_id: '', review_note: '' };
      this.approveModal.open = true;
    },
    closeApprove() {
      this.approveModal.open = false;
    },
    async submitApprove() {
      if (!this.activeApplication) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        const response = await adminPartnerApplicationService.approve(this.activeApplication.id, this.approveForm);
        this.message = response.message || 'Duyệt hồ sơ thành công.';
        this.activeApplication = response.data;
        this.closeApprove();
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không duyệt được hồ sơ.';
      } finally {
        this.savingAction = false;
      }
    },
    async openReject(application) {
      this.clearAlerts();
      this.activeApplication = application.id === this.activeApplication?.id ? this.activeApplication : await this.fetchApplication(application);
      this.rejectForm.reason = '';
      this.rejectModal.open = true;
    },
    closeReject() {
      this.rejectModal.open = false;
    },
    async submitReject() {
      this.savingAction = true;
      this.clearAlerts();
      try {
        const response = await adminPartnerApplicationService.reject(this.activeApplication.id, this.rejectForm);
        this.message = response.message || 'Từ chối hồ sơ thành công.';
        this.activeApplication = response.data;
        this.closeReject();
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không từ chối được hồ sơ.';
      } finally {
        this.savingAction = false;
      }
    },
    openSign(application) {
      this.activeApplication = application;
      this.signModal.open = true;
      this.$nextTick(this.prepareCanvas);
    },
    closeSign() {
      this.signModal.open = false;
    },
    async submitSign() {
      this.savingAction = true;
      this.clearAlerts();
      try {
        const payload = {
          contract_id: this.pendingSportgoContract?.id,
          signature_image: this.signatureData(),
        };
        const response = await adminPartnerApplicationService.signDocument(this.activeApplication.id, payload);
        this.message = response.message || 'Đã ký xác nhận hợp đồng.';
        this.activeApplication = response.data;
        this.closeSign();
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không ký được hợp đồng.';
      } finally {
        this.savingAction = false;
      }
    },
    viewDocument(document) {
      this.viewingDocument = {
        ...document,
        download_url: `/api/admin/partner-applications/${this.activeApplication.id}/documents/${document.id}/download`
      };
      this.showDocumentViewer = true;
    },
    closeDocumentViewer() {
      this.showDocumentViewer = false;
      setTimeout(() => { this.viewingDocument = null; }, 300);
    },
    openTerminate(application) {
      this.activeApplication = application;
      this.terminateForm.reason = '';
      this.terminateModal.open = true;
    },
    closeTerminate() {
      this.terminateModal.open = false;
    },
    async submitTerminate() {
      this.savingAction = true;
      this.clearAlerts();
      try {
        const response = await adminPartnerApplicationService.terminate(this.activeApplication.id, this.terminateForm);
        this.message = response.message || 'Đã khởi tạo chấm dứt hợp tác.';
        this.closeTerminate();
        await this.fetchApplication(this.activeApplication);
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không khởi tạo được chấm dứt hợp tác.';
      } finally {
        this.savingAction = false;
      }
    },
    async confirmTermination(terminationRequestId) {
      if (!window.confirm('Xác nhận chấm dứt hợp tác và tạo quyết toán?')) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        const response = await adminPartnerApplicationService.confirmTermination(this.activeApplication.id, { termination_request_id: terminationRequestId });
        this.message = response.message || 'Đã xác nhận chấm dứt.';
        await this.fetchApplication(this.activeApplication);
        await this.loadApplications(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không xác nhận được yêu cầu chấm dứt.';
      } finally {
        this.savingAction = false;
      }
    },
    async downloadDocument(id) {
      try {
        await adminPartnerApplicationService.downloadDocument(id);
      } catch (err) {
        this.error = err.message || 'Không tải được văn bản.';
      }
    },
    async downloadUploadedDocument(id) {
      try {
        await adminPartnerApplicationService.downloadUploadedDocument(id);
      } catch (err) {
        this.error = err.message || 'Không tải được hồ sơ gốc.';
      }
    },
    prepareCanvas() {
      const canvas = this.$refs.signatureCanvas;
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.strokeStyle = '#0f172a';
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
    },
    pointerPosition(event) {
      const rect = this.$refs.signatureCanvas.getBoundingClientRect();
      return {
        x: ((event.clientX - rect.left) / rect.width) * this.$refs.signatureCanvas.width,
        y: ((event.clientY - rect.top) / rect.height) * this.$refs.signatureCanvas.height,
      };
    },
    startDraw(event) {
      this.drawing = true;
      const point = this.pointerPosition(event);
      const ctx = this.$refs.signatureCanvas.getContext('2d');
      ctx.beginPath();
      ctx.moveTo(point.x, point.y);
    },
    draw(event) {
      if (!this.drawing) return;
      const point = this.pointerPosition(event);
      const ctx = this.$refs.signatureCanvas.getContext('2d');
      ctx.lineTo(point.x, point.y);
      ctx.stroke();
    },
    stopDraw() {
      this.drawing = false;
    },
    clearSignature() {
      this.prepareCanvas();
    },
    signatureData() {
      return this.$refs.signatureCanvas?.toDataURL('image/png') || null;
    },
    isReviewable(status) {
      return ['pending', 'reviewing', 'submitted', 'need_supplement'].includes(status);
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
        contract_pending_owner_signature: 'Chờ chủ sân ký',
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
    uploadedDocumentTypeLabel(type) {
      return {
        identity: 'CCCD/CMND/Hộ chiếu',
        business_license: 'Giấy đăng ký kinh doanh',
        facility: 'Hình ảnh cơ sở sân',
        additional: 'Tài liệu bổ sung',
      }[type] || type || 'Tài liệu';
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
    bankVerificationLabel(status) {
      return {
        verified: 'Đã xác minh tự động',
        pending: 'Chưa xác minh',
        lookup_not_configured: 'Chưa cấu hình VietQR',
        name_mismatch: 'Tên chủ tài khoản không khớp',
        not_found: 'Không tìm thấy tài khoản',
        provider_unavailable: 'Dịch vụ xác minh lỗi',
      }[status] || status || '-';
    },
    compactList(value) {
      if (Array.isArray(value)) return value.filter(Boolean).join(', ') || '-';
      return value || '-';
    },
    courtTypeName(court) {
      return court?.court_type?.name || court?.courtType?.name || court?.court_type_name_snapshot || 'Loại sân';
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));
    },
    formatFileSize(value) {
      const bytes = Number(value || 0);
      if (!bytes) return 'không rõ dung lượng';

      const units = ['B', 'KB', 'MB', 'GB'];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      const size = bytes / (1024 ** index);

      return `${size.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    },
    dateOnly(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('vi-VN');
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
  },
};
</script>

<style scoped>
.partner-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
}

.card,
.modal,
.panel {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
}

.tabs,
.detail-tabs,
.actions,
.pagination,
.modal-footer {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tab-btn {
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #fff;
  color: #475569;
  font-weight: 800;
  cursor: pointer;
}

.tab-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.toolbar {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(180px, 260px);
  gap: 12px;
  padding: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
}

.field.full {
  grid-column: 1 / -1;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 0 12px;
  color: var(--sg-text);
  background: #fff;
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

.notice {
  padding: 12px 14px;
  border-radius: 8px;
  font-weight: 800;
}

.notice.success {
  color: #166534;
  background: #dcfce7;
}

.notice.error {
  color: #991b1b;
  background: #fee2e2;
}

.state-box {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #64748b;
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

.table-card {
  overflow: hidden;
}

.table-scroll {
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
  background: #f8fafc;
  color: #475569;
  font-size: 12px;
  text-transform: uppercase;
}

.center { text-align: center; }
.right { text-align: right; }
.strong { font-weight: 900; color: var(--sg-text); }
.muted { color: #64748b; font-size: 13px; }

.status {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  background: #e2e8f0;
  color: #334155;
}

.status-pending,
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

.status-rejected,
.status-cancelled {
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
  background: #fff;
  border-color: var(--sg-border);
  color: #334155;
}

.icon-btn {
  width: 34px;
  height: 34px;
}

.icon-btn.approve { color: #15803d; }
.icon-btn.danger { color: #dc2626; }

.pagination {
  justify-content: flex-end;
  padding: 12px 16px;
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
}

.modal.large {
  width: min(1060px, 100%);
}

.modal.small {
  width: min(560px, 100%);
}

.modal-header,
.modal-footer,
.detail-tabs {
  padding: 14px 18px;
  border-bottom: 1px solid var(--sg-border);
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--sg-border);
  border-bottom: 0;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3,
.panel h4 {
  margin: 0;
}

.modal-body {
  padding: 18px;
  overflow-y: auto;
}

.detail-grid,
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.panel {
  padding: 14px;
}

.panel.full {
  grid-column: 1 / -1;
}

dl {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 8px 12px;
  margin: 12px 0 0;
}

dt {
  color: #64748b;
  font-weight: 800;
}

dd {
  margin: 0;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.doc-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.doc-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
}

.signature-line {
  margin-top: 4px;
  color: #64748b;
  font-size: 13px;
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: 14px;
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

.signature-pad {
  width: 100%;
  max-width: 620px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  touch-action: none;
  background: #fff;
  display: block;
  margin-bottom: 10px;
}

@media (max-width: 900px) {
  .toolbar,
  .detail-grid,
  .form-grid {
    grid-template-columns: 1fr;
  }

  .panel.full,
  .field.full {
    grid-column: auto;
  }

  dl,
  .doc-row {
    display: block;
  }
}
</style>
