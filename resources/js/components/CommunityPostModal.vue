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
            <span class="content-field-label">Nội dung</span>
            <textarea
              v-model="form.content"
              rows="6"
              maxlength="30000"
              required
              :placeholder="`Bạn muốn chia sẻ điều gì${userFirstName ? `, ${userFirstName}` : ''}?`"
            ></textarea>
            <div class="content-field-footer">
              <span
                class="content-counter"
                :class="{ 'is-invalid': trimmedContentLength > 0 && trimmedContentLength < minimumContentLength }"
              >
                <template v-if="trimmedContentLength > 0 && trimmedContentLength < minimumContentLength">
                  Cần thêm {{ minimumContentLength - trimmedContentLength }} ký tự nữa · {{ trimmedContentLength }}/{{ minimumContentLength }}
                </template>
                <template v-else-if="trimmedContentLength >= minimumContentLength">
                  {{ trimmedContentLength.toLocaleString('vi-VN') }} ký tự
                </template>
                <template v-else>
                  Tối thiểu {{ minimumContentLength }} ký tự
                </template>
              </span>
            </div>
          </label>

          <!-- LƯỚI PREVIEW ẢNH ĐÃ CHỌN -->
          <div v-if="selectedImages.length" class="image-preview-grid">
            <div
              v-for="(img, idx) in selectedImages"
              :key="idx"
              class="image-preview-item"
            >
              <img :src="img.url" :alt="`Ảnh ${idx + 1}`" />
              <button
                type="button"
                class="remove-image-btn"
                aria-label="Xóa ảnh này"
                @click="removeImage(idx)"
              >
                <AppIcon name="x" size="14" />
              </button>
            </div>
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
              <small>Tối đa 10 ảnh JPG, PNG hoặc WebP, tối đa 5 MB/ảnh</small>
            </div>
            <label class="image-picker" :class="{ disabled: selectedImages.length >= 10 }">
              <input
                ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                :disabled="selectedImages.length >= 10"
                @change="handleFileChange"
              />
              <AppIcon name="image" />
              {{ selectedImages.length ? `Thêm ảnh (${selectedImages.length}/10)` : 'Chọn ảnh' }}
            </label>
          </div>

          <p v-if="fileError || errorMsg" class="form-error" role="alert">{{ fileError || errorMsg }}</p>

          <footer class="form-actions">
            <button type="button" class="cancel-button" :disabled="isSubmitting" @click="close">Hủy</button>
            <button type="submit" class="submit-button" :disabled="isSubmitting || !isValid">
              {{ isSubmitting ? 'Đang lưu...' : (isEditing ? 'Lưu thay đổi' : 'Đăng bài') }}
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
  editingPost: { type: Object, default: null },
});

const emit = defineEmits(['close', 'success']);
const user = getAuth();
const minimumContentLength = 20;
const maximumTags = 3;
const availableCategories = ['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi'];
const form = ref({ content: '', post_type: 'news', tags: [] });
const selectedImages = ref([]);
const removedMediaIds = ref([]);
const fileInput = ref(null);
const fileError = ref('');
const errorMsg = ref('');
const isSubmitting = ref(false);

const isEditing = computed(() => Boolean(props.editingPost));
const userInitial = computed(() => String(user?.fullName || 'S').trim().charAt(0).toUpperCase());
const userFirstName = computed(() => String(user?.fullName || '').trim().split(/\s+/).filter(Boolean).at(-1) || '');
const trimmedContentLength = computed(() => form.value.content.trim().length);
const isValid = computed(() => trimmedContentLength.value >= minimumContentLength);

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

watch(
  () => [props.isOpen, props.editingPost],
  ([open, post]) => {
    if (open && post) {
      form.value = {
        content: post.content || post.short_description || '',
        post_type: 'news',
        tags: Array.isArray(post.hashtags) ? post.hashtags.map((t) => t.name) : [],
      };
      removedMediaIds.value = [];
      const mediaList = Array.isArray(post.media) ? post.media : [];
      selectedImages.value = mediaList.map((m) => ({
        id: m.id,
        url: assetUrl(m.url || m.file_url || m.file_path || m.path),
        isExisting: true,
      }));
      fileError.value = '';
      errorMsg.value = '';
    } else if (open && !post) {
      reset();
    }
  },
  { immediate: true }
);

function toggleTag(category) {
  const index = form.value.tags.indexOf(category);
  if (index >= 0) {
    form.value.tags.splice(index, 1);
    return;
  }
  if (form.value.tags.length < maximumTags) form.value.tags.push(category);
}

function removeImage(index) {
  const item = selectedImages.value[index];
  if (item?.isExisting && item.id) {
    removedMediaIds.value.push(item.id);
  } else if (item?.url) {
    URL.revokeObjectURL(item.url);
  }
  selectedImages.value.splice(index, 1);
  if (fileInput.value) fileInput.value.value = '';
}

function clearAllImages() {
  selectedImages.value.forEach((img) => {
    if (img.isExisting && img.id) {
      removedMediaIds.value.push(img.id);
    } else if (img.url) {
      URL.revokeObjectURL(img.url);
    }
  });
  selectedImages.value = [];
  if (fileInput.value) fileInput.value.value = '';
}

async function compressImage(file, maxDimension = 1920, quality = 0.85) {
  return new Promise((resolve) => {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      resolve(file);
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        let { width, height } = img;
        if (width > maxDimension || height > maxDimension) {
          if (width > height) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          } else {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        canvas.toBlob(
          (blob) => {
            if (!blob) {
              resolve(file);
              return;
            }
            const cleanName = file.name.replace(/\.[^/.]+$/, '') + '.webp';
            const webpFile = new File([blob], cleanName, { type: 'image/webp' });
            resolve(webpFile);
          },
          'image/webp',
          quality
        );
      };
      img.onerror = () => resolve(file);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(file);
    reader.readAsDataURL(file);
  });
}

async function handleFileChange(event) {
  fileError.value = '';
  const files = Array.from(event.target.files || []);
  if (!files.length) return;

  const remainingSlots = 10 - selectedImages.value.length;
  if (files.length > remainingSlots) {
    fileError.value = `Bạn chỉ có thể thêm tối đa ${remainingSlots} ảnh nữa (tổng 10 ảnh).`;
  }

  const allowedFiles = files.slice(0, remainingSlots);
  for (const file of allowedFiles) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      fileError.value = 'Chỉ chấp nhận file ảnh JPG, PNG hoặc WebP.';
      continue;
    }
    if (file.size > 15 * 1024 * 1024) {
      fileError.value = 'Mỗi ảnh gốc không được vượt quá 15 MB.';
      continue;
    }
    const optimizedFile = await compressImage(file);
    selectedImages.value.push({
      file: optimizedFile,
      url: URL.createObjectURL(optimizedFile),
      isExisting: false,
    });
  }

  if (fileInput.value) fileInput.value.value = '';
}

function reset() {
  form.value = { content: '', post_type: 'news', tags: [] };
  clearAllImages();
  removedMediaIds.value = [];
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

    const newImages = selectedImages.value.filter((img) => !img.isExisting && img.file);
    newImages.forEach((item, index) => {
      payload.append(`images[${index}]`, item.file);
    });

    if (isEditing.value) {
      payload.append('_method', 'PUT');
      removedMediaIds.value.forEach((id, index) => {
        payload.append(`removed_media_ids[${index}]`, id);
      });
      const targetId = props.editingPost.id || props.editingPost.entity_id;
      const response = await apiFormData(`/api/venue-posts/${targetId}`, payload);
      emit('success', response);
      reset();
      emit('close');
    } else {
      if (newImages.length === 1) {
        payload.append('thumbnail', newImages[0].file);
      }
      const response = await apiFormData('/api/venue-posts', payload);
      emit('success', response);
      reset();
      emit('close');
    }
  } catch (error) {
    errorMsg.value = error.message || 'Không thể lưu bài viết. Vui lòng kiểm tra nội dung và thử lại.';
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

.content-field-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.content-field-label {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.content-field-required {
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
  box-sizing: border-box;
  transition: border-color 0.15s ease;
}

.content-field textarea:focus {
  border-color: #15803d;
}

.content-field-footer {
  display: flex;
  align-items: center;
  justify-content: flex-start;
}

.content-counter {
  color: #475569;
  font-weight: 400;
  font-size: 12px;
}

.content-counter.is-invalid {
  color: #dc2626;
  font-weight: 500;
}

/* ─── IMAGE PREVIEW GRID ─── */
.image-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 8px;
}

.image-preview-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
}

.image-preview-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.remove-image-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.75);
  color: #ffffff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s ease;
}

.remove-image-btn:hover {
  background: #dc2626;
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
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.topic-list button:hover:not(:disabled):not(.active) {
  border-color: #15803d;
  color: #15803d;
  background: #f8fafc;
}

.topic-list button.active {
  background: #15803d;
  border-color: #15803d;
  color: #ffffff !important;
}

.topic-list button.active:hover:not(:disabled) {
  background: #166534;
  border-color: #166534;
  color: #ffffff !important;
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
  transition: all 0.15s ease;
}

.image-picker input {
  display: none;
}

.image-picker:hover:not(.disabled) {
  border-color: #15803d;
  color: #15803d;
}

.image-picker.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f1f5f9;
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
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cancel-button:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #94a3b8;
}

.submit-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
  height: 38px;
  border: 1px solid #15803d;
  border-radius: 6px;
  background: #15803d;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  box-sizing: border-box;
  transition: all 0.15s ease;
}

.submit-button:hover:not(:disabled) {
  background: #166534;
  border-color: #166534;
}

.submit-button:disabled {
  cursor: not-allowed;
  background: #15803d;
  border-color: #15803d;
  color: #ffffff;
  opacity: 0.9;
}
</style>

