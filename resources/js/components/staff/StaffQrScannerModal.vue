<template>
  <Teleport to="body">
    <div v-if="isOpen" class="scanner-backdrop" @click.self="onClose">
      <div class="scanner-dialog">
        <!-- HEADER -->
        <header class="scanner-head">
          <div class="header-titles">
            <h3>Nhận diện &amp; Quét vé đặt sân</h3>
            <span class="scanner-head-subtitle">Quét QR bằng camera hoặc tải ảnh QR</span>
          </div>
          <button type="button" class="scanner-close-btn" aria-label="Đóng" @click="onClose">✕</button>
        </header>

        <!-- BODY -->
        <div class="scanner-body">
          <div class="scanner-method-tabs" role="tablist" aria-label="Cách quét vé">
            <button
              type="button"
              class="scanner-method-tab"
              :class="{ 'is-active': scanMethod === 'camera' }"
              role="tab"
              :aria-selected="scanMethod === 'camera'"
              @click="selectScanMethod('camera')"
            >
              <AppIcon name="camera" :size="15" />
              Camera QR
            </button>
            <button
              type="button"
              class="scanner-method-tab"
              :class="{ 'is-active': scanMethod === 'upload' }"
              role="tab"
              :aria-selected="scanMethod === 'upload'"
              @click="selectScanMethod('upload')"
            >
              <AppIcon name="image" :size="15" />
              Tải ảnh QR
            </button>
          </div>

          <div v-if="scanMethod === 'camera'" class="camera-scanner">
            <div class="camera-preview">
              <video ref="qrVideo" class="qr-video" muted playsinline></video>
              <div class="qr-scan-frame" aria-hidden="true"></div>
              <div v-if="!cameraActive && !cameraError" class="camera-starting">Đang mở camera...</div>
            </div>
            <div class="camera-status-row">
              <span v-if="cameraActive">Đưa mã QR vé vào khung quét</span>
              <span v-else-if="cameraError" class="camera-error">{{ cameraError }}</span>
              <span v-else>Camera sẽ tự nhận diện mã QR</span>
              <button v-if="cameraError" type="button" class="camera-retry-btn" @click="startCamera">Thử lại</button>
            </div>
          </div>

          <div v-else-if="scanMethod === 'upload'" class="upload-qr-panel">
            <label class="upload-qr-picker" for="staff-qr-image-input">
              <AppIcon name="image" :size="25" />
              <strong>{{ uploadSearching ? 'Đang kiểm tra ảnh QR...' : 'Chọn ảnh có mã QR vé' }}</strong>
              <span>JPG, PNG hoặc WEBP · tối đa 5MB · ảnh rõ, không bị cắt góc</span>
              <input
                id="staff-qr-image-input"
                ref="qrImageInput"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                @change="handleQrImageUpload"
              />
            </label>
          </div>

          <!-- Search Error Message -->
          <div v-if="error" class="scanner-msg error">
            {{ error }}
          </div>

          <!-- Empty State Hint -->
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
                <span class="m-val">{{ customerName(matchedBooking) }}</span>
              </div>
              <div class="matched-field">
                <span class="m-label">Số điện thoại:</span>
                <span class="m-val">{{ customerPhone(matchedBooking) }}</span>
              </div>
              <div class="matched-field">
                <span class="m-label">Sân &amp; Khung giờ:</span>
                <span class="m-val text-green-main">{{ matchedBooking.venue_court?.name || 'Sân thể thao' }} ({{ formatTime(matchedBooking.start_time) }} - {{ formatTime(matchedBooking.end_time) }})</span>
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
                <strong class="p-total-val">{{ formatCurrency(matchedBooking.total_price) }}</strong>
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
                v-if="['confirmed', 'pending_approval'].includes(matchedBooking.status)"
                type="button"
                class="ticket-btn btn-checkin"
                :disabled="actionLoading"
                @click="handleCheckInBooking"
              >
                <AppIcon name="check" :size="15" />
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
import QrScanner from 'qr-scanner';
import { playSuccessChime, playWarningChime } from '../../utils/audioChime.js';
import { ownerBookingService } from '../../services/ownerBookings.js';

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
      scanMethod: 'camera',
      scannedCode: '',
      searching: false,
      actionLoading: false,
      uploadSearching: false,
      cameraActive: false,
      cameraError: '',
      qrScanner: null,
      error: '',
      matchedBooking: null,
    };
  },
  watch: {
    isOpen(val) {
      if (val) {
        this.scanMethod = 'camera';
        this.scannedCode = '';
        this.matchedBooking = null;
        this.error = '';
        this.cameraError = '';
        this.uploadSearching = false;
        this.$nextTick(() => {
          if (this.scanMethod === 'camera') {
            this.startCamera();
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
    async selectScanMethod(method) {
      this.stopCamera();
      this.scanMethod = method;
      this.cameraError = '';
      this.error = '';

      await this.$nextTick();
      if (method === 'camera') {
        this.startCamera();
      }
    },
    async startCamera() {
      if (!this.isOpen || this.scanMethod !== 'camera' || this.qrScanner) return;

      this.cameraError = '';
      await this.$nextTick();
      if (!this.$refs.qrVideo) return;

      try {
        if (!(await QrScanner.hasCamera())) {
          throw new Error('Không tìm thấy camera trên thiết bị này.');
        }

        this.qrScanner = new QrScanner(
          this.$refs.qrVideo,
          (result) => this.handleDecodedQr(typeof result === 'string' ? result : result?.data),
          {
            preferredCamera: 'environment',
            maxScansPerSecond: 10,
            highlightScanRegion: true,
            highlightCodeOutline: true,
            returnDetailedScanResult: true,
          },
        );
        await this.qrScanner.start();
        this.cameraActive = true;
      } catch (err) {
        this.stopCamera();
        this.cameraError = err?.name === 'NotAllowedError'
          ? 'Chưa được cấp quyền camera. Hãy cho phép camera cho website SportGo.'
          : (err?.message || 'Không thể mở camera để quét QR.');
      }
    },
    stopCamera() {
      if (this.qrScanner) {
        this.qrScanner.stop();
        this.qrScanner.destroy();
        this.qrScanner = null;
      }
      this.cameraActive = false;
    },
    handleDecodedQr(value) {
      if (!value || this.searching || this.matchedBooking) return;
      const code = this.extractBookingCode(value);
      if (!code) {
        this.cameraError = 'Mã QR không chứa mã vé SportGo hợp lệ.';
        this.stopCamera();
        playWarningChime();
        return;
      }
      this.scannedCode = code;
      this.stopCamera();
      this.handleSearchBooking();
    },
    async handleQrImageUpload(event) {
      const file = event?.target?.files?.[0];
      if (!file) return;

      this.uploadSearching = true;
      this.error = '';
      this.matchedBooking = null;
      try {
        await this.validateQrImageFile(file);
        const result = await QrScanner.scanImage(file, { returnDetailedScanResult: true });
        const value = typeof result === 'string' ? result : result?.data;
        if (!value) throw new Error('Không tìm thấy mã QR trong ảnh.');
        const code = this.extractBookingCode(value);
        if (!code) throw new Error('Ảnh không chứa mã vé SportGo hợp lệ.');
        this.scannedCode = code;
        await this.handleSearchBooking();
      } catch (err) {
        this.error = /No QR code found/i.test(err?.message || '')
          ? 'Không tìm thấy mã QR trong ảnh. Vui lòng chọn ảnh rõ hơn.'
          : (err?.message || 'Không thể đọc ảnh QR.');
        playWarningChime();
      } finally {
        this.uploadSearching = false;
        if (event?.target) event.target.value = '';
      }
    },
    validateQrImageFile(file) {
      const allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
      const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
      const fileName = String(file?.name || '').toLowerCase();
      const extension = fileName.includes('.') ? fileName.split('.').pop() : '';

      if (!file || !file.size) {
        throw new Error('Ảnh QR không hợp lệ hoặc đang bị trống.');
      }
      if (file.size > 5 * 1024 * 1024) {
        throw new Error('Ảnh QR không được vượt quá 5MB.');
      }
      if (!allowedMimeTypes.includes(file.type) || !allowedExtensions.includes(extension)) {
        throw new Error('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');
      }

      return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);
        const cleanup = () => {
          URL.revokeObjectURL(objectUrl);
          image.onload = null;
          image.onerror = null;
        };

        image.onload = () => {
          const { width, height } = image;
          cleanup();
          if (width < 120 || height < 120) {
            reject(new Error('Ảnh QR quá nhỏ. Vui lòng chọn ảnh rõ, tối thiểu 120×120 pixel.'));
          } else if (width > 10000 || height > 10000) {
            reject(new Error('Kích thước ảnh QR không hợp lệ.'));
          } else {
            resolve();
          }
        };
        image.onerror = () => {
          cleanup();
          reject(new Error('Không thể đọc tệp ảnh QR.'));
        };
        image.src = objectUrl;
      });
    },
    extractBookingCode(value) {
      const raw = String(value || '').trim();
      const match = raw.match(/(?:^|[^A-Z0-9])((?:DEMO-)?BK[A-Z0-9-]{6,})(?![A-Z0-9])/i);
      return match?.[1]?.toUpperCase() || null;
    },
    async handleSearchBooking() {
      const code = this.extractBookingCode(this.scannedCode);
      if (!code) {
        this.error = 'Mã QR không chứa mã vé SportGo hợp lệ.';
        playWarningChime();
        return;
      }
      this.scannedCode = code;
      this.searching = true;
      this.error = '';
      this.matchedBooking = null;
      try {
        const response = await ownerBookingService.list({
          venue_cluster_id: this.clusterId || localStorage.getItem('selected_cluster'),
          search: code,
        });
        const items = response.data || [];
        const found = items.find((b) => b.booking_code?.toLowerCase() === code.toLowerCase());
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
    onClose() {
      this.$emit('close');
    },
    customerName(b) {
      return b?.customer?.full_name || b?.customer?.username || b?.walk_in_name || 'Khách vãng lai';
    },
    customerPhone(b) {
      return b?.customer?.phone || b?.walk_in_phone || '—';
    },
    outstandingAmount(b) {
      const paid = (b?.payments || [])
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + Number(p.amount || 0), 0);
      return Math.max(Number(b?.total_price || 0) - paid, 0);
    },
    statusLabel(status) {
      return {
        pending_payment: 'Chờ thanh toán',
        confirmed: 'Đã xác nhận',
        checked_in: 'Đang chơi',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
        rejected: 'Từ chối',
      }[status] || status;
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    },
    formatTime(timeStr) {
      if (!timeStr) return '';
      return String(timeStr).slice(0, 5);
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      try {
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
      } catch {
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
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.scanner-dialog {
  width: 100%;
  max-width: 600px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  animation: pop-in 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes pop-in {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

.scanner-head {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.header-titles h3 {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.scanner-head-subtitle {
  font-size: 12.5px;
  color: #475569;
  font-weight: 400;
  margin-top: 2px;
  display: block;
}

.scanner-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.scanner-close-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.scanner-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.scanner-method-tabs {
  display: flex;
  gap: 6px;
  border-bottom: 1px solid #e2e8f0;
}

.scanner-method-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 11px;
  margin-bottom: -1px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #64748b;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}

.scanner-method-tab:hover,
.scanner-method-tab.is-active {
  color: #087642;
}

.scanner-method-tab.is-active {
  border-bottom-color: #087642;
}

.camera-scanner {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.camera-preview {
  position: relative;
  min-height: 270px;
  overflow: hidden;
  border-radius: 10px;
  background: #0f172a;
}

.qr-video {
  display: block;
  width: 100%;
  min-height: 270px;
  max-height: 360px;
  object-fit: cover;
}

.qr-scan-frame {
  position: absolute;
  inset: 18% 20%;
  border: 2px solid rgba(255, 255, 255, 0.9);
  border-radius: 14px;
  box-shadow: 0 0 0 999px rgba(15, 23, 42, 0.2);
  pointer-events: none;
}

.camera-starting {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: #ffffff;
  font-size: 13px;
}

.camera-status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 24px;
  color: #475569;
  font-size: 12.5px;
}

.camera-error {
  color: #b91c1c;
}

.camera-retry-btn {
  flex-shrink: 0;
  padding: 5px 9px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #087642;
  font-size: 12px;
  cursor: pointer;
}

.upload-qr-panel {
  display: flex;
}

.upload-qr-picker {
  width: 100%;
  min-height: 190px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1.5px dashed #94a3b8;
  border-radius: 10px;
  background: #f8fafc;
  color: #475569;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.upload-qr-picker:hover {
  border-color: #087642;
  background: #f0fdf4;
}

.upload-qr-picker strong {
  color: #0f172a;
  font-size: 14px;
}

.upload-qr-picker span {
  font-size: 12px;
}

.upload-qr-picker input {
  display: none;
}

.scanner-msg.error {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  color: #b91c1c;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
}

/* MATCHED BOOKING CARD */
.matched-booking-card {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  background: #ffffff;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.matched-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
}

.booking-tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  color: #087642;
  background: #dcfce7;
  padding: 2px 6px;
  border-radius: 4px;
  margin-bottom: 4px;
}

.matched-code {
  font-size: 17px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
  font-family: ui-monospace, SFMono-Regular, monospace;
}

.matched-status {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}
.matched-status.confirmed { background: #dbeafe; color: #1d4ed8; }
.matched-status.checked_in { background: #dcfce7; color: #15803d; }
.matched-status.pending_payment { background: #fef3c7; color: #b45309; }

.matched-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 16px;
}

.matched-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.m-label {
  font-size: 12px;
  color: #475569;
}

.m-val {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
}

.text-green-main {
  color: #087642;
}

.matched-payment-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.p-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: #475569;
}

.p-total-val {
  color: #0f172a;
  font-weight: 600;
  font-size: 14.5px;
}

.text-due {
  color: #dc2626;
  font-weight: 600;
}

.text-paid {
  color: #087642;
  font-weight: 600;
}

.matched-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-top: 4px;
}

.ticket-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.12s ease;
}

.btn-checkin {
  background: #087642;
  color: #ffffff;
}

.btn-checkin:hover:not(:disabled) {
  background: #065f35;
}

.btn-collect {
  background: #d97706;
  color: #ffffff;
}

.btn-collect:hover {
  background: #b45309;
}

.already-in-msg {
  color: #087642;
  font-size: 13px;
  font-weight: 500;
}

@media (max-width: 560px) {
  .scanner-method-tabs {
    gap: 2px;
  }

  .scanner-method-tab {
    padding: 8px 7px;
    font-size: 11.5px;
  }

  .camera-preview,
  .qr-video {
    min-height: 230px;
  }

  .matched-grid {
    grid-template-columns: 1fr;
  }

  .matched-actions {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
