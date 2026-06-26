<template>
  <div class="document-modal-overlay" @click.self="emit('close')">
    <div class="document-modal-content">
      <header class="page-head">
        <button class="btn ghost" type="button" @click="emit('close')">
          <AppIcon name="arrowLeft" size="16" />
          Quay lại hồ sơ
        </button>
        <div>
          <p class="eyebrow">{{ application?.venue_name || 'Hồ sơ đối tác' }}</p>
          <h1>{{ document?.title || documentTypeLabel(document?.document_type) || 'Văn bản' }}</h1>
        </div>
        <button v-if="document?.download_url" class="btn ghost" type="button" @click="downloadFile(document.download_url, document.title)">
          <AppIcon name="download" size="16" />
          Tải file
        </button>
      </header>

      <div class="modal-body" style="flex: 1; overflow: hidden; padding: 24px; display: flex; flex-direction: column;">
        <div v-if="loading" class="state">Đang tải văn bản...</div>
        <div v-else-if="error" class="state error">{{ error }}</div>

        <div v-else class="document-layout" style="flex: 1; overflow: hidden;">
          <DocumentPreviewPane :document="document" />

          <aside class="side-panel" style="height: 100%; overflow-y: auto; padding-right: 8px;">
            <section v-if="isGeneratedDocument">
              <h2>Trạng thái chữ ký</h2>
              <div class="signature-list">
                <div v-for="side in requiredSides" :key="side.key" class="signature-item" :class="{ signed: signatureBySide(side.key) }">
                  <span>{{ side.label }}</span>
                  <strong>{{ signatureBySide(side.key) ? formatDate(signatureBySide(side.key).signed_at) : 'Chưa ký' }}</strong>
                </div>
              </div>
            </section>

            <section v-if="canSubmitApplication" class="action-box">
              <h2>Gửi hồ sơ</h2>
              <p>Đơn đăng ký đã có chữ ký điện tử. Bạn có thể gửi hồ sơ để SportGo xét duyệt.</p>
              <button class="btn primary full" type="button" :disabled="saving" @click="submitApplication">
                <AppIcon name="send" size="16" />
                Gửi hồ sơ
              </button>
            </section>

            <section v-if="canSign" class="sign-box">
              <h2>Ký điện tử</h2>
              <p>{{ signHint }}</p>
              <label class="confirm-line">
                <input v-model="confirmed" type="checkbox" />
                <span>{{ confirmationText }}</span>
              </label>
              <div class="canvas-wrap">
                <canvas
                  ref="canvas"
                  width="420"
                  height="180"
                  @pointerdown="startDraw"
                  @pointermove="draw"
                  @pointerup="stopDraw"
                  @pointerleave="stopDraw"
                ></canvas>
                <span v-if="signatureEmpty">Ký vào đây</span>
              </div>
              <div v-if="otpSent" class="otp-box">
                <label for="signature-otp">Mã OTP</label>
                <input id="signature-otp" v-model.trim="otp" inputmode="numeric" maxlength="6" placeholder="Nhập 6 số OTP" @input="otpError = ''" />
                <small>OTP gắn với hash file {{ hashShort }} và hết hạn lúc {{ formatDate(otpExpiresAt) }}.</small>
                <p v-if="otpError" class="inline-error">{{ otpError }}</p>
              </div>
              <div class="sign-actions">
                <button class="btn ghost" type="button" @click="clearSignature">Ký lại</button>
                <button v-if="!otpSent" class="btn primary" type="button" :disabled="signatureEmpty || !confirmed || saving" @click="requestSignatureOtp">
                  <AppIcon name="pencil" size="16" />
                  {{ saving ? 'Đang lưu...' : 'Ký' }}
                </button>
                <button v-else class="btn primary" type="button" :disabled="otp.length !== 6 || saving" @click="verifySignatureOtp">
                  <AppIcon name="check" size="16" />
                  {{ saving ? 'Đang xác thực...' : 'Xác thực OTP' }}
                </button>
              </div>
            </section>

            <section v-if="!canSign && !canSubmitApplication" class="action-box">
              <h2>Thao tác</h2>
              <p>{{ readonlyHint }}</p>
            </section>
          </aside>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { api, apiDownload } from '../../services/api.js';
import AppIcon from '../../components/AppIcon.vue';
import DocumentPreviewPane from '../../components/DocumentPreviewPane.vue';

const props = defineProps({
  applicationId: { type: [String, Number], required: true },
  documentId: { type: [String, Number], required: true },
});

const emit = defineEmits(['close', 'signed']);

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const otpError = ref('');
const application = ref(null);
const document = ref(null);
const contract = ref(null);
const canvas = ref(null);
const drawing = ref(false);
const signatureEmpty = ref(true);
const confirmed = ref(false);
const otpSent = ref(false);
const otp = ref('');
const signingRequestId = ref('');
const hashShort = ref('');
const otpExpiresAt = ref(null);

const isGeneratedDocument = computed(() => document.value?.source !== 'uploaded');
const isApplicationForm = computed(() => document.value?.document_type === 'partner_application_form');
const isContract = computed(() => document.value?.document_type === 'partner_contract');
const requiredSides = computed(() => isContract.value
  ? [{ key: 'sportgo', label: 'SportGo' }, { key: 'owner', label: 'Chủ sân' }]
  : [{ key: 'owner', label: 'Người đăng ký' }]);
const canSign = computed(() => isGeneratedDocument.value && document.value?.status === 'pending_owner_signature' && !signatureBySide('owner'));
const canSubmitApplication = computed(() => isGeneratedDocument.value && application.value?.status === 'draft' && isApplicationForm.value && document.value?.status === 'completed');
const signHint = computed(() => isContract.value
  ? 'Vui lòng ký xác nhận sau khi đã đọc hợp đồng đã được SportGo ký.'
  : 'Vui lòng ký xác nhận trên đơn đăng ký trước khi gửi hồ sơ.');
const confirmationText = computed(() => isContract.value
  ? 'Tôi xác nhận đã đọc, hiểu rõ toàn bộ nội dung hợp đồng, đồng ý giao kết hợp đồng này với SportGo và xác nhận thông tin trong hợp đồng là đúng.'
  : 'Tôi xác nhận đã đọc, kiểm tra và chịu trách nhiệm về tính chính xác, hợp pháp của toàn bộ thông tin, tài liệu trong đơn đăng ký này.');
const readonlyHint = computed(() => {
  if (!isGeneratedDocument.value) return 'Tài liệu phụ lục chỉ hỗ trợ xem và tải xuống.';
  return document.value?.status === 'completed'
    ? 'Văn bản đã hoàn tất chữ ký bắt buộc.'
    : 'Hiện chưa có thao tác cần thực hiện trên văn bản này.';
});

onMounted(loadData);

async function loadData() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api('/api/user/partner-application');
    const history = response.data?.history || [];
    application.value = history.find((item) => String(item.id) === String(props.applicationId)) || null;
    if (!application.value) throw new Error('Không tìm thấy hồ sơ.');

    document.value = findDocument(application.value, props.documentId);
    if (!document.value) throw new Error('Không tìm thấy văn bản.');
    document.value.download_url = document.value.download_url || `/api/files/documents/${document.value.id}/download`;
    await nextTick();
    prepareCanvas();
  } catch (err) {
    error.value = err.message || 'Không tải được văn bản.';
  } finally {
    loading.value = false;
  }
}

function findDocument(app, documentId) {
  const uploaded = [...(app.documents || []), ...(app.uploaded_documents || [])];
  const docs = [...(app.generated_documents || app.generatedDocuments || [])];
  for (const item of app.contracts || []) {
    const doc = item.generated_document || item.generatedDocument;
    if (doc) docs.push({ ...doc, partner_contract_id: item.id });
    if (doc && String(doc.id) === String(documentId)) contract.value = item;
  }
  const generatedDocument = docs.find((item) => String(item.id) === String(documentId));
  if (generatedDocument) return { ...generatedDocument, source: 'generated' };

  const uploadedDocument = uploaded.find((item) => String(item.id) === String(documentId));
  return uploadedDocument ? { ...uploadedDocument, source: 'uploaded', status: uploadedDocument.status || 'uploaded' } : null;
}

function signatureBySide(side) {
  return (document.value?.signatures || []).find((sig) => sig.signer_side === side && sig.status === 'signed') || null;
}

function prepareCanvas() {
  if (!canvas.value) return;
  const ctx = canvas.value.getContext('2d');
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height);
  ctx.strokeStyle = '#0f172a';
  ctx.lineWidth = 2.5;
  ctx.lineCap = 'round';
  signatureEmpty.value = true;
}

function point(event) {
  const rect = canvas.value.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) / rect.width) * canvas.value.width,
    y: ((event.clientY - rect.top) / rect.height) * canvas.value.height,
  };
}

function startDraw(event) {
  drawing.value = true;
  signatureEmpty.value = false;
  const ctx = canvas.value.getContext('2d');
  const p = point(event);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
}

function draw(event) {
  if (!drawing.value) return;
  const ctx = canvas.value.getContext('2d');
  const p = point(event);
  ctx.lineTo(p.x, p.y);
  ctx.stroke();
}

function stopDraw() {
  drawing.value = false;
}

function clearSignature() {
  prepareCanvas();
  resetOtpState();
}

function resetOtpState() {
  confirmed.value = false;
  otpSent.value = false;
  otp.value = '';
  signingRequestId.value = '';
  hashShort.value = '';
  otpExpiresAt.value = null;
}

async function requestSignatureOtp() {
  if (!canvas.value || !document.value) return;
  saving.value = true;
  otpError.value = '';
  try {
    const signature_image = canvas.value.toDataURL('image/png');
    const payload = {
      signature_image,
      confirmed: confirmed.value,
      confirmation_text: confirmationText.value,
    };
    let response;
    if (isContract.value) {
      response = await api('/api/user/partner-application/sign-contract/request-otp', {
        method: 'POST',
        body: JSON.stringify({ ...payload, contract_id: document.value.partner_contract_id || contract.value?.id }),
      });
    } else {
      response = await api(`/api/user/partner-application/${application.value.id}/sign-document/request-otp`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
    }
    signingRequestId.value = response.data?.signing_request_id || '';
    hashShort.value = response.data?.hash_short || '';
    otpExpiresAt.value = response.data?.expires_at || null;
    otpSent.value = true;
    otp.value = '';
  } catch (err) {
    otpError.value = err.message || 'Không gửi được OTP ký văn bản.';
  } finally {
    saving.value = false;
  }
}

async function verifySignatureOtp() {
  if (!signingRequestId.value || otp.value.length !== 6) return;
  saving.value = true;
  otpError.value = '';
  try {
    if (isContract.value) {
      await api('/api/user/partner-application/sign-contract/verify-otp', {
        method: 'POST',
        body: JSON.stringify({ signing_request_id: signingRequestId.value, otp: otp.value }),
      });
    } else {
      await api(`/api/user/partner-application/${application.value.id}/sign-document/verify-otp`, {
        method: 'POST',
        body: JSON.stringify({ signing_request_id: signingRequestId.value, otp: otp.value }),
      });
    }
    resetOtpState();
    await loadData();
    emit('signed');
  } catch (err) {
    otpError.value = err.message || 'Không xác thực được OTP ký văn bản.';
  } finally {
    saving.value = false;
  }
}

async function downloadFile(url, defaultTitle) {
  try {
    await apiDownload(url);
  } catch (err) {
    alert(err.message || 'Không thể tải xuống văn bản.');
  }
}

async function submitApplication() {
  saving.value = true;
  try {
    await api(`/api/user/partner-application/${application.value.id}/submit`, { method: 'POST' });
    await loadData();
  } catch (err) {
    error.value = err.message || 'Không gửi được hồ sơ.';
  } finally {
    saving.value = false;
  }
}

function documentTypeLabel(type) {
  return { partner_application_form: 'Đơn đăng ký đối tác', partner_contract: 'Hợp đồng đối tác kinh doanh' }[type] || type;
}
function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}
</script>

<style scoped>
.document-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: stretch;
  justify-content: center;
  padding: 32px;
}

.document-modal-content {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 1300px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.page-head {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
  flex-shrink: 0;
}

.page-head h1 { margin: 2px 0 0; color: #0f172a; font-size: 24px; }
.eyebrow { margin: 0; color: #059669; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; }
.document-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; align-items: stretch; padding: 24px; overflow-y: auto; }
.side-panel { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 0; }
.side-panel section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
.side-panel h2 { margin: 0 0 10px; font-size: 15px; color: #0f172a; }
.side-panel p { margin: 0 0 12px; color: #64748b; font-size: 13px; line-height: 1.5; }
.signature-list { display: flex; flex-direction: column; gap: 8px; }
.signature-item { border: 1px solid #facc15; background: #fefce8; border-radius: 8px; padding: 10px; display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: #854d0e; }
.signature-item.signed { border-color: #86efac; background: #f0fdf4; color: #166534; }
.confirm-line { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 10px; align-items: start; margin: 12px 0; color: #334155; font-size: 13px; line-height: 1.45; }
.confirm-line input { margin-top: 2px; width: 16px; height: 16px; accent-color: #0f172a; }
.otp-box { display: grid; gap: 7px; margin-top: 12px; }
.otp-box label { color: #0f172a; font-size: 13px; font-weight: 800; }
.otp-box input { min-height: 40px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 16px; letter-spacing: .16em; }
.otp-box input:focus { outline: 2px solid rgba(15, 23, 42, .18); border-color: #0f172a; }
.otp-box small { color: #64748b; line-height: 1.4; }
.canvas-wrap { position: relative; border: 1px dashed #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; }
canvas { display: block; width: 100%; height: 180px; touch-action: none; cursor: crosshair; }
.canvas-wrap span { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; color: #cbd5e1; font-weight: 800; }
.sign-actions { margin-top: 10px; display: flex; justify-content: flex-end; gap: 8px; }
.btn { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; padding: 0 13px; font-weight: 800; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.btn.primary { background: #0f172a; color: #fff; }
.btn.ghost { background: #fff; border-color: #e5e7eb; color: #334155; }
.btn.full { width: 100%; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
.state { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 44px; text-align: center; color: #64748b; }
.state.error { color: #991b1b; }
@media (max-width: 980px) {
  .page-head, .document-layout { grid-template-columns: 1fr; }
  .side-panel { position: static; }
}
</style>
