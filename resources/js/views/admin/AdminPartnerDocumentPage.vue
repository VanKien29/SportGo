<template>
  <div class="partner-document-page">
    <header class="page-head">
      <button class="btn ghost" type="button" @click="router.push({ name: 'admin-partner-application-detail', params: { id: route.params.id } })">
        <AppIcon name="arrowLeft" size="16" />
        Quay lại hồ sơ
      </button>

      <div class="title-block">
        <p>{{ application?.venue_name || 'Hồ sơ đối tác' }}</p>
        <h2>{{ documentTitle }}</h2>
      </div>

      <a v-if="document?.download_url" class="btn ghost" :href="document.download_url" target="_blank" rel="noopener">
        <AppIcon name="download" size="16" />
        Tải file
      </a>
    </header>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <section v-if="loading" class="state-card">Đang tải văn bản...</section>
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
            <div
              v-for="side in requiredSides"
              :key="side.key"
              class="signature-item"
              :class="{ signed: signatureBySide(side.key) }"
            >
              <span>{{ side.label }}</span>
              <strong>{{ signatureBySide(side.key) ? formatDate(signatureBySide(side.key).signed_at) : 'Chưa ký' }}</strong>
            </div>
          </div>
        </section>

        <section v-if="canSign" class="panel sign-panel">
          <h3>Ký điện tử SportGo</h3>
          <p>Kiểm tra nội dung hợp đồng đã điền đủ thông tin, sau đó ký xác nhận để gửi người dùng ký tiếp.</p>

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

          <div class="sign-actions">
            <button class="btn ghost" type="button" @click="clearSignature">Ký lại</button>
            <button class="btn primary" type="button" :disabled="signatureEmpty || saving" @click="submitSignature">
              <AppIcon name="pencil" size="16" />
              {{ saving ? 'Đang lưu...' : 'Ký' }}
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
import { computed, nextTick, onMounted, ref } from 'vue';
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

const isGeneratedDocument = computed(() => document.value?.source !== 'uploaded');
const isPartnerContract = computed(() => document.value?.document_type === 'partner_contract');
const documentTitle = computed(() => document.value?.title || documentTypeLabel(document.value?.document_type));
const documentKindLabel = computed(() => isGeneratedDocument.value ? documentTypeLabel(document.value?.document_type) : uploadedTypeLabel(document.value?.document_type));
const requiredSides = computed(() => isPartnerContract.value
  ? [{ key: 'sportgo', label: 'SportGo' }, { key: 'owner', label: 'Chủ sân' }]
  : [{ key: 'owner', label: 'Người đăng ký' }]);
const canSign = computed(() => (
  isGeneratedDocument.value
  && isPartnerContract.value
  && document.value?.status === 'pending_sportgo_signature'
  && !signatureBySide('sportgo')
));
const readonlyHint = computed(() => {
  if (!isGeneratedDocument.value) return 'Tài liệu phụ lục chỉ hỗ trợ xem và tải xuống.';
  if (document.value?.status === 'pending_owner_signature') return 'SportGo đã ký. Văn bản đang chờ người dùng ký xác nhận.';
  if (document.value?.status === 'completed') return 'Văn bản đã hoàn tất các chữ ký bắt buộc.';
  return 'Hiện chưa có thao tác ký dành cho admin trên văn bản này.';
});

onMounted(loadData);

async function loadData() {
  loading.value = true;
  error.value = '';

  try {
    const response = await adminPartnerApplicationService.show(route.params.id);
    application.value = response.data;
    document.value = findDocument(application.value, route.params.documentId);
    if (!document.value) throw new Error('Không tìm thấy văn bản.');
    await nextTick();
    prepareCanvas();
  } catch (err) {
    error.value = err.message || 'Không tải được văn bản.';
  } finally {
    loading.value = false;
  }
}

function findDocument(app, documentId) {
  const queryType = route.query.type;
  const generated = app?.documents || [];
  const uploaded = app?.uploaded_documents || [];

  if (queryType === 'uploaded') {
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
}

async function submitSignature() {
  if (!canvas.value || !document.value) return;

  saving.value = true;
  error.value = '';
  message.value = '';

  try {
    const response = await adminPartnerApplicationService.signDocument(application.value.id, {
      contract_id: document.value.partner_contract_id,
      signature_image: canvas.value.toDataURL('image/png'),
    });
    message.value = response.message || 'SportGo đã ký hợp đồng.';
    await loadData();
  } catch (err) {
    error.value = err.message || 'Không lưu được chữ ký.';
  } finally {
    saving.value = false;
  }
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
.partner-document-page {
  display: grid;
  gap: 16px;
  max-width: 1440px;
}

.page-head {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
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
  font-size: 23px;
}

.notice,
.state-card,
.panel {
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

.document-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
}

.side-panel {
  display: grid;
  gap: 14px;
  position: sticky;
  top: 18px;
}

.panel {
  padding: 16px;
}

.panel h3 {
  margin: 0 0 12px;
  color: var(--admin-text, #0f172a);
  font-size: 16px;
}

.panel p {
  margin: 0 0 12px;
  color: var(--admin-faint, #64748b);
  font-size: 13px;
  line-height: 1.5;
}

dl {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 9px 12px;
  margin: 0;
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

.signature-list {
  display: grid;
  gap: 8px;
}

.signature-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border: 1px solid #facc15;
  border-radius: 8px;
  padding: 10px;
  background: #fefce8;
  color: #854d0e;
  font-size: 13px;
}

.signature-item.signed {
  border-color: #86efac;
  background: #f0fdf4;
  color: #166534;
}

.canvas-wrap {
  position: relative;
  overflow: hidden;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #fff;
}

canvas {
  display: block;
  width: 100%;
  height: 190px;
  touch-action: none;
  cursor: crosshair;
}

.canvas-wrap span {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: #cbd5e1;
  font-weight: 850;
  pointer-events: none;
}

.sign-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 10px;
}

.btn {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 0 13px;
  font-weight: 850;
  cursor: pointer;
  text-decoration: none;
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

.btn:disabled {
  opacity: .58;
  cursor: not-allowed;
}

@media (max-width: 1080px) {
  .document-layout {
    grid-template-columns: 1fr;
  }

  .side-panel {
    position: static;
  }
}

@media (max-width: 720px) {
  .page-head {
    grid-template-columns: 1fr;
  }

  .page-head .btn,
  .sign-actions .btn {
    width: 100%;
  }

  .sign-actions {
    flex-direction: column;
  }

  dl {
    grid-template-columns: 1fr;
  }
}
</style>
