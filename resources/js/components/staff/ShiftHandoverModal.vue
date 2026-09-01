<template>
  <Teleport to="body">
    <div v-if="isOpen" class="handover-modal-backdrop" @click.self="onClose">
      <div class="handover-dialog">
        <!-- MODAL HEADER -->
        <header class="handover-head">
          <div class="header-titles">
            <h3 class="handover-title">
              {{ isAlreadyCheckedOut ? 'Báo cáo ca trực & Phiếu bàn giao' : 'Biên bản bàn giao ca trực & Chốt két' }}
            </h3>
            <span class="handover-subtitle">
              {{ isAlreadyCheckedOut ? 'Xem lại đối soát doanh thu, tiền mặt và biên nhận ca trực' : 'Đối soát doanh thu, tiền mặt két & biên nhận ca trực' }}
            </span>
          </div>
          <button type="button" class="handover-close-btn" aria-label="Đóng" @click="onClose">✕</button>
        </header>

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
            <!-- Printable Content Block -->
            <div id="printable-handover-area" class="handover-content">
              <!-- Print ONLY Header -->
              <div class="print-only-header">
                <h2>SPORTGO - PHIẾU BÀN GIAO CA TRỰC</h2>
                <p>{{ summaryData.cluster_name }} · Ngày {{ formatDate(summaryData.date) }}</p>
              </div>

              <!-- 1. Shift Info Banner (Soft tinted, borderless & sleek) -->
              <div class="info-section">
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">Nhân viên trực:</span>
                    <span class="info-val font-semibold">{{ summaryData.staff_name }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Cụm sân:</span>
                    <span class="info-val font-semibold">{{ summaryData.cluster_name }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Ca làm việc:</span>
                    <span class="info-val">{{ summaryData.shift_name }} ({{ summaryData.start_time }} - {{ summaryData.end_time }})</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Thời gian trực thực tế:</span>
                    <span class="info-val text-green-main font-semibold">{{ summaryData.worked_duration_label }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Giờ vào ca:</span>
                    <span class="info-val">{{ summaryData.check_in_at || 'Chưa ghi nhận' }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Giờ kết ca:</span>
                    <span class="info-val">{{ summaryData.check_out_at || (isAlreadyCheckedOut ? 'Đã kết ca' : (summaryData.preview_check_out_at || 'Đang kết ca')) }}</span>
                  </div>
                </div>
              </div>

              <!-- 2. Financial Reconciliation Cards -->
              <div class="financial-section">
                <h4 class="section-heading">Đối soát tài chính &amp; Doanh thu trong ca</h4>

                <div class="financial-grid">
                  <!-- Cash Collected (Cashier Handover Priority) -->
                  <div class="finance-card is-cash">
                    <span class="card-label">Tiền mặt thực thu trong ca</span>
                    <strong class="card-amount text-cash">{{ formatCurrency(summaryData.total_cash_amount) }}</strong>
                    <span class="card-hint">Số tiền mặt nhân viên phải nộp lại két / giao ca sau</span>
                  </div>

                  <!-- Bank / QR Transfer -->
                  <div class="finance-card is-transfer">
                    <span class="card-label">Chuyển khoản QR SePay</span>
                    <strong class="card-amount text-transfer">{{ formatCurrency(summaryData.total_transfer_amount) }}</strong>
                    <span class="card-hint">Tiền đã tự động vào tài khoản chủ sân</span>
                  </div>

                  <!-- Total Revenue -->
                  <div class="finance-card is-total">
                    <span class="card-label">Tổng doanh thu ca</span>
                    <strong class="card-amount text-total">{{ formatCurrency(summaryData.total_revenue) }}</strong>
                    <span class="card-hint">Phục vụ: {{ summaryData.total_bookings }} đơn đặt sân</span>
                  </div>

                  <!-- Outstanding Debt / Unpaid -->
                  <div class="finance-card" :class="summaryData.total_unpaid_amount > 0 ? 'is-debt' : 'is-clean'">
                    <span class="card-label">Tiền nợ / Chưa thu tồn đọng</span>
                    <strong class="card-amount" :class="summaryData.total_unpaid_amount > 0 ? 'text-debt' : 'text-clean'">
                      {{ formatCurrency(summaryData.total_unpaid_amount) }}
                    </strong>
                    <span class="card-hint">
                      {{ summaryData.total_unpaid_amount > 0 ? 'Cần bàn giao ca sau theo dõi thu hộ' : 'Không có khoản nợ tồn đọng' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- 3. Handover Notes Input -->
              <div class="notes-section">
                <label for="handover-notes-field" class="notes-label">
                  Ghi chú bàn giao ca (Tình trạng sân, tiền trong két, sự cố...)
                </label>
                <textarea
                  id="handover-notes-field"
                  v-model="handoverNotes"
                  class="notes-textarea"
                  rows="3"
                  :disabled="isAlreadyCheckedOut"
                  :placeholder="isAlreadyCheckedOut ? 'Không có ghi chú bàn giao' : 'Ví dụ: Đã nộp đủ tiền mặt vào két quầy, bóng sân số 2 bị hỏng 1 quả...'"
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
        <footer class="handover-footer">
          <div class="footer-left">
            <button
              v-if="summaryData"
              type="button"
              class="handover-btn btn-print"
              @click="printHandoverReport"
            >
              <AppIcon name="printer" :size="14" />
              <span>In phiếu bàn giao</span>
            </button>
          </div>

          <div class="footer-right">
            <template v-if="isAlreadyCheckedOut">
              <button
                type="button"
                class="handover-btn btn-confirm"
                @click="onClose"
              >
                Đóng
              </button>
            </template>
            <template v-else>
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
            </template>
          </div>
        </footer>
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
      if (val) {
        this.handoverNotes = '';
        this.error = '';
        this.loadSummary();
      }
    },
    scheduleId() {
      if (this.isOpen) {
        this.loadSummary();
      }
    },
  },
  computed: {
    isAlreadyCheckedOut() {
      return Boolean(
        this.summaryData?.is_checked_out ||
        this.summaryData?.status === 'checked_out' ||
        this.summaryData?.status === 'completed'
      );
    },
  },
  methods: {
    async loadSummary() {
      if (!this.scheduleId) return;
      this.loading = true;
      this.error = '';
      try {
        const res = await ownerStaffShiftService.handoverSummary(this.scheduleId);
        if (res && (res.success || res.data)) {
          this.summaryData = res.data || res;
          this.handoverNotes = this.summaryData.handover_notes || this.summaryData.notes || '';
        } else {
          this.error = res?.message || 'Không thể lấy dữ liệu ca trực.';
        }
      } catch (err) {
        this.error = err.message || 'Không thể kết nối máy chủ.';
      } finally {
        this.loading = false;
      }
    },
    async handleConfirmCheckout() {
      if (!this.scheduleId) return;
      this.submitting = true;
      this.error = '';
      try {
        const payload = {
          handover_notes: this.handoverNotes,
          notes: this.handoverNotes,
        };
        const res = await ownerStaffShiftService.checkOut(this.scheduleId, payload);
        if (res && (res.success || res.data)) {
          this.onClose();
          this.$emit('checked-out', res.data || res);
        } else {
          this.error = res?.message || 'Kết thúc ca thất bại.';
        }
      } catch (err) {
        this.error = err.message || 'Có lỗi xảy ra trong quá trình kết ca.';
      } finally {
        this.submitting = false;
      }
    },
    onClose() {
      this.$emit('close');
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      try {
        const cleanDate = String(dateStr).includes('T')
          ? String(dateStr).split('T')[0]
          : String(dateStr).split(' ')[0];
        const parts = cleanDate.split('-');
        if (parts.length === 3) {
          return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return dateStr;
      } catch {
        return dateStr;
      }
    },
    printHandoverReport() {
      if (!this.summaryData) return;
      const s = this.summaryData;
      const dateFormatted = this.formatDate(s.date);
      const notesText = this.handoverNotes
        ? String(this.handoverNotes).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br/>')
        : 'Không có ghi chú bàn giao.';

      const html = `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Phiếu Bàn Giao Ca - ${s.staff_name || 'NV'}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 12mm 15mm;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      color: #0f172a;
      background: #ffffff;
      padding: 8px 12px;
      font-size: 13px;
      line-height: 1.45;
    }
    .header {
      text-align: center;
      padding-bottom: 12px;
      border-bottom: 2px solid #0f172a;
      margin-bottom: 16px;
    }
    .header h1 {
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }
    .header p {
      font-size: 13px;
      color: #475569;
    }
    .info-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 12px 16px;
      margin-bottom: 16px;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 24px;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      border-bottom: 1px dashed #e2e8f0;
      padding-bottom: 4px;
    }
    .info-label {
      color: #64748b;
      font-size: 12px;
    }
    .info-val {
      font-weight: 600;
      color: #0f172a;
      text-align: right;
    }
    .text-green {
      color: #166534;
    }
    .section-title {
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .finance-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 16px;
    }
    .finance-card {
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 10px 14px;
      background: #ffffff;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .finance-card.is-cash {
      background: #f4f8f5;
      border-color: #a7f3d0;
    }
    .finance-card.is-transfer {
      background: #f0f9ff;
      border-color: #bae6fd;
    }
    .finance-card.is-total {
      background: #f8fafc;
      border-color: #cbd5e1;
    }
    .finance-card.is-debt {
      background: #fffbeb;
      border-color: #fde68a;
    }
    .card-label {
      font-size: 11.5px;
      color: #64748b;
      display: block;
      margin-bottom: 2px;
    }
    .card-amount {
      font-size: 16.5px;
      font-weight: 700;
      color: #0f172a;
      display: block;
      margin-bottom: 2px;
    }
    .text-cash { color: #166534; }
    .text-transfer { color: #0284c7; }
    .text-debt { color: #d97706; }
    .card-hint {
      font-size: 11px;
      color: #64748b;
    }
    .notes-box {
      border: 1px dashed #cbd5e1;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 24px;
      background: #fafafa;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .notes-title {
      font-size: 12px;
      font-weight: 600;
      color: #475569;
      margin-bottom: 4px;
    }
    .notes-content {
      font-size: 12.5px;
      color: #0f172a;
    }
    .signatures {
      display: flex;
      justify-content: space-between;
      padding: 0 40px;
      margin-top: 24px;
      page-break-inside: avoid;
    }
    .sig-col {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .sig-title {
      font-size: 13px;
      font-weight: 600;
      color: #0f172a;
    }
    .sig-subtitle {
      font-size: 11.5px;
      color: #64748b;
      margin-bottom: 50px;
    }
    .sig-name {
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
    }
    .print-footer {
      text-align: center;
      font-size: 11px;
      color: #94a3b8;
      margin-top: 30px;
      border-top: 1px dotted #e2e8f0;
      padding-top: 8px;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>SPORTGO - PHIẾU BÀN GIAO CA TRỰC</h1>
    <p>${s.cluster_name || 'Cụm sân'} · Ngày ${dateFormatted}</p>
  </div>

  <div class="info-box">
    <div class="info-grid">
      <div class="info-row">
        <span class="info-label">Nhân viên trực:</span>
        <span class="info-val">${s.staff_name || '—'}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Cụm sân:</span>
        <span class="info-val">${s.cluster_name || '—'}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Ca làm việc:</span>
        <span class="info-val">${s.shift_name || '—'} (${s.start_time || ''} - ${s.end_time || ''})</span>
      </div>
      <div class="info-row">
        <span class="info-label">Thời gian trực thực tế:</span>
        <span class="info-val text-green">${s.worked_duration_label || '—'}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Giờ vào ca:</span>
        <span class="info-val">${s.check_in_at || 'Chưa ghi nhận'}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Giờ kết ca:</span>
        <span class="info-val">${s.check_out_at || 'Chưa kết ca'}</span>
      </div>
    </div>
  </div>

  <div class="section-title">Đối soát tài chính &amp; Doanh thu trong ca</div>
  <div class="finance-grid">
    <div class="finance-card is-cash">
      <span class="card-label">Tiền mặt thực thu trong ca</span>
      <span class="card-amount text-cash">${this.formatCurrency(s.total_cash_amount)}</span>
      <span class="card-hint">Số tiền mặt nhân viên phải nộp lại két / giao ca sau</span>
    </div>

    <div class="finance-card is-transfer">
      <span class="card-label">Chuyển khoản QR SePay</span>
      <span class="card-amount text-transfer">${this.formatCurrency(s.total_transfer_amount)}</span>
      <span class="card-hint">Tiền đã tự động vào tài khoản chủ sân</span>
    </div>

    <div class="finance-card is-total">
      <span class="card-label">Tổng doanh thu ca</span>
      <span class="card-amount">${this.formatCurrency(s.total_revenue)}</span>
      <span class="card-hint">Phục vụ: ${s.total_bookings || 0} đơn đặt sân</span>
    </div>

    <div class="finance-card ${s.total_unpaid_amount > 0 ? 'is-debt' : 'is-total'}">
      <span class="card-label">Tiền nợ / Chưa thu tồn đọng</span>
      <span class="card-amount ${s.total_unpaid_amount > 0 ? 'text-debt' : ''}">${this.formatCurrency(s.total_unpaid_amount)}</span>
      <span class="card-hint">${s.total_unpaid_amount > 0 ? 'Cần bàn giao ca sau theo dõi thu hộ' : 'Không có khoản nợ tồn đọng'}</span>
    </div>
  </div>

  <div class="notes-box">
    <div class="notes-title">Ghi chú bàn giao ca:</div>
    <div class="notes-content">${notesText}</div>
  </div>

  <div class="signatures">
    <div class="sig-col">
      <span class="sig-title">Người bàn giao</span>
      <span class="sig-subtitle">(Ký và ghi rõ họ tên)</span>
      <span class="sig-name">${s.staff_name || '—'}</span>
    </div>
    <div class="sig-col">
      <span class="sig-title">Người nhận bàn giao</span>
      <span class="sig-subtitle">(Ký và ghi rõ họ tên)</span>
      <span class="sig-name">.........................................</span>
    </div>
  </div>

  <div class="print-footer">
    Hệ thống Quản lý Sân thể thao SportGo · In lúc ${new Date().toLocaleTimeString('vi-VN')} ${new Date().toLocaleDateString('vi-VN')}
  </div>
</body>
</html>`;

      let iframe = document.getElementById('sportgo-print-iframe');
      if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'sportgo-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);
      }

      const doc = iframe.contentWindow.document;
      doc.open();
      doc.write(html);
      doc.close();

      setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      }, 250);
    },
  },
};
</script>

<style scoped>
.handover-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  box-sizing: border-box;
}

.handover-dialog {
  background: #ffffff;
  border-radius: 12px;
  max-width: 680px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2);
  border: 1px solid #cbd5e1;
  overflow: hidden;
  box-sizing: border-box;
  margin: 0 auto;
  animation: pop-in 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes pop-in {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

/* HEADER */
.handover-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 22px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.handover-title {
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.handover-subtitle {
  font-size: 13px;
  color: #475569;
  font-weight: 400;
  margin: 0;
}

.handover-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.handover-close-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

/* BODY */
.handover-body {
  padding: 20px 22px;
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
  color: #475569;
  font-size: 13px;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid #e2e8f0;
  border-top-color: #087642;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.handover-error-box {
  padding: 12px 16px;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  border-radius: 8px;
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
  border-radius: 5px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 500;
}

/* CONTENT SECTIONS */
.handover-content {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.info-section {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px 18px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 12px;
  color: #475569;
  font-weight: 400;
}

.info-val {
  font-size: 13.5px;
  color: #0f172a;
}

.text-green-main {
  color: #166534;
}

/* FINANCIAL SECTION */
.financial-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section-heading {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.financial-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.finance-card {
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  transition: all 0.15s ease;
}

.finance-card.is-cash {
  background: #f4f8f5;
  border: 1px solid #cbdcd0;
}

.finance-card.is-transfer {
  background: #f0f9ff;
  border: 1px solid #bae6fd;
}

.finance-card.is-total {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.finance-card.is-debt {
  background: #fffbeb;
  border: 1px solid #fde68a;
}

.finance-card.is-clean {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.card-label {
  font-size: 12.5px;
  color: #475569;
  font-weight: 500;
}

.finance-card.is-cash .card-label {
  color: #166534;
}

.finance-card.is-transfer .card-label {
  color: #0369a1;
}

.finance-card.is-debt .card-label {
  color: #92400e;
}

.card-amount {
  font-size: 20px;
  font-weight: 600;
  line-height: 1.2;
  margin: 2px 0;
}

.card-amount.text-cash {
  color: #166534;
}

.card-amount.text-transfer {
  color: #0284c7;
}

.card-amount.text-total {
  color: #0f172a;
}

.card-amount.text-debt {
  color: #d97706;
}

.card-amount.text-clean {
  color: #166534;
}

.card-hint {
  font-size: 12px;
  color: #64748b;
  line-height: 1.3;
}

.finance-card.is-cash .card-hint {
  color: #166534;
}

.finance-card.is-transfer .card-hint {
  color: #0284c7;
}

.finance-card.is-debt .card-hint {
  color: #b45309;
}

/* NOTES SECTION */
.notes-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notes-label {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
}

.notes-textarea {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13.5px;
  color: #0f172a;
  outline: none;
  font-family: inherit;
  resize: vertical;
  background: #ffffff;
  transition: border-color 0.15s ease;
}

.notes-textarea:focus {
  border-color: #166534;
  box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.1);
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
  padding: 14px 22px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.footer-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.handover-btn {
  padding: 8px 16px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}

.btn-print {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
}

.btn-print:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-cancel {
  background: transparent;
  border: none;
  color: #475569;
}

.btn-cancel:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-cancel {
  background: transparent;
  border: none;
  color: #475569;
}

.btn-cancel:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-confirm {
  background: #166534;
  color: #ffffff;
  border: none;
  font-weight: 600;
}

.btn-confirm:hover:not(:disabled) {
  background: #14532d;
}

.btn-confirm:disabled,
.btn-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* PRINT STYLING */
@media print {
  @page {
    size: A4 portrait;
    margin: 12mm 15mm;
  }

  body {
    background: #ffffff !important;
  }

  /* Hide entire backdrop modal UI, frames, headers, buttons */
  .handover-head,
  .handover-footer,
  .handover-close-btn,
  .handover-loading,
  .handover-error-box,
  .retry-btn,
  .pos-header,
  .pos-mobile-bottom-nav,
  .pos-workspace,
  .staff-schedule-container {
    display: none !important;
  }

  .handover-modal-backdrop {
    position: static !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    height: auto !important;
    display: block !important;
    backdrop-filter: none !important;
  }

  .handover-dialog {
    position: static !important;
    box-shadow: none !important;
    border: none !important;
    border-radius: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    padding: 0 !important;
    margin: 0 !important;
    background: transparent !important;
    animation: none !important;
  }

  .handover-body {
    padding: 0 !important;
    overflow: visible !important;
    max-height: none !important;
  }

  #printable-handover-area {
    position: static !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 14px !important;
  }

  .print-only-header {
    display: block !important;
    text-align: center !important;
    margin-bottom: 12px !important;
    border-bottom: 2px solid #0f172a !important;
    padding-bottom: 8px !important;
  }

  .print-only-header h2 {
    font-size: 18px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin: 0 0 4px 0 !important;
    letter-spacing: 0.5px !important;
  }

  .print-only-header p {
    font-size: 13px !important;
    color: #475569 !important;
    margin: 0 !important;
  }

  .info-section {
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    padding: 10px 14px !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .info-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 6px 16px !important;
  }

  .section-heading {
    font-size: 13.5px !important;
    margin-bottom: 6px !important;
  }

  .financial-grid {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 8px !important;
  }

  .finance-card {
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .finance-card.is-cash {
    background: #f4f8f5 !important;
    border-color: #a7f3d0 !important;
  }

  .finance-card.is-transfer {
    background: #f0f9ff !important;
    border-color: #bae6fd !important;
  }

  .finance-card.is-total {
    background: #f8fafc !important;
  }

  .finance-card.is-debt {
    background: #fffbeb !important;
    border-color: #fde68a !important;
  }

  .card-amount {
    font-size: 16px !important;
    margin: 1px 0 !important;
  }

  .notes-section {
    border: 1px dashed #cbd5e1 !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
  }

  .notes-textarea {
    border: none !important;
    padding: 0 !important;
    resize: none !important;
    background: transparent !important;
    width: 100% !important;
    font-size: 12.5px !important;
  }

  .print-signatures {
    display: flex !important;
    justify-content: space-between !important;
    margin-top: 24px !important;
    padding: 0 40px !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .sig-col {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    font-size: 12.5px !important;
    color: #0f172a !important;
  }

  .sig-line {
    height: 48px !important;
  }

  .sig-name {
    font-size: 13px !important;
    font-weight: 600 !important;
  }
}

@media (max-width: 640px) {
  .handover-modal-backdrop {
    padding: 10px;
    align-items: center;
    justify-content: center;
  }
  .handover-dialog {
    max-width: 100%;
    width: 100%;
    max-height: 92vh;
    border-radius: 12px;
    margin: 0 auto;
  }
  .handover-head {
    padding: 12px 14px;
  }
  .handover-title {
    font-size: 15px;
  }
  .handover-subtitle {
    font-size: 12px;
  }
  .handover-body {
    padding: 12px;
    gap: 12px;
  }
  .info-section {
    padding: 12px;
  }
  .info-grid,
  .financial-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .finance-card {
    padding: 12px;
  }
  .card-amount {
    font-size: 18px;
  }
  .handover-footer {
    flex-direction: row;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
  }
  .footer-left,
  .footer-right {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .footer-left {
    flex: 1;
  }
  .footer-right {
    flex: 1.2;
  }
  .handover-btn {
    flex: 1;
    justify-content: center;
    padding: 8px 10px;
    font-size: 12px;
    white-space: nowrap;
  }
}
</style>
