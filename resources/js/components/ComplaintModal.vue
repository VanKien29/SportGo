<template>
  <div v-if="isOpen" class="modal-overlay" @click.self="close">
    <div class="modal-container">
      <div class="modal-header">
        <h3 class="modal-title">Gửi khiếu nại</h3>
        <button class="close-btn" @click="close">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <div class="modal-body">
        <p class="desc-text">Vui lòng cung cấp chi tiết để chúng tôi hỗ trợ bạn tốt nhất.</p>
        
        <form @submit.prevent="submit" class="complaint-form">
          
          <div class="input-area">
            <label class="field-label">Loại khiếu nại <span class="required">*</span></label>
            <div class="radio-group">
              <label class="radio-label" :class="{ active: form.complaint_type === 'system' }">
                <input type="radio" v-model="form.complaint_type" value="system" required />
                <div class="radio-text">
                  <strong>Hệ thống SportGo</strong>
                  <p>Lỗi ứng dụng, thanh toán, tài khoản...</p>
                </div>
              </label>
              <label class="radio-label" :class="{ active: form.complaint_type === 'venue' }">
                <input type="radio" v-model="form.complaint_type" value="venue" required />
                <div class="radio-text">
                  <strong>Sân / Chủ sân</strong>
                  <p>Dịch vụ sân, thái độ nhân viên, sai lịch...</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Nếu chọn khiếu nại sân -->
          <template v-if="form.complaint_type === 'venue'">
            <div class="input-area mt-3">
              <label class="field-label">Lịch đặt sân / Cụm sân liên quan <span class="required">*</span></label>
              <select v-model="form.booking_id" class="content-input" @change="onBookingChange">
                <option value="">-- Chọn lịch đặt sân gần đây (Tuỳ chọn) --</option>
                <option v-for="bk in recentBookings" :key="bk.id" :value="bk.id">
                  {{ formatDate(bk.booking_date) }} - {{ bk.venueCluster?.name }} (Mã: {{ bk.booking_code }})
                </option>
              </select>
            </div>
            
            <div class="input-area mt-3" v-if="!form.booking_id">
              <label class="field-label">Hoặc chọn Cụm sân <span class="required">*</span></label>
              <select v-model="form.venue_cluster_id" class="content-input" :required="!form.booking_id">
                <option value="">-- Chọn cụm sân --</option>
                <option v-for="vc in uniqueVenueClusters" :key="vc.id" :value="vc.id">
                  {{ vc.name }}
                </option>
              </select>
              <small class="help-text">Danh sách sân bạn đã từng đặt.</small>
            </div>
          </template>

          <div class="input-area mt-3">
            <label class="field-label">Nội dung chi tiết <span class="required">*</span></label>
            <textarea 
              v-model="form.content" 
              class="content-input" 
              rows="4" 
              required
              placeholder="Mô tả cụ thể vấn đề bạn gặp phải..."
            ></textarea>
          </div>

          <div class="input-area mt-3">
            <label class="field-label">Ảnh minh chứng (nếu có)</label>
            <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" @change="onImageSelected" class="content-input" style="padding: 8px;" />
            
            <div v-if="imagePreview" class="image-preview" style="margin-top: 8px; position: relative; max-width: 200px;">
              <img :src="imagePreview" style="width: 100%; border-radius: 6px; border: 1px solid #ced0d4;" />
              <button type="button" @click="removeImage" style="position: absolute; top: -8px; right: -8px; background: white; border: 1px solid #ced0d4; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
          </div>

          <div v-if="errorMsg" class="error-alert mt-3">
            {{ errorMsg }}
          </div>

          <div class="form-actions">
            <button type="button" class="btn secondary" @click="close" :disabled="isSubmitting">Hủy</button>
            <button type="submit" class="btn primary submit-btn" :disabled="isSubmitting || !isValid">
              <span v-if="isSubmitting" class="spinner"></span>
              <span v-else>Gửi khiếu nại</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { api, apiFormData } from '@/services/api';

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'success']);

const form = ref({
  complaint_type: 'system',
  venue_cluster_id: '',
  booking_id: '',
  content: '',
  imageFile: null
});

const isSubmitting = ref(false);
const errorMsg = ref('');
const imagePreview = ref(null);
const recentBookings = ref([]);

const uniqueVenueClusters = computed(() => {
  const map = new Map();
  recentBookings.value.forEach(bk => {
    if (bk.venueCluster) {
      map.set(bk.venueCluster.id, bk.venueCluster);
    }
  });
  return Array.from(map.values());
});

const isValid = computed(() => {
  if (!form.value.complaint_type || !form.value.content) return false;
  if (form.value.complaint_type === 'venue') {
    if (!form.value.booking_id && !form.value.venue_cluster_id) return false;
  }
  return true;
});

const fetchBookings = async () => {
  try {
    const res = await api('/api/bookings?per_page=50');
    recentBookings.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách booking', err);
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchBookings();
  }
});

watch(() => form.value.complaint_type, (newVal) => {
  if (newVal === 'system') {
    form.value.venue_cluster_id = '';
    form.value.booking_id = '';
  }
});

const onBookingChange = () => {
  const selectedBooking = recentBookings.value.find(b => b.id === form.value.booking_id);
  if (selectedBooking) {
    form.value.venue_cluster_id = selectedBooking.venue_cluster_id;
  }
};

const formatDate = (val) => {
  if (!val) return '';
  return new Date(val).toLocaleDateString('vi-VN');
};

const onImageSelected = (event) => {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      errorMsg.value = 'Vui lòng chọn ảnh nhỏ hơn 5MB.';
      return;
    }
    form.value.imageFile = file;
    imagePreview.value = URL.createObjectURL(file);
    errorMsg.value = '';
  }
};

const removeImage = () => {
  form.value.imageFile = null;
  if (imagePreview.value) {
    URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
  }
};

const close = () => {
  form.value.complaint_type = 'system';
  form.value.venue_cluster_id = '';
  form.value.booking_id = '';
  form.value.content = '';
  removeImage();
  errorMsg.value = '';
  emit('close');
};

const submit = async () => {
  if (!isValid.value) return;
  
  isSubmitting.value = true;
  errorMsg.value = '';

  try {
    const formData = new FormData();
    formData.append('complaint_type', form.value.complaint_type);
    formData.append('content', form.value.content);
    if (form.value.complaint_type === 'venue') {
      if (form.value.venue_cluster_id) formData.append('venue_cluster_id', form.value.venue_cluster_id);
      if (form.value.booking_id) formData.append('booking_id', form.value.booking_id);
    }
    
    if (form.value.imageFile) {
      formData.append('evidence_image', form.value.imageFile);
    }

    await apiFormData('/api/complaints', formData);
    
    emit('success');
    close();
  } catch (err) {
    errorMsg.value = err.message || 'Đã xảy ra lỗi khi gửi khiếu nại.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
  backdrop-filter: blur(2px);
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
.close-btn:hover {
  background: #d8dadf;
}
.modal-body {
  padding: 20px;
  overflow-y: auto;
}
.desc-text {
  font-size: 14px;
  color: #65676b;
  margin-bottom: 16px;
}
.field-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #050505;
  margin-bottom: 8px;
}
.required {
  color: #e41e3f;
}
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.radio-label {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
  padding: 12px;
  border: 1px solid #ced0d4;
  border-radius: 8px;
  transition: all 0.2s;
}
.radio-label:hover {
  background: #f0f2f5;
}
.radio-label.active {
  border-color: #1877f2;
  background: #e7f3ff;
}
.radio-label input[type="radio"] {
  margin-top: 4px;
}
.radio-text strong {
  display: block;
  font-size: 15px;
  color: #1c1e21;
}
.radio-label.active .radio-text strong {
  color: #1877f2;
}
.radio-text p {
  margin: 4px 0 0;
  font-size: 13px;
  color: #65676b;
}
.content-input {
  width: 100%;
  border: 1px solid #ced0d4;
  border-radius: 8px;
  padding: 12px;
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
}
.content-input:focus {
  outline: none;
  border-color: #1877f2;
  box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.2);
}
.help-text {
  font-size: 12px;
  color: #65676b;
  display: block;
  margin-top: 4px;
}
.mt-3 {
  margin-top: 16px;
}
.error-alert {
  background: #ffebe8;
  color: #fa3e3e;
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}
.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  font-size: 15px;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.btn.secondary {
  background: #e4e6eb;
  color: #050505;
}
.btn.secondary:hover:not(:disabled) {
  background: #d8dadf;
}
.btn.primary {
  background: #1877f2;
  color: #fff;
}
.btn.primary:hover:not(:disabled) {
  background: #166fe5;
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
