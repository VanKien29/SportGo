<template>
  <div
    v-if="isOpen"
    class="modal-overlay"
    role="presentation"
    @click.self="close"
  >
    <section
      class="moderation-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="report-modal-title"
    >
      <header class="modal-header">
        <div class="modal-header-text">
          <h2 id="report-modal-title" class="modal-title">
            {{ targetType === 'venue_cluster' ? 'Báo cáo cụm sân' : 'Báo cáo nội dung' }}
          </h2>
          <p class="modal-subtitle">Chọn lý do phù hợp để gửi tới ban quản trị SportGo xem xét.</p>
        </div>
        <button type="button" class="modal-close-btn" aria-label="Đóng" @click="close">
          <AppIcon name="x" :size="18" />
        </button>
      </header>

      <div class="modal-body">
        <!-- Target object summary if provided -->
        <div v-if="targetName" class="target-summary-row">
          <span class="target-label">Đối tượng báo cáo:</span>
          <span class="target-val">{{ targetName }}</span>
        </div>

        <form class="moderation-form" @submit.prevent="submit">
          <!-- Reason choices -->
          <div class="field-group">
            <label class="field-label">
              <span>Lý do báo cáo</span>
              <span class="field-required">Bắt buộc</span>
            </label>
            <div class="reason-list" role="radiogroup">
              <label
                v-for="option in reasonOptions"
                :key="option.value"
                class="reason-item"
                :class="{ 'reason-item--selected': form.reason === option.value }"
              >
                <input
                  v-model="form.reason"
                  type="radio"
                  :value="option.value"
                  class="reason-radio"
                  required
                />
                <div class="reason-content">
                  <span class="reason-title">{{ option.label }}</span>
                  <span class="reason-desc">{{ option.description }}</span>
                </div>
              </label>
            </div>
          </div>

          <!-- Extra description -->
          <div class="field-group">
            <label class="field-label" for="report-description-input">
              <span>Thông tin bổ sung</span>
              <span class="field-optional">Không bắt buộc</span>
            </label>
            <textarea
              id="report-description-input"
              v-model.trim="form.description"
              class="field-control field-textarea"
              rows="3"
              maxlength="1000"
              placeholder="Mô tả ngắn gọn nội dung hoặc hành vi vi phạm..."
            ></textarea>
            <div class="field-counter">{{ form.description.length }}/1000</div>
          </div>

          <!-- Image evidence -->
          <div class="field-group">
            <label class="field-label" for="report-image-input">
              <span>Ảnh minh chứng</span>
              <span class="field-optional">Tối đa 5 MB</span>
            </label>
            <input
              id="report-image-input"
              ref="fileInput"
              class="field-control file-input-control"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="onImageSelected"
            />
          </div>

          <!-- Image preview -->
          <div v-if="imagePreview" class="image-preview-box">
            <img :src="imagePreview" alt="Ảnh minh chứng đã chọn" class="preview-img" />
            <button type="button" class="preview-remove-btn" aria-label="Xóa ảnh đã chọn" @click="removeImage">
              <AppIcon name="trash" :size="15" />
            </button>
          </div>

          <p v-if="errorMsg" class="form-error" role="alert">{{ errorMsg }}</p>

          <!-- Footer actions -->
          <footer class="form-actions">
            <button type="button" class="btn-cancel" :disabled="isSubmitting" @click="close">
              Hủy
            </button>
            <button type="submit" class="btn-submit" :disabled="isSubmitting || !form.reason">
              {{ isSubmitting ? 'Đang gửi...' : 'Gửi báo cáo' }}
            </button>
          </footer>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { apiFormData } from '@/services/api';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  targetType: { type: String, required: true },
  targetId: { type: [String, Number], required: true },
  targetName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'success']);
const isSubmitting = ref(false);
const errorMsg = ref('');
const imagePreview = ref('');
const fileInput = ref(null);

const form = reactive({
  reason: '',
  description: '',
  imageFile: null,
});

const reasonOptions = [
  {
    value: 'spam',
    label: 'Nội dung rác hoặc quảng cáo',
    description: 'Tin rác, quảng cáo dịch vụ trái phép hoặc spam bình luận.',
  },
  {
    value: 'offensive',
    label: 'Nội dung không phù hợp',
    description: 'Hình ảnh, ngôn từ nhạy cảm hoặc vi phạm thuần phong mỹ tục.',
  },
  {
    value: 'harassment',
    label: 'Quấy rối hoặc xúc phạm',
    description: 'Ngôn từ kích động, đe dọa hoặc xúc phạm người khác.',
  },
  {
    value: 'fake',
    label: 'Gian lận hoặc giả mạo',
    description: 'Thông tin sai lệch, giả danh cá nhân hoặc cụm sân.',
  },
  {
    value: 'other',
    label: 'Lý do khác',
    description: 'Vấn đề vi phạm quy chuẩn cộng đồng khác.',
  },
];

function revokePreview() {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  imagePreview.value = '';
}

function removeImage() {
  form.imageFile = null;
  revokePreview();
  if (fileInput.value) fileInput.value.value = '';
}

function reset() {
  form.reason = '';
  form.description = '';
  removeImage();
  errorMsg.value = '';
}

function close() {
  if (isSubmitting.value) return;
  reset();
  emit('close');
}

function handleKeydown(event) {
  if (event.key === 'Escape' && props.isOpen) close();
}

function onImageSelected(event) {
  const file = event.target.files?.[0];
  removeImage();
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    errorMsg.value = 'Ảnh minh chứng phải nhỏ hơn hoặc bằng 5 MB.';
    return;
  }
  form.imageFile = file;
  imagePreview.value = URL.createObjectURL(file);
  errorMsg.value = '';
}

async function submit() {
  if (!form.reason || isSubmitting.value) return;
  if (!props.targetType || props.targetId === '' || props.targetId === null) {
    errorMsg.value = 'Không xác định được đối tượng cần báo cáo.';
    return;
  }

  isSubmitting.value = true;
  errorMsg.value = '';
  try {
    const payload = new FormData();
    payload.append('target_type', props.targetType);
    payload.append('target_id', String(props.targetId));
    payload.append('reason', form.reason);
    if (form.description) payload.append('description', form.description);
    if (form.imageFile) payload.append('evidence_image', form.imageFile);

    const response = await apiFormData('/api/reports', payload);
    emit('success', response);
    reset();
    emit('close');
  } catch (error) {
    errorMsg.value = error.message || 'Không thể gửi báo cáo. Vui lòng thử lại.';
  } finally {
    isSubmitting.value = false;
  }
}

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen && !isSubmitting.value) reset();
});

onMounted(() => document.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown);
  revokePreview();
});
</script>

<style scoped>
/* ==========================================================================
   REPORT MODAL - UNIFIED WHITE, BORDERLESS & MINIMALIST FLAT STYLING
   ========================================================================== */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.moderation-modal {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  color: #0f172a;
  font-family: inherit;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Reset all font-weights to 400 throughout */
.moderation-modal *,
.moderation-modal h2,
.moderation-modal span,
.moderation-modal label,
.moderation-modal button {
  font-weight: 400 !important;
  background-image: none !important;
}

/* Header */
.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  border: none !important;
}

.modal-header-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modal-title {
  font-size: 18px;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
}

.modal-subtitle {
  font-size: 13px;
  color: #334155;
  margin: 0;
  line-height: 1.45;
}

.modal-close-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: #0f172a;
  cursor: pointer;
  border-radius: 6px;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.modal-close-btn:hover {
  background: #f1f5f9;
}

/* Target object summary */
.target-summary-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  margin-bottom: 4px;
}

.target-label {
  color: #64748b;
}

.target-val {
  color: #0f172a;
}

/* Body & Form */
.modal-body {
  display: flex;
  flex-direction: column;
}

.moderation-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13.5px;
  color: #0f172a;
}

.field-required {
  font-size: 12px;
  color: #ef4444;
}

.field-optional {
  font-size: 12px;
  color: #64748b;
}

/* Reason Radio List */
.reason-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reason-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 11px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
  box-sizing: border-box;
  width: 100%;
}

.reason-item:hover {
  border-color: #94a3b8;
  background: #f8fafc;
}

.reason-item--selected {
  border-color: #15803d !important;
  background: #f0fdf4 !important;
}

.reason-radio {
  width: 16px !important;
  min-width: 16px !important;
  max-width: 16px !important;
  height: 16px !important;
  flex: 0 0 16px !important;
  margin: 2px 0 0 0 !important;
  padding: 0 !important;
  accent-color: #15803d;
  cursor: pointer;
}

.reason-content {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.reason-title {
  font-size: 13.5px;
  color: #0f172a;
  line-height: 1.35;
}

.reason-desc {
  font-size: 12px;
  color: #475569;
  line-height: 1.4;
}

/* Field Controls */
.field-control {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #94a3b8;
  border-radius: 8px;
  font-size: 13.5px;
  color: #0f172a;
  background: #ffffff;
  outline: none;
  font-family: inherit;
  box-sizing: border-box;
  transition: border-color 0.15s ease;
}

.field-control:focus {
  border-color: #15803d;
}

.field-textarea {
  resize: vertical;
  min-height: 80px;
  line-height: 1.5;
}

.file-input-control {
  padding: 7px 10px;
}

.field-counter {
  font-size: 12px;
  color: #64748b;
  text-align: right;
  margin-top: 2px;
}

/* Image preview */
.image-preview-box {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  max-height: 160px;
  border: 1px solid #94a3b8;
  background: #f8fafc;
}

.preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.preview-remove-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.75);
  color: #ffffff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease;
}

.preview-remove-btn:hover {
  background: #dc2626;
}

.form-error {
  font-size: 13px;
  color: #ef4444;
  margin: 0;
  line-height: 1.4;
}

/* Footer Actions */
.form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 8px;
  border: none !important;
}

.btn-cancel {
  padding: 9px 18px;
  border: 1px solid #94a3b8;
  background: #ffffff;
  color: #334155;
  border-radius: 6px;
  font-size: 13.5px;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.btn-cancel:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-submit {
  padding: 9px 22px;
  border: none;
  background: #15803d;
  color: #ffffff;
  border-radius: 6px;
  font-size: 13.5px;
  cursor: pointer;
  transition: background 0.15s ease;
}

.btn-submit:hover {
  background: #166534;
}

.btn-submit:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
}
</style>
