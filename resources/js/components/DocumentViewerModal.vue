<template>
  <Teleport to="body">
    <div v-if="show" class="document-viewer-overlay">
      <div class="document-viewer-backdrop"></div>

      <section class="document-modal-shell" role="dialog" aria-modal="true" :aria-label="document?.title || 'Xem văn bản'">
        <header class="document-modal-header">
          <div class="document-modal-title">
            <span class="document-modal-icon"><AppIcon name="fileText" size="18" /></span>
            <div>
              <h2>{{ document?.title || 'Xem văn bản' }}</h2>
              <p>{{ document?.document_code || 'Văn bản điện tử SportGo' }}</p>
            </div>
          </div>
          <div class="document-modal-tools">
            <button v-if="document?.download_url" class="document-tool-button" type="button" title="Tải văn bản" @click="downloadDocument">
              <AppIcon name="download" size="17" />
              <span>Tải xuống</span>
            </button>
            <button class="document-close-button" type="button" title="Đóng" aria-label="Đóng màn xem văn bản" @click="$emit('close')">
              <AppIcon name="x" size="21" />
            </button>
          </div>
        </header>

        <div class="document-modal-body" :class="{ 'has-signing-actions': actionMode }">
          <main class="document-stage">
            <div class="document-stage-bar">
              <span><AppIcon name="eye" size="16" /> Nội dung văn bản</span>
              <small>Cuộn để kiểm tra toàn bộ file</small>
            </div>

            <div ref="scrollContainer" class="document-scroll-area">
              <div v-if="loading" class="document-state-layer">
                <span class="document-spinner"></span>
                <strong>Đang tải văn bản...</strong>
              </div>
              <div v-else-if="error" class="document-state-layer document-state-error">
                <AppIcon name="alert" size="36" />
                <strong>Không thể hiển thị văn bản</strong>
                <p>{{ error }}</p>
                <button v-if="document?.download_url" class="document-primary-button" type="button" @click="downloadDocument">Tải file để xem</button>
              </div>

              <div v-show="fileType === 'docx'" ref="docxContainer" class="document-preview-docx" :class="{ 'is-loading': loading }"></div>
              <iframe v-if="fileType === 'pdf'" :src="fileUrl" class="document-preview-frame" :class="{ 'is-loading': loading }" title="Nội dung PDF"></iframe>
              <div v-if="fileType === 'image'" class="document-preview-image">
                <img :src="fileUrl" :class="{ 'is-loading': loading }" alt="Nội dung văn bản" />
              </div>
              <div v-if="fileType === 'unsupported'" class="document-unsupported" :class="{ 'is-loading': loading }">
                <AppIcon name="fileSearch" size="42" />
                <strong>Chưa hỗ trợ xem định dạng này</strong>
                <button v-if="document?.download_url" class="document-primary-button" type="button" @click="downloadDocument">Tải file</button>
              </div>
            </div>
          </main>

          <aside class="document-side-panel">
            <div v-if="actionMode" class="document-signing-workspace">
              <div class="document-side-heading">
                <span class="document-side-step">Thao tác cần làm</span>
                <h3>Ký xác nhận văn bản</h3>
              </div>
              <div class="document-action-scroll">
                <slot name="actions"></slot>

                <details class="document-signature-history">
                  <summary>Lịch sử chữ ký <span>{{ document?.signatures?.length || 0 }}</span></summary>
                  <div class="document-signature-list">
                    <p v-if="!document?.signatures?.length" class="document-empty-signature">Chưa có chữ ký được lưu.</p>
                    <article v-for="sig in document?.signatures || []" :key="sig.id" class="document-signature-item">
                      <div class="signature-avatar">{{ sig.signer_side === 'owner' ? 'CS' : 'SG' }}</div>
                      <div>
                        <strong>{{ sig.signer_full_name || (sig.signer_side === 'owner' ? 'Chủ sân' : 'Đại diện SportGo') }}</strong>
                        <p>{{ sig.signer_title || (sig.signer_side === 'owner' ? 'Chủ sân' : 'Đại diện SportGo') }}</p>
                        <small v-if="sig.status === 'signed'">Đã ký lúc {{ formatDate(sig.signed_at) }}</small>
                      </div>
                    </article>
                  </div>
                </details>
              </div>
            </div>

            <div v-else class="document-signature-summary">
              <div class="document-side-heading">
                <span class="document-side-step">Hồ sơ điện tử</span>
                <h3>Thông tin chữ ký</h3>
              </div>
              <div class="document-signature-list">
                <p v-if="!document?.signatures?.length" class="document-empty-signature">Văn bản chưa có chữ ký.</p>
                <article v-for="sig in document?.signatures || []" :key="sig.id" class="document-signature-item">
                  <div class="signature-avatar">{{ sig.signer_side === 'owner' ? 'CS' : 'SG' }}</div>
                  <div>
                    <span class="signature-status" :class="{ pending: sig.status !== 'signed' }">{{ sig.status === 'signed' ? 'Đã ký' : 'Chờ ký' }}</span>
                    <strong>{{ sig.signer_full_name || (sig.signer_side === 'owner' ? 'Chủ sân' : 'Đại diện SportGo') }}</strong>
                    <p>{{ sig.signer_title }}{{ sig.signer_organization ? ` · ${sig.signer_organization}` : '' }}</p>
                    <small v-if="sig.status === 'signed'">{{ formatDate(sig.signed_at) }}</small>
                    <small v-if="sig.ip_address">IP: {{ sig.ip_address }}</small>
                  </div>
                </article>
              </div>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue';
import { renderAsync } from 'docx-preview';
import AppIcon from './AppIcon.vue';
import { apiDownload, readToken } from '../services/api.js';

const props = defineProps({
  show: Boolean,
  document: Object,
  actionMode: Boolean,
});

const emit = defineEmits(['close']);

const docxContainer = ref(null);
const scrollContainer = ref(null);
const loading = ref(false);
const error = ref(null);
const fileType = ref(null);
const fileUrl = ref(null);
let resizeObserver = null;

watch(() => props.show, async (newVal) => {
  if (newVal && props.document) {
    loadDocument();
  } else {
    error.value = null;
    cleanup();
  }
});

watch(() => props.document, (newDoc) => {
  if (props.show && newDoc) {
    loadDocument();
  }
});

async function loadDocument() {
  if (!props.document?.download_url) {
    error.value = 'Không tìm thấy đường dẫn văn bản.';
    return;
  }
  
  loading.value = true;
  error.value = null;
  if (docxContainer.value) docxContainer.value.innerHTML = '';
  
  try {
    const token = readToken();
    const response = await fetch(props.document.download_url, {
      headers: token ? { 'Authorization': `Bearer ${token}` } : {}
    });
    
    if (!response.ok) throw new Error(`HTTP ${response.status} - Lỗi tải file`);
    
    const blob = await response.blob();
    const mimeType = blob.type.toLowerCase();
    
    await nextTick();
    
    if (mimeType === 'application/pdf') {
      fileType.value = 'pdf';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (mimeType.startsWith('image/')) {
      fileType.value = 'image';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (mimeType.includes('officedocument.wordprocessingml') || mimeType.includes('msword')) {
      fileType.value = 'docx';
      if (docxContainer.value) {
        await renderAsync(blob, docxContainer.value, null, {
          className: 'docx',
          inWrapper: true,
          ignoreWidth: false,
          ignoreHeight: false,
          ignoreFonts: false,
          breakPages: true,
          ignoreLastRenderedPageBreak: true,
          experimental: false,
          trimXmlDeclaration: true,
          debug: false,
        });
        updateDocumentScale();
      }
    } else {
      fileType.value = 'unsupported';
    }
  } catch (err) {
    console.error('Error rendering DOCX:', err);
    error.value = err.message || 'Lỗi không xác định khi xem văn bản.';
  } finally {
    loading.value = false;
  }
}

function cleanup() {
  if (fileUrl.value) {
    URL.revokeObjectURL(fileUrl.value);
    fileUrl.value = null;
  }
  fileType.value = null;
  if (docxContainer.value) docxContainer.value.innerHTML = '';
}

function updateDocumentScale() {
  const container = docxContainer.value;
  const scrollArea = scrollContainer.value;
  const section = container?.querySelector('section.docx');
  if (!container || !scrollArea || !section) return;

  container.style.setProperty('--document-scale', '1');
  const availableWidth = Math.max(0, scrollArea.clientWidth - 32);
  const documentWidth = section.offsetWidth || section.getBoundingClientRect().width;
  const scale = documentWidth > 0 ? Math.min(1, Math.max(0.82, availableWidth / documentWidth)) : 1;
  container.style.setProperty('--document-scale', scale.toFixed(3));

  resizeObserver?.disconnect();
  resizeObserver = new ResizeObserver(updateDocumentScale);
  resizeObserver.observe(scrollArea);
}

onUnmounted(() => {
  resizeObserver?.disconnect();
  cleanup();
});

function downloadDocument() {
  if (!props.document?.download_url) return;
  apiDownload(props.document.download_url);
}

function formatDate(dateString) {
  if (!dateString) return '';
  const d = new Date(dateString);
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<style>
.document-viewer-overlay {
  position: fixed;
  inset: 0;
  z-index: 600;
  display: grid;
  place-items: center;
  padding: 12px;
}

.document-viewer-backdrop {
  position: absolute;
  inset: 0;
  background: rgb(15 23 42 / 76%);
  backdrop-filter: blur(3px);
}

.document-modal-shell {
  position: relative;
  display: grid;
  width: min(1660px, calc(100vw - 24px));
  height: min(980px, calc(100dvh - 24px));
  grid-template-rows: 62px minmax(0, 1fr);
  overflow: hidden;
  border: 1px solid #d8e2dc;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 28px 80px rgb(15 23 42 / 38%);
}

.document-modal-header {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid #dbe4df;
  padding: 10px 14px 10px 18px;
  background: #fff;
}

.document-modal-title,
.document-modal-tools,
.document-tool-button,
.document-stage-bar,
.document-signature-item {
  display: flex;
  align-items: center;
}

.document-modal-title {
  min-width: 0;
  gap: 10px;
}

.document-modal-icon {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  border: 1px solid #bbf7d0;
  border-radius: 7px;
  background: #f0fdf4;
  color: #15803d;
}

.document-modal-title div {
  min-width: 0;
}

.document-modal-title h2,
.document-side-heading h3 {
  margin: 0;
  color: #17211b;
  letter-spacing: 0;
}

.document-modal-title h2 {
  overflow: hidden;
  font-size: 15px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.document-modal-title p {
  margin: 2px 0 0;
  color: #64748b;
  font-size: 12px;
}

.document-modal-tools {
  flex: 0 0 auto;
  gap: 8px;
}

.document-tool-button,
.document-close-button,
.document-primary-button {
  min-height: 38px;
  border-radius: 7px;
  font: inherit;
  font-weight: 750;
  cursor: pointer;
}

.document-tool-button {
  gap: 7px;
  border: 1px solid #dbe4df;
  padding: 0 12px;
  background: #fff;
  color: #334155;
}

.document-close-button {
  display: grid;
  width: 38px;
  place-items: center;
  border: 1px solid transparent;
  background: transparent;
  color: #64748b;
}

.document-tool-button:hover,
.document-close-button:hover {
  border-color: #cbd5e1;
  background: #f1f5f9;
  color: #0f172a;
}

.document-modal-body {
  display: grid;
  min-width: 0;
  min-height: 0;
  grid-template-columns: minmax(0, 1fr) 320px;
}

.document-modal-body.has-signing-actions {
  grid-template-columns: minmax(0, 1fr) clamp(380px, 29vw, 430px);
}

.document-stage {
  display: grid;
  min-width: 0;
  min-height: 0;
  grid-template-rows: 42px minmax(0, 1fr);
  background: #e8eeea;
}

.document-stage-bar {
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid #d7e0da;
  padding: 0 16px;
  background: #f8faf9;
  color: #334155;
  font-size: 12px;
  font-weight: 750;
}

.document-stage-bar span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.document-stage-bar small {
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
}

.document-scroll-area {
  position: relative;
  display: flex;
  min-width: 0;
  min-height: 0;
  align-items: flex-start;
  justify-content: center;
  overflow: auto;
  padding: 16px;
}

.document-state-layer {
  position: absolute;
  inset: 0;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 10px;
  padding: 24px;
  background: rgb(241 245 249 / 92%);
  color: #475569;
  text-align: center;
}

.document-state-layer p {
  max-width: 520px;
  margin: 0;
}

.document-state-error {
  color: #991b1b;
}

.document-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #bbf7d0;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: document-spin .8s linear infinite;
}

@keyframes document-spin {
  to { transform: rotate(360deg); }
}

.document-preview-docx {
  --document-scale: 1;
  width: max-content;
  min-width: 100%;
  min-height: 100%;
  opacity: 1;
}

.document-preview-docx .docx-wrapper {
  display: flex !important;
  width: max-content !important;
  min-width: 100% !important;
  align-items: center !important;
  flex-direction: column !important;
  padding: 0 !important;
  background: transparent !important;
}

.document-preview-docx section.docx {
  flex: none !important;
  zoom: var(--document-scale);
  margin: 0 auto 16px !important;
  box-shadow: 0 1px 3px rgb(15 23 42 / 14%), 0 10px 28px rgb(15 23 42 / 10%) !important;
}

.document-preview-frame {
  width: 100%;
  min-height: 100%;
  border: 0;
  background: #fff;
  box-shadow: 0 2px 16px rgb(15 23 42 / 12%);
}

.document-preview-image,
.document-unsupported {
  display: flex;
  width: 100%;
  min-height: 100%;
  align-items: center;
  justify-content: center;
}

.document-preview-image img {
  max-width: 100%;
  height: auto;
  background: #fff;
  box-shadow: 0 2px 16px rgb(15 23 42 / 12%);
}

.document-unsupported {
  flex-direction: column;
  gap: 12px;
  color: #64748b;
}

.document-primary-button {
  border: 1px solid #15803d;
  padding: 0 14px;
  background: #16a34a;
  color: #fff;
}

.is-loading {
  opacity: 0;
}

.document-side-panel {
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  border-left: 1px solid #dbe4df;
  background: #fff;
}

.document-signing-workspace,
.document-signature-summary {
  display: grid;
  height: 100%;
  min-height: 0;
  grid-template-rows: auto minmax(0, 1fr);
}

.document-side-heading {
  border-bottom: 1px solid #e2e8f0;
  padding: 16px 18px 13px;
}

.document-side-step {
  display: block;
  margin-bottom: 4px;
  color: #15803d;
  font-size: 11px;
  font-weight: 850;
  text-transform: uppercase;
}

.document-side-heading h3 {
  font-size: 17px;
}

.document-action-scroll,
.document-signature-summary > .document-signature-list {
  min-height: 0;
  overflow-y: auto;
  padding: 16px 18px 20px;
}

.document-signature-history {
  margin-top: 18px;
  border-top: 1px solid #e2e8f0;
  padding-top: 12px;
}

.document-signature-history summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #475569;
  font-size: 13px;
  font-weight: 750;
  cursor: pointer;
}

.document-signature-history summary span {
  display: grid;
  width: 22px;
  height: 22px;
  place-items: center;
  border-radius: 50%;
  background: #f1f5f9;
  font-size: 11px;
}

.document-signature-list {
  display: grid;
  gap: 10px;
}

.document-signature-history .document-signature-list {
  margin-top: 10px;
}

.document-signature-item {
  position: relative;
  align-items: flex-start;
  gap: 10px;
  border: 1px solid #dfe7e2;
  border-radius: 7px;
  padding: 11px;
  background: #f8faf9;
}

.signature-avatar {
  display: grid;
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  place-items: center;
  border-radius: 50%;
  background: #dcfce7;
  color: #166534;
  font-size: 11px;
  font-weight: 850;
}

.document-signature-item > div:last-child {
  min-width: 0;
}

.document-signature-item strong,
.document-signature-item p,
.document-signature-item small {
  display: block;
}

.document-signature-item strong {
  padding-right: 46px;
  color: #1e293b;
  font-size: 13px;
  overflow-wrap: anywhere;
}

.document-signature-item p,
.document-signature-item small,
.document-empty-signature {
  color: #64748b;
  font-size: 11px;
}

.document-signature-item p {
  margin: 2px 0 5px;
}

.document-signature-item small + small {
  margin-top: 2px;
}

.signature-status {
  position: absolute;
  top: 9px;
  right: 9px;
  color: #15803d;
  font-size: 10px;
  font-weight: 800;
}

.signature-status.pending {
  color: #b45309;
}

.document-empty-signature {
  margin: 0;
  border: 1px dashed #cbd5e1;
  border-radius: 7px;
  padding: 18px 12px;
  text-align: center;
}

@media (max-width: 1080px) {
  .document-modal-body.has-signing-actions {
    grid-template-columns: minmax(0, 1fr) 360px;
  }
}

@media (max-width: 820px) {
  .document-viewer-overlay {
    padding: 0;
  }

  .document-modal-shell {
    width: 100vw;
    height: 100dvh;
    border: 0;
    border-radius: 0;
  }

  .document-modal-body,
  .document-modal-body.has-signing-actions {
    overflow-y: auto;
    grid-template-columns: 1fr;
    grid-template-rows: minmax(54vh, 62vh) auto;
  }

  .document-stage,
  .document-side-panel {
    min-height: 0;
  }

  .document-side-panel {
    overflow: visible;
    border-top: 1px solid #dbe4df;
    border-left: 0;
  }

  .document-signing-workspace,
  .document-signature-summary {
    height: auto;
  }

  .document-action-scroll,
  .document-signature-summary > .document-signature-list {
    overflow: visible;
  }
}

@media (max-width: 520px) {
  .document-modal-shell {
    grid-template-rows: 58px minmax(0, 1fr);
  }

  .document-modal-header {
    padding: 8px 9px 8px 12px;
  }

  .document-modal-icon,
  .document-stage-bar small,
  .document-tool-button span {
    display: none;
  }

  .document-tool-button {
    width: 38px;
    padding: 0;
    justify-content: center;
  }

  .document-modal-title h2 {
    font-size: 14px;
  }

  .document-scroll-area {
    padding: 10px;
  }

  .document-action-scroll,
  .document-side-heading,
  .document-signature-summary > .document-signature-list {
    padding-right: 14px;
    padding-left: 14px;
  }
}
</style>
