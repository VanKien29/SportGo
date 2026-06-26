<template>
  <div class="partner-detail-page">
    <header class="page-head">
      <button class="btn ghost" type="button" @click="router.push({ name: 'admin-partner-applications' })">
        <AppIcon name="arrowLeft" size="16" />
        Quay lại
      </button>

      <div class="title-block">
        <p>Hồ sơ đối tác</p>
        <h2>{{ application?.venue_name || 'Đang tải hồ sơ' }}</h2>
      </div>

      <span v-if="application" class="status" :class="`status-${application.status}`">
        {{ statusLabel(application.status) }}
      </span>
    </header>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <section v-if="loading" class="state-card">Đang tải hồ sơ...</section>
    <section v-else-if="!application" class="state-card error">Không tìm thấy hồ sơ đối tác.</section>

    <template v-else>
      <section class="action-strip">
        <div>
          <strong>{{ application.business_name || application.applicant_full_name }}</strong>
          <span>{{ application.user?.email || application.applicant_email || '-' }}</span>
        </div>

        <div class="action-buttons">
          <button
            v-if="isReviewable(application.status)"
            class="btn primary"
            type="button"
            @click="actionMode = 'approve'"
          >
            <AppIcon name="check" size="16" />
            Duyệt
          </button>
          <button
            v-if="isReviewable(application.status)"
            class="btn danger"
            type="button"
            @click="actionMode = 'reject'"
          >
            <AppIcon name="x" size="16" />
            Từ chối
          </button>
          <button
            v-if="pendingSportgoDocument"
            class="btn primary"
            type="button"
            @click="openDocument(pendingSportgoDocument)"
          >
            <AppIcon name="pencil" size="16" />
            Ký hợp đồng
          </button>
        </div>
      </section>

      <section v-if="actionMode === 'approve'" class="review-panel">
        <div class="panel-head">
          <div>
            <h3>Duyệt hồ sơ và tạo hợp đồng</h3>
            <p>Sau khi duyệt, hệ thống tạo giấy/hợp đồng đối tác và chờ SportGo ký trước.</p>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="clearAction">
            <AppIcon name="x" size="16" />
          </button>
        </div>

        <form class="review-form" @submit.prevent="submitApprove">
          <label v-if="requiresInitialCourt" class="field" :class="{ invalid: fieldErrors.initial_court_name }">
            <span>Tên sân con ban đầu</span>
            <input v-model.trim="approveForm.initial_court_name" type="text" placeholder="Ví dụ: Sân 1" />
            <small v-if="fieldErrors.initial_court_name">{{ fieldErrors.initial_court_name }}</small>
          </label>

          <label v-if="requiresInitialCourt" class="field" :class="{ invalid: fieldErrors.court_type_id }">
            <span>Loại sân</span>
            <select v-model="approveForm.court_type_id">
              <option value="">Chọn loại sân con</option>
              <option v-for="courtType in leafCourtTypes" :key="courtType.id" :value="courtType.id">
                {{ courtType.name }}
              </option>
            </select>
            <small v-if="fieldErrors.court_type_id">{{ fieldErrors.court_type_id }}</small>
          </label>

          <label class="field full">
            <span>Ghi chú duyệt</span>
            <textarea v-model.trim="approveForm.review_note" rows="4" placeholder="Ghi chú nội bộ nếu cần"></textarea>
          </label>

          <p v-if="actionError" class="inline-error full">{{ actionError }}</p>

          <div class="form-actions full">
            <button class="btn ghost" type="button" @click="clearAction">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving">
              <AppIcon name="check" size="16" />
              {{ saving ? 'Đang duyệt...' : 'Duyệt và tạo hợp đồng' }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="actionMode === 'reject'" class="review-panel danger-panel">
        <div class="panel-head">
          <div>
            <h3>Từ chối hồ sơ</h3>
            <p>Bắt buộc nhập lý do. Lý do này sẽ được gửi cho người đăng ký qua email.</p>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="clearAction">
            <AppIcon name="x" size="16" />
          </button>
        </div>

        <form class="review-form" @submit.prevent="submitReject">
          <label class="field full" :class="{ invalid: fieldErrors.reason }">
            <span>Lý do từ chối</span>
            <textarea v-model.trim="rejectForm.reason" rows="5" placeholder="Nêu rõ nội dung cần bổ sung hoặc lý do hồ sơ chưa đạt"></textarea>
            <small v-if="fieldErrors.reason">{{ fieldErrors.reason }}</small>
          </label>

          <p v-if="actionError" class="inline-error full">{{ actionError }}</p>

          <div class="form-actions full">
            <button class="btn ghost" type="button" @click="clearAction">Hủy</button>
            <button class="btn danger" type="submit" :disabled="saving">
              <AppIcon name="x" size="16" />
              {{ saving ? 'Đang từ chối...' : 'Từ chối hồ sơ' }}
            </button>
          </div>
        </form>
      </section>

      <nav class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          :class="{ active: activeTab === tab.value }"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="summary-grid">
        <InfoPanel title="Người đăng ký" :items="[
          ['Họ tên', application.applicant_full_name || application.user?.full_name],
          ['Điện thoại', application.applicant_phone || application.user?.phone],
          ['Email', application.applicant_email || application.user?.email],
          ['Ngày sinh', dateOnly(application.applicant_birth_date)],
          ['Địa chỉ liên hệ', application.applicant_address],
        ]" />

        <InfoPanel title="Giấy tờ và pháp lý" :items="[
          ['Người đại diện', application.representative_name],
          ['Loại giấy tờ', identityLabel(application.representative_identity_type)],
          ['Số giấy tờ', application.representative_identity_number],
          ['Ngày cấp', dateOnly(application.representative_identity_issued_date)],
          ['Nơi cấp', application.representative_identity_issued_place],
          ['Địa chỉ pháp lý', application.business_address],
        ]" />

        <InfoPanel title="Đơn vị kinh doanh" :items="[
          ['Tên đơn vị/cá nhân', application.business_name],
          ['Mã số thuế', application.tax_code || '-'],
          ['Mã kinh doanh', application.business_code || '-'],
          ['Số giấy phép', application.business_license_number],
          ['Loại đăng ký', applicantTypeLabel(application.applicant_type)],
        ]" />

        <InfoPanel title="Ngân hàng" :items="[
          ['Ngân hàng', application.bank_name],
          ['Mã ngân hàng', application.bank_code],
          ['Số tài khoản', application.account_number],
          ['Chủ tài khoản', application.account_holder_name],
          ['Chi nhánh', application.bank_branch || '-'],
          ['Xác minh', bankVerificationLabel(application.bank_verification_status)],
        ]" />

        <InfoPanel class="wide" title="Cụm sân" :items="[
          ['Tên cụm sân', application.venue_name],
          ['Địa chỉ sân', application.venue_address],
          ['Tỉnh/Thành phố', application.venue_province],
          ['Phường/Xã', application.venue_ward],
          ['Tọa độ', coordinateText(application)],
          ['Google Maps', application.venue_map_url || '-'],
          ['Liên hệ sân', contactText(application)],
          ['Giờ mở cửa', application.expected_opening_hours || '-'],
          ['Tiện ích', compactList(application.amenities)],
          ['Số sân con', application.court_count_total || application.courts_count || 0],
          ['Giá cơ bản', money(application.base_price_per_hour)],
          ['Ghi chú trạng thái', application.status_reason || '-'],
        ]" />
      </section>

      <section v-if="activeTab === 'courts'" class="content-card">
        <div class="card-head">
          <h3>Sân quản lý</h3>
          <span>{{ application.courts?.length || 0 }} sân</span>
        </div>

        <div class="court-grid">
          <article v-for="court in application.courts || []" :key="court.id || court.name" class="court-card">
            <strong>{{ court.name }}</strong>
            <span>{{ courtTypeName(court) }}</span>
            <p v-if="court.note">{{ court.note }}</p>
          </article>
        </div>

        <p v-if="!application.courts?.length" class="empty-text">Hồ sơ chưa có sân con.</p>
      </section>

      <section v-if="activeTab === 'documents'" class="documents-grid">
        <article class="content-card">
          <div class="card-head">
            <h3>Văn bản hệ thống</h3>
            <span>{{ generatedDocuments.length }} văn bản</span>
          </div>

          <div class="doc-list">
            <div v-for="document in generatedDocuments" :key="document.id" class="doc-row">
              <div>
                <strong>{{ document.title || documentTypeLabel(document.document_type) }}</strong>
                <p>{{ document.document_code }} · {{ documentStatusLabel(document.status) }} · {{ signatureSummary(document.signatures) }}</p>
              </div>
              <div class="row-actions">
                <button class="btn primary small icon-only" title="Xem" type="button" @click="openDocument(document)">
                  <AppIcon name="eye" size="15" />
                </button>
                <a class="btn ghost small icon-only" title="Tải xuống" :href="document.download_url" target="_blank" rel="noopener">
                  <AppIcon name="download" size="15" />
                </a>
              </div>
            </div>
            <p v-if="!generatedDocuments.length" class="empty-text">Chưa có văn bản hệ thống.</p>
          </div>
        </article>

        <article class="content-card">
          <div class="card-head">
            <h3>Tài liệu phụ lục</h3>
            <span>{{ uploadedDocuments.length }} file</span>
          </div>

          <div class="doc-list">
            <div v-for="document in uploadedDocuments" :key="document.id" class="doc-row">
              <div>
                <strong>{{ document.title || uploadedTypeLabel(document.document_type) }}</strong>
                <p>{{ document.file_name || uploadedTypeLabel(document.document_type) }} · {{ fileSize(document.file_size) }}</p>
              </div>
              <div class="row-actions">
                <button class="btn primary small icon-only" title="Xem" type="button" @click="openDocument(document, 'uploaded')">
                  <AppIcon name="eye" size="15" />
                </button>
                <a class="btn ghost small icon-only" title="Tải xuống" :href="document.download_url" target="_blank" rel="noopener">
                  <AppIcon name="download" size="15" />
                </a>
              </div>
            </div>
            <p v-if="!uploadedDocuments.length" class="empty-text">Chưa có tài liệu phụ lục.</p>
          </div>
        </article>
      </section>

      <section v-if="activeTab === 'history'" class="content-card">
        <div class="card-head">
          <h3>Lịch sử xử lý</h3>
          <span>{{ application.status_histories?.length || 0 }} mốc</span>
        </div>

        <div class="timeline">
          <article v-for="item in application.status_histories || []" :key="`${item.new_status}-${item.created_at}`" class="timeline-row">
            <span></span>
            <div>
              <strong>{{ statusLabel(item.new_status) }}</strong>
              <p>{{ formatDate(item.created_at) }} · {{ item.changed_by?.full_name || item.actor_type || '-' }}</p>
              <p v-if="item.reason">{{ item.reason }}</p>
            </div>
          </article>
          <p v-if="!application.status_histories?.length" class="empty-text">Chưa có lịch sử xử lý.</p>
        </div>
      </section>

      <section v-if="activeTab === 'settlement'" class="content-card">
        <div class="card-head">
          <h3>Quyết toán và chấm dứt</h3>
          <span>{{ application.termination_requests?.length || 0 }} yêu cầu</span>
        </div>

        <div class="doc-list">
          <div v-for="request in application.termination_requests || []" :key="request.id" class="doc-row">
            <div>
              <strong>{{ request.termination_code || 'Yêu cầu chấm dứt' }}</strong>
              <p>{{ terminationStatusLabel(request.status) }} · {{ request.reason || '-' }}</p>
            </div>
          </div>
          <p v-if="!application.termination_requests?.length" class="empty-text">Chưa có yêu cầu chấm dứt hoặc quyết toán.</p>
        </div>
      </section>

    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, defineComponent, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '../../components/AppIcon.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const message = ref('');
const actionError = ref('');
const application = ref(null);
const courtTypes = ref([]);
const activeTab = ref('overview');
const actionMode = ref(route.query.action || '');
const fieldErrors = reactive({});

const approveForm = reactive({
  initial_court_name: '',
  court_type_id: '',
  review_note: '',
});

const rejectForm = reactive({
  reason: '',
});

const tabs = [
  { value: 'overview', label: 'Tổng quan' },
  { value: 'courts', label: 'Sân quản lý' },
  { value: 'documents', label: 'Tài liệu & văn bản' },
  { value: 'history', label: 'Lịch sử' },
  { value: 'settlement', label: 'Quyết toán' },
];

const generatedDocuments = computed(() => (application.value?.documents || []).filter(d => d.source !== 'uploaded'));
const uploadedDocuments = computed(() => application.value?.uploaded_documents || []);
const requiresInitialCourt = computed(() => !(application.value?.courts || []).length);
const leafCourtTypes = computed(() => courtTypes.value.filter((type) => type.is_active !== false && Number(type.children_count || 0) === 0));
const pendingSportgoDocument = computed(() => generatedDocuments.value.find((document) => (
  document.document_type === 'partner_contract' && document.status === 'pending_sportgo_signature'
)) || null);

onMounted(async () => {
  await Promise.all([loadApplication(), loadCourtTypes()]);
});

async function loadApplication() {
  loading.value = true;
  error.value = '';

  try {
    const response = await adminPartnerApplicationService.show(route.params.id);
    application.value = response.data;
  } catch (err) {
    error.value = err.message || 'Không tải được hồ sơ đối tác.';
  } finally {
    loading.value = false;
  }
}

async function loadCourtTypes() {
  try {
    const response = await adminPartnerApplicationService.courtTypes();
    courtTypes.value = response.data || [];
  } catch {
    courtTypes.value = [];
  }
}

function openDocument(doc, type = 'generated') {
  router.push({
    name: 'admin-partner-application-document',
    params: { id: application.value.id, documentId: doc.id },
    query: type === 'uploaded' ? { type: 'uploaded' } : {},
  });
}

function clearAction() {
  actionMode.value = '';
  actionError.value = '';
  clearFieldErrors();
}

async function submitApprove() {
  clearAlerts();
  if (!validateApprove()) return;

  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.approve(application.value.id, {
      initial_court_name: approveForm.initial_court_name,
      court_type_id: approveForm.court_type_id,
      review_note: approveForm.review_note,
    });
    message.value = response.message || 'Đã duyệt hồ sơ và tạo hợp đồng.';
    application.value = response.data;
    clearAction();
  } catch (err) {
    applyActionError(err, 'Không duyệt được hồ sơ.');
  } finally {
    saving.value = false;
  }
}

async function submitReject() {
  clearAlerts();
  clearFieldErrors();

  if (!rejectForm.reason) {
    fieldErrors.reason = 'Vui lòng nhập lý do từ chối.';
    return;
  }

  saving.value = true;
  try {
    const response = await adminPartnerApplicationService.reject(application.value.id, { reason: rejectForm.reason });
    message.value = response.message || 'Đã từ chối hồ sơ.';
    application.value = response.data;
    clearAction();
  } catch (err) {
    applyActionError(err, 'Không từ chối được hồ sơ.');
  } finally {
    saving.value = false;
  }
}

function validateApprove() {
  clearFieldErrors();

  if (requiresInitialCourt.value && !approveForm.initial_court_name) {
    fieldErrors.initial_court_name = 'Vui lòng nhập tên sân con ban đầu.';
  }

  if (requiresInitialCourt.value && !approveForm.court_type_id) {
    fieldErrors.court_type_id = 'Vui lòng chọn loại sân con.';
  }

  return !Object.keys(fieldErrors).length;
}

function clearAlerts() {
  message.value = '';
  error.value = '';
  actionError.value = '';
  clearFieldErrors();
}

function clearFieldErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
}

function applyActionError(err, fallback) {
  const errors = err.data?.errors || {};
  Object.entries(errors).forEach(([key, value]) => {
    fieldErrors[key] = Array.isArray(value) ? value[0] : value;
  });
  actionError.value = err.message || fallback;
}

function isReviewable(status) {
  return ['pending', 'reviewing', 'submitted', 'need_supplement'].includes(status);
}

const InfoPanel = defineComponent({
  props: { title: String, items: Array },
  setup(props) {
    return () => h('article', { class: 'info-panel' }, [
      h('h3', props.title),
      h('dl', (props.items || []).flatMap(([label, value]) => [
        h('dt', label),
        h('dd', value || '-'),
      ])),
    ]);
  },
});

function statusLabel(status) {
  return {
    draft: 'Chờ ký đơn',
    pending: 'Chờ duyệt',
    submitted: 'Chờ duyệt',
    reviewing: 'Đang xem xét',
    need_supplement: 'Cần bổ sung',
    contract_pending_sportgo_signature: 'Chờ SportGo ký',
    contract_pending_owner_signature: 'Chờ chủ sân ký',
    completed: 'Hoàn tất',
    rejected: 'Từ chối',
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function identityLabel(value) {
  return { cccd: 'CCCD', cmnd: 'CMND', passport: 'Hộ chiếu' }[value] || value || '-';
}

function applicantTypeLabel(value) {
  return { individual: 'Cá nhân', business: 'Hộ kinh doanh', company: 'Doanh nghiệp' }[value] || value || '-';
}

function bankVerificationLabel(status) {
  return {
    verified: 'Đã xác minh',
    pending: 'Chưa xác minh',
    lookup_not_configured: 'Chưa cấu hình tra cứu',
    name_mismatch: 'Tên chủ tài khoản không khớp',
    not_found: 'Không tìm thấy tài khoản',
    provider_unavailable: 'Dịch vụ xác minh lỗi',
  }[status] || status || '-';
}

function documentTypeLabel(type) {
  return {
    partner_application_form: 'Đơn đăng ký đối tác',
    partner_contract: 'Giấy/hợp đồng đối tác kinh doanh',
    termination_request: 'Đơn yêu cầu chấm dứt',
    mutual_liquidation_minutes: 'Biên bản thanh lý',
    unilateral_termination_notice: 'Công văn chấm dứt',
    settlement_minutes: 'Biên bản quyết toán',
  }[type] || type || 'Văn bản';
}

function uploadedTypeLabel(type) {
  return {
    identity: 'CCCD/CMND/Hộ chiếu',
    business_license: 'Giấy đăng ký kinh doanh',
    facility: 'Ảnh cơ sở/sân',
    bank: 'Chứng từ ngân hàng',
    lease: 'Hợp đồng thuê mặt bằng',
    additional: 'Tài liệu bổ sung',
  }[type] || type || 'Tài liệu';
}

function documentStatusLabel(status) {
  return {
    generated: 'Đã sinh',
    pending_owner_signature: 'Chờ chủ sân ký',
    pending_sportgo_signature: 'Chờ SportGo ký',
    completed: 'Hoàn tất',
  }[status] || status || '-';
}

function terminationStatusLabel(status) {
  return {
    submitted: 'Chờ xác nhận',
    reviewing: 'Đang xem xét',
    transition_period: 'Giai đoạn chuyển tiếp',
    completed: 'Đã hoàn tất',
  }[status] || status || '-';
}

function signatureSummary(signatures = []) {
  if (!signatures?.length) return 'Chưa có chữ ký';
  return signatures
    .filter((signature) => signature.status === 'signed')
    .map((signature) => `${signature.signer_side === 'sportgo' ? 'SportGo' : 'Chủ sân'}: ${formatDate(signature.signed_at)}`)
    .join(' · ') || 'Chưa có chữ ký';
}

function courtTypeName(court) {
  return court?.court_type?.name || court?.courtType?.name || court?.court_type_name_snapshot || 'Loại sân';
}

function coordinateText(item) {
  return item?.venue_latitude && item?.venue_longitude ? `${item.venue_latitude}, ${item.venue_longitude}` : '-';
}

function contactText(item) {
  return [item?.venue_phone, item?.venue_email].filter(Boolean).join(' · ') || '-';
}

function compactList(value) {
  return Array.isArray(value) ? value.filter(Boolean).join(', ') || '-' : value || '-';
}

function money(value) {
  const number = Number(value || 0);
  return number > 0
    ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(number)
    : '-';
}

function fileSize(value) {
  const bytes = Number(value || 0);
  if (!bytes) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

function dateOnly(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('vi-VN');
}
</script>

<style scoped>
.partner-detail-page {
  display: grid;
  gap: 16px;
  max-width: 1280px;
}

.page-head,
.action-strip,
.panel-head,
.card-head,
.form-actions,
.row-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.title-block {
  flex: 1;
  min-width: 0;
}

.title-block p {
  margin: 0 0 4px;
  color: #047857;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.title-block h2 {
  margin: 0;
  color: var(--admin-text, #0f172a);
  font-size: 24px;
}

.notice,
.state-card,
.action-strip,
.review-panel,
.info-panel,
.content-card {
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  background: var(--admin-surface, #fff);
}

.notice,
.state-card {
  padding: 14px 16px;
  font-weight: 750;
}

.notice.success {
  color: #166534;
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.notice.error,
.state-card.error {
  color: #991b1b;
  background: #fef2f2;
  border-color: #fecaca;
}

.state-card {
  color: var(--admin-faint, #64748b);
  text-align: center;
  padding: 36px;
}

.action-strip,
.review-panel,
.content-card,
.info-panel {
  padding: 16px;
}

.action-strip strong,
.action-strip span {
  display: block;
}

.action-strip span {
  margin-top: 4px;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

.action-buttons,
.row-actions {
  flex-wrap: wrap;
}

.tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.tabs button {
  min-height: 38px;
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  padding: 0 13px;
  background: var(--admin-surface, #fff);
  color: var(--admin-faint, #64748b);
  font-weight: 850;
  cursor: pointer;
  white-space: nowrap;
}

.tabs button.active {
  border-color: #0f172a;
  background: #0f172a;
  color: #fff;
}

.panel-head {
  align-items: flex-start;
  margin-bottom: 14px;
}

.panel-head h3,
.info-panel h3,
.content-card h3 {
  margin: 0;
  color: var(--admin-text, #0f172a);
  font-size: 16px;
}

.panel-head p {
  margin: 5px 0 0;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

.review-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.field {
  display: grid;
  gap: 6px;
}

.field span {
  color: var(--admin-text, #0f172a);
  font-size: 13px;
  font-weight: 850;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--admin-border, #dbe3ef);
  border-radius: 8px;
  padding: 10px 11px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #0f172a);
  font: inherit;
}

.field.invalid input,
.field.invalid select,
.field.invalid textarea {
  border-color: #dc2626;
}

.field small,
.inline-error {
  color: #b91c1c;
  font-size: 12px;
  font-weight: 750;
}

.full,
.wide {
  grid-column: 1 / -1;
}

.form-actions {
  justify-content: flex-end;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

dl {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
  gap: 9px 14px;
  margin: 14px 0 0;
}

dt {
  color: var(--admin-faint, #64748b);
  font-size: 13px;
}

dd {
  margin: 0;
  color: var(--admin-text, #111827);
  font-weight: 750;
  overflow-wrap: anywhere;
}

.card-head {
  margin-bottom: 12px;
}

.card-head span {
  color: var(--admin-faint, #64748b);
  font-size: 12px;
  font-weight: 850;
}

.court-grid,
.documents-grid {
  display: grid;
  gap: 14px;
}

.documents-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
}

.court-grid {
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}

.court-card,
.doc-row {
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 8px;
  padding: 13px;
  background: var(--admin-surface-muted, #f8fafc);
}

.court-card {
  display: grid;
  gap: 5px;
}

.court-card span,
.court-card p,
.doc-row p,
.timeline-row p,
.empty-text {
  margin: 4px 0 0;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
  line-height: 1.45;
}

.doc-list {
  display: grid;
  gap: 10px;
}

.doc-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.timeline {
  display: grid;
  gap: 12px;
}

.timeline-row {
  display: grid;
  grid-template-columns: 13px minmax(0, 1fr);
  gap: 10px;
}

.timeline-row > span {
  width: 10px;
  height: 10px;
  margin-top: 5px;
  border-radius: 999px;
  background: #0f172a;
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-weight: 850;
  cursor: pointer;
  text-decoration: none;
}

.btn {
  min-height: 38px;
  padding: 0 13px;
}

.btn.small {
  min-height: 34px;
  padding: 0 11px;
  font-size: 13px;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: transparent;
  color: var(--admin-faint, #64748b);
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.ghost {
  background: var(--admin-surface, #fff);
  border-color: var(--admin-border, #e5e7eb);
  color: var(--admin-text, #334155);
}

.btn.danger {
  background: #fee2e2;
  color: #b91c1c;
  border-color: #fecaca;
}

.btn:disabled {
  opacity: .58;
  cursor: not-allowed;
}

.status {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  border-radius: 999px;
  padding: 0 11px;
  background: #fef3c7;
  color: #92400e;
  font-size: 12px;
  font-weight: 900;
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

@media (max-width: 980px) {
  .summary-grid,
  .documents-grid,
  .review-form {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  .page-head,
  .action-strip,
  .doc-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .action-buttons,
  .row-actions,
  .btn {
    width: 100%;
  }

  dl {
    grid-template-columns: 1fr;
  }
}
</style>
