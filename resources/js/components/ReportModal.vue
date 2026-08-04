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
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
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

