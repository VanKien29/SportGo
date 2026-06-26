<template>
  <div class="partner-detail-shell">
    <PublicNavbar />
    <main class="partner-detail">
      <header class="page-head">
        <button class="btn ghost" type="button" @click="$router.push({ name: 'partner-application' })">
          <AppIcon name="arrowLeft" size="16" />
          Quay lại
        </button>
        <div>
          <p class="eyebrow">Hồ sơ đối tác</p>
          <h1>{{ application?.venue_name || 'Đang tải hồ sơ' }}</h1>
        </div>
        <span v-if="application" class="status" :class="`status-${application.status}`">{{ statusLabel(application.status) }}</span>
      </header>

      <div v-if="loading" class="state">Đang tải hồ sơ...</div>
      <div v-else-if="error" class="state error">{{ error }}</div>

      <template v-else-if="application">
        <section class="summary-grid">
          <InfoPanel title="Người đăng ký" :items="[
            ['Họ tên', application.applicant_full_name],
            ['Điện thoại', application.applicant_phone],
            ['Email', application.applicant_email],
            ['Ngày sinh', dateOnly(application.applicant_birth_date)],
            ['Địa chỉ liên hệ', application.applicant_address],
          ]" />
          <InfoPanel title="Giấy tờ & pháp lý" :items="[
            ['Người đại diện', application.representative_name],
            ['Loại giấy tờ', identityLabel(application.representative_identity_type)],
            ['Số giấy tờ', application.representative_identity_number],
            ['Tên đơn vị', application.business_name],
            ['Mã số thuế', application.tax_code || '-'],
            ['Địa chỉ pháp lý', application.business_address],
          ]" />
          <InfoPanel title="Ngân hàng" :items="[
            ['Ngân hàng', application.bank_name],
            ['Số tài khoản', application.account_number],
            ['Chủ tài khoản', application.account_holder_name],
            ['Chi nhánh', application.bank_branch || '-'],
          ]" />
          <InfoPanel title="Cụm sân" :items="[
            ['Địa chỉ', application.venue_address],
            ['Tỉnh/Thành phố', application.venue_province],
            ['Phường/Xã', application.venue_ward],
            ['Tọa độ', coordinateText(application)],
            ['Giá cơ bản', money(application.base_price_per_hour)],
          ]" />
        </section>

        <section class="section">
          <div class="section-head">
            <h2>Văn bản hệ thống</h2>
          </div>
          <div class="doc-list">
            <article v-for="document in generatedDocuments" :key="document.id" class="doc-row">
              <div>
                <strong>{{ document.title || documentTypeLabel(document.document_type) }}</strong>
                <p>{{ document.document_code }} · {{ documentStatusLabel(document.status) }} · {{ signatureSummary(document.signatures) }}</p>
              </div>
              <div class="row-actions">
                <button class="btn primary small icon-only" title="Xem / Ký" type="button" @click="openDocument(document)">
                  <AppIcon name="eye" size="16" />
                </button>
                <a class="btn ghost small icon-only" title="Tải xuống" :href="document.download_url" target="_blank" rel="noopener">
                  <AppIcon name="download" size="16" />
                </a>
              </div>
            </article>
            <p v-if="!generatedDocuments.length" class="empty">Chưa có văn bản nào.</p>
          </div>
        </section>

        <section class="section">
          <div class="section-head">
            <h2>Tài liệu phụ lục</h2>
          </div>
          <div class="doc-list">
            <article v-for="document in uploadedDocuments" :key="document.id" class="doc-row">
              <div>
                <strong>{{ document.title || uploadedTypeLabel(document.document_type) }}</strong>
                <p>{{ document.file_name || uploadedTypeLabel(document.document_type) }} · {{ fileSize(document.file_size) }}</p>
              </div>
              <div class="row-actions">
                <button class="btn primary small icon-only" title="Xem" type="button" @click="openUploadedDocument(document)">
                  <AppIcon name="eye" size="16" />
                </button>
                <a class="btn ghost small icon-only" title="Tải xuống" :href="document.download_url" target="_blank" rel="noopener">
                  <AppIcon name="download" size="16" />
                </a>
              </div>
            </article>
            <p v-if="!uploadedDocuments.length" class="empty">Chưa có tài liệu phụ lục.</p>
          </div>
        </section>

        <section class="section">
          <div class="section-head">
            <h2>Danh sách sân con</h2>
          </div>
          <div class="court-grid">
            <article v-for="court in application.courts || []" :key="court.id || court.name" class="court-row">
              <strong>{{ court.name }}</strong>
              <span>{{ court.court_type?.name || court.courtType?.name || court.court_type_name_snapshot || 'Loại sân' }}</span>
            </article>
          </div>
        </section>

        <section class="section">
          <div class="section-head">
            <h2>Lịch sử xử lý</h2>
          </div>
          <div class="timeline">
            <article v-for="item in application.status_histories || []" :key="`${item.new_status}-${item.created_at}`" class="timeline-row">
              <span></span>
              <div>
                <strong>{{ statusLabel(item.new_status) }}</strong>
                <p>{{ formatDate(item.created_at) }} · {{ item.changed_by?.full_name || item.actor_type }}</p>
                <p v-if="item.reason">{{ item.reason }}</p>
              </div>
            </article>
          </div>
        </section>

      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const application = ref(null);

const generatedDocuments = computed(() => {
  const docs = [...(application.value?.generated_documents || application.value?.generatedDocuments || [])];
  for (const contract of application.value?.contracts || []) {
    const doc = contract.generated_document || contract.generatedDocument;
    if (doc && !docs.some((item) => item.id === doc.id)) docs.push({ ...doc, partner_contract_id: contract.id });
  }
  return docs;
});
const uploadedDocuments = computed(() => application.value?.documents || application.value?.uploaded_documents || []);

onMounted(loadApplication);

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

function openDocument(doc) {
  router.push({ name: 'partner-application-document', params: { id: application.value.id, documentId: doc.id } });
}

function openUploadedDocument(document) {
  router.push({
    name: 'partner-application-document',
    params: { id: application.value.id, documentId: document.id },
    query: { type: 'uploaded' },
  });
}

const InfoPanel = defineComponent({
  props: { title: String, items: Array },
  setup(props) {
    return () => h('section', { class: 'info-panel' }, [
      h('h2', props.title),
      h('dl', props.items.flatMap(([label, value]) => [
        h('dt', label),
        h('dd', value || '-'),
      ])),
    ]);
  },
});

function identityLabel(value) {
  return { cccd: 'CCCD', cmnd: 'CMND', passport: 'Hộ chiếu' }[value] || value || '-';
}
function coordinateText(item) {
  return item?.venue_latitude && item?.venue_longitude ? `${item.venue_latitude}, ${item.venue_longitude}` : '-';
}
function statusLabel(status) {
  return { draft: 'Chờ ký đơn', submitted: 'Chờ xét duyệt', pending: 'Chờ xét duyệt', reviewing: 'Đang xem xét', need_supplement: 'Cần bổ sung', approved_pending_contract: 'Đã duyệt, chờ hợp đồng', contract_pending_sportgo_signature: 'Chờ SportGo ký', contract_pending_owner_signature: 'Chờ chủ sân ký hợp đồng', completed: 'Đang hoạt động', rejected: 'Bị từ chối', cancelled: 'Đã hủy' }[status] || status || '-';
}
function documentTypeLabel(type) {
  return { partner_application_form: 'Đơn đăng ký đối tác', partner_contract: 'Hợp đồng đối tác kinh doanh' }[type] || type;
}
function uploadedTypeLabel(type) {
  return { identity: 'CCCD/CMND/Hộ chiếu', business_license: 'Giấy đăng ký kinh doanh', facility: 'Ảnh cơ sở/sân', bank: 'Chứng từ ngân hàng', lease: 'Hợp đồng thuê mặt bằng', additional: 'Tài liệu bổ sung' }[type] || type || 'Tài liệu';
}
function documentStatusLabel(status) {
  return { generated: 'Đã sinh', pending_owner_signature: 'Chờ chủ sân ký', pending_sportgo_signature: 'Chờ SportGo ký', completed: 'Hoàn tất' }[status] || status || '-';
}
function signatureSummary(signatures = []) {
  if (!signatures?.length) return 'Chưa có chữ ký';
  return signatures.map((sig) => `${sig.signer_side === 'sportgo' ? 'SportGo' : 'Chủ sân'}: ${formatDate(sig.signed_at)}`).join(' · ');
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

<style scoped>
.partner-detail-shell { min-height: 100vh; background: #f8fafc; }
.partner-detail { max-width: 1180px; margin: 0 auto; padding: 108px 20px 56px; }
.page-head { display: flex; align-items: center; gap: 16px; justify-content: space-between; margin-bottom: 22px; }
.page-head h1 { margin: 2px 0 0; font-size: 26px; color: #0f172a; }
.eyebrow { margin: 0; font-size: 12px; color: #059669; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.info-panel, .section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px; }
.info-panel h2, .section h2 { margin: 0; font-size: 15px; color: #0f172a; }
dl { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 9px 14px; margin: 14px 0 0; }
dt { color: #64748b; font-size: 13px; }
dd { margin: 0; color: #111827; font-weight: 700; overflow-wrap: anywhere; }
.section { margin-top: 16px; }
.section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.doc-list { display: flex; flex-direction: column; gap: 10px; }
.doc-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
.row-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.doc-row p, .timeline-row p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.court-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.court-row { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 4px; }
.court-row span { color: #64748b; font-size: 13px; }
.timeline { display: flex; flex-direction: column; gap: 12px; }
.timeline-row { display: grid; grid-template-columns: 12px minmax(0, 1fr); gap: 10px; }
.timeline-row > span { width: 10px; height: 10px; border-radius: 999px; background: #0f172a; margin-top: 5px; }
.btn { min-height: 38px; display: inline-flex; align-items: center; gap: 8px; border-radius: 8px; padding: 0 13px; font-weight: 800; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.btn.primary { background: #0f172a; color: #fff; }
.btn.ghost { background: #fff; border-color: #e5e7eb; color: #334155; }
.status { display: inline-flex; border-radius: 999px; padding: 6px 11px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 900; }
.status-completed { background: #dcfce7; color: #166534; }
.status-rejected, .status-cancelled { background: #fee2e2; color: #991b1b; }
.state { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 40px; text-align: center; color: #64748b; }
.state.error { color: #991b1b; }
.empty { color: #64748b; }
@media (max-width: 800px) {
  .page-head, .doc-row { align-items: flex-start; flex-direction: column; }
  .row-actions { width: 100%; justify-content: flex-start; }
  .summary-grid { grid-template-columns: 1fr; }
  dl { grid-template-columns: 1fr; }
}
</style>
