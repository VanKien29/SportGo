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
          <label class="field-block">
            <span>Cụm sân</span>
            <ClientCustomSelect
              v-model="form.venue_id"
              :options="venueOptions"
              placeholder="Chọn cụm sân"
            />
          </label>

          <div class="field-grid">
            <label class="field-block">
              <span>Lịch đã đặt</span>
              <ClientCustomSelect
                v-model="form.booking_id"
                :options="bookingOptions"
                :disabled="!form.venue_id"
                icon="clock"
                placeholder="Chọn ngày và khung giờ"
              />
            </label>

            <label class="field-block">
              <span>Số người cần thêm <small>1–50 người</small></span>
              <input
                v-model.number="form.required_players"
                class="field-control"
                type="number"
                min="1"
                max="50"
                required
              />
            </label>
          </div>

          <label class="field-block">
            <span>Mô tả <small>Không bắt buộc</small></span>
            <textarea
              v-model.trim="form.content"
              class="field-control"
              rows="4"
              maxlength="2000"
              placeholder="Trình độ mong muốn, cách chia chi phí hoặc lưu ý cho người tham gia"
            ></textarea>
            <small
              class="character-count"
              :class="{ invalid: form.content.length > 0 && form.content.length < 10 }"
            >
              {{ form.content.length > 0 && form.content.length < 10
                ? `Cần thêm ${10 - form.content.length} ký tự nữa`
                : `${form.content.length}/2000` }}
            </small>
          </label>

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
const form = reactive({ venue_id: '', booking_id: '', required_players: 1, content: '' });
const userBookings = ref([]);
const bookingsLoading = ref(false);
const bookingsError = ref('');
const isSubmitting = ref(false);
const errorMsg = ref('');
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

const isValid = computed(() => {
  const players = Number(form.required_players);
  const descriptionValid = !form.content || form.content.length >= 10;
  return Boolean(form.venue_id && form.booking_id)
    && players >= 1
    && players <= 50
    && descriptionValid;
});

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const [year, month, day] = String(value).slice(0, 10).split('-');
  return day && month && year ? `${day}/${month}/${year}` : value;
}

function reset() {
  form.venue_id = '';
  form.booking_id = '';
  form.required_players = 1;
  form.content = '';
  errorMsg.value = '';
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
  }, 12_000);
  submitTimer = timer;
  try {
    const response = await api('/api/matchmaking-posts', {
      method: 'POST',
      signal: controller.signal,
      body: JSON.stringify({
        booking_id: form.booking_id,
        required_players: Number(form.required_players),
        content: form.content || null,
      }),
    });
    userBookings.value = userBookings.value.filter(
      (booking) => String(booking.id) !== String(form.booking_id),
    );
    eligibleLoadedAt = 0;
    reset();
    // Close and unlock before refreshing the community rail. A slow GET must
    // never leave the create modal looking as if POST is still running.
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
  color: #16a34a;
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
  background: #16a34a;
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
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  color: #0f172a;
  font-weight: 400;
  font-family: inherit;
  outline: none;
  background: #ffffff;
}

.field-control:focus {
  border-color: #15803d;
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
  background: #15803d;
  color: #ffffff;
  border: 1px solid #15803d;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s ease;
}

.mpm-book-btn:hover {
  background: #166534;
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

.form-error {
  color: #dc2626;
  font-size: 13px;
  font-weight: 400;
  margin: 0;
}
</style>
