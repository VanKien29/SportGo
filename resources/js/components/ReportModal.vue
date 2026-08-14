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
        <div>
          <span class="modal-kicker">An toàn cộng đồng</span>
          <h2 id="report-modal-title">Báo cáo nội dung</h2>
        </div>
        <button type="button" class="icon-button" aria-label="Đóng" @click="close">
          <AppIcon name="x" size="18" />
        </button>
      </header>

      <div class="modal-body">
        <p class="modal-description">
          Chọn lý do phù hợp để đội ngũ SportGo kiểm tra. Danh tính người báo cáo không hiển thị với đối tượng bị báo cáo.
        </p>

        <div v-if="targetName" class="target-summary">
          <span>Đối tượng</span>
          <strong>{{ targetName }}</strong>
        </div>

        <form class="moderation-form" @submit.prevent="submit">
          <fieldset class="reason-list">
            <legend>Lý do báo cáo</legend>
            <label
              v-for="option in reasonOptions"
              :key="option.value"
              class="reason-option"
              :class="{ selected: form.reason === option.value }"
            >
              <input v-model="form.reason" type="radio" :value="option.value" required />
              <span>
                <strong>{{ option.label }}</strong>
                <small>{{ option.description }}</small>
              </span>
            </label>
          </fieldset>

          <label class="field-block">
            <span>Thông tin bổ sung <small>Không bắt buộc</small></span>
            <textarea
              v-model.trim="form.description"
              class="field-control"
              rows="3"
              maxlength="1000"
              placeholder="Mô tả ngắn gọn nội dung hoặc hành vi cần kiểm tra"
            ></textarea>
            <small class="character-count">{{ form.description.length }}/1000</small>
          </label>

          <label class="field-block">
            <span>Ảnh minh chứng <small>JPG, PNG hoặc WebP, tối đa 5 MB</small></span>
            <input
              ref="fileInput"
              class="field-control file-control"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="onImageSelected"
            />
          </label>

          <div v-if="imagePreview" class="image-preview">
            <img :src="imagePreview" alt="Ảnh minh chứng đã chọn" />
            <button type="button" class="remove-image" aria-label="Bỏ ảnh đã chọn" @click="removeImage">
              <AppIcon name="trash" size="16" />
            </button>
          </div>

          <p v-if="errorMsg" class="form-error" role="alert">{{ errorMsg }}</p>

          <footer class="form-actions">
            <button type="button" class="cancel-button" :disabled="isSubmitting" @click="close">Hủy</button>
            <button type="submit" class="submit-button" :disabled="isSubmitting || !form.reason">
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
    value: 'harassment',
    label: 'Quấy rối hoặc xúc phạm',
    description: 'Ngôn từ kích động, đe dọa, xúc phạm người khác.',
  },
  {
    value: 'fraud',
    label: 'Gian lận hoặc giả mạo',
    description: 'Thông tin sai lệch, giả danh cá nhân hoặc tổ chức.',
  },
  {
    value: 'inappropriate_content',
    label: 'Nội dung không phù hợp',
    description: 'Hình ảnh, ngôn từ nhạy cảm hoặc vi phạm thuần phong mỹ tục.',
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

.moderation-modal {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  color: #0f172a;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}

.modal-kicker {
  display: block;
  font-size: 12px;
  color: #15803d;
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

.modal-description {
  font-size: 13.5px;
  color: #475569;
  line-height: 1.5;
  margin: 0 0 14px 0;
}

.target-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  margin-bottom: 16px;
}

.target-summary span {
  color: #475569;
}

.target-summary strong {
  color: #0f172a;
  font-weight: 500;
}

.moderation-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.reason-list {
  border: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reason-list legend {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 8px;
}

.reason-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.reason-option:hover {
  border-color: #94a3b8;
}

.reason-option.selected {
  border-color: #15803d;
  background: #f8fafc;
}

.reason-option input {
  margin-top: 3px;
  accent-color: #15803d;
}

.reason-option strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.reason-option small {
  font-size: 12px;
  color: #475569;
  display: block;
  margin-top: 2px;
}

.field-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-block span {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.field-block small {
  color: #475569;
  font-weight: 400;
  font-size: 12px;
}

.field-control {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13.5px;
  color: #0f172a;
  font-family: inherit;
  outline: none;
  background: #ffffff;
  box-sizing: border-box;
}

.field-control:focus {
  border-color: #15803d;
}

.character-count {
  font-size: 12px;
  color: #475569;
  text-align: right;
}

.image-preview {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  max-height: 180px;
  border: 1px solid #cbd5e1;
}

.image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.remove-image {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 26px;
  height: 26px;
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

.remove-image:hover {
  background: #dc2626;
}

.form-error {
  color: #dc2626;
  font-size: 13px;
  margin: 0;
}

.form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;
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
}

.cancel-button:hover {
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
