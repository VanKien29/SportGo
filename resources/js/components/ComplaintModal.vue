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
          <AppIcon name="close" size="18" />
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
            <SgButton type="secondary" :disabled="isSubmitting" @click="close">Hủy</SgButton>
            <SgButton native-type="submit" type="primary" :loading="isSubmitting" :disabled="!isValid">
              Gửi khiếu nại
            </SgButton>
          </footer>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import SgButton from './common/SgButton.vue';
import { api, apiFormData } from '@/services/api';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  initialType: { type: String, default: 'system' },
  initialVenueId: { type: [String, Number], default: '' },
  initialVenueName: { type: String, default: '' },
  initialBookingId: { type: [String, Number], default: '' },
});

const emit = defineEmits(['close', 'success']);
const form = reactive({
  complaint_type: 'system',
  venue_cluster_id: '',
  booking_id: '',
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
  const clusters = new Map();
  recentBookings.value.forEach((booking) => {
    const cluster = booking.venue_cluster || booking.venueCluster || booking.venue_court?.venue_cluster;
    if (cluster?.id) clusters.set(String(cluster.id), cluster);
  });
  if (props.initialVenueId) {
    clusters.set(String(props.initialVenueId), {
      id: props.initialVenueId,
      name: props.initialVenueName || 'Cụm sân đang xem',
    });
  }
  return Array.from(clusters.values());
});

const isValid = computed(() => {
  if (!form.content || !form.complaint_type) return false;
  return form.complaint_type !== 'venue' || Boolean(form.venue_cluster_id);
});

function applyInitialContext() {
  form.complaint_type = props.initialType === 'venue' ? 'venue' : 'system';
  form.venue_cluster_id = props.initialVenueId ? String(props.initialVenueId) : '';
  form.booking_id = props.initialBookingId ? String(props.initialBookingId) : '';
}

async function fetchBookings() {
  bookingsLoading.value = true;
  try {
    const response = await api('/api/bookings?per_page=50');
    recentBookings.value = Array.isArray(response.data) ? response.data : [];
    if (form.booking_id) onBookingChange();
  } catch (error) {
    recentBookings.value = [];
    if (!props.initialVenueId) {
      errorMsg.value = error.message || 'Không thể tải lịch đặt sân gần đây.';
    }
  } finally {
    bookingsLoading.value = false;
  }
}

function bookingCluster(booking) {
  return booking?.venue_cluster || booking?.venueCluster || booking?.venue_court?.venue_cluster || null;
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
  if (!booking) {
    if (!props.initialVenueId) form.venue_cluster_id = '';
    return;
  }
  const cluster = bookingCluster(booking);
  form.venue_cluster_id = String(booking.venue_cluster_id || cluster?.id || '');
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
      payload.append('venue_cluster_id', String(form.venue_cluster_id));
      if (form.booking_id) payload.append('booking_id', String(form.booking_id));
    }
    if (form.imageFile) payload.append('evidence_image', form.imageFile);

    const response = await apiFormData('/api/complaints', payload);
    emit('success', response);
    reset();
    emit('close');
  } catch (error) {
    errorMsg.value = error.message || 'Không thể gửi khiếu nại. Vui lòng thử lại.';
  } finally {
    isSubmitting.value = false;
  }
}

watch(() => form.complaint_type, (type) => {
  if (type === 'system') {
    form.booking_id = '';
    form.venue_cluster_id = '';
  } else if (props.initialVenueId && !form.venue_cluster_id) {
    form.venue_cluster_id = String(props.initialVenueId);
  }
});

watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    applyInitialContext();
    fetchBookings();
  } else if (!isSubmitting.value) {
    reset();
  }
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

.complaint-modal {
  width: min(100%, 580px);
  max-height: 90vh;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  color: var(--admin-text);
}

.modal-header,
.form-actions,
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
  margin: 0 0 18px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
  line-height: 1.55;
}

.complaint-form,
.type-list,
.field-block {
  display: grid;
  gap: 10px;
}

.type-list {
  margin: 0;
  padding: 0;
  border: 0;
}

.type-list legend,
.field-block > span {
  margin-bottom: 2px;
  color: var(--admin-text);
  font-size: var(--admin-font-size-base);
  font-weight: 600;
}

.type-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 12px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  cursor: pointer;
}

.type-option.selected {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
}

.type-option input {
  margin-top: 3px;
  accent-color: var(--admin-primary);
}

.type-option > span {
  display: grid;
  gap: 2px;
}

.type-option strong {
  font-size: var(--admin-font-size-base);
}

.type-option small,
.field-block small,
.character-count {
  color: var(--admin-muted);
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

select.field-control,
.file-control {
  min-height: 40px;
  padding: 8px 10px;
}

textarea.field-control {
  min-height: 108px;
  padding: 10px 12px;
  resize: vertical;
}

.field-control:focus {
  border-color: var(--admin-primary);
}

.field-hint {
  line-height: 1.45;
}

.character-count {
  justify-self: end;
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

  .complaint-modal {
    max-height: 94vh;
    border-radius: var(--admin-radius-lg) var(--admin-radius-lg) 0 0;
  }

  .modal-body {
    max-height: calc(94vh - 74px);
  }
}
</style>
