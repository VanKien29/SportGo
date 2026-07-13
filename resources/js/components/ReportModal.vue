<template>
  <div v-if="isOpen" class="modal-overlay" @click.self="close">
    <div class="modal-container report-modal">
      <div class="modal-header">
        <h3 class="modal-title">Báo cáo nội dung</h3>
        <button class="close-btn" @click="close">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <div class="modal-body">
        <p class="report-desc">Vui lòng chọn lý do báo cáo để chúng tôi xem xét nội dung này. Báo cáo của bạn được ẩn danh.</p>
        
        <form @submit.prevent="submit" class="report-form">
          <div class="radio-group">
            <label v-for="option in reasonOptions" :key="option.value" class="radio-label">
              <input type="radio" v-model="form.reason" :value="option.value" required />
              <div class="radio-text">
                <strong>{{ option.label }}</strong>
                <p v-if="option.desc">{{ option.desc }}</p>
              </div>
            </label>
          </div>

          <div class="input-area" v-if="form.reason">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Chi tiết thêm (không bắt buộc):</label>
            <textarea 
              v-model="form.description" 
              class="content-input" 
              rows="3" 
              placeholder="Mô tả rõ hơn về vi phạm..."
            ></textarea>
          </div>

          <div v-if="errorMsg" class="error-alert">
            {{ errorMsg }}
          </div>

          <div class="form-actions">
            <button type="button" class="btn secondary" @click="close" :disabled="isSubmitting">Hủy</button>
            <button type="submit" class="btn primary submit-btn" :disabled="isSubmitting || !form.reason">
              <span v-if="isSubmitting" class="spinner"></span>
              <span v-else>Gửi báo cáo</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { api } from '@/services/api';

const props = defineProps({
  isOpen: Boolean,
  targetType: String,
  targetId: String,
});

const emit = defineEmits(['close', 'success']);

const form = ref({
  reason: '',
  description: ''
});

const isSubmitting = ref(false);
const errorMsg = ref('');

const reasonOptions = [
  { value: 'spam', label: 'Spam', desc: 'Nội dung quảng cáo, lừa đảo, hoặc đăng lặp lại nhiều lần.' },
  { value: 'offensive', label: 'Nội dung phản cảm', desc: 'Sử dụng ngôn từ gây thù ghét, thô tục hoặc nhạy cảm.' },
  { value: 'fake', label: 'Giả mạo / Thông tin sai lệch', desc: 'Thông tin không đúng sự thật hoặc mạo danh người khác.' },
  { value: 'harassment', label: 'Quấy rối / Bắt nạt', desc: 'Công kích cá nhân, xúc phạm hoặc đe dọa.' },
  { value: 'other', label: 'Lý do khác', desc: 'Vi phạm các chính sách khác của hệ thống.' }
];

const close = () => {
  form.value.reason = '';
  form.value.description = '';
  errorMsg.value = '';
  emit('close');
};

const submit = async () => {
  if (!form.value.reason) return;
  
  isSubmitting.value = true;
  errorMsg.value = '';

  try {
    await api('/api/reports', {
      method: 'POST',
      body: JSON.stringify({
        target_type: props.targetType,
        target_id: props.targetId,
        reason: form.value.reason,
        description: form.value.description
      })
    });
    
    emit('success');
    close();
  } catch (err) {
    errorMsg.value = err.message || 'Đã xảy ra lỗi khi gửi báo cáo.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}
.modal-container {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e4e6eb;
}
.modal-title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #1c1e21;
}
.close-btn {
  background: #e4e6eb;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #65676b;
  transition: background 0.2s;
}
.close-btn.never-hover-class-placeholder {
  background: #d8dadf;
}
.modal-body {
  padding: 20px;
  overflow-y: auto;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}
.btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  font-size: 15px;
  transition: all 0.2s;
}
.btn.secondary {
  background: #e4e6eb;
  color: #050505;
}
.btn.secondary.never-hover-class-placeholder:not(:disabled) {
  background: #d8dadf;
}
.btn.primary {
  background: #1877f2;
  color: #fff;
}
.btn.primary.never-hover-class-placeholder:not(:disabled) {
  background: #166fe5;
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.report-modal {
  max-width: 500px;
}
.report-desc {
  font-size: 14px;
  color: #65676b;
  margin-bottom: 16px;
}
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}
.radio-label {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
  padding: 12px;
  border: 1px solid #e4e6eb;
  border-radius: 8px;
  transition: all 0.2s;
}
.radio-label.never-hover-class-placeholder {
  background: #f0f2f5;
}
.radio-label input[type="radio"] {
  margin-top: 4px;
}
.radio-text strong {
  display: block;
  font-size: 15px;
  color: #1c1e21;
}
.radio-text p {
  margin: 4px 0 0;
  font-size: 13px;
  color: #65676b;
}
.content-input {
  width: 100%;
  border: 1px solid #ced0d4;
  border-radius: 6px;
  padding: 12px;
  font-family: inherit;
  resize: vertical;
}
.content-input:focus {
  outline: none;
  border-color: #1877f2;
}
.error-alert {
  background: #ffebe8;
  color: #fa3e3e;
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 16px;
  font-size: 14px;
}
</style>
