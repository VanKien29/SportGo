<template>
  <section class="fee-page">

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">Đang tải dữ liệu phí nền tảng...</div>
    <div v-else-if="!clusterId" class="state-card">Vui lòng chọn cụm sân ở thanh bên để xem phí.</div>

    <template v-else>
      <div v-if="summary.overdue" class="deadline-alert overdue-alert">
        <span class="alert-icon">!</span>
        <div>
          <strong>{{ summary.overdue }} kỳ phí đã quá hạn</strong>
          <p>Vui lòng thanh toán ngay.</p>
        </div>
      </div>
      <div v-else-if="dueSoonCount" class="deadline-alert due-alert">
        <span class="alert-icon">i</span>
        <div>
          <strong>{{ dueSoonCount }} kỳ phí sắp đến hạn</strong>
          <p>Vui lòng thanh toán trước hạn đóng để tránh gián đoạn hoạt động cụm sân.</p>
        </div>
      </div>

      <div class="summary-grid">
        <article class="summary-card primary-card">
          <span>Tổng cần thanh toán</span>
          <strong>{{ money(summary.outstanding_amount) }}</strong>
          <small>{{ summary.pending + summary.overdue }} kỳ chưa hoàn tất</small>
        </article>
        <article class="summary-card">
          <span>Chờ thanh toán</span>
          <strong>{{ summary.pending }}</strong>
          <small>Kỳ còn trong hạn</small>
        </article>
        <article class="summary-card">
          <span>Quá hạn</span>
          <strong class="danger-text">{{ summary.overdue }}</strong>
          <small>Cần xử lý sớm</small>
        </article>
        <article class="summary-card">
          <span>Tổng số kỳ</span>
          <strong>{{ summary.total }}</strong>
          <small>{{ venueName || 'Cụm sân đang chọn' }}</small>
        </article>
      </div>

      <article v-if="paymentAccount" class="bank-card">
        <div>
          <p class="eyebrow">THÔNG TIN THANH TOÁN</p>
          <h3>Tài khoản nhận phí của SportGo</h3>
          <p class="muted">Mỗi kỳ phí sẽ có QR và mã chuyển khoản riêng để hệ thống tự xác nhận.</p>
        </div>
        <dl>
          <div><dt>Ngân hàng</dt><dd>{{ paymentAccount.bank_name }}</dd></div>
          <div><dt>Số tài khoản</dt><dd>{{ paymentAccount.account_number }}</dd></div>
          <div><dt>Chủ tài khoản</dt><dd>{{ paymentAccount.account_holder_name }}</dd></div>
        </dl>
      </article>

      <article class="table-card">
        <div class="table-head">
          <div>
            <h3>Lịch sử kỳ phí</h3>
            <p>Dữ liệu được tính theo kỳ phí và hạn đóng trên hệ thống.</p>
          </div>
          <div class="table-actions">
            <button class="refresh-btn" type="button" :disabled="loading" @click="loadFees">Làm mới</button>
            <select v-model="statusFilter">
              <option value="">Tất cả trạng thái</option>
              <option value="pending">Chờ thanh toán</option>
              <option value="overdue">Quá hạn</option>
              <option value="paid">Đã thanh toán</option>
              <option value="cancelled">Đã hủy</option>
            </select>
          </div>
        </div>

        <div v-if="!filteredFees.length" class="empty-state">Chưa có kỳ phí phù hợp.</div>
        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Kỳ phí</th>
                <th>Hạn đóng</th>
                <th>Số sân</th>
                <th>Số tiền</th>
                <th>Trạng thái</th>
                <th>Thanh toán</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fee in filteredFees" :key="fee.id">
                <td>
                  <strong>{{ date(fee.period_start) }} - {{ date(fee.period_end) }}</strong>
                  <small>{{ cycleLabel(fee) }} · {{ fee.tier?.name || 'Theo cấu hình' }}</small>
                </td>
                <td>
                  <strong :class="{ 'danger-text': fee.effective_status === 'overdue' }">{{ date(fee.due_date) }}</strong>
                  <small v-if="fee.warning_level === 'due_soon'">Còn {{ fee.days_until_due }} ngày</small>
                  <small v-if="fee.effective_status === 'overdue'" class="danger-text">Quá hạn {{ Math.abs(fee.days_until_due) }} ngày</small>
                </td>
                <td>{{ fee.court_count }}</td>
                <td>
                  <strong>{{ money(fee.amount_due) }}</strong>
                  <small v-if="fee.amount_paid">Đã ghi nhận {{ money(fee.amount_paid) }}</small>
                </td>
                <td><span class="status-pill" :class="fee.effective_status">{{ statusLabel(fee.effective_status) }}</span></td>
                <td>
                  <span v-if="fee.effective_status === 'paid'" class="auto-status paid">Tự động xác nhận</span>
                  <span v-else class="auto-status">QR ngân hàng</span>
                  <small v-if="fee.payment?.code">Mã: {{ fee.payment.code }}</small>
                </td>
                <td class="action-cell">
                  <button v-if="canPay(fee)" class="submit-btn" type="button" :disabled="submitting" @click="openPaymentModal(fee)">
                    Thanh toán
                  </button>
                  <span v-else-if="fee.effective_status === 'paid'" class="paid-at">{{ paidAt(fee.paid_at) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </template>

    <div v-if="paymentModal" class="modal-backdrop" @click.self="closePaymentModal">
      <section class="payment-modal">
        <header>
          <div>
            <p class="eyebrow">THANH TOÁN TỰ ĐỘNG</p>
            <h3>{{ date(paymentModal.fee.period_start) }} - {{ date(paymentModal.fee.period_end) }}</h3>
          </div>
          <button type="button" class="close-btn" @click="closePaymentModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle;">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </header>

        <div class="amount-box">
          <span>Số tiền cần chuyển</span>
          <strong>{{ money(paymentModal.amount) }}</strong>
        </div>

        <div class="qr-payment">
          <img :src="paymentModal.qr_url" alt="QR thanh toán phí nền tảng">
          <div>
            <strong>Quét QR để chuyển khoản</strong>
            <span>{{ paymentModal.payment_account.bank_name }} · {{ paymentModal.payment_account.account_number }}</span>
            <span>Chủ tài khoản: {{ paymentModal.payment_account.account_holder_name }}</span>
            <span>Nội dung: <b>{{ paymentModal.transfer_content }}</b></span>
            <button class="copy-btn" type="button" @click="copyTransferContent">Sao chép nội dung</button>
          </div>
        </div>

        <p class="review-note auto-note">
          <span class="poll-dot"></span>
          Đang chờ giao dịch từ ngân hàng. Sau khi chuyển đúng số tiền và nội dung, kỳ phí sẽ tự chuyển sang “Đã thanh toán”.
        </p>

        <footer>
          <button class="cancel-btn" type="button" @click="closePaymentModal">Đóng</button>
        </footer>
      </section>
    </div>
  </section>
</template>

<script>
import { ownerPlatformFeeService } from '../../services/ownerPlatformFees.js';

export default {
  name: 'OwnerPlatformFees',
  data() {
    return {
      fees: [],
      summary: { total: 0, pending: 0, overdue: 0, outstanding_amount: 0 },
      paymentAccount: null,
      venueName: '',
      loading: true,
      submitting: false,
      error: '',
      success: '',
      statusFilter: '',
      paymentModal: null,
      paymentPollInterval: null,
      clusterId: localStorage.getItem('selected_cluster') || '',
    };
  },
  computed: {
    dueSoonCount() {
      return this.fees.filter((fee) => fee.warning_level === 'due_soon').length;
    },
    filteredFees() {
      if (!this.statusFilter) return this.fees;
      return this.fees.filter((fee) => fee.effective_status === this.statusFilter);
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChanged);
    await this.loadFees();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChanged);
    this.clearPaymentPolling();
  },
  methods: {
    async handleClusterChanged(event) {
      this.closePaymentModal();
      this.clusterId = event.detail?.id || localStorage.getItem('selected_cluster') || '';
      await this.loadFees();
    },
    async loadFees() {
      this.loading = true;
      this.error = '';
      this.success = '';

      if (!this.clusterId) {
        this.loading = false;
        return;
      }

      try {
        const response = await ownerPlatformFeeService.list(this.clusterId);
        this.fees = response.data || [];
        this.summary = response.summary || this.summary;
        this.paymentAccount = response.payment_account || null;
        this.venueName = response.venue_cluster?.name || '';
      } catch (error) {
        this.error = error.message || 'Không thể tải dữ liệu phí nền tảng.';
      } finally {
        this.loading = false;
      }
    },
    async openPaymentModal(fee) {
      this.submitting = true;
      this.error = '';
      this.success = '';

      try {
        const response = await ownerPlatformFeeService.createPayment(fee.id);
        this.paymentModal = {
          fee: response.data,
          amount: response.amount,
          qr_url: response.qr_url,
          transfer_content: response.transfer_content,
          payment_account: response.payment_account,
        };
        this.startPaymentPolling();
      } catch (error) {
        this.error = error.message || 'Không thể tạo QR thanh toán phí nền tảng.';
      } finally {
        this.submitting = false;
      }
    },
    closePaymentModal() {
      this.clearPaymentPolling();
      this.paymentModal = null;
    },
    startPaymentPolling() {
      this.clearPaymentPolling();
      this.paymentPollInterval = window.setInterval(this.refreshPaymentStatus, 4000);
    },
    clearPaymentPolling() {
      if (!this.paymentPollInterval) return;
      window.clearInterval(this.paymentPollInterval);
      this.paymentPollInterval = null;
    },
    async refreshPaymentStatus() {
      if (!this.paymentModal?.fee?.id) return;

      try {
        const response = await ownerPlatformFeeService.detail(this.paymentModal.fee.id);
        const fee = response.data;
        if (fee.effective_status !== 'paid') return;

        this.clearPaymentPolling();
        this.paymentModal = null;
        await this.loadFees();
        this.success = 'Thanh toán đã được ngân hàng xác nhận tự động.';
      } catch {
        // Giữ polling; lỗi mạng tạm thời không làm gián đoạn QR đang hiển thị.
      }
    },
    async copyTransferContent() {
      try {
        await navigator.clipboard.writeText(this.paymentModal?.transfer_content || '');
        this.success = 'Đã sao chép nội dung chuyển khoản.';
      } catch {
        this.error = 'Không thể sao chép nội dung chuyển khoản.';
      }
    },
    canPay(fee) {
      return ['pending', 'overdue'].includes(fee.effective_status) && Number(fee.amount_remaining) > 0;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(value || 0);
    },
    date(value) {
      if (!value) return 'Chưa cập nhật';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(`${value}T00:00:00`));
    },
    cycleLabel(fee) {
      return fee.period_months === 12 ? 'Theo năm' : `${fee.period_months || 1} tháng`;
    },
    statusLabel(status) {
      return {
        pending: 'Chờ thanh toán',
        overdue: 'Quá hạn',
        paid: 'Đã thanh toán',
        cancelled: 'Đã hủy',
      }[status] || status;
    },
    paidAt(value) {
      if (!value) return 'Đã hoàn tất';
      return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
      }).format(new Date(value));
    },
  },
};
</script>

<style scoped>
.fee-page{display:grid;gap:18px;max-width:1280px}.table-head,.payment-modal header,.payment-modal footer{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.table-head h3,.bank-card h3,.payment-modal h3{margin:0;color:#0f172a}.table-head p,.muted{margin:6px 0 0;color:#64748b}.eyebrow{margin:0 0 6px;color:#059669;font-size:11px;font-weight:900;letter-spacing:.11em}.refresh-btn,.submit-btn,.cancel-btn,.close-btn{border:0;border-radius:9px;font:inherit;font-weight:800;cursor:pointer}.refresh-btn,.cancel-btn{padding:10px 14px;background:#f1f5f9;color:#334155}.submit-btn{padding:9px 13px;background:#059669;color:#fff}.submit-btn:disabled,.refresh-btn:disabled{opacity:.55;cursor:not-allowed}.state-card,.table-card,.bank-card,.summary-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px}.state-card{padding:34px;text-align:center;color:#64748b}.alert,.deadline-alert{border-radius:12px;padding:14px 16px}.alert{font-weight:750}.alert.error{background:#fee2e2;color:#991b1b}.alert.success{background:#dcfce7;color:#166534}.deadline-alert{display:flex;align-items:center;gap:13px}.deadline-alert strong{display:block;margin-bottom:4px}.deadline-alert p{margin:0;font-size:13px}.overdue-alert{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}.due-alert{background:#fffbeb;border:1px solid #fde68a;color:#92400e}.alert-icon{display:grid;place-items:center;width:30px;height:30px;flex:0 0 30px;border:2px solid currentColor;border-radius:50%;font-weight:900}.summary-grid{display:grid;grid-template-columns:1.45fr repeat(3,1fr);gap:14px}.summary-card{display:grid;gap:7px;padding:19px}.summary-card span,.summary-card small{color:#64748b}.summary-card strong{font-size:24px;color:#0f172a}.primary-card{border-color:#a7f3d0;background:linear-gradient(135deg,#ecfdf5,#fff)}.primary-card strong{color:#047857}.danger-text{color:#dc2626!important}.bank-card{display:flex;justify-content:space-between;gap:24px;padding:20px}.bank-card dl{display:grid;grid-template-columns:repeat(3,minmax(130px,1fr));gap:24px;margin:0}.bank-card dl div{display:grid;gap:5px}.bank-card dt{color:#64748b;font-size:12px}.bank-card dd{margin:0;color:#0f172a;font-weight:850}.table-card{overflow:hidden}.table-head{padding:18px 20px;border-bottom:1px solid #e2e8f0}.table-actions{display:flex;align-items:center;gap:12px}.table-head select{border:1px solid #cbd5e1;border-radius:9px;padding:9px 12px;background:#fff;font:inherit;color:#334155}.table-wrap{overflow:auto}table{width:100%;min-width:1050px;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em}td{color:#334155;font-size:13px}td strong,td small,td a{display:block}td small{margin-top:5px;color:#64748b}td a{margin-top:5px;color:#047857;font-weight:750;text-decoration:none}.status-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:850}.status-pill.pending{background:#fef3c7;color:#92400e}.status-pill.overdue{background:#fee2e2;color:#991b1b}.status-pill.paid{background:#dcfce7;color:#166534}.status-pill.cancelled{background:#e2e8f0;color:#475569}.action-cell{text-align:right}.empty-state{padding:40px;text-align:center;color:#64748b}.modal-backdrop{position:fixed;inset:0;z-index:600;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.58)}.payment-modal{display:grid;gap:16px;width:min(570px,calc(100vw - 32px));padding:22px;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.28)}.close-btn{padding:2px 8px;background:transparent;color:#64748b;font-size:25px}.amount-box{display:flex;justify-content:space-between;align-items:center;padding:14px;border-radius:10px;background:#ecfdf5;color:#065f46}.amount-box strong{font-size:20px}.review-note{margin:0;padding:12px;border-radius:9px;background:#f8fafc;color:#475569;font-size:13px;line-height:1.5}.payment-modal footer{justify-content:flex-end}.payment-modal .cancel-btn{padding:9px 14px}@media(max-width:1050px){.summary-grid{grid-template-columns:repeat(2,1fr)}.bank-card{display:grid}.bank-card dl{grid-template-columns:repeat(3,1fr)}}@media(max-width:680px){.table-head{display:grid;gap:12px}.table-actions{display:grid;grid-template-columns:1fr;gap:8px}.summary-grid{grid-template-columns:1fr}.bank-card dl{grid-template-columns:1fr;gap:12px}.refresh-btn,.table-head select{width:100%}}
.qr-payment{display:grid;grid-template-columns:150px 1fr;gap:16px;align-items:center;padding:14px;border:1px solid #a7f3d0;border-radius:12px;background:#f0fdf4}.qr-payment img{display:block;width:150px;height:150px;border-radius:8px;background:#fff;object-fit:contain}.qr-payment div{display:grid;gap:7px;color:#475569;font-size:13px}.qr-payment strong{color:#065f46;font-size:15px}.copy-btn{justify-self:start;border:0;padding:0;background:transparent;color:#047857;font:inherit;font-weight:850;text-decoration:underline;cursor:pointer}.auto-status{display:inline-flex;border-radius:999px;padding:5px 9px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:850}.auto-status.paid{background:#dcfce7;color:#166534}.paid-at{color:#64748b;font-size:12px;font-weight:750}.auto-note{display:flex;align-items:center;gap:9px;background:#eff6ff;color:#1e40af}.poll-dot{width:9px;height:9px;flex:0 0 9px;border-radius:50%;background:#2563eb;box-shadow:0 0 0 0 rgba(37,99,235,.45);animation:poll-pulse 1.5s infinite}@keyframes poll-pulse{70%{box-shadow:0 0 0 8px rgba(37,99,235,0)}100%{box-shadow:0 0 0 0 rgba(37,99,235,0)}}@media(max-width:560px){.qr-payment{grid-template-columns:1fr}.qr-payment img{margin:auto}}
</style>
