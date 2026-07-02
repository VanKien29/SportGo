<template>
  <div class="partner-document-page">
    <PublicNavbar />

    <main class="partner-document-main">
      <header class="partner-page-header">
        <button class="btn btn-secondary" type="button" @click="goBack">
          <AppIcon name="arrow-left" size="16" />
          {{ route.query.from === 'registration' ? 'Quay lại form nhập' : 'Quay lại hồ sơ' }}
        </button>

        <div class="partner-page-title">
          <p>{{ documentTypeLabel(document?.document_type, document?.source) }}</p>
          <h1>{{ document?.title || document?.file_name || 'Văn bản hồ sơ' }}</h1>
          <small v-if="application">{{ application.venue_name }} · {{ statusLabel(application.status) }}</small>
        </div>

        <div class="partner-doc-actions">
          <button v-if="document" class="btn btn-primary" type="button" :disabled="!document.download_url" @click="scrollToDocument">
            <AppIcon name="eye" size="16" />
            Xem file
          </button>
          <button v-if="document?.download_url" class="btn btn-secondary" type="button" @click="downloadFile(document.download_url)">
            <AppIcon name="download" size="16" />
            Tải file
          </button>
        </div>
      </header>

      <section v-if="loading" class="partner-card">
        <div class="partner-card-body partner-empty">Đang tải văn bản...</div>
      </section>

      <section v-else-if="error" class="partner-card">
        <div class="partner-card-body partner-field-error">{{ error }}</div>
      </section>

      <template v-else-if="document">
        <div class="partner-sign-layout">
          <section ref="viewerSection" class="partner-card partner-document-card">
            <div class="partner-card-head">
              <div>
                <h2>Nội dung văn bản</h2>
                <p>Văn bản phải được xem từ file hệ thống đã sinh trước khi ký xác nhận.</p>
              </div>
            </div>
            <div class="partner-card-body">
              <div v-if="!document.download_url" class="partner-document-hero">
                <div class="partner-document-hero-inner">
                  <AppIcon name="fileText" size="48" />
                  <h2>{{ documentTypeLabel(document.document_type, document.source) }}</h2>
                  <p class="partner-doc-warning">
                    File văn bản chưa sẵn sàng hoặc bản cũ đã mất file. Vui lòng quay lại tạo lại văn bản.
                  </p>
                </div>
              </div>
              <div v-else class="partner-inline-document-viewer">
                <DocumentPreviewPane :document="document" @loaded="hasViewedFile = true" />
              </div>
            </div>
          </section>

          <aside>
            <section class="partner-card">
              <div class="partner-card-head">
                <h3>Trạng thái chữ ký</h3>
              </div>
              <div class="partner-card-body">
                <div class="partner-sign-status">
                  <article
                    v-for="side in requiredSides"
                    :key="side.key"
                    class="partner-sign-status-item"
                    :class="{ signed: signatureBySide(side.key) }"
                  >
                    <span>{{ side.label }}</span>
                    <strong>{{ signatureBySide(side.key) ? formatDate(signatureBySide(side.key).signed_at) : 'Chưa ký' }}</strong>
                  </article>
                </div>
              </div>
            </section>

            <section v-if="canSign" class="partner-card">
              <div class="partner-card-head">
                <div>
                  <h3>Ký điện tử</h3>
                  <p>OTP chỉ gửi sau khi bạn đã mở file và bấm ký xác nhận.</p>
                </div>
              </div>
              <div class="partner-card-body partner-action-stack">
                <div v-if="!hasViewedFile" class="partner-alert">
                  <strong>Cần xem file trước</strong>
                  <p>Vui lòng kiểm tra nội dung văn bản đang hiển thị ở khung bên trái trước khi ký.</p>
                </div>

                <label class="partner-confirm-box">
                  <input v-model="confirmed" type="checkbox" />
                  <span>{{ confirmationText }}</span>
                </label>

                <div class="partner-sign-canvas-wrap">
                  <canvas
                    ref="canvas"
                    width="420"
                    height="180"
                    @pointerdown="startDraw"
                    @pointermove="draw"
                    @pointerup="stopDraw"
                    @pointerleave="stopDraw"
                  ></canvas>
                  <span v-if="signatureEmpty" class="partner-sign-canvas-placeholder">Ký vào đây</span>
                </div>

                <div v-if="otpSent" class="partner-otp-box">
                  <label for="partner-sign-otp">Mã OTP</label>
                  <input id="partner-sign-otp" v-model.trim="otp" maxlength="6" inputmode="numeric" placeholder="Nhập 6 số OTP" @input="otpError = ''" />
                  <small>OTP hết hạn lúc {{ formatDate(otpExpiresAt) }}.</small>
                </div>

                <p v-if="otpError" class="partner-field-error">{{ otpError }}</p>

                <div class="partner-action-row">
                  <button class="btn btn-secondary" type="button" @click="clearSignature">
                    <AppIcon name="rotateCcw" size="16" />
                    Ký lại
                  </button>
                  <button
                    v-if="!otpSent"
                    class="btn btn-primary"
                    type="button"
                    :disabled="!hasViewedFile || !confirmed || signatureEmpty || saving"
                    @click="requestSignatureOtp"
                  >
                    <AppIcon name="pencil" size="16" />
                    {{ saving ? 'Đang gửi OTP...' : 'Ký xác nhận' }}
                  </button>
                  <button
                    v-else
                    class="btn btn-primary"
                    type="button"
                    :disabled="otp.length !== 6 || saving"
                    @click="verifySignatureOtp"
                  >
                    <AppIcon name="check" size="16" />
                    {{ saving ? 'Đang xác thực...' : 'Xác thực OTP' }}
                  </button>
                </div>
              </div>
            </section>

            <section v-else class="partner-card">
              <div class="partner-card-head">
                <h3>Thao tác</h3>
              </div>
              <div class="partner-card-body">
                <p class="partner-empty">{{ readonlyHint }}</p>
              </div>
            </section>
          </aside>
        </div>
      </template>
    </main>

  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import AppIcon from '../../components/AppIcon.vue';
import DocumentPreviewPane from '../../components/DocumentPreviewPane.vue';
import { api, apiDownload } from '../../services/api.js';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const otpError = ref('');
const application = ref(null);
const document = ref(null);
const contract = ref(null);
const hasViewedFile = ref(false);
const viewerSection = ref(null);
const canvas = ref(null);
const drawing = ref(false);
const signatureEmpty = ref(true);
const confirmed = ref(false);
const otpSent = ref(false);
const otp = ref('');
const signingRequestId = ref('');
const otpExpiresAt = ref(null);
const DRAFT_KEY = 'sportgo_partner_application_draft_v3';

const isGeneratedDocument = computed(() => document.value?.source !== 'uploaded');
const isApplicationForm = computed(() => document.value?.document_type === 'partner_application_form');
const isContract = computed(() => document.value?.document_type === 'partner_contract');
const requiredSides = computed(() => {
  if (document.value?.source === 'uploaded') return [];
  return isContract.value
    ? [{ key: 'sportgo', label: 'SportGo' }, { key: 'owner', label: 'Chủ sân' }]
    : [{ key: 'owner', label: 'Người đăng ký' }];
});
const canSign = computed(() => (
  isGeneratedDocument.value
  && document.value?.status === 'pending_owner_signature'
  && Boolean(document.value?.download_url)
  && !signatureBySide('owner')
));
const confirmationText = computed(() => (isContract.value
  ? 'Tôi xác nhận đã đọc, hiểu rõ toàn bộ nội dung hợp đồng, đồng ý giao kết hợp đồng này với SportGo và xác nhận thông tin trong hợp đồng là đúng.'
  : 'Tôi xác nhận đã đọc, kiểm tra và chịu trách nhiệm về tính chính xác, hợp pháp của toàn bộ thông tin, tài liệu trong đơn đăng ký này.'
));
const readonlyHint = computed(() => {
  if (document.value?.source === 'uploaded') return 'Tài liệu phụ lục chỉ hỗ trợ xem và tải xuống.';
  if (document.value?.status === 'completed') return 'Văn bản đã hoàn tất chữ ký bắt buộc.';
  return 'Hiện chưa có thao tác cần thực hiện trên văn bản này.';
});

onMounted(loadPage);
watch(() => [route.params.id, route.params.documentId, route.query.type], loadPage);

async function loadPage() {
  loading.value = true;
  error.value = '';
  resetOtpState();
  hasViewedFile.value = false;

  try {
    const response = await api(`/api/user/partner-application/${route.params.id}`);
    application.value = response.data || null;
    if (!application.value) throw new Error('Không tìm thấy hồ sơ.');

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
  const uploaded = [...(app.documents || []), ...(app.uploaded_documents || [])];
  if (route.query.type === 'uploaded') {
    const uploadedDocument = uploaded.find((item) => String(item.id) === String(documentId));
    return uploadedDocument ? { ...uploadedDocument, source: 'uploaded', status: uploadedDocument.status || 'uploaded' } : null;
  }

  const docs = [...(app.generated_documents || app.generatedDocuments || [])];
  for (const item of app.contracts || []) {
    const generated = item.generated_document || item.generatedDocument;
    if (generated) docs.push({ ...generated, partner_contract_id: item.id });
    if (generated && String(generated.id) === String(documentId)) contract.value = item;
  }

  const generatedDocument = docs.find((item) => String(item.id) === String(documentId));
  if (generatedDocument) {
    const downloadUrl = generatedDocument.download_url
      || (generatedDocument.file_available !== false ? `/api/files/documents/${generatedDocument.id}/download` : null);

    return {
      ...generatedDocument,
      source: 'generated',
      download_url: downloadUrl,
    };
  }

  const uploadedDocument = uploaded.find((item) => String(item.id) === String(documentId));
  return uploadedDocument ? { ...uploadedDocument, source: 'uploaded', status: uploadedDocument.status || 'uploaded' } : null;
}

function goBack() {
  if (route.query.from === 'registration') {
    router.push({ name: 'partner-application', query: { editDraft: route.params.id } });
    return;
  }

  router.push({ name: 'partner-application-detail', params: { id: route.params.id } });
}

function scrollToDocument() {
  if (!document.value) return;
  if (!document.value.download_url) {
    error.value = 'File văn bản này không còn tồn tại. Vui lòng quay lại tạo lại văn bản.';
    return;
  }
  viewerSection.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
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
  if (!canvas.value) return;
  drawing.value = true;
  signatureEmpty.value = false;
  const ctx = canvas.value.getContext('2d');
  const p = point(event);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
}

function draw(event) {
  if (!drawing.value || !canvas.value) return;
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
  otpExpiresAt.value = null;
  otpError.value = '';
}

async function requestSignatureOtp() {
  if (!canvas.value || !document.value) return;
  saving.value = true;
  otpError.value = '';

  try {
    const signatureImage = canvas.value.toDataURL('image/png');
    const payload = {
      signature_image: signatureImage,
      confirmed: confirmed.value,
      confirmation_text: confirmationText.value,
    };

    const response = isContract.value
      ? await api('/api/user/partner-application/sign-contract/request-otp', {
        method: 'POST',
        body: JSON.stringify({ ...payload, contract_id: document.value.partner_contract_id || contract.value?.id }),
      })
      : await api(`/api/user/partner-application/${application.value.id}/sign-document/request-otp`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });

    signingRequestId.value = response.data?.signing_request_id || '';
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
      localStorage.removeItem(DRAFT_KEY);
    }

    if (isContract.value) {
      router.push({ name: 'partner-application-detail', params: { id: application.value.id } });
    } else {
      router.push({ name: 'partner-application' });
    }
  } catch (err) {
    otpError.value = err.message || 'Không xác thực được OTP ký văn bản.';
  } finally {
    saving.value = false;
  }
}

async function downloadFile(url) {
  if (!url) return;
  try {
    await apiDownload(url);
  } catch (err) {
    error.value = err.message || 'Không tải được file.';
  }
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

function documentTypeLabel(type, source) {
  if (source === 'uploaded') return 'Tài liệu phụ lục';
  return {
    partner_application_form: 'Đơn đăng ký đối tác',
    partner_contract: 'Hợp đồng đối tác kinh doanh',
  }[type] || 'Văn bản hệ thống';
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}
</script>

<style>
@import "../../../css/partner/partner.css";

.partner-document-card .partner-card-body {
  min-height: 760px;
}

.partner-inline-document-viewer {
  height: calc(100vh - 240px);
  min-height: 760px;
}

.partner-inline-document-viewer .document-preview-pane {
  min-height: 100%;
}

@media (max-width: 980px) {
  .partner-inline-document-viewer {
    height: auto;
    min-height: 620px;
  }
}
</style>
