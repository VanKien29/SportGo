<template>
  <div class="booking-container">
    <PublicNavbar />

    <main class="booking-main">
      <div class="booking-header">
        <h1 class="page-title">Đặt Sân Trực Tuyến</h1>
        <p class="page-desc">Chọn cụm sân, sân con và khung giờ chơi phù hợp với bạn.</p>
      </div>

      <div class="booking-grid" v-if="!loadingInit">
        <!-- Cột trái: Form nhập thông tin -->
        <div class="form-section">
          <!-- Card 1: Chọn sân chơi -->
          <div class="card">
            <div class="card-header">
              <span class="card-icon">1</span>
              <h2>Chọn sân chơi</h2>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label for="cluster">Cụm sân</label>
                <select id="cluster" v-model="selectedClusterId" @change="onClusterChange" class="form-control">
                  <option value="" disabled>-- Chọn cụm sân --</option>
                  <option v-for="c in clusters" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>

              <div class="form-group" v-if="selectedClusterId">
                <label for="court">Sân con</label>
                <select id="court" v-model="selectedCourtId" @change="checkAvailability" class="form-control">
                  <option value="" disabled>-- Chọn sân con --</option>
                  <option v-for="ct in availableCourts" :key="ct.id" :value="ct.id">
                    {{ ct.name }} ({{ ct.court_type?.name || 'Chưa phân loại' }})
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Card 2: Chọn thời gian -->
          <div class="card" v-if="selectedCourtId">
            <div class="card-header">
              <span class="card-icon">2</span>
              <h2>Chọn thời gian chơi</h2>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label for="date">Ngày đặt sân</label>
                <input
                  type="date"
                  id="date"
                  v-model="bookingDate"
                  :min="minDate"
                  @change="checkAvailability"
                  class="form-control"
                />
              </div>

              <div class="time-range-group">
                <div class="form-group">
                  <label for="start_time">Giờ bắt đầu</label>
                  <select id="start_time" v-model="startTime" @change="onTimeChange" class="form-control">
                    <option v-for="t in timeOptions" :key="'start-'+t" :value="t">{{ t }}</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="end_time">Giờ kết thúc</label>
                  <select id="end_time" v-model="endTime" @change="onTimeChange" class="form-control">
                    <option v-for="t in timeOptions" :key="'end-'+t" :value="t">{{ t }}</option>
                  </select>
                </div>
              </div>

              <!-- Trạng thái trống/bận -->
              <div class="availability-status" v-if="checkingAvailability">
                <div class="spinner-small"></div> Đang kiểm tra lịch trống...
              </div>
              <div class="availability-status" v-else-if="availabilityChecked">
                <span v-if="isAvailable" class="status-badge success">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  Khung giờ này còn trống
                </span>
                <span v-else class="status-badge danger">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                  Sân đã bận hoặc đang được giữ chỗ
                </span>
              </div>
            </div>
          </div>

          <!-- Card 3: Chọn phương thức thanh toán -->
          <div class="card" v-if="selectedCourtId && isAvailable">
            <div class="card-header">
              <span class="card-icon">3</span>
              <h2>Chọn hình thức thanh toán</h2>
            </div>
            <div class="card-body">
              <div class="payment-options">
                <!-- Không trả trước -->
                <label
                  v-if="config.allow_no_prepay"
                  class="payment-option-card"
                  :class="{ active: paymentOption === 'no_prepay' }"
                >
                  <input type="radio" v-model="paymentOption" value="no_prepay" class="hidden-radio" />
                  <div class="option-info">
                    <span class="option-title">Không trả trước</span>
                    <span class="option-desc">Thanh toán trực tiếp tại sân khi đến chơi.</span>
                  </div>
                </label>

                <!-- Đặt cọc -->
                <label
                  v-if="config.allow_deposit"
                  class="payment-option-card"
                  :class="{ active: paymentOption === 'deposit' }"
                >
                  <input type="radio" v-model="paymentOption" value="deposit" class="hidden-radio" />
                  <div class="option-info">
                    <span class="option-title">Đặt cọc trước ({{ config.deposit_percent || 30 }}%)</span>
                    <span class="option-desc">Đặt cọc online để giữ chỗ, phần còn lại trả tại sân.</span>
                  </div>
                </label>

                <!-- Thanh toán hết -->
                <label
                  v-if="config.allow_full_payment"
                  class="payment-option-card"
                  :class="{ active: paymentOption === 'full_payment' }"
                >
                  <input type="radio" v-model="paymentOption" value="full_payment" class="hidden-radio" />
                  <div class="option-info">
                    <span class="option-title">Thanh toán trực tuyến 100%</span>
                    <span class="option-desc">Trả toàn bộ tiền online nhanh gọn, giữ chỗ tức thì.</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Cột phải: Tổng quan đơn đặt -->
        <div class="summary-section">
          <div class="sticky-card">
            <div class="card summary-card">
              <h2>Thông tin đặt sân</h2>
              <div class="divider"></div>

              <div class="summary-details">
                <div class="summary-row">
                  <span class="label">Cụm sân:</span>
                  <span class="val">{{ currentCluster?.name || '-' }}</span>
                </div>
                <div class="summary-row">
                  <span class="label">Sân con:</span>
                  <span class="val">{{ currentCourt?.name || '-' }}</span>
                </div>
                <div class="summary-row">
                  <span class="label">Ngày chơi:</span>
                  <span class="val">{{ formatDate(bookingDate) }}</span>
                </div>
                <div class="summary-row">
                  <span class="label">Khung giờ:</span>
                  <span class="val" v-if="startTime && endTime">{{ startTime }} - {{ endTime }}</span>
                  <span class="val" v-else>-</span>
                </div>
                <div class="summary-row">
                  <span class="label">Thời lượng:</span>
                  <span class="val" v-if="durationMinutes">{{ durationMinutes }} phút</span>
                  <span class="val" v-else>-</span>
                </div>
              </div>

              <div class="divider"></div>

              <div class="price-details" v-if="durationMinutes">
                <div class="summary-row">
                  <span class="label">Đơn giá:</span>
                  <span class="val font-semibold">{{ formatCurrency(hourlyRate) }} / giờ</span>
                </div>
                <div class="summary-row total-row">
                  <span class="label">Tổng tiền:</span>
                  <span class="val price">{{ formatCurrency(totalPrice) }}</span>
                </div>
                <div class="summary-row deposit-row" v-if="paymentOption !== 'no_prepay'">
                  <span class="label">Cần trả trước:</span>
                  <span class="val required-price">{{ formatCurrency(requiredPaymentAmount) }}</span>
                </div>
              </div>

              <div class="error-msg" v-if="submitError">{{ submitError }}</div>

              <button
                class="btn-submit"
                :disabled="!canSubmit || submitting"
                @click="submitBooking"
              >
                <span v-if="submitting" class="spinner-small inline-block"></span>
                <span v-else>Xác nhận đặt sân</span>
              </button>

              <p class="hold-notice" v-if="paymentOption !== 'no_prepay'">
                * Hệ thống sẽ tạm giữ sân trong vòng <strong>20 phút</strong> để bạn thực hiện thanh toán trực tuyến.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div class="loading-state" v-else>
        <div class="spinner"></div>
        <p>Đang tải danh sách sân chơi...</p>
      </div>
    </main>
  </div>
</template>

<script>
import PublicNavbar from '../../../components/PublicNavbar.vue';
import { bookingService } from '../../../services/bookingService.js';
import { getAuth } from '../../../stores/auth.js';

export default {
  name: 'BookingForm',
  components: { PublicNavbar },
  data() {
    return {
      clusters: [],
      selectedClusterId: '',
      selectedCourtId: '',
      bookingDate: new Date().toISOString().split('T')[0],
      startTime: '08:00:00',
      endTime: '09:00:00',
      paymentOption: 'no_prepay',

      loadingInit: true,
      checkingAvailability: false,
      availabilityChecked: false,
      isAvailable: false,
      submitting: false,
      submitError: null,

      timeOptions: [
        '05:00:00', '05:30:00', '06:00:00', '06:30:00', '07:00:00', '07:30:00',
        '08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00',
        '11:00:00', '11:30:00', '12:00:00', '12:30:00', '13:00:00', '13:30:00',
        '14:00:00', '14:30:00', '15:00:00', '15:30:00', '16:00:00', '16:30:00',
        '17:00:00', '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00',
        '20:00:00', '20:30:00', '21:00:00', '21:30:00', '22:00:00'
      ],
    };
  },
  computed: {
    minDate() {
      return new Date().toISOString().split('T')[0];
    },
    currentCluster() {
      return this.clusters.find(c => c.id === this.selectedClusterId);
    },
    availableCourts() {
      return this.currentCluster?.venue_courts || [];
    },
    currentCourt() {
      return this.availableCourts.find(c => c.id === this.selectedCourtId);
    },
    config() {
      return this.currentCluster?.booking_config || {
        allow_full_payment: true,
        allow_deposit: true,
        allow_no_prepay: true,
        deposit_percent: 30,
      };
    },
    durationMinutes() {
      if (!this.startTime || !this.endTime) return 0;
      const startParts = this.startTime.split(':').map(Number);
      const endParts = this.endTime.split(':').map(Number);
      const diff = (endParts[0]*60 + endParts[1]) - (startParts[0]*60 + startParts[1]);
      return diff > 0 ? diff : 0;
    },
    hourlyRate() {
      // Mock đơn giá mặc định hoặc lấy từ PriceSlot nếu có tích hợp sau này
      return 100000;
    },
    totalPrice() {
      return (this.durationMinutes / 60) * this.hourlyRate;
    },
    requiredPaymentAmount() {
      if (this.paymentOption === 'full_payment') {
        return this.totalPrice;
      }
      if (this.paymentOption === 'deposit') {
        const percent = this.config.deposit_percent || 30;
        return this.totalPrice * (percent / 100);
      }
      return 0;
    },
    canSubmit() {
      return (
        this.selectedClusterId &&
        this.selectedCourtId &&
        this.bookingDate &&
        this.startTime &&
        this.endTime &&
        this.durationMinutes > 0 &&
        this.isAvailable &&
        !this.checkingAvailability
      );
    },
  },
  async mounted() {
    // Check login state
    const auth = getAuth();
    if (!auth) {
      this.$router.push('/login');
      return;
    }

    try {
      const res = await bookingService.getInitData();
      this.clusters = res.clusters || [];
      if (this.clusters.length > 0) {
        this.selectedClusterId = this.clusters[0].id;
        this.onClusterChange();
      }
    } catch (err) {
      console.error(err);
    } finally {
      this.loadingInit = false;
    }
  },
  methods: {
    onClusterChange() {
      this.selectedCourtId = '';
      this.isAvailable = false;
      this.availabilityChecked = false;
      if (this.availableCourts.length > 0) {
        this.selectedCourtId = this.availableCourts[0].id;
        this.checkAvailability();
      }
    },
    onTimeChange() {
      this.checkAvailability();
    },
    async checkAvailability() {
      if (!this.selectedCourtId || !this.bookingDate || !this.startTime || !this.endTime) return;

      const startParts = this.startTime.split(':').map(Number);
      const endParts = this.endTime.split(':').map(Number);
      const diff = (endParts[0]*60 + endParts[1]) - (startParts[0]*60 + startParts[1]);

      if (diff <= 0) {
        this.isAvailable = false;
        this.availabilityChecked = true;
        return;
      }

      this.checkingAvailability = true;
      this.submitError = null;

      try {
        const res = await bookingService.checkAvailability({
          venue_court_id: this.selectedCourtId,
          booking_date: this.bookingDate,
          start_time: this.startTime,
          end_time: this.endTime,
        });
        this.isAvailable = res.available;

        // Auto select allowed payment option if current becomes invalid
        if (this.isAvailable) {
          if (this.paymentOption === 'no_prepay' && !this.config.allow_no_prepay) {
            this.paymentOption = this.config.allow_deposit ? 'deposit' : 'full_payment';
          } else if (this.paymentOption === 'deposit' && !this.config.allow_deposit) {
            this.paymentOption = this.config.allow_full_payment ? 'full_payment' : 'no_prepay';
          } else if (this.paymentOption === 'full_payment' && !this.config.allow_full_payment) {
            this.paymentOption = this.config.allow_deposit ? 'deposit' : 'no_prepay';
          }
        }
      } catch (err) {
        console.error(err);
        this.isAvailable = false;
      } finally {
        this.checkingAvailability = false;
        this.availabilityChecked = true;
      }
    },
    async submitBooking() {
      if (!this.canSubmit) return;

      this.submitting = true;
      this.submitError = null;

      try {
        const res = await bookingService.createBooking({
          venue_court_id: this.selectedCourtId,
          booking_date: this.bookingDate,
          start_time: this.startTime,
          end_time: this.endTime,
          payment_option: this.paymentOption,
        });

        // Chuyển hướng sang trang chi tiết đặt chỗ
        this.$router.push({ name: 'booking-detail', params: { id: res.id } });
      } catch (err) {
        this.submitError = err.message || 'Có lỗi xảy ra khi gửi yêu cầu đặt sân.';
      } finally {
        this.submitting = false;
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const [year, month, day] = dateStr.split('-');
      return `${day}/${month}/${year}`;
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    },
  },
};
</script>

<style scoped>
.booking-container {
  min-height: 100vh;
  background: var(--sg-surface);
}

.booking-main {
  max-width: 1200px;
  margin: 0 auto;
  padding: 100px 24px 60px;
}

.booking-header {
  margin-bottom: 32px;
}

.page-title {
  font-size: 32px;
  font-weight: 800;
  color: var(--sg-dark);
  letter-spacing: -0.5px;
}

.page-desc {
  font-size: 15px;
  color: var(--sg-text-muted);
  margin-top: 8px;
}

.booking-grid {
  display: grid;
  grid-template-columns: 7fr 5fr;
  gap: 32px;
  align-items: start;
}

.card {
  background: var(--sg-white);
  border-radius: var(--sg-radius);
  border: 1px solid var(--sg-border);
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: var(--sg-shadow);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.card-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--sg-green);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}

.card-header h2 {
  font-size: 18px;
  font-weight: 700;
  color: var(--sg-dark);
}

.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--sg-text);
  margin-bottom: 6px;
}

.form-control {
  width: 100%;
  height: 42px;
  border-radius: var(--sg-radius-sm);
  border: 1px solid var(--sg-border);
  padding: 0 14px;
  font-size: 14px;
  color: var(--sg-text);
  background: var(--sg-white);
  transition: var(--sg-transition);
}

.form-control:focus {
  outline: none;
  border-color: var(--sg-green);
  box-shadow: 0 0 0 3px var(--sg-green-pale);
}

.time-range-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.availability-status {
  margin-top: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: var(--sg-radius-sm);
  font-size: 13px;
  font-weight: 600;
}

.status-badge.success {
  background: var(--sg-green-pale);
  color: var(--sg-green-dark);
}

.status-badge.danger {
  background: #fef2f2;
  color: var(--sg-danger);
}

.payment-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payment-option-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border-radius: var(--sg-radius-sm);
  border: 1px solid var(--sg-border);
  cursor: pointer;
  transition: var(--sg-transition);
}

.payment-option-card:hover {
  background: var(--sg-surface);
  border-color: var(--sg-green-light);
}

.payment-option-card.active {
  background: var(--sg-green-pale);
  border-color: var(--sg-green);
}

.option-info {
  display: flex;
  flex-direction: column;
}

.option-title {
  font-weight: 700;
  font-size: 14px;
  color: var(--sg-dark);
}

.option-desc {
  font-size: 12px;
  color: var(--sg-text-muted);
  margin-top: 4px;
}

.hidden-radio {
  display: none;
}

/* Summary Panel */
.sticky-card {
  position: sticky;
  top: 84px;
}

.summary-card h2 {
  font-size: 18px;
  font-weight: 700;
  color: var(--sg-dark);
  margin-bottom: 16px;
}

.divider {
  height: 1px;
  background: var(--sg-border);
  margin: 16px 0;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 14px;
}

.summary-row .label {
  color: var(--sg-text-muted);
}

.summary-row .val {
  font-weight: 600;
  color: var(--sg-dark);
}

.total-row {
  margin-top: 16px;
  font-size: 16px;
}

.total-row .price {
  font-size: 20px;
  font-weight: 800;
  color: var(--sg-dark);
}

.deposit-row {
  margin-top: 8px;
  font-size: 14px;
}

.deposit-row .required-price {
  font-size: 16px;
  font-weight: 800;
  color: var(--sg-green-dark);
}

.btn-submit {
  width: 100%;
  height: 48px;
  border-radius: var(--sg-radius);
  background: var(--sg-green);
  color: #fff;
  font-weight: 700;
  font-size: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 24px;
  box-shadow: 0 4px 12px rgba(34,197,94,0.3);
  transition: var(--sg-transition);
}

.btn-submit:hover:not(:disabled) {
  background: var(--sg-green-dark);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(34,197,94,0.4);
}

.btn-submit:disabled {
  background: var(--sg-border);
  color: var(--sg-text-muted);
  box-shadow: none;
  cursor: not-allowed;
}

.hold-notice {
  font-size: 12px;
  color: var(--sg-text-muted);
  margin-top: 14px;
  line-height: 1.5;
  text-align: center;
}

.error-msg {
  padding: 10px 14px;
  background: #fef2f2;
  border-radius: var(--sg-radius-sm);
  color: var(--sg-danger);
  font-size: 13px;
  font-weight: 500;
  margin-top: 14px;
  border: 1px solid rgba(239, 68, 68, 0.1);
}

/* Loading state */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 0;
  color: var(--sg-text-muted);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--sg-border);
  border-top-color: var(--sg-green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 16px;
}

.spinner-small {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 900px) {
  .booking-grid { grid-template-columns: 1fr; gap: 16px; }
  .sticky-card { position: static; }
}
</style>
