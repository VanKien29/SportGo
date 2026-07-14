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
          <AppIcon name="close" size="18" />
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
            <SgButton type="secondary" :disabled="isSubmitting" @click="close">Hủy</SgButton>
            <SgButton native-type="submit" type="primary" :loading="isSubmitting" :disabled="!form.reason">
              Gửi báo cáo
            </SgButton>
          </footer>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import SgButton from './common/SgButton.vue';
import { apiFormData } from '@/services/api';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  targetType: { type: String, required: true },
  targetId: { type: [String, Number], required: true },
  targetName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'success']);
const form = reactive({ reason: '', description: '', imageFile: null });
const isSubmitting = ref(false);
const errorMsg = ref('');
const imagePreview = ref('');
const fileInput = ref(null);

const reasonOptions = [
  { value: 'spam', label: 'Spam', description: 'Quảng cáo, lừa đảo hoặc đăng lặp lại nhiều lần.' },
  { value: 'offensive', label: 'Nội dung phản cảm', description: 'Ngôn từ thù ghét, thô tục hoặc nội dung nhạy cảm.' },
  { value: 'fake', label: 'Thông tin sai lệch', description: 'Thông tin không đúng sự thật hoặc giả mạo.' },
  { value: 'harassment', label: 'Quấy rối hoặc bắt nạt', description: 'Công kích, xúc phạm hoặc đe dọa cá nhân.' },
  { value: 'other', label: 'Lý do khác', description: 'Dấu hiệu vi phạm khác cần SportGo kiểm tra.' },
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

onBeforeUnmount(revokePreview);
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(9, 9, 11, 0.64);
}

.moderation-modal {
  width: min(100%, 560px);
  max-height: 90vh;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  color: var(--admin-text);
}

.modal-header,
.form-actions,
.target-summary,
.image-preview {
  display: flex;
  align-items: center;
}

.modal-header {
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid var(--admin-border-soft);
}

.modal-kicker {
  display: block;
  margin-bottom: 3px;
  color: var(--admin-primary);
  font-size: var(--admin-font-size-xs);
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.modal-header h2 {
  margin: 0;
  font-size: var(--admin-font-size-xl);
}

.icon-button,
.remove-image {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  cursor: pointer;
}

.icon-button {
  width: 36px;
  height: 36px;
}

.icon-button:hover,
.remove-image:hover {
  border-color: var(--admin-primary);
  color: var(--admin-primary-dark);
}

.modal-body {
  max-height: calc(90vh - 74px);
  overflow-y: auto;
  padding: 20px;
}

.modal-description {
  margin: 0 0 16px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
  line-height: 1.55;
}

.target-summary {
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
  padding: 10px 12px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-bg-soft);
  font-size: var(--admin-font-size-sm);
}

.target-summary span,
.field-block small,
.reason-option small {
  color: var(--admin-muted);
}

.moderation-form,
.reason-list,
.field-block {
  display: grid;
  gap: 10px;
}

.reason-list {
  margin: 0;
  padding: 0;
  border: 0;
}

.reason-list legend,
.field-block > span {
  margin-bottom: 2px;
  color: var(--admin-text);
  font-size: var(--admin-font-size-base);
  font-weight: 600;
}

.reason-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 12px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  cursor: pointer;
}

.reason-option.selected {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
}

.reason-option input {
  margin-top: 3px;
  accent-color: var(--admin-primary);
}

.reason-option span {
  display: grid;
  gap: 2px;
}

.reason-option strong {
  font-size: var(--admin-font-size-base);
}

.reason-option small,
.field-block small,
.character-count {
  font-size: var(--admin-font-size-sm);
}

.field-block {
  margin-top: 8px;
}

.field-block > span {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.field-control {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font: inherit;
  font-size: var(--admin-font-size-base);
  outline: none;
}

textarea.field-control {
  min-height: 88px;
  padding: 10px 12px;
  resize: vertical;
}

.file-control {
  padding: 8px;
}

.field-control:focus {
  border-color: var(--admin-primary);
}

.character-count {
  justify-self: end;
  color: var(--admin-faint);
}

.image-preview {
  position: relative;
  width: min(100%, 240px);
  margin-top: 4px;
}

.image-preview img {
  display: block;
  width: 100%;
  max-height: 180px;
  object-fit: cover;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
}

.remove-image {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 32px;
  height: 32px;
  color: var(--admin-danger);
}

.form-error {
  margin: 6px 0 0;
  padding: 10px 12px;
  border: 1px solid var(--admin-danger);
  border-radius: var(--admin-radius);
  background: color-mix(in srgb, var(--admin-danger) 8%, var(--admin-surface));
  color: var(--admin-danger-text);
  font-size: var(--admin-font-size-sm);
}

.form-actions {
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--admin-border-soft);
}

@media (max-width: 560px) {
  .modal-overlay {
    align-items: end;
    padding: 0;
  }

  .moderation-modal {
    max-height: 94vh;
    border-radius: var(--admin-radius-lg) var(--admin-radius-lg) 0 0;
  }

  .modal-body {
    max-height: calc(94vh - 74px);
  }
}
</style>
