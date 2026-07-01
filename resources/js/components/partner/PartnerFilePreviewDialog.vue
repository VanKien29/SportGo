<template>
  <teleport to="body">
    <div v-if="show" class="partner-file-dialog" role="dialog" aria-modal="true">
      <button class="partner-file-dialog__backdrop" type="button" aria-label="Đóng xem file" @click="emit('close')"></button>

      <section class="partner-file-dialog__panel">
        <header class="partner-file-dialog__head">
          <div class="partner-file-dialog__title">
            <span class="partner-file-dialog__type">{{ typeLabel }}</span>
            <h2>{{ document?.title || document?.file_name || 'Văn bản hồ sơ' }}</h2>
            <p v-if="document?.document_code">{{ document.document_code }} · {{ document.document_version ? `Phiên bản ${document.document_version}` : 'Bản hệ thống' }}</p>
          </div>

          <div class="partner-file-dialog__actions">
            <button v-if="document?.download_url" class="btn btn-secondary" type="button" @click="downloadDocument">
              <AppIcon name="download" size="16" />
              Tải file
            </button>
            <button class="btn btn-outline icon-only" type="button" title="Đóng" @click="emit('close')">
              <AppIcon name="x" size="18" />
            </button>
          </div>
        </header>

        <div class="partner-file-dialog__body">
          <div v-if="loading" class="partner-file-state">
            <span class="partner-spinner"></span>
            <p>Đang tải file văn bản...</p>
          </div>

          <div v-else-if="error" class="partner-file-state partner-file-state--error">
            <AppIcon name="alert" size="34" />
            <strong>Không thể hiển thị file</strong>
            <p>{{ error }}</p>
            <button v-if="document?.download_url" class="btn btn-primary" type="button" @click="downloadDocument">
              <AppIcon name="download" size="16" />
              Tải file để kiểm tra
            </button>
          </div>

          <div v-show="fileType === 'docx'" ref="docxContainer" class="partner-docx-canvas"></div>
          <iframe v-if="fileType === 'pdf'" :src="fileUrl" class="partner-file-frame" title="Xem PDF"></iframe>
          <div v-if="fileType === 'image'" class="partner-image-frame">
            <img :src="fileUrl" alt="Tài liệu hồ sơ" />
          </div>

          <div v-if="fileType === 'unsupported'" class="partner-file-state">
            <AppIcon name="fileText" size="38" />
            <strong>Định dạng này không hỗ trợ xem trực tiếp</strong>
            <p>Vui lòng tải file xuống để kiểm tra nội dung.</p>
            <button class="btn btn-primary" type="button" @click="downloadDocument">
              <AppIcon name="download" size="16" />
              Tải file
            </button>
          </div>
        </div>
      </section>
    </div>
  </teleport>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { renderAsync } from 'docx-preview';
import AppIcon from '../AppIcon.vue';
import { apiDownload, readToken } from '../../services/api.js';

const props = defineProps({
  show: { type: Boolean, default: false },
  document: { type: Object, default: null },
});

const emit = defineEmits(['close', 'loaded']);

const docxContainer = ref(null);
const loading = ref(false);
const error = ref('');
const fileType = ref('');
const fileUrl = ref('');

const typeLabel = computed(() => {
  if (props.document?.source === 'uploaded') return 'Tài liệu phụ lục';
  if (props.document?.document_type === 'partner_contract') return 'Hợp đồng đối tác';
  if (props.document?.document_type === 'partner_application_form') return 'Đơn đăng ký đối tác';
  return 'Văn bản hệ thống';
});

watch(
  () => [props.show, props.document?.id, props.document?.download_url],
  () => {
    if (props.show && props.document) loadDocument();
    else cleanup();
  },
  { immediate: true }
);

async function loadDocument() {
  cleanup();
  if (!props.document?.download_url) {
    error.value = 'Văn bản chưa có đường dẫn file để xem.';
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
    if (!blob.size) throw new Error('File đang rỗng. Vui lòng tạo lại văn bản từ hồ sơ.');

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
      await nextTick();
      if (!docxContainer.value) throw new Error('Không khởi tạo được vùng xem DOCX.');
      await renderAsync(blob, docxContainer.value, null, {
        className: 'docx',
        inWrapper: true,
        ignoreWidth: false,
        ignoreHeight: false,
        ignoreFonts: false,
        breakPages: true,
        ignoreLastRenderedPageBreak: false,
        trimXmlDeclaration: true,
      });
    } else {
      fileType.value = 'unsupported';
    }

    emit('loaded', props.document);
  } catch (err) {
    error.value = err.message || 'Không thể xem file văn bản.';
  } finally {
    loading.value = false;
  }
}

function cleanup() {
  if (fileUrl.value) URL.revokeObjectURL(fileUrl.value);
  fileUrl.value = '';
  fileType.value = '';
  error.value = '';
  if (docxContainer.value) docxContainer.value.innerHTML = '';
}

function downloadDocument() {
  if (props.document?.download_url) apiDownload(props.document.download_url);
}

onUnmounted(cleanup);
</script>
