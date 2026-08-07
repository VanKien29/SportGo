<template>
  <Teleport to="body">
    <div v-if="isOpen" class="refund-request-backdrop" role="presentation" @click.self="close">
      <form class="refund-request-modal" role="dialog" aria-modal="true" aria-labelledby="refund-request-title" @submit.prevent="submit">
        <header class="refund-request-modal__header">
          <div>
            <span class="refund-request-modal__eyebrow">Trung tâm hoàn / hủy</span>
            <h2 id="refund-request-title">Tạo yêu cầu hoàn tiền</h2>
            <p>{{ booking?.booking_code ? `Booking #${booking.booking_code}` : 'Booking của bạn' }}</p>
          </div>
          <button type="button" class="refund-request-modal__close" aria-label="Đóng" :disabled="loading" @click="close">
            <AppIcon name="x" :size="18" />
          </button>
        </header>

        <div class="refund-request-modal__body">
          <section class="refund-request-booking">
            <div><small>Booking</small><strong>{{ booking?.venue_cluster?.name || booking?.venueCluster?.name || 'Cụm sân' }}</strong></div>
            <div><small>Thời gian chơi</small><strong>{{ formatDate(booking?.booking_date) }} · {{ formatTime(booking?.start_time) }} - {{ formatTime(booking?.end_time) }}</strong></div>
          </section>

          <section v-if="loadingPolicy" class="refund-request-policy refund-request-policy--loading">
            Đang kiểm tra chính sách hoàn tiền...
          </section>
          <section v-else class="refund-request-policy" :class="{ 'is-warning': maxAmount <= 0 }">
            <div>
              <small>Hạn mức theo chính sách</small>
              <strong>{{ money(maxAmount) }}</strong>
            </div>
            <p>{{ policy?.summary || 'Số tiền sẽ được đối chiếu với chính sách hủy booking hiện hành.' }}</p>
          </section>

          <label class="refund-request-field">
            <span>Số tiền muốn hoàn</span>
            <input v-model="amount" type="number" min="1" :max="maxAmount || undefined" step="1" inputmode="numeric" placeholder="Để trống để yêu cầu mức tối đa" />
            <small>Phương thức nhận: <strong>Ví SportGo</strong>. Số tiền không được vượt quá hạn mức chính sách.</small>
          </label>

          <label class="refund-request-field">
            <span>Lý do yêu cầu <b>*</b></span>
            <textarea v-model.trim="reason" rows="4" maxlength="2000" placeholder="Mô tả rõ lý do bạn cần hoàn tiền (tối thiểu 10 ký tự)" />
            <small>{{ reason.length }}/2000</small>
          </label>

          <p v-if="validationError || error" class="refund-request-error" role="alert">{{ validationError || error }}</p>
        </div>

        <footer class="refund-request-modal__footer">
          <button type="button" class="refund-request-button refund-request-button--secondary" :disabled="loading" @click="close">Để sau</button>
          <button type="submit" class="refund-request-button refund-request-button--primary" :disabled="loading || loadingPolicy || maxAmount <= 0">
            {{ loading ? 'Đang gửi...' : 'Gửi yêu cầu' }}
          </button>
        </footer>
      </form>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from './AppIcon.vue';
import { bookingService } from '../services/bookingService.js';

export default {
  name: 'RefundRequestModal',
  components: { AppIcon },
  props: {
    isOpen: { type: Boolean, default: false },
    booking: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
  },
  emits: ['close', 'submit'],
  data() {
    return {
      amount: '',
      reason: '',
      policy: null,
      loadingPolicy: false,
      validationError: '',
    };
  },
  computed: {
    maxAmount() {
      return Number(this.policy?.refund_amount || 0);
    },
  },
  watch: {
    isOpen(value) {
      if (value) this.initialize();
    },
  },
  methods: {
    async initialize() {
      this.amount = '';
      this.reason = '';
      this.policy = null;
      this.validationError = '';
      if (!this.booking?.id) return;

      this.loadingPolicy = true;
      try {
        this.policy = await bookingService.previewCancellation(this.booking.id);
      } catch (error) {
        this.validationError = error.message || 'Không thể kiểm tra chính sách hoàn tiền.';
      } finally {
        this.loadingPolicy = false;
      }
    },
    close() {
      if (!this.loading) this.$emit('close');
    },
    submit() {
      this.validationError = '';
      if (this.reason.length < 10) {
        this.validationError = 'Lý do yêu cầu phải có ít nhất 10 ký tự.';
        return;
      }

      const requestedAmount = this.amount === '' ? null : Number(this.amount);
      if (requestedAmount !== null && (!Number.isFinite(requestedAmount) || requestedAmount <= 0)) {
        this.validationError = 'Số tiền yêu cầu phải lớn hơn 0.';
        return;
      }
      if (requestedAmount !== null && requestedAmount > this.maxAmount + 0.01) {
        this.validationError = `Số tiền không được vượt quá ${this.money(this.maxAmount)}.`;
        return;
      }
      if (this.maxAmount <= 0) {
        this.validationError = 'Booking này hiện không có khoản tiền đủ điều kiện hoàn.';
        return;
      }

      this.$emit('submit', {
        booking_id: this.booking.id,
        amount: requestedAmount,
        refund_destination: 'user_wallet',
        reason: this.reason,
      });
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    formatDate(value) {
      if (!value) return '-';
      const raw = String(value).split('T')[0];
      const [year, month, day] = raw.split('-');
      return day && month && year ? `${day}/${month}/${year}` : raw;
    },
    formatTime(value) {
      return value ? String(value).slice(0, 5) : '--:--';
    },
  },
};
</script>

<style scoped>
.refund-request-backdrop { position: fixed; z-index: 1500; inset: 0; display: grid; place-items: center; padding: 18px; background: rgba(8, 30, 17, .46); }
.refund-request-modal { width: min(540px, 100%); max-height: min(760px, calc(100vh - 36px)); overflow: auto; border: 1px solid #cfe3d5; border-radius: 16px; background: #fff; box-shadow: 0 26px 74px rgba(13, 48, 28, .22); }
.refund-request-modal__header, .refund-request-modal__footer { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
.refund-request-modal__header { padding: 21px 22px 17px; border-bottom: 1px solid #e0eee4; }
.refund-request-modal__eyebrow { color: #087642; font-size: 10px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.refund-request-modal h2 { margin: 6px 0 0; color: #14261d; font-size: 21px; }
.refund-request-modal__header p { margin: 5px 0 0; color: #718078; font-size: 12px; }
.refund-request-modal__close { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid #cfe3d5; border-radius: 8px; background: #fff; color: #567067; cursor: pointer; }
.refund-request-modal__body { display: grid; gap: 14px; padding: 19px 22px; }
.refund-request-booking { display: grid; grid-template-columns: 1fr 1fr; gap: 11px; padding: 13px; border: 1px solid #dcece1; border-radius: 10px; background: #f7fbf8; }
.refund-request-booking div, .refund-request-policy { display: grid; gap: 4px; }
.refund-request-booking small, .refund-request-policy small, .refund-request-field > small { color: #718078; font-size: 11px; }
.refund-request-booking strong { color: #14261d; font-size: 13px; }
.refund-request-policy { padding: 13px; border: 1px solid #bde4c9; border-radius: 10px; background: #eaf8ee; }
.refund-request-policy.is-warning { border-color: #f1d6a4; background: #fff8e9; }
.refund-request-policy strong { color: #087642; font-size: 20px; }
.refund-request-policy.is-warning strong { color: #9a6400; }
.refund-request-policy p { margin: 3px 0 0; color: #56695e; font-size: 12px; line-height: 1.45; }
.refund-request-field { display: grid; gap: 6px; }
.refund-request-field > span { color: #30473b; font-size: 12px; font-weight: 800; }
.refund-request-field > span b { color: #b42318; }
.refund-request-field input, .refund-request-field textarea { width: 100%; box-sizing: border-box; border: 1px solid #bfdaca; border-radius: 8px; background: #fff; color: #14261d; font: inherit; padding: 10px 11px; outline: none; }
.refund-request-field input { min-height: 42px; }
.refund-request-field textarea { resize: vertical; }
.refund-request-field input:focus, .refund-request-field textarea:focus { border-color: #0b9b4b; box-shadow: 0 0 0 3px rgba(11, 155, 75, .12); }
.refund-request-field > small:last-child { justify-self: end; }
.refund-request-error { margin: 0; padding: 9px 11px; border: 1px solid #efb1b1; border-radius: 8px; background: #fff1f1; color: #a52828; font-size: 12px; }
.refund-request-modal__footer { align-items: center; justify-content: flex-end; padding: 15px 22px; border-top: 1px solid #e0eee4; }
.refund-request-button { min-height: 40px; padding: 0 15px; border: 1px solid #bfdaca; border-radius: 8px; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; }
.refund-request-button:disabled { cursor: not-allowed; opacity: .55; }
.refund-request-button--secondary { background: #fff; color: #30473b; }
.refund-request-button--primary { border-color: #0b9b4b; background: #0b9b4b; color: #fff; }
@media (max-width: 560px) { .refund-request-booking { grid-template-columns: 1fr; } .refund-request-modal__header, .refund-request-modal__body, .refund-request-modal__footer { padding-inline: 16px; } .refund-request-modal__footer { display: grid; grid-template-columns: 1fr 1fr; } .refund-request-button { width: 100%; } }
</style>
