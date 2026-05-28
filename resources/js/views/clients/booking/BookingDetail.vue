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

        <div v-if="paymentNotice" class="payment-notice" :class="paymentNotice.type">
          {{ paymentNotice.message }}
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

              <div class="vnpay-box" v-if="booking.status === 'pending_payment'">
                <div class="divider"></div>
                <button class="btn-vnpay" type="button" @click="payWithVnpay" :disabled="paying || timeLeft <= 0">
                  <span v-if="!paying">Thanh toán qua VNPAY</span>
                  <span v-else>Đang tạo giao dịch...</span>
                </button>
                <p class="pay-hint">Bạn sẽ được chuyển sang cổng VNPAY để hoàn tất thanh toán.</p>
                <div class="error-msg" v-if="payError">{{ payError }}</div>
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
      paying: false,
      payError: '',
      paymentNotice: null,
      timeLeft: 0,
      timerInterval: null,
    };
  },
  computed: {
    formattedTimer() {
      const minutes = Math.floor(this.timeLeft / 60);
      const seconds = this.timeLeft % 60;
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
        pending_payment: 'Vui lòng thực hiện thanh toán trực tuyến để giữ chỗ.',
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
    this.applyPaymentNotice();
    await this.loadBooking();
  },
  beforeUnmount() {
    this.clearTimer();
  },
  methods: {
    async loadBooking() {
      const id = this.$route.params.id;
      this.loading = true;
      try {
        const res = await bookingService.getBooking(id);
        this.booking = res;
        this.timeLeft = res.time_left_seconds || 0;

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
    clearTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
    },
    applyPaymentNotice() {
      const status = this.$route.query.payment_status;
      if (status === 'success') {
        this.paymentNotice = {
          type: 'success',
          message: 'Thanh toán VNPAY thành công. Đơn đặt sân đã được xác nhận.',
        };
      } else if (status === 'failed') {
        this.paymentNotice = {
          type: 'failed',
          message: 'Thanh toán VNPAY chưa thành công. Bạn có thể thử thanh toán lại nếu đơn còn thời gian giữ chỗ.',
        };
      }

      if (status) {
        this.$router.replace({ name: this.$route.name, params: this.$route.params, query: {} });
      }
    },
    async payWithVnpay() {
      if (!this.booking || this.paying || this.timeLeft <= 0) return;

      this.paying = true;
      this.payError = '';

      try {
        const res = await bookingService.createVnpayPayment(this.booking.id);
        window.location.href = res.payment_url;
      } catch (err) {
        this.payError = err.message || 'Không thể tạo giao dịch VNPAY. Vui lòng thử lại.';
        this.paying = false;
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

.payment-notice {
  padding: 14px 18px;
  border-radius: var(--sg-radius-sm);
  font-size: 14px;
  font-weight: 600;
  border: 1px solid var(--sg-border);
}

.payment-notice.success {
  color: var(--sg-green-dark);
  background: var(--sg-green-pale);
  border-color: #bbf7d0;
}

.payment-notice.failed {
  color: #991b1b;
  background: #fef2f2;
  border-color: #fecaca;
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

.vnpay-box {
  margin-top: 18px;
}

.btn-vnpay {
  width: 100%;
  min-height: 44px;
  border-radius: var(--sg-radius-sm);
  background: #0066cc;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
  transition: var(--sg-transition);
}

.btn-vnpay:hover:not(:disabled) {
  filter: brightness(1.05);
  transform: translateY(-1px);
}

.btn-vnpay:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.pay-hint {
  margin-top: 10px;
  color: var(--sg-text-muted);
  font-size: 12px;
  line-height: 1.5;
}

.error-msg {
  color: var(--sg-danger);
  font-size: 13px;
  margin-top: 10px;
  font-weight: 500;
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
