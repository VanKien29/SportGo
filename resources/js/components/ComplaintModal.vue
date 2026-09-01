<template>
  <div v-if="isOpen" class="modal-overlay" role="presentation" @click.self="close">
    <section
      class="complaint-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="complaint-modal-title"
    >
      <header class="modal-header">
        <div class="modal-header-text">
          <h2 id="complaint-modal-title" class="modal-title">Gửi khiếu nại</h2>
          <p class="modal-subtitle">Tiếp nhận và phản hồi các vấn đề phát sinh khi sử dụng dịch vụ tại sân.</p>
        </div>
        <button type="button" class="modal-close-btn" aria-label="Đóng" @click="close">
          <AppIcon name="x" :size="18" />
        </button>
      </header>

      <div class="modal-body">
        <form class="complaint-form" @submit.prevent="submit">
          <!-- Custom Booking Dropdown -->
          <div ref="dropdownRef" class="field-group custom-dropdown-group">
            <label class="field-label">
              <span>Lịch đặt sân liên quan</span>
              <span class="field-required">Bắt buộc</span>
            </label>

            <div class="custom-dropdown">
              <button
                type="button"
                class="dropdown-trigger-btn"
                :class="{ 'dropdown-trigger-btn--open': isDropdownOpen }"
                aria-haspopup="listbox"
                :aria-expanded="isDropdownOpen"
                :disabled="bookingsLoading || !recentBookings.length"
                @click="toggleDropdown"
              >
                <span
                  class="dropdown-trigger-text"
                  :class="{ 'dropdown-trigger-placeholder': !form.booking_id }"
                >
                  {{ currentSelectedLabel }}
                </span>
                <AppIcon
                  name="chevron-down"
                  :size="16"
                  class="dropdown-chevron-icon"
                  :class="{ 'dropdown-chevron-icon--rotated': isDropdownOpen }"
                />
              </button>

              <!-- Dropdown Menu List -->
              <div
                v-if="isDropdownOpen"
                class="dropdown-menu-list"
                role="listbox"
                tabindex="-1"
              >
                <div v-if="bookingsLoading" class="dropdown-empty-state">
                  Đang tải danh sách booking...
                </div>
                <div v-else-if="!recentBookings.length" class="dropdown-empty-state">
                  Hiện chưa có booking trong thời gian tiếp nhận khiếu nại.
                </div>
                <template v-else>
                  <button
                    v-for="booking in recentBookings"
                    :key="booking.id"
                    type="button"
                    role="option"
                    :aria-selected="String(form.booking_id) === String(booking.id)"
                    class="dropdown-option-item"
                    :class="{ 'dropdown-option-item--selected': String(form.booking_id) === String(booking.id) }"
                    @click="selectBooking(booking)"
                  >
                    <span class="dropdown-option-text">{{ bookingOptionLabel(booking) }}</span>
                    <AppIcon
                      v-if="String(form.booking_id) === String(booking.id)"
                      name="check"
                      :size="15"
                      class="dropdown-option-check"
                    />
                  </button>
                </template>
              </div>
            </div>

            <span v-if="bookingsLoading" class="field-hint">Đang tải danh sách booking...</span>
            <span v-else-if="!recentBookings.length" class="field-hint">
              Hiện chưa có booking trong thời gian tiếp nhận khiếu nại.
            </span>
          </div>

          <!-- Content text -->
          <div class="field-group">
            <label class="field-label" for="complaint-content-input">
              <span>Nội dung chi tiết</span>
              <span class="field-required">Bắt buộc</span>
            </label>
            <textarea
              id="complaint-content-input"
              v-model.trim="form.content"
              class="field-control field-textarea"
              rows="4"
              maxlength="2000"
              required
              placeholder="Mô tả cụ thể sự việc, thời điểm và yêu cầu hỗ trợ của bạn..."
            ></textarea>
            <div class="field-counter">{{ form.content.length }}/2000</div>
          </div>

          <!-- Image evidence -->
          <div class="field-group">
            <label class="field-label" for="complaint-image-input">
              <span>Ảnh minh chứng</span>
              <span class="field-optional">Tối đa 5 MB</span>
            </label>
            <input
              id="complaint-image-input"
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

          <!-- Footer Actions -->
          <footer class="form-actions">
            <button type="button" class="btn-cancel" :disabled="isSubmitting" @click="close">
              Hủy
            </button>
            <button type="submit" class="btn-submit" :disabled="isSubmitting || !isValid">
              {{ isSubmitting ? 'Đang gửi...' : 'Gửi khiếu nại' }}
            </button>
          </footer>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { api, apiFormData } from '@/services/api';
import { businessDateLabel } from '@/utils/businessTime.js';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  initialVenueId: { type: [String, Number], default: '' },
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
const isDropdownOpen = ref(false);
const dropdownRef = ref(null);

const isValid = computed(() => Boolean(
  form.content.trim() && form.complaint_type === 'venue' && form.booking_id && form.venue_cluster_id
));

const currentSelectedLabel = computed(() => {
  if (!form.booking_id) return 'Chọn booking đang hoạt động';
  const found = recentBookings.value.find((b) => String(b.id) === String(form.booking_id));
  return found ? bookingOptionLabel(found) : 'Chọn booking đang hoạt động';
});

function bookingCluster(booking) {
  return booking.venue_cluster || booking.venueCluster || booking.cluster || null;
}

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

function bookingOptionLabel(booking) {
  const date = booking.booking_date
    ? businessDateLabel(booking.booking_date)
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

function toggleDropdown() {
  if (bookingsLoading.value || !recentBookings.value.length) return;
  isDropdownOpen.value = !isDropdownOpen.value;
}

function selectBooking(booking) {
  form.booking_id = String(booking.id);
  onBookingChange();
  isDropdownOpen.value = false;
}

function handleClickOutside(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isDropdownOpen.value = false;
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
  isDropdownOpen.value = false;
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

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
  revokePreview();
});
</script>

<style scoped>
/* ==========================================================================
   COMPLAINT MODAL - UNIFIED WHITE, BORDERLESS & MINIMALIST FLAT STYLING
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

.complaint-modal {
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
.complaint-modal *,
.complaint-modal h2,
.complaint-modal span,
.complaint-modal label,
.complaint-modal button {
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

/* Body & Form */
.modal-body {
  display: flex;
  flex-direction: column;
}

.complaint-form {
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

/* ==========================================================================
   CUSTOM DROPDOWN (Replaces native browser <select>)
   ========================================================================== */
.custom-dropdown-group {
  position: relative;
}

.custom-dropdown {
  position: relative;
  width: 100%;
}

.dropdown-trigger-btn {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #94a3b8;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 13.5px;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  box-sizing: border-box;
  text-align: left;
  transition: border-color 0.15s ease;
}

.dropdown-trigger-btn:hover:not(:disabled) {
  border-color: #64748b;
}

.dropdown-trigger-btn--open {
  border-color: #15803d !important;
}

.dropdown-trigger-btn:disabled {
  background: #f8fafc;
  color: #94a3b8;
  cursor: not-allowed;
  border-color: #cbd5e1;
}

.dropdown-trigger-text {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #0f172a;
}

.dropdown-trigger-placeholder {
  color: #64748b;
}

.dropdown-chevron-icon {
  color: #475569;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.dropdown-chevron-icon--rotated {
  transform: rotate(180deg);
}

/* Dropdown Menu List Popover */
.dropdown-menu-list {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 50;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
  max-height: 220px;
  overflow-y: auto;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropdown-option-item {
  width: 100%;
  padding: 9px 12px;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: #0f172a;
  font-size: 13.5px;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s ease, color 0.12s ease;
  box-sizing: border-box;
}

.dropdown-option-item:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.dropdown-option-item--selected {
  background: #f0fdf4 !important;
  color: #15803d !important;
}

.dropdown-option-text {
  flex: 1;
  line-height: 1.4;
}

.dropdown-option-check {
  color: #15803d;
  flex-shrink: 0;
}

.dropdown-empty-state {
  padding: 12px;
  font-size: 13px;
  color: #64748b;
  text-align: center;
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
  min-height: 90px;
  line-height: 1.5;
}

.file-input-control {
  padding: 7px 10px;
}

.field-hint {
  font-size: 12.5px;
  color: #475569;
  margin-top: 2px;
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
