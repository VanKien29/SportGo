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
          <p class="modal-description">Khiếu nại dịch vụ chỉ được gửi từ booking đang hoạt động tại sân.</p>

          <template>
            <label class="field-block">
              <span>Lịch đặt sân liên quan <small>Bắt buộc</small></span>
              <select v-model="form.booking_id" class="field-control" @change="onBookingChange">
                <option value="" disabled>Chọn booking đang hoạt động</option>
                <option v-for="booking in recentBookings" :key="booking.id" :value="booking.id">
                  {{ bookingOptionLabel(booking) }}
                </option>
              </select>
            </label>

            <small v-if="bookingsLoading" class="field-hint">Đang tải booking đủ điều kiện...</small>
            <small v-else-if="!recentBookings.length" class="field-hint">
              Hiện chưa có booking trong thời gian tiếp nhận khiếu nại.
            </small>
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
  complaint_type: 'venue',
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

const isValid = computed(() => Boolean(
  form.content && form.complaint_type === 'venue' && form.booking_id && form.venue_cluster_id
));

function applyInitialContext() {
  form.complaint_type = 'venue';
  form.venue_cluster_id = props.initialVenueId ? String(props.initialVenueId) : '';
  form.booking_id = props.initialBookingId ? String(props.initialBookingId) : '';
}

async function fetchBookings() {
  bookingsLoading.value = true;
  try {
    const response = await api('/api/complaints/eligible-bookings', { cache: 'no-store', dedupe: false });
    recentBookings.value = Array.isArray(response.data) ? response.data : [];
    if (!form.booking_id && recentBookings.value.length) {
      form.booking_id = String(recentBookings.value[0].id);
    }
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
    payload.append('complaint_type', 'venue');
    payload.append('content', form.content);
    payload.append('venue_cluster_id', String(form.venue_cluster_id));
    payload.append('booking_id', String(form.booking_id));
    if (form.imageFile) payload.append('evidence_image', form.imageFile);

    const idempotencyKey = typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const response = await apiFormData('/api/complaints', payload, {
      headers: { 'Idempotency-Key': idempotencyKey }
    });
    emit('success', response);
    reset();
    emit('close');
  } catch (error) {
    errorMsg.value = error.message || 'Không thể gửi khiếu nại. Vui lòng thử lại.';
  } finally {
    isSubmitting.value = false;
  }
}

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
