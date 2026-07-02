<template>
  <div class="upload-box" :class="{ 'upload-box--error': error }">
    <div class="upload-box__head">
      <div>
        <p class="upload-box__label">
          {{ title }}<span v-if="required">*</span>
        </p>
        <p class="upload-box__hint">
          JPG, PNG, WEBP, PDF, DOC hoặc DOCX. File cũ chỉ để đối chiếu; chọn file mới để thay thế/bổ sung.
        </p>
      </div>

      <button type="button" class="upload-box__button" @click="openPicker">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="17 8 12 3 7 8" />
          <line x1="12" x2="12" y1="3" y2="15" />
        </svg>
        Chọn file mới
      </button>
    </div>

    <input
      ref="fileInput"
      class="upload-box__input"
      type="file"
      multiple
      accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"
      @change="handleChange"
    />

    <p v-if="error" class="upload-box__error">{{ error }}</p>

    <div v-if="existingFiles.length" class="upload-box__saved">
      <p>File đã nộp trước</p>
      <ul>
        <li
          v-for="file in existingFiles"
          :key="file.id || file.file_name || file.title"
          :class="{ 'upload-box__saved-old': file.status === 'rejected' }"
        >
          <span>{{ file.file_name || file.title || 'Tài liệu đã lưu' }}</span>
          <div>
            <small v-if="savedFileSize(file)">{{ savedFileSize(file) }}</small>
            <small v-if="file.status === 'rejected'">{{ file.reject_reason || 'Bản cũ đã được thay thế' }}</small>
            <a v-if="file.download_url" :href="file.download_url" target="_blank" rel="noopener">Xem/tải</a>
          </div>
        </li>
      </ul>
    </div>

    <div v-if="files.length" class="upload-box__selected">
      <p>File mới sẽ gửi</p>
      <ul>
        <li v-for="(file, idx) in files" :key="file.name + idx">
          <span>{{ file.name }} · {{ fileSize(file) }}</span>
          <button type="button" @click="emit('remove', idx)">Xóa</button>
        </li>
      </ul>
    </div>

    <p v-else class="upload-box__empty">Chưa chọn file mới.</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  title: { type: String, required: true },
  required: { type: Boolean, default: false },
  files: { type: Array, default: () => [] },
  existingFiles: { type: Array, default: () => [] },
  error: { type: String, default: '' },
});

const emit = defineEmits(['change', 'remove']);
const fileInput = ref(null);

function openPicker() {
  fileInput.value?.click();
}

function handleChange(event) {
  emit('change', event);
  event.target.value = '';
}

const fileSize = (file) => {
  const bytes = Number(file?.size || 0);
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
};

const savedFileSize = (file) => {
  const bytes = Number(file?.file_size || file?.size || 0);
  if (!bytes) return '';
  return fileSize({ size: bytes });
};
</script>

<style scoped>
.upload-box {
  border: 1px dashed var(--border-color);
  border-radius: 12px;
  padding: 16px;
  background: #f8fafc;
}

.upload-box--error {
  border-color: #f87171;
}

.upload-box__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.upload-box__label {
  margin: 0;
  color: #0f172a;
  font-size: 13px;
  font-weight: 800;
}

.upload-box__label span {
  margin-left: 3px;
  color: #ef4444;
}

.upload-box__hint,
.upload-box__empty,
.upload-box__error {
  margin: 6px 0 0;
  font-size: 12px;
}

.upload-box__hint,
.upload-box__empty {
  color: #64748b;
}

.upload-box__error {
  color: #dc2626;
  font-weight: 700;
}

.upload-box__input {
  display: none;
}

.upload-box__button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 36px;
  flex: 0 0 auto;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  background: #fff;
  padding: 0 12px;
  color: #0f172a;
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
}

.upload-box__button:hover {
  border-color: #10b981;
  color: #047857;
}

.upload-box__saved,
.upload-box__selected {
  margin-top: 12px;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 12px;
}

.upload-box__saved {
  border: 1px solid #bbf7d0;
  background: #ecfdf5;
  color: #047857;
}

.upload-box__selected {
  border: 1px solid #dbeafe;
  background: #eff6ff;
  color: #1d4ed8;
}

.upload-box__saved p,
.upload-box__selected p {
  margin: 0 0 6px;
  font-weight: 900;
}

.upload-box__saved ul,
.upload-box__selected ul {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.upload-box__saved li,
.upload-box__selected li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.upload-box__saved-old {
  color: #92400e;
}

.upload-box__saved-old span {
  text-decoration: line-through;
}

.upload-box__saved span,
.upload-box__selected span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upload-box__saved div {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.upload-box__saved a {
  color: #0369a1;
  font-weight: 800;
  text-decoration: none;
}

.upload-box__saved a:hover {
  text-decoration: underline;
}

.upload-box__selected button {
  border: 0;
  background: transparent;
  color: #dc2626;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

@media (max-width: 640px) {
  .upload-box__head {
    display: grid;
  }

  .upload-box__button {
    justify-content: center;
    width: 100%;
  }
}
</style>
