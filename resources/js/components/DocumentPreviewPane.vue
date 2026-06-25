<template>
  <section class="document-preview-pane">
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

const docxContainer = ref(null);
const loading = ref(false);
const error = ref('');
const fileType = ref('');
const fileUrl = ref('');

watch(() => props.document?.download_url, () => {
  loadDocument();
}, { immediate: true });

async function loadDocument() {
  cleanup();
  if (!props.document?.download_url) {
    error.value = 'Không tìm thấy đường dẫn văn bản.';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const token = readToken();
    const response = await fetch(props.document.download_url, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    });

    if (!response.ok) throw new Error(`Không tải được file (${response.status}).`);

    const blob = await response.blob();
    const mimeType = (blob.type || '').toLowerCase();
    await nextTick();

    if (mimeType === 'application/pdf') {
      fileType.value = 'pdf';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (mimeType.startsWith('image/')) {
      fileType.value = 'image';
      fileUrl.value = URL.createObjectURL(blob);
    } else if (mimeType.includes('officedocument.wordprocessingml') || mimeType.includes('msword')) {
      fileType.value = 'docx';
      await renderAsync(blob, docxContainer.value, null, {
        className: 'docx',
        inWrapper: true,
        ignoreWidth: false,
        ignoreHeight: true,
        ignoreFonts: false,
        breakPages: true,
        ignoreLastRenderedPageBreak: true,
        trimXmlDeclaration: true,
      });
    } else {
      fileType.value = 'unsupported';
    }
  } catch (err) {
    error.value = err.message || 'Không thể hiển thị văn bản.';
  } finally {
    loading.value = false;
  }
}

function cleanup() {
  if (fileUrl.value) URL.revokeObjectURL(fileUrl.value);
  fileUrl.value = '';
  fileType.value = '';
  if (docxContainer.value) docxContainer.value.innerHTML = '';
}

function downloadDocument() {
  if (props.document?.download_url) apiDownload(props.document.download_url);
}

onUnmounted(cleanup);
</script>

<style scoped>
.document-preview-pane {
  min-height: 720px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f3f4f6;
  overflow: auto;
  padding: 20px;
}

.docx-surface {
  width: min(100%, 860px);
  min-height: 680px;
  margin: 0 auto;
  background: #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}

.file-frame {
  width: 100%;
  min-height: 760px;
  border: 0;
  background: #fff;
  border-radius: 8px;
}

.image-frame {
  display: flex;
  min-height: 680px;
  align-items: flex-start;
  justify-content: center;
}

.image-frame img {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}

.preview-state {
  min-height: 520px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #475569;
}

.preview-state.error {
  color: #991b1b;
}

.spinner {
  width: 30px;
  height: 30px;
  border: 3px solid #dbeafe;
  border-top-color: #2563eb;
  border-radius: 999px;
  animation: spin .8s linear infinite;
}

.preview-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 0;
  border-radius: 8px;
  background: #0f172a;
  color: #fff;
  min-height: 38px;
  padding: 0 14px;
  font-weight: 800;
  cursor: pointer;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

:deep(.docx-wrapper) {
  background: transparent !important;
  padding: 0 !important;
}

:deep(.docx) {
  box-shadow: none !important;
  margin-bottom: 0 !important;
}
</style>
