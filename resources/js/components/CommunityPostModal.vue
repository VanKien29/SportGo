<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-container">
      <div class="modal-header">
        <div style="width: 32px"></div> <!-- Spacer for centering -->
        <h3 class="modal-title">Tạo bài viết</h3>
        <button class="close-btn" @click="close">
          <AppIcon name="x" size="20" />
        </button>
      </div>
      
      <div class="modal-body">
        <form @submit.prevent="submit" class="fb-form">
          <div class="user-info">
            <div class="user-avatar">{{ userInitial }}</div>
            <div class="user-meta">
              <div class="user-name">{{ user?.fullName || 'Người dùng' }}</div>
              <div class="privacy-badge">
                <i class="fas fa-globe-asia"></i> Công khai
              </div>
            </div>
          </div>

          <div class="category-selection">
            <span class="category-label">Chủ đề:</span>
            <div class="category-chips">
              <button 
                v-for="cat in availableCategories" 
                :key="cat"
                type="button"
                class="category-chip"
                :class="{ active: (form.tags || []).includes(cat) }"
                @click="toggleTag(cat)"
              >
                {{ cat }}
              </button>
            </div>
          </div>

          <div class="input-area">
            <textarea 
              v-model="form.content" 
              class="content-input" 
              rows="4" 
              required 
              :placeholder="`Bạn đang nghĩ gì, ${userFirstName}?`"
            ></textarea>
          </div>

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
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { apiFormData } from '@/services/api';
import CustomSelect from '@/components/CustomSelect.vue';
import AppIcon from '@/components/AppIcon.vue';
import { getAuth } from '@/stores/auth.js';

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'success']);

const user = getAuth();
const userInitial = computed(() => user?.fullName?.charAt(0)?.toUpperCase() || '?');
const userFirstName = computed(() => {
  if (!user || !user.fullName) return '';
  const parts = user.fullName.split(' ');
  return parts[parts.length - 1];
});

const form = ref({
  content: '',
  post_type: 'news',
  tags: []
});

const availableCategories = ['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi'];

const toggleTag = (cat) => {
  const index = form.value.tags.indexOf(cat);
  if (index === -1) {
    form.value.tags.push(cat);
  } else {
    form.value.tags.splice(index, 1);
  }
};

const selectedFiles = ref([]);
const fileError = ref('');
const errorMsg = ref('');
const isSubmitting = ref(false);

const isValid = computed(() => {
  return form.value.content && form.value.content.trim().length > 0;
});

const imagePreviewUrl = ref(null);

const handleFileChange = (e) => {
  fileError.value = '';
  const files = e.target.files;
  if (!files || files.length === 0) return;
  
  const file = files[0];
  if (file.size > 5 * 1024 * 1024) {
    fileError.value = 'Kích thước file không được vượt quá 5MB.';
    return;
  }
  
  selectedFiles.value = [file];
  if (imagePreviewUrl.value) URL.revokeObjectURL(imagePreviewUrl.value);
  imagePreviewUrl.value = URL.createObjectURL(file);
};

const removeFile = () => {
  selectedFiles.value = [];
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
    imagePreviewUrl.value = null;
  }
};

const close = () => {
  emit('close');
  // Reset form
  setTimeout(() => {
    form.value = { content: '', post_type: 'news', tags: [] };
    removeFile();
    errorMsg.value = '';
    fileError.value = '';
  }, 200);
};

const submit = async () => {
  if (!isValid.value) return;
  
  isSubmitting.value = true;
  errorMsg.value = '';

  try {
    const formData = new FormData();
    
    // Auto-generate title from content
    let autoTitle = form.value.content.trim().substring(0, 80);
    if (form.value.content.trim().length > 80) autoTitle += '...';
    if (autoTitle.length < 5) autoTitle = autoTitle.padEnd(5, '.');
    formData.append('title', autoTitle);
    
    // Auto-generate short_description from content
    let shortDesc = form.value.content.substring(0, 150);
    if (form.value.content.length > 150) {
      shortDesc += '...';
    }
    // Pad to minimum 10 chars if needed
    if (shortDesc.length < 10) {
      shortDesc = shortDesc.padEnd(10, '.');
    }
    formData.append('short_description', shortDesc);
    
    formData.append('content', form.value.content);
    formData.append('post_type', form.value.post_type);
    
    form.value.tags.forEach((tag, index) => {
      formData.append(`tags[${index}]`, tag);
    });
    
    if (selectedFiles.value.length > 0) {
      formData.append('thumbnail', selectedFiles.value[0]);
    }

    await apiFormData('/api/venue-posts', formData);
    
    emit('success');
    close();
  } catch (err) {
    errorMsg.value = err.message || 'Đã có lỗi xảy ra khi tạo bài viết. Vui lòng kiểm tra lại.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}
.dark .modal-overlay {
  background: rgba(0, 0, 0, 0.7);
}

.modal-container {
  background: white;
  width: 100%;
  max-width: 500px;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(0,0,0,0.2);
  overflow: hidden;
  max-height: calc(100vh - 40px);
  display: flex;
  flex-direction: column;
}
.dark .modal-container {
  background: #242526;
  border: 1px solid #3e4042;
}

.modal-header {
  padding: 16px;
  border-bottom: 1px solid #e5e5e5;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
}
.dark .modal-header {
  border-bottom-color: #3e4042;
}

.modal-title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #050505;
  flex-grow: 1;
  text-align: center;
}
.dark .modal-title {
  color: #e4e6eb;
}

.privacy-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #e4e6eb;
  color: #050505;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  margin-top: 4px;
}
.dark .privacy-badge {
  background: #3a3b3c;
  color: #e4e6eb;
}

.category-selection {
  padding: 0 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-label {
  font-size: 13px;
  font-weight: 600;
  color: #65676b;
}

.dark .category-label {
  color: #b0b3b8;
}

.category-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.category-chip {
  padding: 6px 12px;
  border: 1px solid #ced0d4;
  border-radius: 16px;
  background: transparent;
  color: #050505;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.dark .category-chip {
  border-color: #3e4042;
  color: #e4e6eb;
}

.category-chip.never-hover-class-placeholder {
  background: #f0f2f5;
}

.dark .category-chip.never-hover-class-placeholder {
  background: #3a3b3c;
}

.category-chip.active {
  background: #e7f3ff;
  color: #1877f2;
  border-color: #e7f3ff;
}

.dark .category-chip.active {
  background: #263951;
  color: #2e89ff;
  border-color: #263951;
}

.close-btn {
  background: #e4e6eb;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  font-size: 18px;
  color: #606770;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.close-btn.never-hover-class-placeholder {
  background: #d8dadf;
}
.dark .close-btn {
  background: #3a3b3c;
  color: #b0b3b8;
}
.dark .close-btn.never-hover-class-placeholder {
  background: #4e4f50;
}

.modal-body {
  padding: 16px;
  overflow-y: auto;
}

.fb-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e7f8ef;
  color: #0b7a46;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
}

.user-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-weight: 600;
  color: #050505;
  font-size: 15px;
}
.dark .user-name {
  color: #e4e6eb;
}

.privacy-select :deep(.select-trigger) {
  background: #e4e6eb;
  border: none;
  border-radius: 6px;
  padding: 4px 8px;
  height: auto;
  min-height: 24px;
}
.privacy-select :deep(.selected-text) {
  font-size: 13px;
  font-weight: 600;
  color: #050505;
  margin-right: 4px;
}
.privacy-select :deep(.chevron) {
  width: 14px;
  height: 14px;
  color: #050505;
}

.dark .privacy-select :deep(.select-trigger) {
  background: #3a3b3c;
}
.dark .privacy-select :deep(.selected-text),
.dark .privacy-select :deep(.chevron) {
  color: #e4e6eb;
}

.input-area {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.content-input {
  width: 100%;
  border: none;
  font-size: 18px;
  color: #050505;
  outline: none;
  resize: vertical;
  min-height: 100px;
  background: transparent;
  padding: 8px;
}
.content-input::placeholder {
  color: #8f939c;
  font-size: 24px;
}
.dark .content-input {
  color: #e4e6eb;
}

/* Image Preview */
.image-preview-area {
  position: relative;
  margin-top: 8px;
  border-radius: 8px;
  border: 1px solid #ced0d4;
  padding: 8px;
  background: #f7f8fa;
}
.dark .image-preview-area {
  border-color: #3e4042;
  background: #242526;
}

.post-image-preview {
  width: 100%;
  max-height: 300px;
  object-fit: contain;
  border-radius: 6px;
  display: block;
}

.remove-image-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: white;
  border: 1px solid #ced0d4;
  color: #606770;
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.remove-image-btn.never-hover-class-placeholder {
  background: #f2f2f2;
}

/* FB Addons Box */
.fb-addons-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid #ced0d4;
  border-radius: 8px;
  padding: 8px 16px;
  margin-top: 12px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.dark .fb-addons-box {
  border-color: #3e4042;
}

.fb-addons-text {
  font-weight: 600;
  font-size: 15px;
  color: #050505;
}
.dark .fb-addons-text {
  color: #e4e6eb;
}

.fb-addons-actions {
  display: flex;
  gap: 8px;
}

.fb-action-btn {
  background: transparent;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 20px;
  transition: background 0.2s;
}
.fb-action-btn.never-hover-class-placeholder {
  background: #f0f2f5;
}
.dark .fb-action-btn.never-hover-class-placeholder {
  background: #3a3b3c;
}

.icon-image { color: #45bd62; }
.icon-tag { color: #1877f2; }
.icon-smile { color: #f7b928; }
.icon-location { color: #f5533d; }

.hidden-input {
  display: none;
}

.error-alert {
  background: #fef2f2;
  color: #b91c1c;
  padding: 12px;
  border-radius: 8px;
  font-size: 13px;
}

.form-actions {
  margin-top: 8px;
}

.submit-btn {
  width: 100%;
  background: #0b7a46;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  display: flex;
  justify-content: center;
  align-items: center;
}

.submit-btn.never-hover-class-placeholder:not(:disabled) {
  background: #096338;
}

.submit-btn:disabled {
  background: #e4e6eb;
  color: #bcc0c4;
  cursor: not-allowed;
}
.dark .submit-btn:disabled {
  background: #3a3b3c;
  color: #55585c;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
