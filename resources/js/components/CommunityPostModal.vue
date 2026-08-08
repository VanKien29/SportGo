<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-overlay community-composer-overlay" role="presentation" @click.self="close">
      <section
        class="composer-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="community-composer-title"
        aria-describedby="community-composer-description"
      >
        <header class="modal-header">
          <button type="button" class="icon-button" aria-label="Đóng cửa sổ tạo bài" @click="close">
            <AppIcon name="x" />
          </button>
        </header>

        <form class="composer-form" @submit.prevent="submit">
          <div class="author-row">
            <span class="author-avatar">{{ userInitial }}</span>
            <div>
              <strong>{{ user?.fullName || 'Thành viên SportGo' }}</strong>
              <span class="visibility-badge"><AppIcon name="users" /> Công khai</span>
            </div>
          </div>

          <p id="community-composer-description" class="composer-description">
            Chia sẻ kinh nghiệm, câu hỏi hoặc câu chuyện từ buổi chơi của bạn.
          </p>

          <label class="content-field">
            <span>Nội dung <small>Bắt buộc</small></span>
            <textarea
              v-model="form.content"
              rows="6"
              maxlength="30000"
              required
              :placeholder="`Bạn muốn chia sẻ điều gì${userFirstName ? `, ${userFirstName}` : ''}?`"
            ></textarea>
            <small :class="{ invalid: trimmedContentLength > 0 && trimmedContentLength < minimumContentLength }">
              {{ trimmedContentLength < minimumContentLength
                ? `Cần ít nhất ${minimumContentLength} ký tự · ${trimmedContentLength}/${minimumContentLength}`
                : `${trimmedContentLength.toLocaleString('vi-VN')} ký tự` }}
            </small>
          </label>

          <div v-if="imagePreviewUrl" class="image-preview">
            <img :src="imagePreviewUrl" alt="Ảnh sẽ đính kèm bài viết" />
            <button type="button" class="remove-image" aria-label="Bỏ ảnh đã chọn" @click="removeFile">
              <AppIcon name="trash" />
            </button>
          </div>

          <fieldset class="topic-fieldset">
            <legend>
              <span>Chủ đề</span>
              <small>Không bắt buộc, tối đa 3</small>
            </legend>
            <div class="topic-list">
              <button
                v-for="category in availableCategories"
                :key="category"
                type="button"
                :class="{ active: form.tags.includes(category) }"
                :disabled="!form.tags.includes(category) && form.tags.length >= maximumTags"
                @click="toggleTag(category)"
              >
                #{{ category }}
              </button>
            </div>
          </fieldset>

          <div class="attachment-row">
            <div>
              <strong>Ảnh minh họa</strong>
              <small>Một ảnh JPG, PNG hoặc WebP, tối đa 5 MB</small>
            </div>
            <label class="image-picker">
              <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                @change="handleFileChange"
              />
              <AppIcon name="image" />
              {{ selectedFile ? 'Đổi ảnh' : 'Chọn ảnh' }}
            </label>
          </div>

          <p v-if="fileError || errorMsg" class="form-error" role="alert">{{ fileError || errorMsg }}</p>

          <footer class="form-actions">
            <button type="button" class="cancel-button" :disabled="isSubmitting" @click="close">Hủy</button>
            <button type="submit" class="submit-button" :disabled="isSubmitting || !isValid">
              <span v-if="isSubmitting" class="spinner" aria-hidden="true"></span>
              {{ isSubmitting ? 'Đang đăng...' : 'Đăng bài' }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { apiFormData } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'success']);
const user = getAuth();
const minimumContentLength = 20;
const maximumTags = 3;
const availableCategories = ['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi'];
const form = ref({ content: '', post_type: 'news', tags: [] });
const selectedFile = ref(null);
const imagePreviewUrl = ref('');
const fileInput = ref(null);
const fileError = ref('');
const errorMsg = ref('');
const isSubmitting = ref(false);

const userInitial = computed(() => String(user?.fullName || 'S').trim().charAt(0).toUpperCase());
const userFirstName = computed(() => String(user?.fullName || '').trim().split(/\s+/).filter(Boolean).at(-1) || '');
const trimmedContentLength = computed(() => form.value.content.trim().length);
const isValid = computed(() => trimmedContentLength.value >= minimumContentLength);

function toggleTag(category) {
  const index = form.value.tags.indexOf(category);
  if (index >= 0) {
    form.value.tags.splice(index, 1);
    return;
  }
  if (form.value.tags.length < maximumTags) form.value.tags.push(category);
}

function revokePreview() {
  if (imagePreviewUrl.value) URL.revokeObjectURL(imagePreviewUrl.value);
  imagePreviewUrl.value = '';
}

function removeFile() {
  selectedFile.value = null;
  revokePreview();
  if (fileInput.value) fileInput.value.value = '';
}

function handleFileChange(event) {
  fileError.value = '';
  const file = event.target.files?.[0];
  removeFile();
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    fileError.value = 'Ảnh phải có định dạng JPG, PNG hoặc WebP.';
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    fileError.value = 'Kích thước ảnh không được vượt quá 5 MB.';
    return;
  }

  selectedFile.value = file;
  imagePreviewUrl.value = URL.createObjectURL(file);
}

function reset() {
  form.value = { content: '', post_type: 'news', tags: [] };
  removeFile();
  fileError.value = '';
  errorMsg.value = '';
}

function close() {
  if (isSubmitting.value) return;
  reset();
  emit('close');
}

function createTitle(content) {
  const normalized = content.replace(/\s+/g, ' ').trim();
  const sliced = normalized.slice(0, 80);
  return sliced.length < normalized.length ? `${sliced}...` : sliced;
}

async function submit() {
  if (!isValid.value || isSubmitting.value) return;
  isSubmitting.value = true;
  errorMsg.value = '';

  try {
    const content = form.value.content.trim();
    const title = createTitle(content);
    const payload = new FormData();
    payload.append('title', title);
    payload.append('short_description', content.slice(0, 500));
    payload.append('content', content);
    payload.append('post_type', form.value.post_type);
    payload.append('is_draft', '0');
    form.value.tags.forEach((tag, index) => payload.append(`tags[${index}]`, tag));
    if (selectedFile.value) payload.append('thumbnail', selectedFile.value);

    const response = await apiFormData('/api/venue-posts', payload);
    emit('success', response);
    reset();
    emit('close');
  } catch (error) {
    errorMsg.value = error.message || 'Không thể đăng bài viết. Vui lòng kiểm tra nội dung và thử lại.';
  } finally {
    isSubmitting.value = false;
  }
}

function handleEscape(event) {
  if (event.key === 'Escape' && props.isOpen) close();
}

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen && !isSubmitting.value) reset();
});

onMounted(() => document.addEventListener('keydown', handleEscape));
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleEscape);
  revokePreview();
});
</script>

<style scoped>
/* ─── MODAL OVERLAY ─── */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

/* ─── MODAL CONTAINER ─── */
.composer-modal {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  width: 100%;
  max-width: 580px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  color: #0f172a;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ─── HEADER ─── */
.modal-header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.modal-kicker {
  display: block;
  font-size: 12px;
  color: #16a34a;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.modal-header h2 {
  font-size: 20px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.icon-button {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  color: #0f172a;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.icon-button:hover {
  background: #f8fafc;
}

/* ─── FORM & AUTHOR ─── */
.composer-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.author-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #16a34a;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.author-row strong {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
}

.visibility-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #1e293b;
  font-weight: 400;
}

.composer-description {
  font-size: 13.5px;
  color: #1e293b;
  font-weight: 400;
  margin: 0;
  line-height: 1.5;
}

/* ─── CONTENT FIELD ─── */
.content-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.content-field span {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.content-field small {
  color: #dc2626;
  font-weight: 400;
  font-size: 12px;
}

.content-field textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  color: #0f172a;
  font-weight: 400;
  font-family: inherit;
  resize: vertical;
  outline: none;
  background: #ffffff;
}

.content-field textarea:focus {
  border-color: #16a34a;
}

.content-field small.invalid {
  color: #dc2626;
}

/* ─── IMAGE PREVIEW ─── */
.image-preview {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  max-height: 240px;
}

.image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.remove-image {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.7);
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* ─── TOPIC FIELDSET ─── */
.topic-fieldset {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 14px;
  margin: 0;
}

.topic-fieldset legend {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  padding: 0 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.topic-fieldset legend small {
  color: #1e293b;
  font-weight: 400;
  font-size: 12px;
}

.topic-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.topic-list button {
  padding: 5px 12px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #0f172a;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}

.topic-list button:hover:not(:disabled) {
  border-color: #16a34a;
  color: #16a34a;
}

.topic-list button.active {
  background: #16a34a;
  border-color: #16a34a;
  color: #ffffff;
}

.topic-list button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ─── ATTACHMENT ─── */
.attachment-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 14px;
}

.attachment-row strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.attachment-row small {
  font-size: 12px;
  color: #1e293b;
  font-weight: 400;
}

.image-picker {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.image-picker input {
  display: none;
}

.image-picker:hover {
  border-color: #16a34a;
  color: #16a34a;
}

/* ─── ERROR ─── */
.form-error {
  color: #dc2626;
  font-size: 13px;
  font-weight: 400;
  margin: 0;
}

/* ─── ACTIONS ─── */
.form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 8px;
}

.cancel-button {
  padding: 9px 18px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.cancel-button:hover:not(:disabled) {
  background: #f8fafc;
}

.submit-button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 20px;
  border: none;
  border-radius: 6px;
  background: #16a34a;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.submit-button:hover:not(:disabled) {
  background: #15803d;
}

.submit-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid #ffffff;
  border-top-color: transparent;
  border-radius: 50%;
  animation: cm-spin 0.75s linear infinite;
}

@keyframes cm-spin { to { transform: rotate(360deg); } }
</style>

