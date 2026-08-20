<template>
  <Teleport to="body">
    <div v-if="isOpen" class="handover-modal-backdrop" @click.self="onClose">
      <div class="handover-dialog">
        <!-- MODAL HEADER -->
        <div class="handover-header">
          <div class="header-title-wrap">
            <AppIcon name="fileText" :size="18" class="text-green-main" />
            <h3 class="handover-title">Biên bản bàn giao ca trực &amp; Chốt két</h3>
          </div>
          <button type="button" class="handover-close-btn" aria-label="Đóng" @click="onClose">✕</button>
        </div>

        <!-- MODAL BODY -->
        <div class="handover-body">
          <div v-if="loading" class="handover-loading">
            <div class="loading-spinner"></div>
            <span>Đang tổng hợp dữ liệu ca trực...</span>
          </div>

          <div v-else-if="error" class="handover-error-box">
            <span>{{ error }}</span>
            <button type="button" class="retry-btn" @click="loadSummary">Thử lại</button>
          </div>

          <template v-else-if="summaryData">
            <!-- Printable Printable Content Block -->
            <div id="printable-handover-area" class="handover-content">
              <!-- Print ONLY Header -->
              <div class="print-only-header">
                <h2>SPORTGO - PHIẾU BÀN GIAO CA TRỰC</h2>
                <p>{{ summaryData.cluster_name }} · Ngày {{ formatDate(summaryData.date) }}</p>
              </div>

              <!-- General Shift Info Row -->
              <div class="info-section">
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">Nhân viên trực:</span>
                    <span class="info-val font-medium">{{ summaryData.staff_name }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Cụm sân:</span>
                    <span class="info-val">{{ summaryData.cluster_name }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Ca làm việc:</span>
                    <span class="info-val font-medium">{{ summaryData.shift_name }} ({{ summaryData.start_time }} - {{ summaryData.end_time }})</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Thời gian trực thực tế:</span>
                    <span class="info-val text-green-main font-medium">{{ summaryData.worked_duration_label }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Giờ vào ca:</span>
                    <span class="info-val">{{ summaryData.check_in_at || 'Chưa ghi nhận' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Giờ kết ca:</span>
                    <span class="info-val">{{ summaryData.check_out_at }}</span>
                  </div>
                </div>
              </div>

              <!-- Financial Reconciliation Block -->
              <div class="financial-section">
                <div class="section-heading">Đối soát tài chính &amp; Doanh thu trong ca</div>

                <div class="financial-grid">
                  <!-- Cash Collected (Crucial for Shift Handover) -->
                  <div class="finance-card highlight-cash">
                    <div class="card-label">Tiền mặt thực thu trong ca</div>
                    <div class="card-amount text-cash">{{ formatCurrency(summaryData.total_cash_amount) }}</div>
                    <div class="card-hint">Số tiền mặt nhân viên phải nộp lại két / giao ca sau</div>
                  </div>

                  <!-- Bank / QR Transfer -->
                  <div class="finance-card">
                    <div class="card-label">Chuyển khoản QR SePay</div>
                    <div class="card-amount text-transfer">{{ formatCurrency(summaryData.total_transfer_amount) }}</div>
                    <div class="card-hint">Tiền đã tự động vào tài khoản chủ sân</div>
                  </div>

                  <!-- Total Revenue -->
                  <div class="finance-card">
                    <div class="card-label">Tổng doanh thu ca</div>
                    <div class="card-amount font-medium">{{ formatCurrency(summaryData.total_revenue) }}</div>
                    <div class="card-hint">Phục vụ: {{ summaryData.total_bookings }} đơn đặt sân</div>
                  </div>

                  <!-- Outstanding Debt / Unpaid -->
                  <div class="finance-card" :class="{ 'has-debt': summaryData.total_unpaid_amount > 0 }">
                    <div class="card-label">Tiền nợ / Chưa thu tồn đọng</div>
                    <div class="card-amount" :class="summaryData.total_unpaid_amount > 0 ? 'text-debt' : ''">
                      {{ formatCurrency(summaryData.total_unpaid_amount) }}
                    </div>
                    <div class="card-hint">Cần bàn giao ca sau theo dõi thu hộ</div>
                  </div>
                </div>
              </div>

              <!-- Handover Notes Input -->
              <div class="notes-section">
                <label for="handover-notes-field" class="notes-label">
                  <span>Ghi chú bàn giao ca (Tình trạng sân, tiền trong két, sự cố...)</span>
                </label>
                <textarea
                  id="handover-notes-field"
                  v-model="handoverNotes"
                  class="notes-textarea"
                  rows="3"
                  placeholder="Ví dụ: Đã nộp đủ tiền mặt vào két quầy, bóng sân số 2 bị hỏng 1 quả..."
                  maxlength="1000"
                ></textarea>
              </div>

              <!-- Signature Area for Print -->
              <div class="print-signatures">
                <div class="sig-col">
                  <span>Người bàn giao (Nhân viên)</span>
                  <div class="sig-line"></div>
                  <span class="sig-name">{{ summaryData.staff_name }}</span>
                </div>
                <div class="sig-col">
                  <span>Người nhận bàn giao</span>
                  <div class="sig-line"></div>
                  <span class="sig-name">...........................</span>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- MODAL FOOTER -->
        <div class="handover-footer">
          <div class="footer-left">
            <button
              v-if="summaryData"
              type="button"
              class="handover-btn btn-print"
              @click="printHandoverReport"
            >
              <AppIcon name="download" :size="14" />
              <span>In phiếu bàn giao</span>
            </button>
          </div>

          <div class="footer-right">
            <button
              type="button"
              class="handover-btn btn-cancel"
              :disabled="submitting"
              @click="onClose"
            >
              Hủy / Tiếp tục ca
            </button>

            <button
              type="button"
              class="handover-btn btn-confirm"
              :disabled="loading || submitting"
              @click="handleConfirmCheckout"
            >
              <span>{{ submitting ? 'Đang kết ca...' : 'Xác nhận kết ca & Bàn giao' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';

export default {
  name: 'ShiftHandoverModal',
  components: { AppIcon },
  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
    scheduleId: {
      type: [Number, String],
      default: null,
    },
  },
  emits: ['close', 'checked-out'],
  data() {
    return {
      loading: false,
      submitting: false,
      error: '',
      summaryData: null,
      handoverNotes: '',
    };
  },
  watch: {
    isOpen(val) {
      if (val && this.scheduleId) {
        this.loadSummary();
      } else {
        this.summaryData = null;
        this.handoverNotes = '';
        this.error = '';
      }
    },
  },
  methods: {
    async loadSummary() {
      if (!this.scheduleId) return;
      this.loading = true;
      this.error = '';
      try {
        const response = await ownerStaffShiftService.handoverSummary(this.scheduleId);
        this.summaryData = response.data || null;
        if (this.summaryData?.notes) {
          this.handoverNotes = this.summaryData.notes;
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải biên bản ca trực.';
      } finally {
        this.loading = false;
      }
    },
    async handleConfirmCheckout() {
      if (!this.scheduleId || this.submitting) return;
      this.submitting = true;
      this.error = '';
      try {
        await ownerStaffShiftService.checkOut(this.scheduleId, {
          notes: this.handoverNotes.trim() || undefined,
        });
        this.$emit('checked-out');
        this.onClose();
      } catch (err) {
        this.error = err.message || 'Không thể thực hiện kết ca.';
      } finally {
        this.submitting = false;
      }
    },
    onClose() {
      this.$emit('close');
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
    formatCurrency(val) {
      if (val === undefined || val === null) return '0 đ';
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
      }).format(val);
    },
    printHandoverReport() {
      window.print();
    },
  },
};
</script>

<style scoped>
.handover-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(17, 24, 39, 0.6);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.handover-dialog {
  background: #ffffff;
  border-radius: 8px;
  max-width: 680px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  color: #111827;
  font-weight: 400;
}

/* HEADER */
.handover-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.header-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.text-green-main {
  color: #087642;
}

.handover-title {
  font-size: 15.5px;
  font-weight: 500;
  color: #111827;
  margin: 0;
}

.handover-close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #6b7280;
  cursor: pointer;
  padding: 4px;
}

.handover-close-btn:hover {
  color: #111827;
}

/* BODY */
.handover-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.handover-loading {
  padding: 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: #4b5563;
  font-size: 13px;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid #e5e7eb;
  border-top-color: #087642;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.handover-error-box {
  padding: 14px;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  border-radius: 6px;
  color: #b91c1c;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.retry-btn {
  background: #ffffff;
  border: 1px solid #b91c1c;
  color: #b91c1c;
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

/* CONTENT SECTIONS */
.info-section {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 14px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 11.5px;
  color: #6b7280;
}

.info-val {
  font-size: 13px;
  color: #111827;
}

/* FINANCIAL SECTION */
.financial-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-heading {
  font-size: 13px;
  font-weight: 500;
  color: #111827;
}

.financial-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.finance-card {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 12px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.finance-card.highlight-cash {
  border-color: #86efac;
  border-left: 3px solid #087642;
}

.finance-card.has-debt {
  border-color: #fde68a;
  border-left: 3px solid #d97706;
}

.card-label {
  font-size: 11.5px;
  color: #4b5563;
}

.card-amount {
  font-size: 17px;
  font-weight: 500;
  color: #111827;
}

.card-amount.text-cash {
  color: #087642;
}

.card-amount.text-transfer {
  color: #2563eb;
}

.card-amount.text-debt {
  color: #d97706;
}

.card-hint {
  font-size: 10.5px;
  color: #6b7280;
  margin-top: 2px;
}

/* NOTES SECTION */
.notes-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.notes-label {
  font-size: 12px;
  color: #374151;
}

.notes-textarea {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 13px;
  color: #111827;
  outline: none;
  font-family: inherit;
  resize: vertical;
}

.notes-textarea:focus {
  border-color: #087642;
}

/* SIGNATURES (Print Only by default) */
.print-only-header,
.print-signatures {
  display: none;
}

/* FOOTER */
.handover-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 20px;
  border-top: 1px solid #e5e7eb;
}

.footer-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.handover-btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid transparent;
}

.btn-print {
  background: #ffffff;
  border-color: #d1d5db;
  color: #374151;
}

.btn-print:hover {
  background: #f9fafb;
}

.btn-cancel {
  background: #ffffff;
  border-color: #d1d5db;
  color: #374151;
}

.btn-cancel:hover {
  background: #f9fafb;
}

.btn-confirm {
  background: #087642;
  color: #ffffff;
}

.btn-confirm:hover {
  background: #065e34;
}

.btn-confirm:disabled,
.btn-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* PRINT STYLING */
@media print {
  body * {
    visibility: hidden;
  }
  #printable-handover-area,
  #printable-handover-area * {
    visibility: visible;
  }
  #printable-handover-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    padding: 20px;
  }
  .print-only-header {
    display: block;
    text-align: center;
    margin-bottom: 20px;
  }
  .print-only-header h2 {
    font-size: 18px;
    margin: 0 0 4px 0;
  }
  .print-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 40px;
    padding: 0 20px;
  }
  .sig-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 50px;
    font-size: 12px;
  }
  .sig-name {
    font-size: 13px;
    font-weight: 500;
  }
  .notes-textarea {
    border: none;
    padding: 0;
  }
}

@media (max-width: 640px) {
  .info-grid,
  .financial-grid {
    grid-template-columns: 1fr;
  }
  .handover-footer {
    flex-direction: column-reverse;
  }
  .footer-right,
  .footer-left {
    width: 100%;
    justify-content: stretch;
  }
  .handover-btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
