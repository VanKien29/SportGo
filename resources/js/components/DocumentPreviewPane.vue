<template>
  <section ref="previewPane" class="document-preview-pane">
    <div v-if="loading" class="preview-state">
      <span class="spinner"></span>
      <p>Đang tải nội dung văn bản...</p>
    </div>

    <div v-else-if="error" class="preview-state error">
      <AppIcon name="alert" size="32" />
      <p>{{ error }}</p>
      <button v-if="document?.download_url" class="preview-btn" type="button" @click="downloadDocument">
        <AppIcon name="download" size="16" />
        Tải xuống
      </button>
    </div>

    <div v-show="fileType === 'docx'" ref="docxContainer" class="docx-surface"></div>
    <iframe v-if="fileType === 'pdf'" :src="fileUrl" class="file-frame" title="PDF preview"></iframe>

    <div v-if="fileType === 'image'" class="image-frame">
      <img :src="fileUrl" alt="Tài liệu đính kèm" />
    </div>

    <div v-if="fileType === 'unsupported'" class="preview-state">
      <AppIcon name="fileText" size="36" />
      <p>Định dạng này không hỗ trợ xem trực tiếp. Vui lòng tải xuống để xem.</p>
      <button class="preview-btn" type="button" @click="downloadDocument">
        <AppIcon name="download" size="16" />
        Tải xuống
      </button>
    </div>
  </section>
</template>

<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { renderAsync } from 'docx-preview';
import AppIcon from './AppIcon.vue';
import { apiDownload, readToken } from '../services/api.js';

const props = defineProps({
  document: { type: Object, default: null },
});
const emit = defineEmits(['loaded']);

const docxContainer = ref(null);
const previewPane = ref(null);
const loading = ref(false);
const error = ref('');
const fileType = ref('');
const fileUrl = ref('');
let resizeObserver = null;
let scaleFrame = null;
let loadSequence = 0;

watch(() => props.document?.download_url, loadDocument, { immediate: true });

async function loadDocument() {
  const currentLoad = ++loadSequence;
  cleanup();
  loading.value = false;
  if (!props.document?.download_url) {
    error.value = 'Không tìm thấy đường dẫn văn bản.';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const token = readToken();
    const response = await fetch(props.document.download_url, {
      cache: 'no-store',
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    });
    if (currentLoad !== loadSequence) return;

    if (!response.ok) throw new Error(`Không tải được file (${response.status}).`);

    const blob = await response.blob();
    if (currentLoad !== loadSequence) return;
    if (!blob.size) throw new Error('File văn bản đang rỗng. Vui lòng tạo lại văn bản.');

    const mimeType = (blob.type || '').toLowerCase();
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
      await renderAsync(blob, docxContainer.value, null, {
        className: 'docx',
        inWrapper: true,
        ignoreWidth: false,
        ignoreHeight: false,
        ignoreFonts: false,
        breakPages: true,
        ignoreLastRenderedPageBreak: true,
        trimXmlDeclaration: true,
      });
      await nextTick();
      observePreviewSize();
      scheduleDocumentScale();
    } else {
      fileType.value = 'unsupported';
    }
    emit('loaded', props.document);
  } catch (err) {
    if (currentLoad !== loadSequence) return;
    error.value = err.message || 'Không thể hiển thị văn bản.';
  } finally {
    if (currentLoad === loadSequence) loading.value = false;
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

function cleanup() {
  if (fileUrl.value) URL.revokeObjectURL(fileUrl.value);
  fileUrl.value = '';
  fileType.value = '';
  if (docxContainer.value) {
    const page = docxContainer.value.querySelector('section.docx');
    page?.style.removeProperty('width');
    page?.style.removeProperty('min-width');
    docxContainer.value.innerHTML = '';
    docxContainer.value.style.removeProperty('--document-scale');
  }
}

function observePreviewSize() {
  if (resizeObserver || !previewPane.value) return;
  resizeObserver = new ResizeObserver(scheduleDocumentScale);
  resizeObserver.observe(previewPane.value);
}

function scheduleDocumentScale() {
  if (scaleFrame) cancelAnimationFrame(scaleFrame);
  scaleFrame = requestAnimationFrame(() => {
    scaleFrame = null;
    updateDocumentScale();
  });
}

function updateDocumentScale() {
  const pane = previewPane.value;
  const container = docxContainer.value;
  const page = container?.querySelector('section.docx');
  if (!pane || !container || !page) return;

  container.style.setProperty('--document-scale', '1');
  page.style.removeProperty('width');
  page.style.removeProperty('min-width');
  const paneStyle = window.getComputedStyle(pane);
  const horizontalPadding = parseFloat(paneStyle.paddingLeft || 0) + parseFloat(paneStyle.paddingRight || 0);
  const availableWidth = Math.max(0, pane.clientWidth - horizontalPadding);
  const naturalPageWidth = page.offsetWidth || page.getBoundingClientRect().width;
  const pageWidth = Math.max(naturalPageWidth, page.scrollWidth || 0);
  page.style.setProperty('width', `${pageWidth}px`, 'important');
  page.style.setProperty('min-width', `${pageWidth}px`, 'important');
  const scale = pageWidth > 0 ? Math.min(1, availableWidth / pageWidth) : 1;
  container.style.setProperty('--document-scale', Math.max(0.25, scale).toFixed(4));
}

function downloadDocument() {
  if (props.document?.download_url) apiDownload(props.document.download_url);
}

onUnmounted(() => {
  loadSequence += 1;
  if (scaleFrame) cancelAnimationFrame(scaleFrame);
  resizeObserver?.disconnect();
  resizeObserver = null;
  cleanup();
});
</script>

<style scoped src="../../css/client-document-preview.css"></style>
