<template>
  <div class="partner-document-page">
    <header class="page-head">
      <button class="btn ghost" type="button" @click="goBack">
        <AppIcon name="arrowLeft" size="16" />
        Quay lại hồ sơ
      </button>

      <div class="title-block">
        <p>{{ application?.venue_name || 'Hồ sơ đối tác' }}</p>
        <h2>{{ documentTitle }}</h2>
      </div>

      <button v-if="document?.download_url" class="btn ghost" type="button" @click="downloadCurrentDocument">
        <AppIcon name="download" size="16" />
        Tải file
      </button>
    </header>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <section v-if="loading" class="state-box animate-fade-in">
      <div class="spinner"></div>
      <p>Đang tải văn bản...</p>
    </section>
    <section v-else-if="!document" class="state-card error">Không tìm thấy văn bản.</section>

    <div v-else class="document-layout">
      <DocumentPreviewPane :document="document" />

      <aside class="side-panel">
        <section class="panel">
          <h3>Thông tin văn bản</h3>
          <dl>
            <dt>Loại</dt>
            <dd>{{ documentKindLabel }}</dd>
            <dt>Trạng thái</dt>
            <dd>{{ documentStatusLabel(document.status) }}</dd>
            <dt>Mã văn bản</dt>
            <dd>{{ document.document_code || '-' }}</dd>
            <dt>Ngày sinh/tải lên</dt>
            <dd>{{ formatDate(document.generated_at || document.uploaded_at) }}</dd>
          </dl>
        </section>

        <section v-if="isGeneratedDocument" class="panel">
          <h3>Chữ ký</h3>
          <div class="signature-list">
            <div v-for="side in requiredSides" :key="side.key" class="signature-item" :class="{ signed: signatureBySide(side.key) }">
              <span>{{ side.label }}</span>
              <strong>{{ signatureBySide(side.key) ? formatDate(signatureBySide(side.key).signed_at) : 'Chưa ký' }}</strong>
            </div>
          </div>
        </section>

        <section v-if="signingLogs.length" class="panel">
          <h3>Nhật ký chữ ký</h3>
          <div class="otp-log-list">
            <article v-for="log in signingLogs" :key="log.id || log.otp_reference" class="otp-log-item">
              <div class="otp-log-head">
                <strong>{{ signerSideLabel(log.signer_side) }}</strong>
                <span>{{ log.otp_status || log.status || '-' }}</span>
              </div>
              <dl class="otp-log-grid">
                <dt>Mã tham chiếu</dt>
                <dd>{{ log.otp_reference || '-' }}</dd>
                <dt>Gửi yêu cầu</dt>
                <dd>{{ formatDate(log.otp_sent_at) }}</dd>
                <dt>Xác minh chữ ký</dt>
                <dd>{{ formatDate(log.otp_verified_at) }}</dd>
                <dt>Lần nhập</dt>
                <dd>{{ log.attempt_count ?? '-' }}</dd>
                <dt>IP</dt>
                <dd>{{ log.ip_address || '-' }}</dd>
                <dt>Thiết bị</dt>
                <dd>{{ log.device_label || log.device || '-' }}</dd>
                <dt>Vị trí chữ ký</dt>
                <dd>{{ log.signature_position || '-' }}</dd>
              </dl>
            </article>
          </div>
        </section>

        <section v-if="canSign" class="panel sign-panel">
          <h3>Ký đại diện SportGo</h3>
          <p>Kiểm tra toàn bộ nội dung, ký tên và lưu bằng tài khoản admin đang đăng nhập trước khi chuyển văn bản cho chủ sân.</p>

          <div class="sign-steps" aria-label="Các bước ký văn bản">
            <span class="done">1. Kiểm tra file</span>
            <span :class="{ done: !signatureEmpty }">2. Ký tên</span>
            <span :class="{ done: !signatureEmpty && confirmed }">3. Xác nhận</span>
          </div>

          <label class="confirm-line">
            <input v-model="confirmed" type="checkbox" :disabled="saving" />
            <span>{{ confirmationText }}</span>
          </label>

          <div class="canvas-wrap">
            <canvas
              ref="canvas"
              width="440"
              height="190"
              @pointerdown="startDraw"
              @pointermove="draw"
              @pointerup="stopDraw"
              @pointerleave="stopDraw"
            ></canvas>
            <span v-if="signatureEmpty">Ký vào đây</span>
          </div>

          <p v-if="signError" class="field-error" role="alert">{{ signError }}</p>

          <div class="sign-actions">
            <button class="btn ghost" type="button" :disabled="saving" @click="clearSignature">Ký lại</button>
            <button
              class="btn primary"
              type="button"
              :disabled="signatureEmpty || !confirmed || saving"
              @click="submitSignature"
            >
              <AppIcon name="check" size="16" />
              {{ saving ? 'Đang lưu chữ ký...' : 'Xác nhận và ký' }}
            </button>
          </div>
        </section>

        <section v-else class="panel">
          <h3>Thao tác</h3>
          <p>{{ readonlyHint }}</p>
        </section>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '../../components/AppIcon.vue';
import DocumentPreviewPane from '../../components/DocumentPreviewPane.vue';
import { adminPartnerApplicationService } from '../../services/adminPartnerApplications.js';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const message = ref('');
const application = ref(null);
const document = ref(null);
const canvas = ref(null);
const drawing = ref(false);
const signatureEmpty = ref(true);
const confirmed = ref(false);
const signError = ref('');

const isGeneratedDocument = computed(() => document.value?.source !== 'uploaded');
const isPartnerContract = computed(() => document.value?.document_type === 'partner_contract');
const isTwoPartyDocument = computed(() => ['partner_contract', 'venue_scale_appendix', 'venue_location_appendix'].includes(document.value?.document_type));
const partnerContractId = computed(() => (
  document.value?.partner_contract_id
  || application.value?.contracts?.find((contract) => (
    String(contract.generated_document_id || '') === String(document.value?.id || '')
    || String(contract.generatedDocument?.id || '') === String(document.value?.id || '')
  ))?.id
  || null
));
const documentTitle = computed(() => document.value?.title || documentTypeLabel(document.value?.document_type));
const documentKindLabel = computed(() => isGeneratedDocument.value ? documentTypeLabel(document.value?.document_type) : uploadedTypeLabel(document.value?.document_type));
const signingLogs = computed(() => document.value?.signing_requests || []);
const requiredSides = computed(() => isTwoPartyDocument.value
  ? [{ key: 'sportgo', label: 'SportGo' }, { key: 'owner', label: 'Chủ sân' }]
  : [{ key: 'owner', label: 'Người đăng ký' }]);
const canSign = computed(() => (
  isGeneratedDocument.value
  && isTwoPartyDocument.value
  && document.value?.status === 'pending_sportgo_signature'
  && Boolean(document.value?.download_url)
  && !signatureBySide('sportgo')
));
const confirmationText = computed(() => (
  isPartnerContract.value
    ? 'Tôi xác nhận đã kiểm tra toàn bộ nội dung hợp đồng, ký với vai trò đại diện SportGo/Admin được ủy quyền và chịu trách nhiệm về phiên bản văn bản đang hiển thị.'
    : 'Tôi xác nhận đã kiểm tra toàn bộ nội dung phụ lục, ký với vai trò đại diện SportGo/Admin được ủy quyền và chịu trách nhiệm về phiên bản văn bản đang hiển thị.'
));
const readonlyHint = computed(() => {
  if (!isGeneratedDocument.value) return 'Tài liệu phụ lục chỉ hỗ trợ xem và tải xuống.';
  if (!document.value?.download_url) return 'File văn bản không còn tồn tại nên hệ thống đã khóa thao tác ký. Vui lòng tạo lại file trước khi tiếp tục.';
  if (document.value?.status === 'pending_owner_signature') return 'SportGo đã ký. Văn bản đang chờ người dùng ký xác nhận.';
  if (document.value?.status === 'completed') return 'Văn bản đã hoàn tất các chữ ký bắt buộc.';
  return 'Hiện chưa có thao tác ký dành cho admin trên văn bản này.';
});

onMounted(loadData);
watch(() => [route.params.id, route.params.documentId, route.query.type], loadData);

function goBack() {
  if (route.query.from === 'venue-cluster' && route.query.clusterId) {
    router.push({ name: 'admin-venue-cluster-detail', params: { id: route.query.clusterId } });
    return;
  }

  router.push({ name: 'admin-partner-application-detail', params: { id: route.params.id } });
}

async function loadData() {
  loading.value = true;
  error.value = '';
  message.value = '';
  resetSigningState();
  confirmed.value = false;

  try {
    const response = await adminPartnerApplicationService.show(route.params.id);
    application.value = response.data;
    document.value = findDocument(application.value, route.params.documentId);
    if (!document.value) throw new Error('Không tìm thấy văn bản.');
    if (document.value.source !== 'uploaded' && document.value.file_available !== false) {
      document.value.download_url = document.value.download_url || `/api/files/documents/${document.value.id}/download`;
    }
    await nextTick();
    prepareCanvas();
  } catch (err) {
    error.value = err.message || 'Không tải được văn bản.';
  } finally {
    loading.value = false;
  }
}

function findDocument(app, documentId) {
  const generated = app?.documents || [];
  const uploaded = app?.uploaded_documents || [];

  if (route.query.type === 'uploaded') {
    const file = uploaded.find((item) => String(item.id) === String(documentId));
    return file ? { ...file, source: 'uploaded', status: file.status || 'uploaded' } : null;
  }

  const generatedDocument = generated.find((item) => String(item.id) === String(documentId));
  if (generatedDocument) return { ...generatedDocument, source: 'generated' };

  const uploadedDocument = uploaded.find((item) => String(item.id) === String(documentId));
  return uploadedDocument ? { ...uploadedDocument, source: 'uploaded', status: uploadedDocument.status || 'uploaded' } : null;
}

function signatureBySide(side) {
  return (document.value?.signatures || []).find((signature) => signature.signer_side === side && signature.status === 'signed') || null;
}

function signerSideLabel(side) {
  return {
    sportgo: 'SportGo',
    owner: 'Chủ sân',
    user: 'Người dùng',
  }[side] || side || '-';
}

function prepareCanvas() {
  if (!canvas.value) return;
  const context = canvas.value.getContext('2d');
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.value.width, canvas.value.height);
  context.strokeStyle = '#0f172a';
  context.lineWidth = 2.5;
  context.lineCap = 'round';
  signatureEmpty.value = true;
}

function pointerPosition(event) {
  const rect = canvas.value.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) / rect.width) * canvas.value.width,
    y: ((event.clientY - rect.top) / rect.height) * canvas.value.height,
  };
}

function startDraw(event) {
  if (!canvas.value) return;
  drawing.value = true;
  signatureEmpty.value = false;
  const context = canvas.value.getContext('2d');
  const point = pointerPosition(event);
  context.beginPath();
  context.moveTo(point.x, point.y);
}

function draw(event) {
  if (!drawing.value || !canvas.value) return;
  const context = canvas.value.getContext('2d');
  const point = pointerPosition(event);
  context.lineTo(point.x, point.y);
  context.stroke();
}

function stopDraw() {
  drawing.value = false;
}

function clearSignature() {
  prepareCanvas();
  confirmed.value = false;
  resetSigningState();
}

function resetSigningState() {
  signError.value = '';
}

async function downloadCurrentDocument() {
  if (!document.value?.id) return;
  error.value = '';
  try {
    if (document.value.source === 'uploaded') {
      await adminPartnerApplicationService.downloadUploadedDocument(document.value.id);
    } else {
      await adminPartnerApplicationService.downloadDocument(document.value.id);
    }
  } catch (err) {
    error.value = err.message || 'Không tải được file.';
  }
}

async function submitSignature() {
  if (!canvas.value || !document.value) return;

  if (!document.value.download_url) {
    signError.value = 'File văn bản không còn tồn tại. Vui lòng tạo lại file trước khi ký.';
    return;
  }

  saving.value = true;
  error.value = '';
  message.value = '';
  signError.value = '';

  try {
    const payload = {
      signature_image: canvas.value.toDataURL('image/png'),
    };
    if (isPartnerContract.value) {
      payload.contract_id = partnerContractId.value;
    } else {
      payload.document_id = document.value.id;
    }

    const response = await adminPartnerApplicationService.signDocument(application.value.id, payload);
    message.value = response.message || 'SportGo đã ký văn bản.';
    await loadData();
  } catch (err) {
    signError.value = err.message || 'Không thể lưu chữ ký văn bản.';
  } finally {
    saving.value = false;
  }
}

function documentTypeLabel(type) {
  if (type === 'venue_scale_appendix') return 'Phụ lục thay đổi quy mô sân';
  if (type === 'venue_location_appendix') return 'Phụ lục thay đổi vị trí cụm sân';

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
  }[type] || type || 'Tài liệu phụ lục';
}

function documentStatusLabel(status) {
  return {
    generated: 'Đã sinh',
    pending_owner_signature: 'Chờ chủ sân ký',
    pending_sportgo_signature: 'Chờ SportGo ký',
    completed: 'Hoàn tất',
    uploaded: 'Đã tải lên',
    approved: 'Đã duyệt',
    pending: 'Chờ xử lý',
  }[status] || status || '-';
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}
</script>

<style scoped>
.partner-document-page { display: grid; gap: 16px; max-width: 1440px; margin: 0 auto; }
.page-head { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 14px; align-items: center; }
.title-block p { margin: 0 0 4px; color: #047857; font-size: 11px; font-weight: 400; letter-spacing: .1em; text-transform: uppercase; }
.title-block h2 { margin: 0; color: var(--admin-text, #0f172a); font-size: 23px; }
.notice, .state-card, .panel { border: 1px solid var(--admin-border, #e5e7eb); border-radius: 8px; background: var(--admin-surface, #fff); }
.notice, .state-card { padding: 14px 16px; font-weight: 400; }
.notice.success { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
.notice.error, .state-card.error { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
.document-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
.side-panel { display: grid; gap: 14px; position: sticky; top: 84px; max-height: calc(100vh - 104px); overflow-y: auto; padding-right: 4px; }
.panel { padding: 16px; }
.panel h3 { margin: 0 0 12px; color: #0f172a; font-size: 15px; }
.panel p { margin: 0 0 12px; color: #64748b; font-size: 13px; line-height: 1.55; }
dl { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 8px 10px; margin: 0; font-size: 13px; }
dt { color: #64748b; }
dd { margin: 0; color: #0f172a; font-weight: 400; overflow-wrap: anywhere; }
.signature-list { display: grid; gap: 8px; }
.signature-item { border: 1px solid #facc15; background: #fefce8; border-radius: 8px; padding: 10px; display: flex; justify-content: space-between; gap: 10px; color: #854d0e; font-size: 13px; }
.signature-item.signed { border-color: #86efac; background: #f0fdf4; color: #166534; }
.otp-log-list { display: grid; gap: 10px; }
.otp-log-item { border: 1px solid #dbe7df; background: #f8fbf8; border-radius: 8px; padding: 12px; }
.otp-log-head { display: flex; justify-content: space-between; gap: 10px; align-items: center; margin-bottom: 10px; color: #0f172a; font-size: 13px; }
.otp-log-head span { border-radius: 999px; background: #e8f5eb; color: #166534; padding: 3px 8px; font-size: 11px; font-weight: 400; }
.otp-log-grid { grid-template-columns: 96px minmax(0, 1fr); font-size: 12px; }
.otp-log-grid dd { font-weight: 400; }
.confirm-line { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 10px; align-items: start; margin: 12px 0; color: #334155; font-size: 13px; font-weight: 400; line-height: 1.45; }
.confirm-line input { margin-top: 2px; width: 16px; height: 16px; accent-color: #0f172a; }
.sign-steps { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
.sign-steps span { border: 1px solid #dbe7df; border-radius: 999px; background: #f8fafc; color: #64748b; padding: 5px 9px; font-size: 12px; font-weight: 400; }
.sign-steps span.done { border-color: #86efac; background: #f0fdf4; color: #166534; }
.canvas-wrap { position: relative; border: 1px dashed #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; }
.canvas-wrap.locked { background: #f8fafc; opacity: .78; }
.canvas-wrap.locked canvas { cursor: not-allowed; }
canvas { display: block; width: 100%; height: 190px; touch-action: none; cursor: crosshair; }
.canvas-wrap span { position: absolute; inset: 0; display: grid; place-items: center; color: #cbd5e1; font-weight: 400; pointer-events: none; }
.otp-box { display: grid; gap: 8px; margin-top: 12px; border: 1px solid #bbf7d0; border-radius: 8px; background: #f0fdf4; padding: 12px; }
.otp-box label { color: #14532d; font-size: 13px; font-weight: 400; }
.otp-box input { width: 100%; min-height: 44px; box-sizing: border-box; border: 1px solid #86efac; border-radius: 8px; background: #fff; color: #0f172a; padding: 0 12px; font: inherit; font-size: 18px; font-weight: 400; letter-spacing: .24em; }
.otp-box input:focus { border-color: #16a34a; outline: 3px solid rgba(34, 197, 94, .16); }
.otp-box input[aria-invalid="true"] { border-color: #ef4444; }
.otp-box small { color: #475569; font-size: 12px; line-height: 1.45; }
.field-error { margin: 10px 0 0 !important; color: #b91c1c !important; font-weight: 400; }
.sign-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.btn { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; padding: 0 13px; font-weight: 400; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.btn.primary { background: #0f172a; color: #fff; }
.btn.ghost { background: #fff; border-color: #e5e7eb; color: #334155; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 980px) {
  .page-head, .document-layout { grid-template-columns: 1fr; }
  .side-panel { position: static; max-height: none; overflow: visible; }
}
</style>
