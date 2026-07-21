<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-overlay" role="presentation" @click.self="close">
      <section class="composer-modal" role="dialog" aria-modal="true" aria-labelledby="community-composer-title">
        <header class="modal-header">
          <div class="header-spacer" aria-hidden="true"></div>
          <h2 id="community-composer-title">Tạo bài chia sẻ</h2>
          <button type="button" class="icon-button" aria-label="Đóng cửa sổ tạo bài" @click="close">
            <AppIcon name="close" />
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

          <label class="content-field">
            <span class="sr-only">Nội dung bài viết</span>
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

          <!-- Image Preview Area -->
          <div v-if="imagePreviewUrl" class="image-preview-area">
            <button type="button" class="remove-image-btn" @click="removeFile">&times;</button>
            <img :src="imagePreviewUrl" alt="Preview" class="post-image-preview" />
          </div>

          <!-- Addons Area (Facebook style) -->
          <div class="fb-addons-box">
            <span class="fb-addons-text">Thêm vào bài viết của bạn</span>
            <div class="fb-addons-actions">
              <label class="fb-action-btn icon-image" title="Thêm ảnh/video">
                <input type="file" class="hidden-input" accept=".jpg,.jpeg,.png,.webp" @change="handleFileChange" />
                <AppIcon name="image" size="24" />
              </label>
            </div>
          </div>

          <div v-if="errorMsg" class="error-alert">
            <i class="fas fa-exclamation-triangle"></i> {{ errorMsg }}
          </div>

          <div class="form-actions">
            <button type="submit" class="btn submit-btn" :disabled="isSubmitting || !isValid">
              <span v-if="isSubmitting" class="spinner"></span>
              <span v-else>Đăng</span>
            </button>
          </div>

          <fieldset class="topic-fieldset">
            <legend>Chủ đề <small>Không bắt buộc, tối đa 3</small></legend>
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
              <strong>Thêm vào bài viết</strong>
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
              Chọn ảnh
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

<style scoped src="../../css/client-community-post-modal.css"></style>
