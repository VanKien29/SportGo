<template>
  <div class="document-page-shell">
    <PublicNavbar />
    <main class="document-page">
      <header class="page-head">
        <button class="btn ghost" type="button" @click="$router.push({ name: 'partner-application-detail', params: { id: route.params.id } })">
          <AppIcon name="arrowLeft" size="16" />
          Quay lại hồ sơ
        </button>
        <div>
          <p class="eyebrow">{{ application?.venue_name || 'Hồ sơ đối tác' }}</p>
          <h1>{{ document?.title || documentTypeLabel(document?.document_type) || 'Văn bản' }}</h1>
        </div>
        <a v-if="document?.download_url" class="btn ghost" :href="document.download_url" target="_blank" rel="noopener">
          <AppIcon name="download" size="16" />
          Tải file
        </a>
      </header>

      <div v-if="loading" class="state">Đang tải văn bản...</div>
      <div v-else-if="error" class="state error">{{ error }}</div>

      <div v-else class="document-layout">
        <DocumentPreviewPane :document="document" />

        <aside class="side-panel">
          <section>
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
            <div class="sign-actions">
              <button class="btn ghost" type="button" @click="clearSignature">Ký lại</button>
              <button class="btn primary" type="button" :disabled="signatureEmpty || saving" @click="submitSignature">
                <AppIcon name="pencil" size="16" />
                {{ saving ? 'Đang lưu...' : 'Ký' }}
              </button>
            </div>
          </section>

          <section v-if="!canSign && !canSubmitApplication" class="action-box">
            <h2>Thao tác</h2>
            <p>{{ readonlyHint }}</p>
          </section>
        </aside>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import PublicNavbar from '../../components/PublicNavbar.vue';
import AppIcon from '../../components/AppIcon.vue';
import DocumentPreviewPane from '../../components/DocumentPreviewPane.vue';
import { api } from '../../services/api.js';

const route = useRoute();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const application = ref(null);
const document = ref(null);
const contract = ref(null);
const canvas = ref(null);
const drawing = ref(false);
const signatureEmpty = ref(true);

const isApplicationForm = computed(() => document.value?.document_type === 'partner_application_form');
const isContract = computed(() => document.value?.document_type === 'partner_contract');
const requiredSides = computed(() => isContract.value
  ? [{ key: 'sportgo', label: 'SportGo' }, { key: 'owner', label: 'Chủ sân' }]
  : [{ key: 'owner', label: 'Người đăng ký' }]);
const canSign = computed(() => document.value?.status === 'pending_owner_signature' && !signatureBySide('owner'));
const canSubmitApplication = computed(() => application.value?.status === 'draft' && isApplicationForm.value && document.value?.status === 'completed');
const signHint = computed(() => isContract.value
  ? 'Vui lòng ký xác nhận sau khi đã đọc hợp đồng đã được SportGo ký.'
  : 'Vui lòng ký xác nhận trên đơn đăng ký trước khi gửi hồ sơ.');
const readonlyHint = computed(() => document.value?.status === 'completed'
  ? 'Văn bản đã hoàn tất chữ ký bắt buộc.'
  : 'Hiện chưa có thao tác cần thực hiện trên văn bản này.');

onMounted(loadData);

async function loadData() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api('/api/user/partner-application');
    application.value = (response.data?.history || []).find((item) => String(item.id) === String(route.params.id)) || null;
    if (!application.value) throw new Error('Không tìm thấy hồ sơ.');
    document.value = findDocument(application.value, route.params.documentId);
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
  const docs = [...(app.generated_documents || app.generatedDocuments || [])];
  for (const item of app.contracts || []) {
    const doc = item.generated_document || item.generatedDocument;
    if (doc) docs.push({ ...doc, partner_contract_id: item.id });
    if (doc && String(doc.id) === String(documentId)) contract.value = item;
  }
  return docs.find((item) => String(item.id) === String(documentId)) || null;
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
}

async function submitSignature() {
  if (!canvas.value || !document.value) return;
  saving.value = true;
  try {
    const signature_image = canvas.value.toDataURL('image/png');
    if (isContract.value) {
      await api('/api/user/partner-application/sign-contract', {
        method: 'POST',
        body: JSON.stringify({ contract_id: document.value.partner_contract_id || contract.value?.id, signature_image }),
      });
    } else {
      await api(`/api/user/partner-application/${application.value.id}/sign-document`, {
        method: 'POST',
        body: JSON.stringify({ signature_image }),
      });
    }
    await loadData();
  } catch (err) {
    error.value = err.message || 'Không lưu được chữ ký.';
  } finally {
    saving.value = false;
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
.document-page-shell { min-height: 100vh; background: #f8fafc; }
.document-page { max-width: 1320px; margin: 0 auto; padding: 108px 20px 56px; }
.page-head { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 16px; align-items: center; margin-bottom: 18px; }
.page-head h1 { margin: 2px 0 0; color: #0f172a; font-size: 24px; }
.eyebrow { margin: 0; color: #059669; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; }
.document-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; align-items: start; }
.side-panel { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 92px; }
.side-panel section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
.side-panel h2 { margin: 0 0 10px; font-size: 15px; color: #0f172a; }
.side-panel p { margin: 0 0 12px; color: #64748b; font-size: 13px; line-height: 1.5; }
.signature-list { display: flex; flex-direction: column; gap: 8px; }
.signature-item { border: 1px solid #facc15; background: #fefce8; border-radius: 8px; padding: 10px; display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: #854d0e; }
.signature-item.signed { border-color: #86efac; background: #f0fdf4; color: #166534; }
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
