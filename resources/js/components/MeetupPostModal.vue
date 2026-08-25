<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-overlay" role="presentation" @click.self="close">
      <section class="meetup-modal" role="dialog" aria-modal="true" aria-labelledby="meetup-modal-title">
      <header class="modal-header">
        <button type="button" class="icon-button" aria-label="Đóng" @click="close">
          <AppIcon name="x" size="18" />
        </button>
      </header>

      <div class="modal-body">
        <div class="author-summary">
          <div class="author-avatar">{{ userInitial }}</div>
          <div>
            <strong>{{ user?.fullName || 'Người dùng' }}</strong>
            <span><AppIcon name="users" size="14" /> Hiển thị công khai trong cộng đồng</span>
          </div>
        </div>

        <div v-if="bookingsLoading" class="modal-state">
          <span class="spinner" aria-hidden="true"></span>
          Đang tải lịch sân đủ điều kiện...
        </div>

        <div v-else-if="bookingsError" class="modal-state empty modal-state--error" role="alert">
          <AppIcon name="alert" size="28" />
          <strong>Không thể tải lịch đã đặt</strong>
          <span>{{ bookingsError }}</span>
          <SgButton type="secondary" @click="fetchEligibleBookings">Tải lại</SgButton>
        </div>

        <div v-else-if="!userBookings.length" class="modal-state empty">
          <AppIcon name="calendar" size="32" />
          <strong>Chưa có lịch sân phù hợp</strong>
          <span>Bạn cần một booking sắp tới đã được xác nhận để đăng bài tìm người ghép kèo.</span>
          <div class="empty-actions">
            <button type="button" class="mpm-book-btn" @click="goToBooking">
              Đặt sân ngay
            </button>
            <button type="button" class="mpm-cancel-btn" @click="close">
              Đóng
            </button>
          </div>
        </div>

        <form v-else class="meetup-form" @submit.prevent="submit">
          <div class="field-block">
            <span class="field-label">Cụm sân</span>
            <ClientCustomSelect
              v-model="form.venue_id"
              :options="venueOptions"
              placeholder="Chọn cụm sân"
            />
          </div>

          <div class="field-grid">
            <div class="field-block">
              <span class="field-label">Lịch đã đặt</span>
              <ClientCustomSelect
                v-model="form.booking_id"
                :options="bookingOptions"
                :disabled="!form.venue_id"
                icon="clock"
                placeholder="Chọn ngày và khung giờ"
              />
            </div>

            <div class="field-block">
              <label for="mpm-required-players" class="field-label">
                <span>Số người cần thêm</span>
              </label>
              <input
                id="mpm-required-players"
                v-model.number="form.required_players"
                class="field-control"
                type="number"
                min="1"
                max="50"
                required
              />
            </div>
          </div>

          <!-- BANNER THÔNG TIN MÔN THỂ THAO & LOẠI SÂN TỰ ĐỘNG -->
          <div v-if="selectedBooking" class="sport-badge-card">
            <div class="sport-badge-main">
              <span class="sport-icon-box">
                <AppIcon :name="selectedBooking.sport_icon || 'activity'" size="18" />
              </span>
              <div class="sport-info-text">
                <div class="sport-title-row">
                  <strong class="sport-name">{{ selectedBooking.sport_name }}</strong>
                  <span class="court-type-pill">{{ selectedBooking.court_type_name }}</span>
                </div>
                <small v-if="selectedBooking.court_name" class="court-detail-name">
                  {{ selectedBooking.court_name }}
                </small>
              </div>
            </div>
            <div v-if="selectedBooking.total_price" class="booking-price-chip">
              <span>Tổng tiền sân:</span>
              <strong>{{ formatMoney(selectedBooking.total_price) }}</strong>
            </div>
          </div>

          <!-- BỘ CHỌN TRÌNH ĐỘ MONG MUỐN -->
          <div class="field-block">
            <span class="field-label">Trình độ mong muốn</span>
            <div class="skill-pills">
              <button
                v-for="s in skillOptions"
                :key="s.value"
                type="button"
                class="skill-pill"
                :class="{ 'is-active': form.skill_level === s.value }"
                @click="form.skill_level = s.value"
              >
                <span class="skill-dot"></span>
                <span>{{ s.label }}</span>
              </button>
            </div>
          </div>

          <!-- BỘ CHỌN HÌNH THỨC CHI PHÍ -->
          <div class="field-block">
            <span class="field-label">Chi phí tham gia</span>
            <div class="cost-type-grid">
              <button
                type="button"
                class="cost-type-btn"
                :class="{ 'is-active': form.cost_type === 'split' }"
                @click="form.cost_type = 'split'"
              >
                <div class="cost-text">
                  <strong>Chia đều tiền sân</strong>
                  <small v-if="estimatedSplitCost">{{ formatMoney(estimatedSplitCost) }}/người</small>
                  <small v-else>Cưa đều chi phí</small>
                </div>
              </button>

              <button
                type="button"
                class="cost-type-btn"
                :class="{ 'is-active': form.cost_type === 'free' }"
                @click="form.cost_type = 'free'"
              >
                <div class="cost-text">
                  <strong>Miễn phí</strong>
                  <small>Chủ bao sân</small>
                </div>
              </button>

              <button
                type="button"
                class="cost-type-btn"
                :class="{ 'is-active': form.cost_type === 'custom' }"
                @click="form.cost_type = 'custom'"
              >
                <div class="cost-text">
                  <strong>Tùy chỉnh</strong>
                  <small>Nhập giá riêng</small>
                </div>
              </button>
            </div>

            <div v-if="form.cost_type === 'custom'" class="custom-cost-input-wrap">
              <label for="mpm-custom-cost" class="custom-cost-label">Số tiền mỗi người đóng (VNĐ)</label>
              <input
                id="mpm-custom-cost"
                v-model.number="form.cost_per_player"
                type="number"
                step="5000"
                min="0"
                placeholder="Ví dụ: 30000"
                class="field-control"
              />
            </div>
          </div>

          <div class="field-block">
            <label for="mpm-content" class="field-label">
              <span>Mô tả thêm</span>
              <small>Không bắt buộc</small>
            </label>
            <textarea
              id="mpm-content"
              v-model.trim="form.content"
              class="field-control"
              rows="3"
              maxlength="2000"
              placeholder="Ghi chú thêm về quy định, chuẩn bị đồ đạc hoặc lưu ý cho người tham gia..."
            ></textarea>
            <small
              class="character-count"
              :class="{ invalid: form.content.length > 0 && form.content.length < 10 }"
            >
              {{ form.content.length > 0 && form.content.length < 10
                ? `Cần thêm ${10 - form.content.length} ký tự nữa`
                : `${form.content.length}/2000` }}
            </small>
          </div>

          <!-- LƯỚI PREVIEW ẢNH ĐÃ CHỌN -->
          <div v-if="selectedImages.length" class="image-preview-grid">
            <div
              v-for="(img, idx) in selectedImages"
              :key="idx"
              class="image-preview-item"
            >
              <img :src="img.url" :alt="`Ảnh ${idx + 1}`" />
              <button
                type="button"
                class="remove-image-btn"
                aria-label="Xóa ảnh này"
                @click="removeImage(idx)"
              >
                <AppIcon name="x" size="14" />
              </button>
            </div>
          </div>

          <div class="attachment-row">
            <div>
              <strong>Ảnh minh họa</strong>
              <small>Tùy chọn, tối đa 5 ảnh JPG, PNG, WebP</small>
            </div>
            <label class="image-picker" :class="{ disabled: selectedImages.length >= 5 }">
              <AppIcon name="image" size="16" />
              <span>{{ selectedImages.length ? 'Thêm ảnh' : 'Chọn ảnh' }}</span>
              <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                :disabled="selectedImages.length >= 5"
                @change="handleFileChange"
              />
            </label>
          </div>
          <p v-if="fileError" class="form-error" role="alert">{{ fileError }}</p>

          <p v-if="errorMsg" class="form-error" role="alert">{{ errorMsg }}</p>

          <footer class="form-actions">
            <button type="button" class="cancel-button" :disabled="isSubmitting" @click="close">Hủy</button>
            <button type="submit" class="submit-button" :disabled="isSubmitting || !isValid">
              {{ isSubmitting ? 'Đang đăng...' : 'Đăng bài giao lưu' }}
            </button>
          </footer>
        </form>
      </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import ClientCustomSelect from '@/components/ClientCustomSelect.vue';
import SgButton from '@/components/common/SgButton.vue';
import { api } from '@/services/api';
import { getAuth } from '@/stores/auth.js';

const router = useRouter();
const toast = useToast();
const props = defineProps({ isOpen: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'success']);
const user = getAuth();
const userInitial = computed(() => user?.fullName?.charAt(0)?.toUpperCase() || '?');
const form = reactive({
  venue_id: '',
  booking_id: '',
  required_players: 1,
  skill_level: 'all',
  cost_type: 'split',
  cost_per_player: null,
  content: '',
});
const userBookings = ref([]);
const bookingsLoading = ref(false);
const bookingsError = ref('');
const isSubmitting = ref(false);
const errorMsg = ref('');
const fileError = ref('');
const selectedImages = ref([]);
const fileInput = ref(null);

const skillOptions = [
  { value: 'all', label: 'Mọi trình độ (Vui vẻ)' },
  { value: 'beginner', label: 'Mới chơi' },
  { value: 'intermediate', label: 'Trung bình' },
  { value: 'advanced', label: 'Khá / Nâng cao' },
];

let eligibleRequestController = null;
let eligibleRequestId = 0;
let eligibleLoadedAt = 0;
let eligibleRequestTimer = null;
let submitController = null;
let submitTimer = null;

function goToBooking() {
  close();
  router.push('/venues');
}

function removeImage(index) {
  const item = selectedImages.value[index];
  if (item?.url) URL.revokeObjectURL(item.url);
  selectedImages.value.splice(index, 1);
  fileError.value = '';
  if (fileInput.value) fileInput.value.value = '';
}

function clearAllImages() {
  selectedImages.value.forEach((img) => {
    if (img?.url) URL.revokeObjectURL(img.url);
  });
  selectedImages.value = [];
  fileError.value = '';
  if (fileInput.value) fileInput.value.value = '';
}

async function compressImage(file, maxDimension = 1920, quality = 0.85) {
  return new Promise((resolve) => {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      resolve(file);
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        let { width, height } = img;
        if (width > maxDimension || height > maxDimension) {
          if (width > height) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          } else {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        canvas.toBlob(
          (blob) => {
            if (blob) {
              resolve(new File([blob], file.name.replace(/\.[^.]+$/, '.webp'), { type: 'image/webp' }));
            } else {
              resolve(file);
            }
          },
          'image/webp',
          quality
        );
      };
      img.onerror = () => resolve(file);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(file);
    reader.readAsDataURL(file);
  });
}

async function handleFileChange(event) {
  fileError.value = '';
  const files = Array.from(event.target.files || []);
  if (!files.length) return;

  const remainingSlots = 5 - selectedImages.value.length;
  if (remainingSlots <= 0) {
    fileError.value = 'Đã đạt giới hạn tối đa 5 ảnh.';
    if (fileInput.value) fileInput.value.value = '';
    return;
  }

  if (files.length > remainingSlots) {
    toast.info(`Đã tự động lấy ${remainingSlots} ảnh hợp lệ (tối đa 5 ảnh).`);
  }

  const allowedFiles = files.slice(0, remainingSlots);
  for (const file of allowedFiles) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      fileError.value = 'Chỉ chấp nhận file ảnh JPG, PNG hoặc WebP.';
      continue;
    }
    if (file.size > 15 * 1024 * 1024) {
      fileError.value = 'Mỗi ảnh không được vượt quá 15 MB.';
      continue;
    }
    const optimizedFile = await compressImage(file);
    selectedImages.value.push({
      file: optimizedFile,
      url: URL.createObjectURL(optimizedFile),
    });
  }

  if (fileInput.value) fileInput.value.value = '';
}

const venueOptions = computed(() => {
  const venues = new Map();
  userBookings.value.forEach((booking) => {
    if (!venues.has(String(booking.venue_id))) {
      const location = booking.location ? ` · ${booking.location}` : '';
      venues.set(String(booking.venue_id), {
        value: String(booking.venue_id),
        label: `${booking.venue_name}${location}`,
      });
    }
  });
  return Array.from(venues.values());
});

const bookingOptions = computed(() => {
  if (!form.venue_id) return [];
  return userBookings.value
    .filter((booking) => String(booking.venue_id) === String(form.venue_id))
    .map((booking) => ({
      value: String(booking.id),
      label: `${formatDate(booking.date)} · ${booking.time}`,
    }));
});

const selectedBooking = computed(() => {
  if (!form.booking_id) return null;
  return userBookings.value.find((b) => String(b.id) === String(form.booking_id)) || null;
});

const estimatedSplitCost = computed(() => {
  if (!selectedBooking.value?.total_price) return 0;
  const totalPeople = Number(form.required_players || 1) + 1;
  return Math.round((Number(selectedBooking.value.total_price) / totalPeople) / 1000) * 1000;
});

const isValid = computed(() => {
  const players = Number(form.required_players);
  const descriptionValid = !form.content || form.content.length >= 10;
  const customCostValid = form.cost_type !== 'custom' || (form.cost_per_player !== null && form.cost_per_player >= 0);
  return Boolean(form.venue_id && form.booking_id)
    && players >= 1
    && players <= 50
    && descriptionValid
    && customCostValid;
});

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const [year, month, day] = String(value).slice(0, 10).split('-');
  return day && month && year ? `${day}/${month}/${year}` : value;
}

function formatMoney(value) {
  if (!value && value !== 0) return '0đ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function reset() {
  form.venue_id = '';
  form.booking_id = '';
  form.required_players = 1;
  form.skill_level = 'all';
  form.cost_type = 'split';
  form.cost_per_player = null;
  form.content = '';
  errorMsg.value = '';
  fileError.value = '';
  clearAllImages();
}

function close() {
  if (isSubmitting.value) return;
  reset();
  emit('close');
}

function abortEligibleRequest() {
  eligibleRequestController?.abort();
  eligibleRequestController = null;
  if (eligibleRequestTimer) {
    clearTimeout(eligibleRequestTimer);
    eligibleRequestTimer = null;
  }
}

async function fetchEligibleBookings({ force = false } = {}) {
  if (bookingsLoading.value) return;
  if (!force && userBookings.value.length && Date.now() - eligibleLoadedAt < 30_000) return;

  abortEligibleRequest();
  const requestId = ++eligibleRequestId;
  const controller = new AbortController();
  let timedOut = false;
  eligibleRequestController = controller;
  eligibleRequestTimer = setTimeout(() => {
    timedOut = true;
    controller.abort();
  }, 12_000);
  bookingsLoading.value = true;
  bookingsError.value = '';
  try {
    const response = await api('/api/matchmaking-posts/eligible-bookings', {
      signal: controller.signal,
      dedupe: false,
    });
    if (requestId !== eligibleRequestId) return;
    const payload = response?.data;
    userBookings.value = Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : [];
    eligibleLoadedAt = Date.now();
  } catch (error) {
    if ((controller.signal.aborted && !timedOut) || requestId !== eligibleRequestId) return;
    userBookings.value = [];
    bookingsError.value = timedOut || error.name === 'AbortError'
      ? 'Tải danh sách lịch sân quá lâu. Vui lòng thử lại.'
      : error.message || 'Không thể tải lịch sân đủ điều kiện.';
  } finally {
    if (requestId === eligibleRequestId) {
      if (eligibleRequestTimer) {
        clearTimeout(eligibleRequestTimer);
        eligibleRequestTimer = null;
      }
      bookingsLoading.value = false;
      eligibleRequestController = null;
    }
  }
}

function handleEscape(event) {
  if (event.key === 'Escape' && props.isOpen) close();
}

async function submit() {
  if (!isValid.value || isSubmitting.value) return;
  isSubmitting.value = true;
  errorMsg.value = '';
  submitController?.abort();
  const controller = new AbortController();
  submitController = controller;
  let timedOut = false;
  const timer = setTimeout(() => {
    timedOut = true;
    controller.abort();
  }, 15_000);
  submitTimer = timer;
  try {
    const payload = new FormData();
    payload.append('booking_id', form.booking_id);
    payload.append('required_players', form.required_players);
    payload.append('skill_level', form.skill_level || 'all');
    payload.append('cost_type', form.cost_type || 'split');
    if (form.cost_type === 'custom' && form.cost_per_player !== null) {
      payload.append('cost_per_player', form.cost_per_player);
    }
    if (form.content) payload.append('content', form.content);
    if (selectedImages.value.length > 0 && selectedImages.value[0].file) {
      payload.append('image', selectedImages.value[0].file);
      selectedImages.value.forEach((item, index) => {
        if (item.file) payload.append(`images[${index}]`, item.file);
      });
    }

    const response = await api('/api/matchmaking-posts', {
      method: 'POST',
      signal: controller.signal,
      body: payload,
    });
    userBookings.value = userBookings.value.filter(
      (booking) => String(booking.id) !== String(form.booking_id),
    );
    eligibleLoadedAt = 0;
    reset();
    isSubmitting.value = false;
    emit('close');
    emit('success', response.data);
  } catch (error) {
    if (controller.signal.aborted && !timedOut) return;
    errorMsg.value = timedOut
      ? 'Tạo bài giao lưu quá lâu. Vui lòng thử lại.'
      : error.message || 'Không thể đăng bài giao lưu.';
    toast.error(errorMsg.value);
  } finally {
    clearTimeout(timer);
    if (submitTimer === timer) submitTimer = null;
    if (submitController === controller) submitController = null;
    isSubmitting.value = false;
  }
}

watch(() => form.venue_id, () => {
  if (!bookingOptions.value.some((option) => String(option.value) === String(form.booking_id))) {
    form.booking_id = '';
  }
});

watch(() => props.isOpen, (isOpen) => {
  if (isOpen) fetchEligibleBookings();
  else if (!isSubmitting.value) reset();
});

onMounted(() => document.addEventListener('keydown', handleEscape));
onBeforeUnmount(() => {
  abortEligibleRequest();
  submitController?.abort();
  if (submitTimer) clearTimeout(submitTimer);
  eligibleRequestId += 1;
  document.removeEventListener('keydown', handleEscape);
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

.meetup-modal {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  width: 100%;
  max-width: 580px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  color: #0f172a;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.modal-kicker {
  display: block;
  font-size: 12px;
  color: #5c7e6e;
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

.author-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}

.author-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #5c7e6e;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.author-summary strong {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
}

.author-summary span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #1e293b;
  font-weight: 400;
}

.meetup-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label,
.field-block span {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.field-block small {
  color: #1e293b;
  font-weight: 400;
  font-size: 12px;
}

.field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.field-control {
  width: 100%;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
  font-family: inherit;
  outline: none;
  background: #ffffff;
  box-sizing: border-box;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

textarea.field-control {
  height: auto;
  min-height: 96px;
  padding: 10px 12px;
  line-height: 1.5;
  font-weight: 400;
}

.field-control:focus {
  border-color: #5c7e6e;
  box-shadow: 0 0 0 3px rgba(92, 126, 110, 0.12);
}

/* BANNER MÔN THỂ THAO & LOẠI SÂN */
.sport-badge-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  background: #edf4f0;
  border: 1px solid rgba(92, 126, 110, 0.25);
  border-radius: 8px;
  box-sizing: border-box;
}

.sport-badge-main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.sport-icon-box {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #5c7e6e;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sport-info-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.sport-title-row {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
}

.sport-name {
  font-size: 13.5px;
  font-weight: 700;
  color: #1e293b;
}

.court-type-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  background: #ffffff;
  border: 1px solid rgba(92, 126, 110, 0.3);
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 600;
  color: #446153;
}

.court-detail-name {
  font-size: 11.5px;
  color: #5c7e6e;
  font-weight: 500;
}

.booking-price-chip {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
  flex-shrink: 0;
  font-size: 11.5px;
  color: #64748b;
}

.booking-price-chip strong {
  color: #1e293b;
  font-size: 13px;
  font-weight: 700;
}

/* BỘ CHỌN TRÌNH ĐỘ */
.skill-pills {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.skill-pill {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 12px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
}

.skill-pill:hover {
  border-color: #5c7e6e;
  background: #f8fafc;
}

.skill-pill.is-active {
  border-color: #5c7e6e;
  background: #edf4f0;
  color: #446153;
  font-weight: 600;
  box-shadow: 0 0 0 1px #5c7e6e;
}

.skill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #94a3b8;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.skill-pill.is-active .skill-dot {
  background: #5c7e6e;
}

/* BỘ CHỌN HÌNH THỨC CHI PHÍ */
.cost-type-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.cost-type-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
}

.cost-type-btn:hover {
  border-color: #5c7e6e;
  background: #f8fafc;
}

.cost-type-btn.is-active {
  border-color: #5c7e6e;
  background: #edf4f0;
  box-shadow: 0 0 0 1px #5c7e6e;
}

.cost-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.cost-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.cost-text strong {
  font-size: 12px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.2;
}

.cost-text small {
  font-size: 10.5px;
  color: #5c7e6e;
  line-height: 1.2;
  margin-top: 2px;
  font-weight: 500;
}

.custom-cost-input-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 8px;
}

.custom-cost-label {
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}

.modal-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 40px 20px;
  text-align: center;
  color: #0f172a;
  font-size: 14px;
}

.modal-state strong {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.modal-state span {
  color: #475569;
  font-size: 13.5px;
  max-width: 380px;
  line-height: 1.5;
}

.empty-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}

.mpm-book-btn {
  padding: 9px 20px;
  background: #5c7e6e;
  color: #ffffff;
  border: 1px solid #5c7e6e;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.mpm-book-btn:hover {
  background: #446153;
  border-color: #446153;
}

.mpm-cancel-btn {
  padding: 9px 18px;
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s ease;
}

.mpm-cancel-btn:hover {
  border-color: #94a3b8;
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
  border: 1px solid #5c7e6e;
  border-radius: 6px;
  background: #5c7e6e;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  box-sizing: border-box;
  transition: all 0.15s ease;
}

.submit-button:hover:not(:disabled) {
  background: #446153;
  border-color: #446153;
}

.submit-button:disabled {
  cursor: not-allowed;
  background: #a3b8ad;
  border-color: #a3b8ad;
  color: #ffffff;
  opacity: 0.85;
}

.image-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));
  gap: 8px;
  margin-top: 4px;
}

.image-preview-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}

.image-preview-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.remove-image-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.7);
  color: #ffffff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease;
  padding: 0;
}

.remove-image-btn:hover {
  background: #dc2626;
}

.attachment-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #edf4f0;
  border: 1px solid rgba(92, 126, 110, 0.25);
  border-radius: 8px;
  gap: 12px;
}

.attachment-row strong {
  display: block;
  font-size: 13.5px;
  font-weight: 600;
  color: #1e293b;
}

.attachment-row small {
  display: block;
  font-size: 11.5px;
  color: #5c7e6e;
}

.image-picker {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  background: #ffffff;
  border: 1px solid #5c7e6e;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #446153;
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.image-picker input[type="file"] {
  display: none;
}

.image-picker:hover:not(.disabled) {
  background: #5c7e6e;
  color: #ffffff;
}

.image-picker.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  border-color: #cbd5e1;
  color: #94a3b8;
}

.form-error {
  color: #dc2626;
  font-size: 13px;
  font-weight: 400;
  margin: 0;
}
</style>
