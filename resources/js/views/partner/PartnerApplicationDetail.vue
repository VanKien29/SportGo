<template>
  <div class="partner-review-page">
    <PublicNavbar />

    <main class="partner-review-main">
      <header class="partner-page-header">
        <button class="btn btn-secondary" type="button" @click="router.push({ name: 'partner-application' })">
          <AppIcon name="arrow-left" size="16" />
          Quay lại
        </button>

        <div class="partner-page-title">
          <p>Hồ sơ đối tác</p>
          <h1>{{ application?.venue_name || 'Đang tải hồ sơ' }}</h1>
          <small v-if="application">{{ application.venue_address || 'Chưa có địa chỉ cụm sân' }}</small>
        </div>

        <span v-if="application" class="partner-status-pill" :class="application.status">
          {{ statusLabel(application.status) }}
        </span>
      </header>

      <section v-if="loading" class="partner-card">
        <div class="partner-card-body partner-empty">Đang tải hồ sơ...</div>
      </section>

      <section v-else-if="error" class="partner-card">
        <div class="partner-card-body partner-field-error">{{ error }}</div>
      </section>

      <template v-else-if="application">
        <div class="partner-layout-grid">
          <div>
            <section v-if="application.status === 'need_supplement'" class="partner-card">
              <div class="partner-card-head">
                <div>
                  <h2>Bổ sung hồ sơ</h2>
                  <p>SportGo cần thêm giấy tờ hoặc giải trình trước khi xét duyệt tiếp.</p>
                </div>
              </div>
              <div class="partner-card-body">
                <div v-if="application.status_reason" class="partner-alert">
                  <strong>Nội dung cần bổ sung</strong>
                  <p>{{ application.status_reason }}</p>
                </div>

                <form class="partner-supplement-form" @submit.prevent="submitSupplement">
                  <label class="partner-field">
                    Phản hồi của bạn
                    <textarea v-model.trim="supplementNote" rows="4" placeholder="Nhập nội dung giải trình hoặc ghi chú cho giấy tờ bổ sung"></textarea>
                  </label>

                  <label class="partner-field">
                    Giấy tờ bổ sung
                    <input ref="supplementFileInput" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" @change="handleSupplementFiles" />
                  </label>

                  <div v-if="supplementFiles.length" class="partner-file-chips">
                    <span v-for="file in supplementFiles" :key="`${file.name}-${file.size}`">{{ file.name }}</span>
                  </div>

                  <p v-if="supplementError" class="partner-field-error">{{ supplementError }}</p>

                  <div class="partner-action-row">
                    <button class="btn btn-secondary" type="button" @click="clearSupplementFiles">Xóa file</button>
                    <button class="btn btn-primary" type="submit" :disabled="submittingSupplement">
                      <AppIcon name="send" size="16" />
                      {{ submittingSupplement ? 'Đang gửi...' : 'Gửi bổ sung' }}
                    </button>
                  </div>
                </form>
              </div>
            </section>

            <section class="partner-card">
              <div class="partner-card-head">
                <h2>Thông tin hồ sơ</h2>
              </div>
              <div class="partner-card-body">
                <div class="partner-info-grid">
                  <InfoBox title="Người đăng ký" :items="[
                    ['Họ tên', application.applicant_full_name],
                    ['Điện thoại', application.applicant_phone],
                    ['Email', application.applicant_email],
                    ['Ngày sinh', dateOnly(application.applicant_birth_date)],
                    ['Địa chỉ liên hệ', application.applicant_address],
                  ]" />

                  <InfoBox title="Giấy tờ và pháp lý" :items="[
                    ['Người đại diện', application.representative_name],
                    ['Loại giấy tờ', identityLabel(application.representative_identity_type)],
                    ['Số giấy tờ', application.representative_identity_number],
                    ['Tên đơn vị', application.business_name],
                    ['Mã số thuế', application.tax_code || '-'],
                    ['Địa chỉ pháp lý', application.business_address],
                  ]" />

                  <InfoBox title="Ngân hàng" :items="[
                    ['Ngân hàng', application.bank_name],
                    ['Số tài khoản', application.account_number],
                    ['Chủ tài khoản', application.account_holder_name],
                    ['Chi nhánh', application.bank_branch || '-'],
                  ]" />

                  <InfoBox title="Cụm sân" :items="[
                    ['Địa chỉ', application.venue_address],
                    ['Tỉnh/Thành phố', application.venue_province],
                    ['Phường/Xã', application.venue_ward],
                    ['Tọa độ', coordinateText(application)],
                    ['Giá cơ bản', money(application.base_price_per_hour)],
                  ]" />
                </div>
              </div>
            </section>

            <section class="partner-card">
              <div class="partner-card-head">
                <h2>Văn bản hệ thống</h2>
              </div>
              <div class="partner-card-body">
                <div v-if="generatedDocuments.length" class="partner-document-list">
                  <article v-for="document in generatedDocuments" :key="document.id" class="partner-doc-row">
                    <div>
                      <strong>{{ document.title || documentTypeLabel(document.document_type) }}</strong>
                      <p>{{ document.document_code || 'Chưa có mã' }} · {{ documentStatusLabel(document.status) }} · {{ signatureSummary(document.signatures) }}</p>
                      <p v-if="!document.download_url" class="partner-doc-warning">File văn bản chưa sẵn sàng hoặc bản cũ đã mất file.</p>
                    </div>
                    <div class="partner-doc-actions">
                      <button class="btn btn-secondary" type="button" :disabled="!document.download_url" @click="previewDocument(document)">
                        <AppIcon name="eye" size="16" />
                        Xem
                      </button>
                      <button v-if="canOpenSigning(document)" class="btn btn-primary" type="button" @click="openDocumentPage(document)">
                        <AppIcon name="pencil" size="16" />
                        Ký
                      </button>
                      <button v-if="document.download_url" class="btn btn-outline icon-only" type="button" title="Tải file" @click="downloadFile(document.download_url)">
                        <AppIcon name="download" size="16" />
                      </button>
                    </div>
                  </article>
                </div>
                <p v-else class="partner-empty">Chưa có văn bản hệ thống.</p>
              </div>
            </section>

            <section class="partner-card">
              <div class="partner-card-head">
                <h2>Tài liệu phụ lục</h2>
              </div>
              <div class="partner-card-body">
                <div v-if="uploadedDocuments.length" class="partner-document-list">
                  <article v-for="document in uploadedDocuments" :key="document.id" class="partner-doc-row">
                    <div>
                      <strong>{{ document.title || uploadedTypeLabel(document.document_type) }}</strong>
                      <p>{{ document.file_name || uploadedTypeLabel(document.document_type) }} · {{ fileSize(document.file_size) }}</p>
                    </div>
                    <div class="partner-doc-actions">
                      <button class="btn btn-secondary" type="button" @click="previewDocument({ ...document, source: 'uploaded' })">
                        <AppIcon name="eye" size="16" />
                        Xem
                      </button>
                      <button class="btn btn-outline icon-only" type="button" title="Tải file" @click="downloadFile(document.download_url)">
                        <AppIcon name="download" size="16" />
                      </button>
                    </div>
                  </article>
                </div>
                <p v-else class="partner-empty">Chưa có tài liệu phụ lục.</p>
              </div>
            </section>

            <section class="partner-card">
              <div class="partner-card-head">
                <h2>Lịch sử xử lý</h2>
              </div>
              <div class="partner-card-body">
                <div v-if="application.status_histories?.length" class="partner-timeline-list">
                  <article v-for="item in application.status_histories" :key="`${item.new_status}-${item.created_at}`" class="partner-timeline-row">
                    <div>
                      <strong>{{ statusLabel(item.new_status) }}</strong>
                      <p>{{ formatDate(item.created_at) }} · {{ item.changed_by?.full_name || item.actor_type || 'Hệ thống' }}</p>
                      <p v-if="item.reason">{{ item.reason }}</p>
                    </div>
                  </article>
                </div>
                <p v-else class="partner-empty">Chưa có lịch sử xử lý.</p>
              </div>
            </section>
          </div>

          <aside>
            <section class="partner-card">
              <div class="partner-card-head">
                <h3>Thao tác nhanh</h3>
              </div>
              <div class="partner-card-body partner-action-stack">
                <button v-if="applicationForm" class="btn btn-primary" type="button" @click="openDocumentPage(applicationForm)">
                  <AppIcon name="fileText" size="16" />
                  Đơn đăng ký
                </button>
                <button v-if="contractDocument" class="btn btn-primary" type="button" @click="openDocumentPage(contractDocument)">
                  <AppIcon name="fileText" size="16" />
                  Hợp đồng đối tác
                </button>
                <button class="btn btn-secondary" type="button" @click="loadApplication">
                  <AppIcon name="refresh" size="16" />
                  Làm mới
                </button>
              </div>
            </section>

            <section class="partner-card">
              <div class="partner-card-head">
                <h3>Danh sách sân con</h3>
              </div>
              <div class="partner-card-body">
                <div v-if="application.courts?.length" class="partner-court-list">
                  <article v-for="court in application.courts" :key="court.id || court.name" class="partner-court-row">
                    <div>
                      <strong>{{ court.name }}</strong>
                      <p>{{ court.court_type?.name || court.courtType?.name || court.court_type_name_snapshot || 'Loại sân' }}</p>
                    </div>
                  </article>
                </div>
                <p v-else class="partner-empty">Chưa có sân con.</p>
              </div>
            </section>
          </aside>
        </div>
      </template>
    </main>

    <PartnerFilePreviewDialog
      :show="previewOpen"
      :document="previewingDocument"
      @close="previewOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import AppIcon from '../../components/AppIcon.vue';
import PartnerFilePreviewDialog from '../../components/partner/PartnerFilePreviewDialog.vue';
import { api, apiDownload, apiFormData } from '../../services/api.js';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const application = ref(null);
const previewOpen = ref(false);
const previewingDocument = ref(null);
const supplementNote = ref('');
const supplementFiles = ref([]);
const supplementError = ref('');
const submittingSupplement = ref(false);
const supplementFileInput = ref(null);

const generatedDocuments = computed(() => {
  const docs = [...(application.value?.generated_documents || application.value?.generatedDocuments || [])];
  for (const contract of application.value?.contracts || []) {
    const doc = contract.generated_document || contract.generatedDocument;
    if (doc && !docs.some((item) => String(item.id) === String(doc.id))) {
      docs.push({ ...doc, partner_contract_id: contract.id });
    }
  }

  return docs.sort((a, b) => new Date(b.created_at || b.generated_at || 0) - new Date(a.created_at || a.generated_at || 0));
});

const uploadedDocuments = computed(() => application.value?.documents || application.value?.uploaded_documents || []);
const applicationForm = computed(() => generatedDocuments.value.find((doc) => doc.document_type === 'partner_application_form'));
const contractDocument = computed(() => generatedDocuments.value.find((doc) => doc.document_type === 'partner_contract'));

const InfoBox = defineComponent({
  props: { title: String, items: Array },
  setup(props) {
    return () => h('section', { class: 'partner-info-box' }, [
      h('h3', props.title),
      h('dl', { class: 'partner-info-list' }, props.items.flatMap(([label, value]) => [
        h('dt', label),
        h('dd', value || '-'),
      ])),
    ]);
  },
});

onMounted(loadApplication);
watch(() => route.params.id, loadApplication);

async function loadApplication() {
  loading.value = true;
  error.value = '';

  try {
    const response = await api('/api/user/partner-application');
    application.value = (response.data?.history || []).find((item) => String(item.id) === String(route.params.id)) || null;
    if (!application.value) error.value = 'Không tìm thấy hồ sơ.';
  } catch (err) {
    error.value = err.message || 'Không tải được hồ sơ.';
  } finally {
    loading.value = false;
  }
}

function previewDocument(document) {
  if (!document?.download_url && document?.file_available === false) {
    error.value = 'File văn bản này không còn tồn tại. Vui lòng tạo lại văn bản từ hồ sơ.';
    return;
  }

  previewingDocument.value = normalizeDocument(document);
  previewOpen.value = true;
}

function normalizeDocument(document) {
  if (!document) return null;
  if (document.source === 'uploaded') return document;
  const downloadUrl = document.download_url || (document.file_available !== false ? `/api/files/documents/${document.id}/download` : null);
  return {
    ...document,
    source: 'generated',
    download_url: downloadUrl,
  };
}

function openDocumentPage(document) {
  router.push({
    name: 'partner-application-document',
    params: { id: application.value.id, documentId: document.id },
  });
}

function canOpenSigning(document) {
  if (document.source === 'uploaded') return false;
  if (!document.download_url && document.file_available === false) return false;
  if (document.document_type === 'partner_application_form') {
    return application.value?.status === 'draft' && document.status === 'pending_owner_signature';
  }
  if (document.document_type === 'partner_contract') {
    return application.value?.status === 'contract_pending_owner_signature' && document.status === 'pending_owner_signature';
  }
  return false;
}

async function downloadFile(url) {
  if (!url) return;
  try {
    await apiDownload(url);
  } catch (err) {
    error.value = err.message || 'Không tải được file.';
  }
}

function handleSupplementFiles(event) {
  supplementFiles.value = Array.from(event.target.files || []);
  supplementError.value = '';
}

function clearSupplementFiles() {
  supplementFiles.value = [];
  if (supplementFileInput.value) supplementFileInput.value.value = '';
}

async function submitSupplement() {
  if (!application.value) return;
  if (!supplementFiles.value.length) {
    supplementError.value = 'Vui lòng chọn ít nhất một giấy tờ bổ sung.';
    return;
  }

  submittingSupplement.value = true;
  supplementError.value = '';

  try {
    const formData = new FormData();
    if (supplementNote.value) formData.append('note', supplementNote.value);
    supplementFiles.value.forEach((file) => formData.append('additional_documents[]', file));
    const response = await apiFormData(`/api/user/partner-application/${application.value.id}/supplement-documents`, formData);
    application.value = response.data;
    supplementNote.value = '';
    clearSupplementFiles();
  } catch (err) {
    supplementError.value = err.message || 'Không gửi được giấy tờ bổ sung.';
  } finally {
    submittingSupplement.value = false;
  }
}

function identityLabel(value) {
  return { cccd: 'CCCD', cmnd: 'CMND', passport: 'Hộ chiếu' }[value] || value || '-';
}

function coordinateText(item) {
  return item?.venue_latitude && item?.venue_longitude ? `${item.venue_latitude}, ${item.venue_longitude}` : '-';
}

function statusLabel(status) {
  return {
    draft: 'Chờ ký đơn',
    submitted: 'Chờ xét duyệt',
    pending: 'Chờ xét duyệt',
    reviewing: 'Đang xem xét',
    need_supplement: 'Cần bổ sung',
    contract_pending_sportgo_signature: 'Chờ SportGo ký',
    contract_pending_owner_signature: 'Chờ ký hợp đồng',
    completed: 'Đang hoạt động',
    rejected: 'Bị từ chối',
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function documentTypeLabel(type) {
  return {
    partner_application_form: 'Đơn đăng ký đối tác',
    partner_contract: 'Hợp đồng đối tác kinh doanh',
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
    cancelled: 'Đã hủy',
  }[status] || status || '-';
}

function signatureSummary(signatures = []) {
  const signed = (signatures || []).filter((sig) => sig.status === 'signed');
  if (!signed.length) return 'Chưa có chữ ký';
  return signed.map((sig) => `${sig.signer_side === 'sportgo' ? 'SportGo' : 'Chủ sân'} ${formatDate(sig.signed_at)}`).join(' · ');
}

function money(value) {
  const number = Number(value || 0);
  return number > 0 ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(number) : '-';
}

function fileSize(value) {
  const bytes = Number(value || 0);
  if (!bytes) return '-';
  const units = ['B', 'KB', 'MB'];
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

<style>
@import "../../../css/partner/partner.css";
</style>
