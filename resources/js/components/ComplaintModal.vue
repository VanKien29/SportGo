<template>
  <div v-if="isOpen" class="modal-overlay" role="presentation" @click.self="close">
    <section
      class="complaint-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="complaint-modal-title"
    >
      <header class="modal-header">
        <div>
          <span class="modal-kicker">Trung tâm hỗ trợ</span>
          <h2 id="complaint-modal-title">Gửi khiếu nại</h2>
        </div>
        <button type="button" class="icon-button" aria-label="Đóng" @click="close">
          <AppIcon name="x" size="18" />
        </button>
      </header>

      <div class="modal-body">
        <p class="modal-description">
          Khiếu nại dùng cho vấn đề cần được hỗ trợ và phản hồi. Nếu bạn chỉ muốn thông báo nội dung vi phạm, hãy dùng chức năng báo cáo.
        </p>

        <form class="complaint-form" @submit.prevent="submit">
          <fieldset class="type-list">
            <legend>Vấn đề liên quan</legend>
            <label class="type-option" :class="{ selected: form.complaint_type === 'system' }">
              <input v-model="form.complaint_type" type="radio" value="system" required />
              <span>
                <strong>Hệ thống SportGo</strong>
                <small>Tài khoản, thanh toán hoặc lỗi sử dụng hệ thống.</small>
              </span>
            </label>
            <label class="type-option" :class="{ selected: form.complaint_type === 'venue' }">
              <input v-model="form.complaint_type" type="radio" value="venue" required />
              <span>
                <strong>Sân hoặc đơn vị vận hành</strong>
                <small>Chất lượng dịch vụ, nhân viên hoặc thông tin lịch sân.</small>
              </span>
            </label>
          </fieldset>

          <template v-if="form.complaint_type === 'venue'">
            <label class="field-block">
              <span>Lịch đặt sân liên quan <small>Không bắt buộc</small></span>
              <select v-model="form.booking_id" class="field-control" @change="onBookingChange">
                <option value="">Không gắn với lịch đặt cụ thể</option>
                <option v-for="booking in recentBookings" :key="booking.id" :value="booking.id">
                  {{ bookingOptionLabel(booking) }}
                </option>
              </select>
            </label>

            <label v-if="!form.booking_id" class="field-block">
              <span>Cụm sân <small>Bắt buộc</small></span>
              <select v-model="form.venue_cluster_id" class="field-control" required>
                <option value="" disabled>Chọn cụm sân cần khiếu nại</option>
                <option v-for="cluster in availableVenueClusters" :key="cluster.id" :value="cluster.id">
                  {{ cluster.name }}
                </option>
              </select>
              <small v-if="bookingsLoading" class="field-hint">Đang tải các sân từ lịch đặt gần đây...</small>
              <small v-else-if="!availableVenueClusters.length" class="field-hint">
                Chưa có cụm sân phù hợp trong lịch đặt của bạn.
              </small>
            </label>
          </template>

          <label class="field-block">
            <span>Nội dung chi tiết <small>Bắt buộc</small></span>
            <textarea
              v-model.trim="form.content"
              class="field-control"
              rows="4"
              maxlength="2000"
              required
              placeholder="Mô tả sự việc, thời điểm và hỗ trợ bạn mong muốn"
            ></textarea>
            <small class="character-count">{{ form.content.length }}/2000</small>
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
            <button type="submit" class="submit-button" :disabled="isSubmitting || !isValid">
              {{ isSubmitting ? 'Đang gửi...' : 'Gửi khiếu nại' }}
            </button>
          </footer>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { api, apiFormData } from '@/services/api';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  initialVenueId: { type: [String, Number], default: '' },
  initialBookingId: { type: [String, Number], default: '' },
});

const emit = defineEmits(['close', 'success']);
const form = reactive({
  complaint_type: 'system',
  booking_id: '',
  venue_cluster_id: '',
  content: '',
  imageFile: null,
});

const recentBookings = ref([]);
const bookingsLoading = ref(false);
const isSubmitting = ref(false);
const errorMsg = ref('');
const imagePreview = ref('');
const fileInput = ref(null);

const availableVenueClusters = computed(() => {
  const map = new Map();
  recentBookings.value.forEach((booking) => {
    const cluster = bookingCluster(booking);
    if (cluster?.id && !map.has(String(cluster.id))) {
      map.set(String(cluster.id), { id: cluster.id, name: cluster.name });
    }
  });
  return Array.from(map.values());
});

const isValid = computed(() => {
  if (!form.content.trim()) return false;
  if (form.complaint_type === 'venue') {
    return Boolean(form.booking_id || form.venue_cluster_id);
  }
  return true;
});

function bookingCluster(booking) {
  return booking.venue_cluster || booking.venueCluster || booking.cluster || null;
}

function applyInitialContext() {
  if (props.initialBookingId) {
    form.complaint_type = 'venue';
    form.booking_id = String(props.initialBookingId);
  } else if (props.initialVenueId) {
    form.complaint_type = 'venue';
    form.venue_cluster_id = String(props.initialVenueId);
  } else {
    form.complaint_type = 'system';
    form.booking_id = '';
    form.venue_cluster_id = '';
  }
}

async function fetchBookings() {
  bookingsLoading.value = true;
  try {
    const res = await api('/api/bookings/my-bookings?per_page=20');
    recentBookings.value = res.data?.data || res.data || [];
    if (props.initialBookingId) {
      onBookingChange();
    }
  } catch {
    recentBookings.value = [];
  } finally {
    bookingsLoading.value = false;
  }
}

function bookingOptionLabel(booking) {
  const date = booking.booking_date
    ? new Date(`${booking.booking_date}T00:00:00`).toLocaleDateString('vi-VN')
    : 'Chưa rõ ngày';
  const cluster = bookingCluster(booking)?.name || 'Cụm sân';
  const code = booking.booking_code || booking.code || booking.id;
  return `${date} · ${cluster} · ${code}`;
}

function onBookingChange() {
  const booking = recentBookings.value.find((item) => String(item.id) === String(form.booking_id));
  if (booking) {
    const cluster = bookingCluster(booking);
    form.venue_cluster_id = cluster ? String(cluster.id) : '';
  } else if (!props.initialVenueId) {
    form.venue_cluster_id = '';
  }
}

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
  form.content = '';
  removeImage();
  errorMsg.value = '';
  applyInitialContext();
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
  if (!isValid.value || isSubmitting.value) return;
  isSubmitting.value = true;
  errorMsg.value = '';
  try {
    const payload = new FormData();
    payload.append('complaint_type', form.complaint_type);
    payload.append('content', form.content);
    if (form.complaint_type === 'venue') {
      if (form.booking_id) payload.append('booking_id', String(form.booking_id));
      if (form.venue_cluster_id) payload.append('venue_cluster_id', String(form.venue_cluster_id));
    }
    if (form.imageFile) payload.append('evidence_image', form.imageFile);

    await apiFormData('/api/complaints', payload);
    emit('success');
    reset();
    emit('close');
  } catch (error) {
    errorMsg.value = error.message || 'Không thể gửi khiếu nại. Vui lòng thử lại.';
  } finally {
    isSubmitting.value = false;
  }
}

watch(() => props.isOpen, (val) => {
  if (val) {
    applyInitialContext();
    fetchBookings();
  }
});

onBeforeUnmount(revokePreview);
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

.complaint-modal {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  width: 100%;
  max-width: 540px;
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
  margin: 0 0 18px 0;
}

.complaint-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.type-list {
  border: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.type-list legend {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 8px;
}

.type-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.type-option:hover {
  border-color: #94a3b8;
}

.type-option.selected {
  border-color: #15803d;
  background: #f8fafc;
}

.type-option input {
  margin-top: 3px;
  accent-color: #15803d;
}

.type-option strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.type-option small {
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

.field-hint {
  font-size: 12px;
  color: #475569;
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
