<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-overlay" role="presentation" @click.self="close">
      <section class="meetup-modal" role="dialog" aria-modal="true" aria-labelledby="meetup-modal-title">
      <header class="modal-header">
        <div>
          <span class="modal-kicker">Cộng đồng SportGo</span>
          <h2 id="meetup-modal-title">Tạo bài giao lưu</h2>
        </div>
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
          <AppIcon name="calendar" size="28" />
          <strong>Chưa có lịch sân phù hợp</strong>
          <span>Bạn cần một booking sắp tới đã được xác nhận và chưa có bài giao lưu.</span>
        </div>

        <form v-else class="meetup-form" @submit.prevent="submit">
          <label class="field-block">
            <span>Cụm sân <small>Bắt buộc</small></span>
            <CustomSelect
              v-model="form.venue_id"
              :options="venueOptions"
              placeholder="Chọn cụm sân"
            />
          </label>

          <div class="field-grid">
            <label class="field-block">
              <span>Lịch đã đặt <small>Bắt buộc</small></span>
              <CustomSelect
                v-model="form.booking_id"
                :options="bookingOptions"
                :disabled="!form.venue_id"
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
                ? `Cần thêm ${10 - form.content.length} ký tự`
                : `${form.content.length}/2000` }}
            </small>
          </label>

          <p v-if="errorMsg" class="form-error" role="alert">{{ errorMsg }}</p>

          <footer class="form-actions">
            <SgButton type="secondary" :disabled="isSubmitting" @click="close">Hủy</SgButton>
            <SgButton native-type="submit" type="primary" :loading="isSubmitting" :disabled="!isValid">
              Đăng bài giao lưu
            </SgButton>
          </footer>
        </form>
      </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import CustomSelect from '@/components/CustomSelect.vue';
import SgButton from '@/components/common/SgButton.vue';
import { api } from '@/services/api';
import { getAuth } from '@/stores/auth.js';

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

async function fetchEligibleBookings() {
  bookingsLoading.value = true;
  bookingsError.value = '';
  try {
    const response = await api('/api/matchmaking-posts/eligible-bookings');
    userBookings.value = Array.isArray(response.data) ? response.data : [];
  } catch (error) {
    userBookings.value = [];
    bookingsError.value = error.message || 'Không thể tải lịch sân đủ điều kiện.';
  } finally {
    bookingsLoading.value = false;
  }
}

function handleEscape(event) {
  if (event.key === 'Escape' && props.isOpen) close();
}

async function submit() {
  if (!isValid.value || isSubmitting.value) return;
  isSubmitting.value = true;
  errorMsg.value = '';
  try {
    const response = await api('/api/matchmaking-posts', {
      method: 'POST',
      body: JSON.stringify({
        booking_id: form.booking_id,
        required_players: Number(form.required_players),
        content: form.content || null,
      }),
    });
    emit('success', response.data);
    reset();
    emit('close');
  } catch (error) {
    errorMsg.value = error.message || 'Không thể đăng bài giao lưu.';
  } finally {
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
onBeforeUnmount(() => document.removeEventListener('keydown', handleEscape));
</script>

<style scoped src="../../css/components/client-meetup-post-modal.css"></style>
