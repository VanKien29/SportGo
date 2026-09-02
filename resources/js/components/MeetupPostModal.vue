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
          <!-- DANH SÁCH THẺ ĐƠN ĐẶT SÂN (INFOGRAPHIC & ISOMETRIC STYLE) -->
          <div class="field-block">
            <span class="field-label">Chọn đơn đặt sân của bạn</span>
            <div class="booking-cards-list">
              <button
                v-for="b in userBookings"
                :key="b.id"
                type="button"
                class="booking-select-card"
                :class="{ 'is-selected': String(form.booking_id) === String(b.id) }"
                @click="selectBooking(b)"
              >
                <!-- ISOMETRIC SPORT COURT ART -->
                <div class="bsc-iso-art" :class="`sport-${b.sport_icon || 'activity'}`">
                  <div class="iso-plane">
                    <div class="iso-court-lines"></div>
                  </div>
                  <div class="iso-icon-float">
                    <AppIcon :name="b.sport_icon || 'activity'" size="18" />
                  </div>
                </div>

                <!-- NỘI DUNG THẺ ĐƠN ĐẶT SÂN -->
                <div class="bsc-content">
                  <div class="bsc-header-row">
                    <span class="bsc-venue-name">{{ b.venue_name }}</span>
                    <span v-if="b.court_name" class="bsc-court-name">({{ b.court_name }})</span>
                  </div>

                  <div class="bsc-sub-row">
                    <span class="bsc-sport-meta">{{ b.sport_name }} ({{ cleanCourtType(b.court_type_name, b.sport_name) }})</span>
                    <span class="bsc-code">{{ b.booking_code || `#BK${b.id}` }}</span>
                  </div>

                  <div class="bsc-footer-row">
                    <span class="bsc-infometric">
                      <AppIcon name="clock" size="13" />
                      <span>{{ formatDate(b.date) }}, {{ b.time }}</span>
                    </span>
                    <span v-if="b.total_price" class="bsc-price">
                      {{ formatMoney(b.total_price) }}
                    </span>
                  </div>
                </div>

                <!-- CUỐNG VÉ & RADIO INDICATOR -->
                <div class="bsc-stub">
                  <div class="bsc-stub-line"></div>
                  <div class="bsc-radio">
                    <span class="bsc-radio-dot"></span>
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div class="field-block">
            <label for="mpm-required-players" class="field-label">
              <span>Số người cần thêm</span>
              <small v-if="suggestedPlayers > 0">Đề xuất cho sân: {{ suggestedPlayers }} người</small>
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
            <p v-if="exceedsRecommended" class="field-warning" role="alert">
              <AppIcon name="alert" size="13" />
              <span>Số lượng người tham gia đang vượt quá số lượng đề xuất của sân đã đặt. Bạn vẫn có thể tiếp tục tạo bài.</span>
            </p>
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
                  <span class="cost-title">Chia đều tiền sân</span>
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
                  <span class="cost-title">Miễn phí</span>
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
                  <span class="cost-title">Tùy chỉnh</span>
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

          <!-- BỘ CHỌN HẠN CHÓT NHẬN ĐĂNG KÝ (LOCK DEADLINE) -->
          <div class="field-block">
            <span class="field-label">
              <span>Hạn chót nhận yêu cầu tham gia</span>
              <small>Tự động đóng bài trước giờ chơi</small>
            </span>
            <div class="deadline-pills">
              <button
                v-for="d in availableDeadlineOptions"
                :key="d.value"
                type="button"
                class="deadline-pill"
                :class="{ 'is-active': form.lock_lead_minutes === d.value }"
                @click="form.lock_lead_minutes = d.value"
              >
                <AppIcon name="clock" size="13" />
                <span>{{ d.label }}</span>
              </button>
            </div>
            <p class="field-hint">
              <AppIcon name="shield" size="13" />
              <span>Sau hạn chót, bài đăng sẽ tự động ngừng nhận thêm người và tự hủy các yêu cầu chưa duyệt.</span>
            </p>
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

          <!-- PREVIEW ẢNH ĐÃ CHỌN (TỐI ĐA 1 ẢNH) -->
          <div v-if="selectedImages.length" class="image-preview-grid">
            <div class="image-preview-item">
              <img :src="selectedImages[0].url" alt="Ảnh bài giao lưu" />
              <button
                type="button"
                class="remove-image-btn"
                aria-label="Xóa ảnh này"
                @click="removeImage(0)"
              >
                <AppIcon name="x" size="14" />
              </button>
            </div>
          </div>

          <div class="attachment-row">
            <div>
              <span class="attachment-label">Ảnh minh họa</span>
              <small>Tùy chọn, tối đa 1 ảnh JPG, PNG, WebP</small>
            </div>
            <label class="image-picker">
              <AppIcon name="image" size="16" />
              <span>{{ selectedImages.length ? 'Đổi ảnh' : 'Chọn ảnh' }}</span>
              <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                @change="handleFileChange"
              />
            </label>
          </div>
          <div v-if="fileError || errorMsg" class="form-alert-banner" role="alert">
            <AppIcon name="alert" :size="16" class="alert-icon" />
            <span>{{ fileError || errorMsg }}</span>
          </div>

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
import { businessDateTime } from '@/utils/businessTime.js';

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
  lock_lead_minutes: 30,
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

const deadlinePresetOptions = [
  { value: 30, label: 'Trước 30 phút (Khuyên dùng)' },
  { value: 15, label: 'Trước 15 phút' },
  { value: 45, label: 'Trước 45 phút' },
  { value: 60, label: 'Trước 1 tiếng' },
  { value: 120, label: 'Trước 2 tiếng' },
  { value: 0, label: 'Đến sát giờ bắt đầu (0 phút)' },
];

const availableDeadlineOptions = computed(() => {
  if (!selectedBooking.value) return deadlinePresetOptions.slice(0, 3);
  const b = selectedBooking.value;
  if (!b.date || !b.time) return deadlinePresetOptions;
  const startTimeStr = b.time.split(' - ')[0] || b.time.split('-')[0] || '';
  const bookingStart = businessDateTime(b.date, startTimeStr.trim());
  const now = new Date();
  const minutesLeft = Math.floor((bookingStart.getTime() - now.getTime()) / 60000);
  if (isNaN(minutesLeft) || minutesLeft <= 0) return deadlinePresetOptions;
  const filtered = deadlinePresetOptions.filter((opt) => opt.value < minutesLeft);
  return filtered.length > 0 ? filtered : [{ value: 0, label: 'Đến sát giờ bắt đầu (0 phút)' }];
});

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

  const file = files[0];
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    fileError.value = 'Chỉ chấp nhận file ảnh JPG, PNG hoặc WebP.';
    if (fileInput.value) fileInput.value.value = '';
    return;
  }
  if (file.size > 15 * 1024 * 1024) {
    fileError.value = 'Ảnh không được vượt quá 15 MB.';
    if (fileInput.value) fileInput.value.value = '';
    return;
  }

  clearAllImages();
  try {
    const optimizedFile = await compressImage(file);
    selectedImages.value = [{
      file: optimizedFile,
      url: URL.createObjectURL(optimizedFile),
    }];
  } catch {
    selectedImages.value = [{
      file,
      url: URL.createObjectURL(file),
    }];
  } finally {
    if (fileInput.value) fileInput.value.value = '';
  }
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
  return Boolean(form.booking_id)
    && players >= 1
    && players <= 50
    && descriptionValid
    && customCostValid;
});

const suggestedPlayers = computed(() => {
  if (!selectedBooking.value) return 0;
  return Number(selectedBooking.value.suggested_players) || 0;
});

const exceedsRecommended = computed(() => {
  if (suggestedPlayers.value <= 0) return false;
  return Number(form.required_players) > suggestedPlayers.value;
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

function cleanCourtType(typeName, sportName) {
  if (!typeName) return 'Sân tiêu chuẩn';
  const match = String(typeName).match(/\((.*?)\)/);
  if (match && match[1]) return match[1].trim();
  if (sportName && String(typeName).toLowerCase().startsWith(String(sportName).toLowerCase())) {
    const cleaned = String(typeName).slice(sportName.length).trim().replace(/^[-·:() ]+/, '').replace(/\)$/, '');
    if (cleaned) return cleaned;
  }
  return typeName;
}

function selectBooking(booking) {
  if (!booking) return;
  form.booking_id = String(booking.id);
  form.venue_id = String(booking.venue_id);
}

function reset() {
  form.venue_id = '';
  form.booking_id = '';
  form.required_players = 1;
  form.lock_lead_minutes = 30;
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

    if (userBookings.value.length > 0 && !form.booking_id) {
      selectBooking(userBookings.value[0]);
    }
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
    payload.append('lock_lead_minutes', form.lock_lead_minutes ?? 30);
    payload.append('skill_level', form.skill_level || 'all');
    payload.append('cost_type', form.cost_type || 'split');
    if (form.cost_type === 'custom' && form.cost_per_player !== null) {
      payload.append('cost_per_player', form.cost_per_player);
    }
    if (form.content) payload.append('content', form.content);
    if (selectedImages.value.length > 0 && selectedImages.value[0].file) {
      payload.append('image', selectedImages.value[0].file);
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
      ? 'Yêu cầu tạo bài quá lâu. Vui lòng thử lại.'
      : error?.response?.data?.message || error?.message || 'Không thể tạo bài giao lưu.';
    toast.error(errorMsg.value);
  } finally {
    if (submitTimer === timer) {
      clearTimeout(submitTimer);
      submitTimer = null;
    }
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
  if (isOpen) fetchEligibleBookings({ force: true });
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
  font-weight: 400;
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

/* DANH SÁCH THẺ ĐƠN ĐẶT SÂN (INFOGRAPHIC & ISOMETRIC STYLE) */
.booking-cards-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 270px;
  overflow-y: auto;
  padding: 4px 3px;
  margin: -4px -3px;
}

.booking-select-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  text-align: left;
  width: 100%;
  box-sizing: border-box;
}

.booking-select-card.is-selected {
  border-color: #5c7e6e;
  background: #f8faf9;
  box-shadow: 0 0 0 1px #5c7e6e;
}

/* KHỐI ISOMETRIC SPORT COURT ART */
.bsc-iso-art {
  width: 44px;
  height: 44px;
  border-radius: 9px;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, #4b705e 0%, #2f4d3e 100%);
  box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.25), 0 2px 5px rgba(47, 77, 62, 0.15);
}

.iso-plane {
  position: absolute;
  width: 32px;
  height: 20px;
  border: 1px solid rgba(255, 255, 255, 0.45);
  border-radius: 2px;
  transform: rotateX(55deg) rotateZ(-30deg);
  background: rgba(255, 255, 255, 0.08);
}

.iso-court-lines {
  position: absolute;
  inset: 0;
  border-top: 1px dashed rgba(255, 255, 255, 0.4);
  top: 50%;
}

.iso-icon-float {
  position: relative;
  z-index: 2;
  color: #ffffff;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
  display: flex;
  align-items: center;
  justify-content: center;
}

/* NỘI DUNG THẺ ĐƠN ĐẶT SÂN */
.bsc-content {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}

.bsc-header-row {
  display: flex;
  align-items: baseline;
  gap: 5px;
  flex-wrap: wrap;
}

.bsc-venue-name {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.3;
}

.bsc-court-name {
  font-size: 12.5px;
  font-weight: 400;
  color: #64748b;
}

.bsc-sub-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.bsc-sport-meta {
  font-size: 12px;
  font-weight: 400;
  color: #475569;
  line-height: 1.3;
}

.bsc-code {
  font-size: 11px;
  font-weight: 500;
  color: #5c7e6e;
  letter-spacing: 0.3px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.bsc-footer-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 1px;
}

.bsc-infometric {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  color: #64748b;
  font-weight: 400;
}

.bsc-infometric svg {
  color: #5c7e6e;
}

.bsc-price {
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.2;
}

/* CUỐNG VÉ & RADIO */
.bsc-stub {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-left: 6px;
  flex-shrink: 0;
  align-self: stretch;
}

.bsc-stub-line {
  height: 80%;
  border-left: 1.5px dashed #e2e8f0;
}

.booking-select-card.is-selected .bsc-stub-line {
  border-left-color: rgba(92, 126, 110, 0.35);
}

.bsc-radio {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  transition: all 0.15s ease;
}

.booking-select-card.is-selected .bsc-radio {
  border-color: #5c7e6e;
  background: #edf4f0;
}

.bsc-radio-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: transparent;
  transition: background 0.15s ease;
}

.booking-select-card.is-selected .bsc-radio-dot {
  background: #5c7e6e;
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
  justify-content: center;
  padding: 9px 12px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 400;
  color: #334155;
  cursor: pointer;
  text-align: center;
}

.skill-pill.is-active {
  border-color: #5c7e6e;
  background: #edf4f0;
  color: #446153;
  font-weight: 500;
  box-shadow: 0 0 0 1px #5c7e6e;
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
  text-align: left;
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

.cost-title {
  font-size: 12px;
  font-weight: 500;
  color: #1e293b;
  line-height: 1.2;
}

.cost-text small {
  font-size: 10.5px;
  color: #5c7e6e;
  line-height: 1.2;
  margin-top: 2px;
  font-weight: 400;
}

.attachment-label {
  font-size: 13.5px;
  font-weight: 500;
  color: #1e293b;
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

/* BỘ CHỌN HẠN CHÓT NHẬN ĐĂNG KÝ (LOCK DEADLINE) */
.deadline-pills {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 8px;
}

.deadline-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 400;
  color: #334155;
  cursor: pointer;
  text-align: left;
}

.deadline-pill svg {
  color: #64748b;
  flex-shrink: 0;
}

.deadline-pill.is-active {
  border-color: #5c7e6e;
  background: #edf4f0;
  color: #446153;
  font-weight: 500;
  box-shadow: 0 0 0 1px #5c7e6e;
}

.deadline-pill.is-active svg {
  color: #5c7e6e;
}

.field-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 4px 0 0;
  font-size: 11.5px;
  color: #64748b;
  line-height: 1.4;
}

.field-hint svg {
  color: #5c7e6e;
  flex-shrink: 0;
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

.form-alert-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #b91c1c;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.4;
  margin-top: 4px;
}

.form-alert-banner .alert-icon {
  flex-shrink: 0;
  color: #ef4444;
}

.field-warning {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  margin: 8px 0 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fef3cd;
  border: 1px solid #f0d77e;
  color: #856404;
  font-size: 12.5px;
  line-height: 1.45;
}
.field-warning > svg {
  flex: 0 0 auto;
  margin-top: 1px;
}
</style>
