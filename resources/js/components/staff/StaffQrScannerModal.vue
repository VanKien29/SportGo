<template>
  <Teleport to="body">
    <div v-if="isOpen" class="scanner-backdrop" @click.self="onClose">
      <div class="scanner-dialog">
        <!-- HEADER -->
        <header class="scanner-head">
          <div class="scanner-head-title">
            <AppIcon name="qrCode" :size="18" class="text-green-main" />
            <h3>Quét mã QR / Nhận diện vé đặt sân</h3>
          </div>
          <button type="button" class="scanner-close-btn" aria-label="Đóng" @click="onClose">✕</button>
        </header>

        <!-- BODY -->
        <div class="scanner-body">
          <!-- Scanner Input & Camera Switch -->
          <div class="scanner-top-bar">
            <div class="scanner-input-wrap">
              <AppIcon name="search" :size="15" class="scanner-search-icon" />
              <input
                ref="barcodeInput"
                v-model.trim="scannedCode"
                type="text"
                class="scanner-text-input"
                placeholder="Quét mã QR vé hoặc nhập mã booking (BK-2026...)"
                autofocus
                @keydown.enter.prevent="handleSearchBooking"
              />
              <button
                type="button"
                class="scanner-search-btn"
                :disabled="!scannedCode || searching"
                @click="handleSearchBooking"
              >
                {{ searching ? 'Đang tìm...' : 'Tìm vé' }}
              </button>
            </div>

            <button
              type="button"
              class="camera-toggle-btn"
              :class="{ active: isCameraActive }"
              @click="toggleCamera"
            >
              <AppIcon name="video" :size="14" />
              <span>{{ isCameraActive ? 'Tắt Camera' : 'Bật Camera quét' }}</span>
            </button>
          </div>

          <!-- Video Camera Stream (if active) -->
          <div v-if="isCameraActive" class="camera-stream-box">
            <video ref="videoElement" class="camera-video" playsinline autoplay></video>
            <div class="camera-scan-overlay">
              <div class="scan-target-box"></div>
              <span>Đưa mã QR vé vào khung quét</span>
            </div>
          </div>

          <!-- Search Error Message -->
          <div v-if="error" class="scanner-msg error">
            {{ error }}
          </div>

          <!-- Booking Result Found -->
          <div v-if="matchedBooking" class="matched-booking-card">
            <div class="matched-head">
              <div>
                <span class="booking-tag">VÉ HỢP LỆ</span>
                <h4 class="matched-code">#{{ matchedBooking.booking_code }}</h4>
              </div>
              <span class="matched-status" :class="matchedBooking.status">
                {{ statusLabel(matchedBooking.status) }}
              </span>
            </div>

            <div class="matched-grid">
              <div class="matched-field">
                <span class="m-label">Khách hàng:</span>
                <strong class="m-val">{{ customerName(matchedBooking) }}</strong>
              </div>
              <div class="matched-field">
                <span class="m-label">Số điện thoại:</span>
                <strong class="m-val">{{ customerPhone(matchedBooking) }}</strong>
              </div>
              <div class="matched-field">
                <span class="m-label">Sân &amp; Khung giờ:</span>
                <span class="m-val font-medium text-green-main">{{ matchedBooking.venue_court?.name || 'Sân thể thao' }} ({{ formatTime(matchedBooking.start_time) }} - {{ formatTime(matchedBooking.end_time) }})</span>
              </div>
              <div class="matched-field">
                <span class="m-label">Ngày đặt:</span>
                <span class="m-val">{{ formatDate(matchedBooking.booking_date) }}</span>
              </div>
            </div>

            <!-- Payment status block -->
            <div class="matched-payment-box">
              <div class="p-row">
                <span>Tổng tiền sân:</span>
                <strong>{{ formatCurrency(matchedBooking.total_price) }}</strong>
              </div>
              <div v-if="outstandingAmount(matchedBooking) > 0" class="p-row is-due">
                <span>Còn phải thu:</span>
                <strong class="text-due">{{ formatCurrency(outstandingAmount(matchedBooking)) }}</strong>
              </div>
              <div v-else class="p-row is-clean">
                <span>Thanh toán:</span>
                <strong class="text-paid">Đã thanh toán đủ 100%</strong>
              </div>
            </div>

            <!-- Action Buttons for this Ticket -->
            <div class="matched-actions">
              <button
                v-if="matchedBooking.status === 'confirmed'"
                type="button"
                class="ticket-btn btn-checkin"
                :disabled="actionLoading"
                @click="handleCheckInBooking"
              >
                <AppIcon name="clock" :size="15" />
                <span>{{ actionLoading ? 'Đang check-in...' : 'Xác nhận Check-in vào sân' }}</span>
              </button>

              <button
                v-if="outstandingAmount(matchedBooking) > 0"
                type="button"
                class="ticket-btn btn-collect"
                @click="handleCollectBooking"
              >
                <AppIcon name="banknote" :size="15" />
                <span>Thu tiền ngay</span>
              </button>

              <span v-if="matchedBooking.status === 'checked_in'" class="already-in-msg">
                ✓ Khách đang trong sân thi đấu
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { ownerBookingService } from '../../services/ownerBookings.js';
import { playSuccessChime, playWarningChime } from '../../utils/audioChime.js';

export default {
  name: 'StaffQrScannerModal',
  components: { AppIcon },
  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
    clusterId: {
      type: [String, Number],
      default: null,
    },
  },
  emits: ['close', 'booking-selected', 'collect-requested', 'checked-in'],
  data() {
    return {
      scannedCode: '',
      searching: false,
      actionLoading: false,
      error: '',
      matchedBooking: null,
      isCameraActive: false,
      mediaStream: null,
    };
  },
  watch: {
    isOpen(val) {
      if (val) {
        this.scannedCode = '';
        this.matchedBooking = null;
        this.error = '';
        this.$nextTick(() => {
          if (this.$refs.barcodeInput) {
            this.$refs.barcodeInput.focus();
          }
        });
      } else {
        this.stopCamera();
      }
    },
  },
  beforeUnmount() {
    this.stopCamera();
  },
  methods: {
    async handleSearchBooking() {
      const code = this.scannedCode.trim();
      if (!code) return;
      this.searching = true;
      this.error = '';
      this.matchedBooking = null;
      try {
        const response = await ownerBookingService.list({
          venue_cluster_id: this.clusterId || localStorage.getItem('selected_cluster'),
          search: code,
        });
        const items = response.data || [];
        const found = items.find((b) => b.booking_code?.toLowerCase() === code.toLowerCase()) || items[0];
        if (found) {
          this.matchedBooking = found;
          playSuccessChime();
        } else {
          this.error = `Không tìm thấy thông tin vé đặt sân khớp với mã "${code}".`;
          playWarningChime();
        }
      } catch (err) {
        this.error = err.message || 'Không thể tìm kiếm đơn đặt sân.';
        playWarningChime();
      } finally {
        this.searching = false;
      }
    },
    async handleCheckInBooking() {
      if (!this.matchedBooking) return;
      this.actionLoading = true;
      this.error = '';
      try {
        await ownerBookingService.updateStatus(this.matchedBooking.id, { action: 'check_in' });
        this.matchedBooking.status = 'checked_in';
        playSuccessChime();
        this.$emit('checked-in', this.matchedBooking);
      } catch (err) {
        this.error = err.message || 'Không thể thực hiện check-in.';
      } finally {
        this.actionLoading = false;
      }
    },
    handleCollectBooking() {
      if (!this.matchedBooking) return;
      this.$emit('collect-requested', this.matchedBooking);
      this.onClose();
    },
    async toggleCamera() {
      if (this.isCameraActive) {
        this.stopCamera();
      } else {
        await this.startCamera();
      }
    },
    async startCamera() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        this.error = 'Trình duyệt không hỗ trợ quét camera trực tiếp.';
        return;
      }
      try {
        this.mediaStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' },
        });
        this.isCameraActive = true;
        this.$nextTick(() => {
          if (this.$refs.videoElement) {
            this.$refs.videoElement.srcObject = this.mediaStream;
          }
        });
      } catch (e) {
        this.error = 'Không thể truy cập camera. Vui lòng cấp quyền hoặc dùng ô nhập mã.';
      }
    },
    stopCamera() {
      if (this.mediaStream) {
        this.mediaStream.getTracks().forEach((track) => track.stop());
        this.mediaStream = null;
      }
      this.isCameraActive = false;
    },
    onClose() {
      this.stopCamera();
      this.$emit('close');
    },
    customerName(booking) {
      if (!booking) return 'Khách hàng';
      return booking.customer?.full_name || booking.customer?.name || booking.walk_in_name || 'Khách đặt sân';
    },
    customerPhone(booking) {
      if (!booking) return '-';
      return booking.customer?.phone || booking.walk_in_phone || '-';
    },
    statusLabel(status) {
      return (
        {
          pending_approval: 'Chờ duyệt',
          pending_payment: 'Chờ thanh toán',
          confirmed: 'Đã xác nhận',
          checked_in: 'Đang chơi',
          completed: 'Đã hoàn thành',
          cancelled: 'Đã hủy',
          rejected: 'Bị từ chối',
        }[status] || 'Đã xác nhận'
      );
    },
    outstandingAmount(booking) {
      if (!booking) return 0;
      const total = Number(booking.final_amount || booking.total_price) || 0;
      const paid = (booking.payments || [])
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
      return Math.max(0, total - paid);
    },
    formatCurrency(val) {
      if (val === undefined || val === null) return '0 đ';
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
      }).format(val);
    },
    formatTime(timeStr) {
      return String(timeStr || '').slice(0, 5);
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      try {
        const d = new Date(`${dateStr}T00:00:00`);
        return d.toLocaleDateString('vi-VN');
      } catch (e) {
        return dateStr;
      }
    },
  },
};
</script>

<style scoped>
.scanner-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(17, 24, 39, 0.6);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.scanner-dialog {
  background: #ffffff;
  border-radius: 8px;
  max-width: 580px;
  width: 100%;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  color: #111827;
  font-weight: 400;
}

/* HEADER */
.scanner-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.scanner-head-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.scanner-head-title h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
}

.text-green-main {
  color: #087642;
}

.scanner-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #6b7280;
  cursor: pointer;
  padding: 4px;
}

/* BODY */
.scanner-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow-y: auto;
}

.scanner-top-bar {
  display: flex;
  align-items: center;
  gap: 10px;
}

.scanner-input-wrap {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
}

.scanner-search-icon {
  position: absolute;
  left: 10px;
  color: #9ca3af;
}

.scanner-text-input {
  width: 100%;
  padding: 8px 80px 8px 32px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
  outline: none;
}

.scanner-text-input:focus {
  border-color: #087642;
}

.scanner-search-btn {
  position: absolute;
  right: 4px;
  background: #087642;
  color: #ffffff;
  border: none;
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 12px;
  cursor: pointer;
}

.camera-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 12.5px;
  color: #374151;
  cursor: pointer;
  white-space: nowrap;
}

.camera-toggle-btn.active {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #b91c1c;
}

/* CAMERA STREAM */
.camera-stream-box {
  position: relative;
  width: 100%;
  height: 220px;
  background: #111827;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.camera-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.camera-scan-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #ffffff;
  font-size: 11.5px;
  background: rgba(0, 0, 0, 0.2);
}

.scan-target-box {
  width: 130px;
  height: 130px;
  border: 2px dashed #087642;
  border-radius: 8px;
}

.scanner-msg {
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 12.5px;
}

.scanner-msg.error {
  background: #fee2e2;
  color: #b91c1c;
}

/* MATCHED BOOKING CARD */
.matched-booking-card {
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  padding: 16px;
  background: #f0fdf4;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.matched-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 8px;
  border-bottom: 1px solid #dcfce7;
}

.booking-tag {
  font-size: 10px;
  font-weight: 500;
  color: #087642;
  letter-spacing: 0.5px;
}

.matched-code {
  margin: 2px 0 0 0;
  font-size: 15px;
  font-weight: 500;
  color: #111827;
}

.matched-status {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 4px;
  background: #ffffff;
  border: 1px solid #d1d5db;
}

.matched-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 12px;
}

.matched-field {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.m-label {
  font-size: 11px;
  color: #6b7280;
}

.m-val {
  font-size: 12.5px;
  color: #111827;
}

.matched-payment-box {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12.5px;
}

.p-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.text-due {
  color: #d97706;
}

.text-paid {
  color: #087642;
}

.matched-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 4px;
}

.ticket-btn {
  padding: 8px 14px;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
}

.btn-checkin {
  background: #087642;
  color: #ffffff;
  flex: 1;
  justify-content: center;
}

.btn-collect {
  background: #ffffff;
  border: 1px solid #087642;
  color: #087642;
}

.already-in-msg {
  font-size: 12.5px;
  color: #087642;
  font-weight: 500;
}
</style>
