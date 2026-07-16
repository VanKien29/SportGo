<template>
  <div v-if="isOpen" class="modal-overlay" @mousedown.self="close">
    <div class="modal-container">
      <div class="modal-header">
        <div style="width: 32px"></div> <!-- Spacer for centering -->
        <h3 class="modal-title">Tạo bài giao lưu</h3>
        <button type="button" class="close-btn" @click="close">
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

          <div v-if="venueOptions.length === 0" class="empty-booking-alert">
            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 12px; color: #9ca3af;"></i>
            <p>Bạn chưa có lịch đặt sân nào sắp tới.</p>
            <p style="font-size: 0.85rem; color: #6b7280; margin-top: 4px;">Chỉ khi bạn đã đặt sân thì mới có thể tạo bài giao lưu tại sân đó.</p>
          </div>

          <template v-else>
            <!-- Select Venue -->
            <div class="form-group mt-3">
            <label class="form-label">Chọn sân <span class="text-danger">*</span></label>
            <CustomSelect 
              v-model="form.venue_id" 
              :options="venueOptions" 
              placeholder="-- Chọn cụm sân giao lưu --" 
              class="w-full"
            />
          </div>

          <div class="form-row mt-3">
            <!-- Meetup Time -->
            <div class="form-group flex-1">
              <label class="form-label">Khung giờ đã đặt <span class="text-danger">*</span></label>
              <CustomSelect 
                v-model="form.booking_id" 
                :options="bookingOptions" 
                placeholder="-- Chọn lịch đã đặt --"
                class="w-full"
                :disabled="!form.venue_id"
              />
            </div>
            
            <!-- Number of Players -->
            <div class="form-group flex-1 ml-3" style="margin-left: 16px;">
              <label class="form-label">Số người cần tuyển <span class="text-danger">*</span></label>
              <input 
                type="number" 
                v-model="form.required_players" 
                class="premium-input" 
                style="color: #111827 !important; -webkit-text-fill-color: #111827 !important;"
                min="1" 
                max="50"
                placeholder="Ví dụ: 4"
                required 
              />
            </div>
          </div>

          <!-- Description -->
          <div class="input-area mt-3">
            <label class="form-label">Mô tả thêm</label>
            <textarea 
              v-model="form.content" 
              class="premium-textarea" 
              rows="4" 
              placeholder="Chia sẻ thêm về trình độ, chi phí, hoặc yêu cầu khác..."
            ></textarea>
          </div>

          <div v-if="errorMsg" class="error-alert mt-3">
            <i class="fas fa-exclamation-triangle"></i> {{ errorMsg }}
          </div>

          <div class="form-actions mt-4">
            <button type="submit" class="btn submit-btn" :disabled="isSubmitting || !isValid">
              <span v-if="isSubmitting" class="spinner"></span>
              <span v-else>Đăng bài</span>
            </button>
          </div>
          </template>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import CustomSelect from '@/components/CustomSelect.vue';
import { getAuth } from '@/stores/auth.js';
import { api } from '@/services/api';

const props = defineProps({
  isOpen: Boolean,
});

const emit = defineEmits(['close', 'success']);

const user = getAuth();
const userInitial = computed(() => user?.fullName?.charAt(0)?.toUpperCase() || '?');

const form = ref({
  venue_id: '',
  booking_id: '',
  required_players: '',
  content: '',
});

const isSubmitting = ref(false);
const errorMsg = ref('');
const userBookings = ref([]); // To be populated from API

function formatDate(dateStr) {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

const venueOptions = computed(() => {
  const unique = [];
  const map = new Map();
  for (const b of userBookings.value) {
    if (!map.has(b.venue_id)) {
      map.set(b.venue_id, true);
      const loc = b.location ? ` (${b.location})` : '';
      unique.push({ value: b.venue_id, label: `${b.venue_name}${loc}` });
    }
  }
  return unique;
});

const bookingOptions = computed(() => {
  if (!form.value.venue_id) return [];
  return userBookings.value
    .filter(b => b.venue_id === form.value.venue_id)
    .map(b => ({ value: b.id, label: `${b.time} - ${formatDate(b.date)}` }));
});

const isValid = computed(() => {
  return form.value.venue_id && form.value.booking_id && form.value.required_players > 0;
});

const close = () => {
  emit('close');
  resetForm();
};

const resetForm = () => {
  form.value = {
    venue_id: '',
    booking_id: '',
    required_players: '',
    content: '',
  };
  errorMsg.value = '';
};

const fetchVenues = async () => {
  try {
    const res = await api('/api/matchmaking-posts/eligible-bookings');
    userBookings.value = res.data;
  } catch (e) {
    console.error('Failed to fetch eligible bookings:', e);
  }
};

onMounted(() => {
  fetchVenues();
});

const submit = async () => {
  if (!isValid.value) return;
  isSubmitting.value = true;
  errorMsg.value = '';

  try {
    const res = await api('/api/matchmaking-posts', {
      method: 'POST',
      body: JSON.stringify(form.value)
    });
    emit('success', res.data);
    close();
  } catch (error) {
    errorMsg.value = error.response?.data?.message || 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<style scoped>
/* Modal Base Styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  padding: 16px;
}

.modal-container {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: #111827;
  text-align: center;
  flex: 1;
}

.close-btn {
  background: #f3f4f6;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4b5563;
  cursor: pointer;
  transition: all 0.2s;
}
.close-btn:hover {
  background: #e5e7eb;
  color: #111827;
}

.modal-body {
  padding: 16px;
  overflow: visible;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.125rem;
}

.user-meta {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #111827;
  font-size: 0.95rem;
}

.privacy-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #4b5563;
  margin-top: 4px;
  font-weight: 500;
}

.form-group {
  margin-bottom: 12px;
}

.form-row {
  display: flex;
  gap: 16px;
}
.flex-1 {
  flex: 1;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.text-danger {
  color: #ef4444;
}

.premium-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.95rem;
  color: #111827 !important;
  -webkit-text-fill-color: #111827 !important;
  transition: all 0.2s;
  background: #fff !important;
}

.premium-input::placeholder,
.premium-textarea::placeholder {
  color: #9ca3af !important;
  opacity: 1;
}

.premium-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  background: #fff;
}

.premium-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 1rem;
  color: #1f2937 !important;
  resize: none;
  transition: all 0.2s;
  background: #fff !important;
}

.premium-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  background: #fff;
}

.mt-3 {
  margin-top: 16px;
}
.mt-4 {
  margin-top: 24px;
}

.error-alert {
  padding: 10px 14px;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 8px;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.submit-btn {
  width: 100%;
  padding: 12px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  justify-content: center;
  align-items: center;
}

.submit-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.submit-btn:disabled {
  background: #93c5fd;
  cursor: not-allowed;
}

.spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-booking-alert {
  text-align: center;
  padding: 30px 20px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px dashed #d1d5db;
  margin-top: 20px;
  color: #374151;
  font-weight: 500;
}
</style>
