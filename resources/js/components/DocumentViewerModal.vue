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
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
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
let scaleFrame = null;
let loadSequence = 0;

watch([() => props.show, () => props.document?.preview_url || props.document?.download_url], async ([show, downloadUrl]) => {
  if (show && downloadUrl) {
    await nextTick();
    loadDocument();
  } else {
    loadSequence += 1;
    loading.value = false;
    error.value = show ? 'Không tìm thấy đường dẫn văn bản.' : null;
    cleanup();
  }
}, { immediate: true, flush: 'post' });

async function loadDocument() {
  if (!props.document?.download_url) {
    error.value = 'Không tìm thấy đường dẫn văn bản.';
    return;
  }
  
  const currentLoad = ++loadSequence;
  cleanup();
  loading.value = true;
  error.value = null;
  if (docxContainer.value) docxContainer.value.innerHTML = '';
  
  try {
    const token = readToken();
    const response = await fetch(viewUrl(props.document), {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: token ? { 'Authorization': `Bearer ${token}` } : {}
    });
    if (currentLoad !== loadSequence) return;
    
    if (!response.ok) throw new Error(`HTTP ${response.status} - Lỗi tải file`);
    
    const blob = await response.blob();
    if (currentLoad !== loadSequence) return;
    if (!blob.size) throw new Error('File văn bản đang rỗng. Vui lòng tạo lại văn bản.');
    const mimeType = blob.type.toLowerCase();
    const detectedType = detectFileType(mimeType, response);
    
    await nextTick();
    
    if (detectedType === 'pdf') {
      fileType.value = 'pdf';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (detectedType === 'image') {
      fileType.value = 'image';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (detectedType === 'docx') {
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
        await nextTick();
        observeDocumentSize();
        scheduleDocumentScale();
      }
    } else {
      fileType.value = 'unsupported';
    }
  } catch (err) {
    if (currentLoad !== loadSequence) return;
    console.error('Error rendering DOCX:', err);
    error.value = err.message || 'Lỗi không xác định khi xem văn bản.';
  } finally {
    if (currentLoad === loadSequence) loading.value = false;
  }
}

function cleanup() {
  if (fileUrl.value) {
    URL.revokeObjectURL(fileUrl.value);
    fileUrl.value = null;
  }
  fileType.value = null;
  if (docxContainer.value) {
    docxContainer.value.innerHTML = '';
    docxContainer.value.style.removeProperty('--document-scale');
  }
}

function detectFileType(mimeType, response) {
  const disposition = response.headers.get('Content-Disposition') || '';
  const source = [
    mimeType,
    disposition,
    props.document?.download_url,
    props.document?.file_name,
    props.document?.title,
  ].filter(Boolean).join(' ').toLowerCase();

  if (source.includes('application/pdf') || source.includes('.pdf')) return 'pdf';
  if (source.includes('image/') || /\.(png|jpe?g|webp|gif)(\?|$|\s|")/.test(source)) return 'image';
  if (
    source.includes('officedocument.wordprocessingml')
    || source.includes('application/msword')
    || /\.(docx?|dotx)(\?|$|\s|")/.test(source)
  ) return 'docx';

  return 'unsupported';
}

function viewUrl(document) {
  if (document?.preview_url) return document.preview_url;
  if (!document?.download_url) return '';
  return `${document.download_url}${document.download_url.includes('?') ? '&' : '?'}mode=view`;
}

function observeDocumentSize() {
  if (resizeObserver || !scrollContainer.value) return;
  resizeObserver = new ResizeObserver(scheduleDocumentScale);
  resizeObserver.observe(scrollContainer.value);
}

function scheduleDocumentScale() {
  if (scaleFrame) cancelAnimationFrame(scaleFrame);
  scaleFrame = requestAnimationFrame(() => {
    scaleFrame = null;
    updateDocumentScale();
  });
}

function updateDocumentScale() {
  const container = docxContainer.value;
  const scrollArea = scrollContainer.value;
  const section = container?.querySelector('section.docx');
  if (!container || !scrollArea || !section) return;

  const wrapper = container.querySelector('.docx-wrapper');
  wrapper?.style.setProperty('width', '100%', 'important');
  wrapper?.style.setProperty('min-width', '100%', 'important');
  wrapper?.style.setProperty('box-sizing', 'border-box', 'important');
  wrapper?.style.setProperty('align-items', 'center', 'important');
  container.style.setProperty('--document-scale', '1');
}

onMounted(() => window.addEventListener('resize', scheduleDocumentScale));

onUnmounted(() => {
  window.removeEventListener('resize', scheduleDocumentScale);
  if (scaleFrame) cancelAnimationFrame(scaleFrame);
  resizeObserver?.disconnect();
  resizeObserver = null;
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
