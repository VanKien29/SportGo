<template>
  <Teleport to="body">
    <div v-if="isOpen" class="scanner-backdrop" @click.self="onClose">
      <div class="scanner-dialog">
        <!-- HEADER -->
        <header class="scanner-head">
          <div class="header-titles">
            <h3>Nhận diện &amp; Quét vé đặt sân</h3>
            <span class="scanner-head-subtitle">Hỗ trợ súng quét mã vạch 2D/QR USB, tra cứu theo mã đơn hoặc SĐT khách</span>
          </div>
          <button type="button" class="scanner-close-btn" aria-label="Đóng" @click="onClose">✕</button>
        </header>

        <!-- BODY -->
        <div class="scanner-body">
          <!-- Scanner Input Bar -->
          <div class="scanner-top-bar">
            <div class="scanner-input-wrap">
              <AppIcon name="search" :size="15" class="scanner-search-icon" />
              <input
                ref="barcodeInput"
                v-model.trim="scannedCode"
                type="text"
                class="scanner-text-input"
                placeholder="Bắn súng quét QR vé hoặc nhập mã booking (#BK-1001), SĐT..."
                autofocus
                @keydown.enter.prevent="handleSearchBooking"
              />
              <button
                type="button"
                class="scanner-search-btn"
                :disabled="!scannedCode || searching"
                @click="handleSearchBooking"
              >
                {{ searching ? 'Đang tìm...' : 'Nhận diện vé' }}
              </button>
            </div>
          </div>

          <!-- Search Error Message -->
          <div v-if="error" class="scanner-msg error">
            {{ error }}
          </div>

          <!-- Empty State Hint -->
          <div v-if="!matchedBooking && !error" class="scanner-hint-box">
            <div class="hint-text">
              <strong>Sẵn sàng quét vé:</strong> Đưa súng bắn mã vạch vào mã QR trên điện thoại khách hàng hoặc nhập mã đơn / SĐT để tra cứu nhanh.
            </div>
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
      scannedCode: '',
      searching: false,
      actionLoading: false,
      error: '',
      matchedBooking: null,
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
      }
    },
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
  left: 12px;
  color: #64748b;
}

.scanner-text-input {
  width: 100%;
  padding: 10px 110px 10px 36px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13.5px;
  color: #0f172a;
  outline: none;
  font-family: inherit;
  transition: border-color 0.15s ease;
}

.scanner-text-input:focus {
  border-color: #087642;
  box-shadow: 0 0 0 3px rgba(8, 118, 66, 0.1);
}

.scanner-search-btn {
  position: absolute;
  right: 6px;
  background: #087642;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.12s ease;
}

.scanner-search-btn:hover:not(:disabled) {
  background: #065f35;
}

.scanner-search-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.scanner-hint-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px 16px;
  font-size: 13px;
  color: #475569;
  line-height: 1.5;
}

.scanner-hint-box strong {
  color: #0f172a;
  font-weight: 600;
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
</style>
