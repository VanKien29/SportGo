<template>
  <div class="detail-container">
    <PublicNavbar />

    <main class="detail-main" v-if="!loading">
      <div class="detail-content" v-if="booking">
        <!-- Trạng thái nổi bật phía trên -->
        <div class="status-banner" :class="statusClass">
          <div class="banner-icon">
            <svg v-if="booking.status === 'confirmed'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            <svg v-else-if="booking.status === 'pending_payment'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
            <svg v-else-if="booking.status === 'pending_approval'" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="16" x2="12" y2="12"/>
              <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <svg v-else width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
          </div>
          <div class="banner-text">
            <h2>{{ statusTitle }}</h2>
            <p>{{ statusDescription }}</p>
          </div>
        </div>

        <div class="detail-grid">
          <!-- Cột trái: Thông tin đơn -->
          <div class="info-section">
            <div class="card info-card">
              <div class="card-header-simple">
                <h2>Chi tiết đơn đặt #{{ booking.booking_code }}</h2>
                <span class="badge" :class="booking.status">{{ statusLabel }}</span>
              </div>
              <div class="divider"></div>

              <div class="info-list">
                <div class="info-item">
                  <span class="label">Cụm sân:</span>
                  <span class="val font-semibold">{{ booking.venue_court?.venue_cluster?.name }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Địa chỉ:</span>
                  <span class="val">{{ booking.venue_court?.venue_cluster?.address }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Sân chơi:</span>
                  <span class="val">{{ booking.venue_court?.name }} ({{ booking.venue_court?.court_type?.name }})</span>
                </div>
                <div class="info-item">
                  <span class="label">Ngày chơi:</span>
                  <span class="val font-semibold">{{ formatDate(booking.booking_date) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Khung giờ:</span>
                  <span class="val font-semibold">{{ booking.start_time }} - {{ booking.end_time }}</span>
                </div>
                <div class="info-item">
                  <span class="label">Thời lượng:</span>
                  <span class="val">{{ booking.duration_minutes }} phút</span>
                </div>
                <div class="info-item">
                  <span class="label">Hình thức đặt:</span>
                  <span class="val">{{ booking.booking_type === 'single' ? 'Đặt lẻ' : 'Đặt cố định' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cột phải: Thanh toán & Đếm ngược -->
          <div class="payment-section">
            <!-- Đếm ngược giữ chỗ (Nếu đang chờ thanh toán) -->
            <div class="card countdown-card" v-if="booking.status === 'pending_payment'">
              <h3>Thời gian thanh toán còn lại</h3>
              <div class="timer">{{ formattedTimer }}</div>
              <p class="timer-desc">Vui lòng thanh toán trước khi thời gian kết thúc để không bị hủy sân tự động.</p>
            </div>

            <!-- Card giá trị và thanh toán -->
            <div class="card price-card">
              <h3>Thông tin chi phí</h3>
              <div class="divider"></div>

              <div class="price-rows">
                <div class="price-row">
                  <span>Tổng tiền sân:</span>
                  <span class="val">{{ formatCurrency(booking.total_price) }}</span>
                </div>
                <div class="price-row">
                  <span>Hình thức thanh toán:</span>
                  <span class="val font-medium">{{ paymentOptionLabel }}</span>
                </div>
                <div class="price-row highlighted" v-if="booking.status === 'pending_payment'">
                  <span>Số tiền cần trả ngay:</span>
                  <span class="val price">{{ formatCurrency(booking.required_payment_amount) }}</span>
                </div>
              </div>

              <div class="sepay-box" v-if="booking.status === 'pending_payment'">
                <div class="divider"></div>
                <button
                  v-if="!sepayPayment"
                  class="btn-sepay"
                  type="button"
                  :disabled="creatingSepay || timeLeft <= 0"
                  @click="createSepayPayment"
                >
                  {{ creatingSepay ? 'Đang tạo QR...' : 'Tạo QR thanh toán SePay' }}
                </button>

                <div v-if="sepayError" class="error-msg">{{ sepayError }}</div>

                <button
                  v-if="!sepayPayment"
                  class="btn-cancel-payment btn-cancel-standalone"
                  type="button"
                  :disabled="cancellingPayment"
                  @click="cancelPayment"
                >
                  {{ cancellingPayment ? 'Đang hủy thanh toán...' : 'Hủy thanh toán' }}
                </button>

                <div v-if="sepayPayment" class="sepay-panel">
                  <div class="qr-wrap">
                    <img :src="sepayPayment.qr_url" alt="QR thanh toán SePay" />
                  </div>
                  <div class="transfer-info">
                    <div class="transfer-row">
                      <span>Ngân hàng</span>
                      <strong>{{ sepayPayment.payment_account?.bank_name || sepayPayment.payment_account?.bank_code || 'SePay' }}</strong>
                    </div>
                    <div class="transfer-row">
                      <span>Số tài khoản</span>
                      <strong>{{ sepayPayment.payment_account?.account_number || sepayPayment.payment_account?.account_number_masked }}</strong>
                    </div>
                    <div class="transfer-row">
                      <span>Chủ tài khoản</span>
                      <strong>{{ sepayPayment.payment_account?.account_holder_name || 'Đang cập nhật' }}</strong>
                    </div>
                    <div class="transfer-row">
                      <span>Nội dung</span>
                      <button class="copy-value" type="button" @click="copyText(sepayPayment.transfer_content)">
                        {{ sepayPayment.transfer_content }}
                      </button>
                    </div>
                    <div class="transfer-row">
                      <span>Số tiền</span>
                      <strong>{{ formatCurrency(sepayPayment.payment?.amount) }}</strong>
                    </div>
                  </div>
                  <div class="payment-waiting">
                    <span class="mini-spinner" aria-hidden="true"></span>
                    <span>Đang chờ thanh toán. Hệ thống sẽ tự cập nhật khi nhận webhook SePay.</span>
                  </div>
                  <button class="btn-cancel-payment" type="button" :disabled="cancellingPayment" @click="cancelPayment">
                    {{ cancellingPayment ? 'Đang hủy thanh toán...' : 'Hủy thanh toán' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="detail-empty" v-else>
        <p>Không tìm thấy thông tin đơn đặt sân.</p>
        <router-link to="/booking" class="btn-back">Quay lại đặt sân</router-link>
      </div>
    </main>

    <!-- Loading State -->
    <main class="detail-loading" v-else>
      <div class="spinner"></div>
      <p>Đang tải thông tin đơn đặt sân...</p>
    </main>
  </div>
</template>

<script>
import PublicNavbar from '../../../components/PublicNavbar.vue';
import { bookingService } from '../../../services/bookingService.js';

export default {
  name: 'BookingDetail',
  components: { PublicNavbar },
  data() {
    return {
      booking: null,
      loading: true,
      creatingSepay: false,
      cancellingPayment: false,
      sepayPayment: null,
      sepayError: '',
      timeLeft: 0,
      timerInterval: null,
      paymentPollInterval: null,
    };
  },
  computed: {
    formattedTimer() {
      const totalSeconds = Math.max(0, Math.floor(Number(this.timeLeft) || 0));
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },
    statusClass() {
      if (!this.booking) return '';
      return this.booking.status;
    },
    statusTitle() {
      if (!this.booking) return '';
      const map = {
        confirmed: 'Đặt Sân Thành Công!',
        pending_payment: 'Đơn Chờ Thanh Toán',
        pending_approval: 'Chờ Chủ Sân Duyệt',
        expired: 'Đơn Đã Hết Hạn',
        cancelled: 'Đơn Đã Bị Hủy',
      };
      return map[this.booking.status] || 'Trạng thái không xác định';
    },
    statusDescription() {
      if (!this.booking) return '';
      const map = {
        confirmed: 'Đơn của bạn đã được xác nhận. Hẹn gặp lại bạn tại sân chơi!',
        pending_payment: 'Vui lòng hoàn tất thanh toán để giữ chỗ.',
        pending_approval: 'Chủ sân đang kiểm tra thông tin cấu hình và duyệt đơn đặt của bạn.',
        expired: 'Bạn đã quá hạn thanh toán 20 phút. Sân đã được giải phóng.',
        cancelled: 'Đơn đặt sân này đã bị hủy bỏ bởi hệ thống hoặc người dùng.',
      };
      return map[this.booking.status] || '';
    },
    statusLabel() {
      if (!this.booking) return '';
      const map = {
        confirmed: 'Đã xác nhận',
        pending_payment: 'Chờ thanh toán',
        pending_approval: 'Chờ duyệt',
        expired: 'Hết hạn',
        cancelled: 'Đã hủy',
      };
      return map[this.booking.status] || this.booking.status;
    },
    paymentOptionLabel() {
      if (!this.booking) return '';
      const map = {
        full_payment: 'Thanh toán hết trực tuyến',
        deposit: 'Đặt cọc giữ chỗ',
        no_prepay: 'Thanh toán trực tiếp tại sân',
      };
      return map[this.booking.payment_option] || this.booking.payment_option;
    },
  },
  async mounted() {
    await this.loadBooking();
  },
  beforeUnmount() {
    this.clearTimer();
    this.clearPaymentPolling();
  },
  methods: {
    async loadBooking() {
      const id = this.$route.params.id;
      this.loading = true;
      try {
        const res = await bookingService.getBooking(id);
        this.booking = res;
        this.timeLeft = this.normalizeTimeLeft(res.time_left_seconds);

        if (this.booking.status === 'pending_payment' && this.timeLeft > 0) {
          this.startTimer();
        } else {
          this.clearTimer();
        }
      } catch (err) {
        console.error(err);
      } finally {
        this.loading = false;
      }
    },
    startTimer() {
      this.clearTimer();
      this.timerInterval = setInterval(() => {
        if (this.timeLeft > 0) {
          this.timeLeft--;
        } else {
          this.clearTimer();
          // Khi đếm ngược về 0, chuyển trạng thái đơn đặt sân sang expired
          if (this.booking) {
            this.booking.status = 'expired';
          }
        }
      }, 1000);
    },
    normalizeTimeLeft(value) {
      return Math.max(0, Math.floor(Number(value) || 0));
    },
    clearTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
    },
    async createSepayPayment() {
      if (!this.booking || this.creatingSepay || this.timeLeft <= 0) return;

      this.creatingSepay = true;
      this.sepayError = '';

      try {
        const res = await bookingService.createSepayPayment(this.booking.id);
        this.sepayPayment = res;
        this.startPaymentPolling();
      } catch (err) {
        this.sepayError = err.message || 'Không thể tạo thông tin thanh toán SePay.';
      } finally {
        this.creatingSepay = false;
      }
    },
    async refreshBookingStatus() {
      if (!this.booking) return;

      try {
        const res = await bookingService.getBooking(this.booking.id);
        this.booking = res;
        this.timeLeft = this.normalizeTimeLeft(res.time_left_seconds);

        if (this.booking.status !== 'pending_payment') {
          this.clearPaymentPolling();
          this.clearTimer();
          this.sepayPayment = null;
        }
      } catch (err) {
        this.sepayError = err.message || 'Không thể kiểm tra trạng thái thanh toán.';
      }
    },
    startPaymentPolling() {
      this.clearPaymentPolling();
      this.paymentPollInterval = setInterval(() => {
        this.refreshBookingStatus();
      }, 5000);
    },
    async cancelPayment() {
      if (!this.booking || this.cancellingPayment) return;

      const confirmed = window.confirm('Bạn chắc chắn muốn hủy thanh toán và hủy đơn đặt sân này?');
      if (!confirmed) return;

      this.cancellingPayment = true;
      this.sepayError = '';

      try {
        const res = await bookingService.cancelPayment(this.booking.id);
        this.booking = res.booking || this.booking;
        this.timeLeft = 0;
        this.sepayPayment = null;
        this.clearTimer();
        this.clearPaymentPolling();
      } catch (err) {
        this.sepayError = err.message || 'Không thể hủy thanh toán.';
      } finally {
        this.cancellingPayment = false;
      }
    },
    clearPaymentPolling() {
      if (this.paymentPollInterval) {
        clearInterval(this.paymentPollInterval);
        this.paymentPollInterval = null;
      }
    },
    async copyText(text) {
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
      } catch {
        this.sepayError = 'Không thể sao chép nội dung chuyển khoản.';
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      // handle ISO datetime or simple date string
      const dateOnly = dateStr.includes('T') ? dateStr.split('T')[0] : dateStr;
      const [year, month, day] = dateOnly.split('-');
      return `${day}/${month}/${year}`;
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    },
  },
};
</script>

<style scoped>
.detail-container {
  min-height: 100vh;
  background: var(--sg-surface);
}

.detail-main {
  max-width: 1000px;
  margin: 0 auto;
  padding: 100px 24px 60px;
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Status Banner */
.status-banner {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px;
  border-radius: var(--sg-radius);
  border: 1px solid var(--sg-border);
  box-shadow: var(--sg-shadow);
  background: var(--sg-white);
}

.status-banner.confirmed {
  border-left: 6px solid var(--sg-green);
}
.status-banner.confirmed .banner-icon {
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
}

.status-banner.pending_payment {
  border-left: 6px solid #eab308;
}
.status-banner.pending_payment .banner-icon {
  background: #fef9c3;
  color: #a16207;
}

.status-banner.pending_approval {
  border-left: 6px solid #2563eb;
}
.status-banner.pending_approval .banner-icon {
  background: #dbeafe;
  color: #1d4ed8;
}

.status-banner.expired, .status-banner.cancelled {
  border-left: 6px solid var(--sg-danger);
}
.status-banner.expired .banner-icon, .status-banner.cancelled .banner-icon {
  background: #fef2f2;
  color: var(--sg-danger);
}

.banner-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 56px;
}

.banner-text h2 {
  font-size: 20px;
  font-weight: 800;
  color: var(--sg-dark);
}

.banner-text p {
  font-size: 14px;
  color: var(--sg-text-muted);
  margin-top: 4px;
}

/* Detail Grid */
.detail-grid {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 24px;
  align-items: start;
}

.card {
  background: var(--sg-white);
  border-radius: var(--sg-radius);
  border: 1px solid var(--sg-border);
  padding: 24px;
  box-shadow: var(--sg-shadow);
}

.card-header-simple {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header-simple h2 {
  font-size: 18px;
  font-weight: 800;
  color: var(--sg-dark);
}

.badge {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: var(--sg-radius-full);
}

.badge.confirmed { background: var(--sg-green-pale); color: var(--sg-green-dark); }
.badge.pending_payment { background: #fef9c3; color: #a16207; }
.badge.pending_approval { background: #dbeafe; color: #1d4ed8; }
.badge.expired { background: #fef2f2; color: var(--sg-danger); }
.badge.cancelled { background: #fef2f2; color: var(--sg-danger); }

.divider {
  height: 1px;
  background: var(--sg-border);
  margin: 16px 0;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.info-item .label {
  color: var(--sg-text-muted);
}

.info-item .val {
  color: var(--sg-dark);
  font-weight: 500;
  max-width: 60%;
  text-align: right;
}

/* Right Section */
.payment-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.countdown-card {
  text-align: center;
  border: 1px solid #fef08a;
  background: #fffbeb;
}

.countdown-card h3 {
  font-size: 14px;
  color: #a16207;
  font-weight: 700;
  text-transform: uppercase;
}

.countdown-card .timer {
  font-size: 36px;
  font-weight: 900;
  color: #b45309;
  margin: 10px 0;
  font-family: monospace;
}

.countdown-card .timer-desc {
  font-size: 12px;
  color: #d97706;
}

.price-card h3 {
  font-size: 16px;
  font-weight: 700;
  color: var(--sg-dark);
}

.price-rows {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.price-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: var(--sg-text-muted);
}

.price-row .val {
  color: var(--sg-dark);
  font-weight: 600;
}

.price-row.highlighted {
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px dashed var(--sg-border);
  color: var(--sg-dark);
  font-weight: 700;
}

.price-row.highlighted .price {
  font-size: 22px;
  color: var(--sg-green-dark);
  font-weight: 800;
}

.sepay-box {
  margin-top: 18px;
}

.btn-sepay,
.btn-cancel-payment {
  width: 100%;
  min-height: 44px;
  border-radius: var(--sg-radius-sm);
  color: #fff;
  font-size: 14px;
  font-weight: 800;
  transition: var(--sg-transition);
}

.btn-sepay {
  background: var(--sg-green);
}

.btn-cancel-payment {
  background: var(--sg-danger);
}

.btn-cancel-standalone {
  margin-top: 12px;
}

.btn-sepay:hover:not(:disabled),
.btn-cancel-payment:hover:not(:disabled) {
  filter: brightness(1.05);
  transform: translateY(-1px);
}

.btn-sepay:disabled,
.btn-cancel-payment:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.sepay-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.qr-wrap {
  display: flex;
  justify-content: center;
  padding: 12px;
  border: 1px solid var(--sg-border);
  border-radius: var(--sg-radius-sm);
  background: #fff;
}

.qr-wrap img {
  width: min(220px, 100%);
  aspect-ratio: 1;
  object-fit: contain;
}

.transfer-info {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.transfer-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: var(--sg-text-muted);
}

.transfer-row strong {
  color: var(--sg-dark);
  text-align: right;
  word-break: break-word;
}

.copy-value {
  max-width: 60%;
  color: var(--sg-green-dark);
  font-weight: 900;
  text-align: right;
  word-break: break-word;
  background: transparent;
  text-decoration: underline;
}

.payment-waiting {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: var(--sg-radius-sm);
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
  font-size: 13px;
  font-weight: 700;
  line-height: 1.45;
}

.mini-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(22, 163, 74, 0.22);
  border-top-color: var(--sg-green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  flex: 0 0 auto;
}

.error-msg {
  color: var(--sg-danger);
  font-size: 13px;
  margin-top: 10px;
  font-weight: 600;
}

.detail-empty {
  text-align: center;
  padding: 60px 0;
  color: var(--sg-text-muted);
}

.btn-back {
  display: inline-block;
  margin-top: 16px;
  padding: 10px 24px;
  background: var(--sg-green);
  color: #fff;
  border-radius: var(--sg-radius);
  font-weight: 600;
}

/* Loading state */
.detail-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120px 0;
  color: var(--sg-text-muted);
}

.spinner {
  width: 44px;
  height: 44px;
  border: 3px solid var(--sg-border);
  border-top-color: var(--sg-green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 800px) {
  .detail-grid { grid-template-columns: 1fr; }
}
</style>
