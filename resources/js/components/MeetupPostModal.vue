<template>
  <div v-if="isOpen" class="modal-overlay" role="presentation" @click.self="close">
    <section class="meetup-modal" role="dialog" aria-modal="true" aria-labelledby="meetup-modal-title">
      <header class="modal-header">
        <div>
          <span class="modal-kicker">Cộng đồng SportGo</span>
          <h2 id="meetup-modal-title">Tạo bài giao lưu</h2>
        </div>
        <button type="button" class="icon-button" aria-label="Đóng" @click="close">
          <AppIcon name="close" size="18" />
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
            <small class="character-count">{{ form.content.length }}/2000</small>
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
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
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
  errorMsg.value = '';
  try {
    const response = await api('/api/matchmaking-posts/eligible-bookings');
    userBookings.value = Array.isArray(response.data) ? response.data : [];
  } catch (error) {
    userBookings.value = [];
    errorMsg.value = error.message || 'Không thể tải lịch sân đủ điều kiện.';
  } finally {
    bookingsLoading.value = false;
  }
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

.meetup-modal {
  width: min(100%, 620px);
  max-height: 90vh;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  color: var(--admin-text);
}

.modal-header,
.author-summary,
.form-actions {
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

.icon-button {
  display: grid;
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
  cursor: pointer;
}

.icon-button:hover {
  border-color: var(--admin-primary);
  color: var(--admin-primary-dark);
}

.modal-body {
  max-height: calc(90vh - 74px);
  overflow-y: auto;
  padding: 20px;
}

.author-summary {
  gap: 11px;
  margin-bottom: 18px;
  padding: 10px 12px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-bg-soft);
}

.author-avatar {
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border-radius: 50%;
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  font-size: var(--admin-font-size-lg);
  font-weight: 600;
}

.author-summary > div:last-child {
  display: grid;
  gap: 3px;
}

.author-summary strong {
  font-size: var(--admin-font-size-base);
}

.author-summary span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
}

.modal-state {
  display: grid;
  min-height: 190px;
  place-items: center;
  align-content: center;
  gap: 10px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-base);
  text-align: center;
}

.modal-state.empty span {
  max-width: 380px;
  line-height: 1.5;
}

.spinner {
  width: 24px;
  height: 24px;
  border: 2px solid var(--admin-border);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.meetup-form,
.field-block {
  display: grid;
  gap: 9px;
}

.field-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(140px, 0.5fr);
  gap: 14px;
}

.field-block {
  margin-top: 10px;
}

.field-block > span {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: var(--admin-text);
  font-size: var(--admin-font-size-base);
  font-weight: 600;
}

.field-block small,
.character-count {
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
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

input.field-control {
  min-height: 40px;
  padding: 8px 10px;
}

textarea.field-control {
  min-height: 106px;
  padding: 10px 12px;
  resize: vertical;
}

.field-control:focus {
  border-color: var(--admin-primary);
}

.character-count {
  justify-self: end;
}

.form-error {
  margin: 8px 0 0;
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
  margin-top: 14px;
  padding-top: 16px;
  border-top: 1px solid var(--admin-border-soft);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .modal-overlay {
    align-items: end;
    padding: 0;
  }

  .meetup-modal {
    max-height: 94vh;
    border-radius: var(--admin-radius-lg) var(--admin-radius-lg) 0 0;
  }

  .modal-body {
    max-height: calc(94vh - 74px);
  }

  .field-grid {
    grid-template-columns: 1fr;
  }
}
</style>
