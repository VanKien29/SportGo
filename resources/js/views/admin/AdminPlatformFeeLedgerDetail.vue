<template>
  <section class="detail-page">
    <button class="link-btn" type="button" @click="$router.push({ name: 'admin-platform-fee-ledgers' })">Quay lại danh sách</button>

    <div v-if="!ledger" class="panel empty">Không tìm thấy kỳ phí.</div>
    <template v-else>
      <!-- Ledger Info Bar -->
      <div class="ledger-info-bar" style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
        <h2 style="margin: 0;">{{ ledger.code }}</h2>
        <span style="color: #64748b; font-size: 14px; font-weight: 600;">{{ ledger.venue?.name }} - {{ ledger.owner?.full_name }}</span>
        <span class="status-dot" :class="ledger.status" :title="statusLabel(ledger.status)" :aria-label="statusLabel(ledger.status)"></span>
      </div>

      <section class="grid">
        <div class="panel metric"><span>Số tiền phải đóng</span><strong>{{ money(ledger.amount_due) }}</strong></div>
        <div class="panel metric"><span>Đã đóng</span><strong>{{ money(ledger.amount_paid) }}</strong></div>
        <div class="panel metric"><span>Còn thiếu</span><strong>{{ money(ledger.remaining_amount) }}</strong></div>
        <div class="panel metric"><span>Hạn thanh toán</span><strong>{{ date(ledger.due_date) }}</strong></div>
      </section>

      <section class="panel">
        <h3>Snapshot kỳ phí</h3>
        <div class="info-grid">
          <div><span>Cụm sân</span><strong>{{ ledger.venue?.name }}</strong></div>
          <div><span>Chủ sân</span><strong>{{ ledger.owner?.full_name }}</strong></div>
          <div><span>Số sân snapshot</span><strong>{{ ledger.court_count }}</strong></div>
          <div><span>Bậc phí snapshot</span><strong>{{ ledger.tier_name }}</strong></div>
          <div><span>Kỳ đóng</span><strong>{{ ledger.period_months }} tháng</strong></div>
          <div><span>Thời gian kỳ phí</span><strong>{{ date(ledger.period_start) }} - {{ date(ledger.period_end) }}</strong></div>
          <div><span>Giá/sân/tháng snapshot</span><strong>{{ money(ledger.price_per_court_month) }}</strong></div>
          <div><span>Giảm giá snapshot</span><strong>{{ percent(ledger.discount_percent) }}</strong></div>
          <div><span>Tổng trước giảm</span><strong>{{ money(ledger.base_amount) }}</strong></div>
          <div><span>Số tiền giảm</span><strong>{{ money(ledger.discount_amount) }}</strong></div>
          <div><span>Ngày thanh toán</span><strong>{{ ledger.paid_at ? date(ledger.paid_at) : '-' }}</strong></div>
          <div><span>Lý do hủy</span><strong>{{ ledger.cancelled_reason || '-' }}</strong></div>
        </div>
      </section>

      <section class="panel">
        <h3>Hành động</h3>
        <div class="actions">
          <button class="btn primary icon-text" type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="payFull">
            <AppIcon name="creditCard" size="18" />
            <span>Xác nhận thanh toán đủ</span>
          </button>
          <button class="btn secondary icon-text" type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="markOverdueNow">
            <AppIcon name="clock" size="18" />
            <span>Đánh dấu quá hạn</span>
          </button>
          <button class="btn danger icon-text" type="button" :disabled="ledger.status !== 'overdue'" @click="lockVenue">
            <AppIcon name="lock" size="18" />
            <span>Khóa cụm sân</span>
          </button>
          <button class="btn secondary icon-text" type="button" :disabled="ledger.status !== 'paid'" @click="unlockVenue">
            <AppIcon name="unlock" size="18" />
            <span>Mở khóa sau thanh toán</span>
          </button>
        </div>
      </section>

      <section class="panel">
        <h3>Phiếu thu</h3>
        <div v-if="ledger.receipt" class="receipt">
          <strong>{{ ledger.receipt.code }}</strong>
          <span>{{ money(ledger.receipt.amount) }} - {{ date(ledger.receipt.issued_at) }}</span>
          <p>{{ ledger.receipt.content }}</p>
        </div>
        <div v-else class="empty compact">Chưa có phiếu thu.</div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>Lịch sử email nhắc thanh toán</h3>
          <button class="btn secondary icon-text" type="button" @click="sendCurrentReminder">
            <AppIcon name="bell" size="18" />
            <span>Gửi nhắc phí theo ngày hiện tại</span>
          </button>
        </div>
        <div v-if="emailLogs.length === 0" class="empty compact">Chưa có email nhắc phí nào được gửi.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Loại email</th>
              <th>Email nhận</th>
              <th>Tiêu đề</th>
              <th>Trạng thái</th>
              <th>Thời gian gửi</th>
              <th>Lý do lỗi</th>
              <th>Nội dung</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in emailLogs" :key="log.id">
              <td><span class="email-chip">{{ reminderLabel(log.type) }}</span></td>
              <td>{{ log.email || '-' }}</td>
              <td>{{ log.subject }}</td>
              <td>{{ log.status }}</td>
              <td>{{ log.sent_at ? date(log.sent_at) : '-' }}</td>
              <td>{{ log.error_reason || '-' }}</td>
              <td><button class="link-btn" type="button" @click="selectedEmail = log">Xem</button></td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>

    <div v-if="selectedEmail" class="modal-backdrop" @click.self="selectedEmail = null">
      <div class="modal">
        <header class="modal-head">
          <h3>Nội dung email</h3>
          <button class="icon-close" type="button" title="Đóng" aria-label="Đóng" @click="selectedEmail = null">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <p>{{ selectedEmail.content }}</p>
      </div>
    </div>

    <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>
  </section>
</template>

<script>
import {
  confirmLedgerPayment,
  getLedgerById,
  lockVenueForOverdueLedger,
  markLedgerOverdue,
  unlockVenueAfterPayment,
} from '../../services/platformFeeLedger.service.js';
import {
  getEmailLogsByLedgerId,
  getReminderTypeForDate,
  sendPlatformFeeReminderEmail,
} from '../../services/platformFeeReminder.service.js';
import AppIcon from '../../components/AppIcon.vue';

export default {
  name: 'AdminPlatformFeeLedgerDetail',
  components: { AppIcon },
  data() {
    return {
      ledger: null,
      emailLogs: [],
      selectedEmail: null,
      toast: '',
      toastType: 'success',
    };
  },
  mounted() {
    this.loadDetail();
  },
  methods: {
    async loadDetail() {
      this.ledger = await getLedgerById(this.$route.params.id);
      this.emailLogs = await getEmailLogsByLedgerId(this.$route.params.id);
    },
    async payFull() {
      await this.run(() => confirmLedgerPayment(this.ledger.id, { amount: this.ledger.remaining_amount }), 'Đã xác nhận thanh toán đủ.');
    },
    async markOverdueNow() {
      const reason = prompt('Nhập lý do quá hạn:', 'Quá hạn thanh toán');
      if (!reason) return;
      await this.run(() => markLedgerOverdue(this.ledger.id, reason), 'Đã đánh dấu quá hạn.');
    },
    async lockVenue() {
      const reason = prompt('Nhập lý do khóa cụm sân:', 'Quá hạn phí duy trì hệ thống');
      if (!reason) return;
      await this.run(() => lockVenueForOverdueLedger(this.ledger.id, reason), 'Đã khóa cụm sân.');
    },
    async unlockVenue() {
      await this.run(() => unlockVenueAfterPayment(this.ledger.id), 'Đã mở khóa cụm sân.');
    },
    async sendCurrentReminder() {
      const type = getReminderTypeForDate(this.ledger, new Date());
      if (!type) {
        this.showMessage('Hôm nay không đúng mốc gửi email cho kỳ phí này.', 'error');
        return;
      }
      await this.run(() => sendPlatformFeeReminderEmail(this.ledger, type), 'Đã xử lý email nhắc phí.');
    },
    async run(action, success) {
      try {
        await action();
        this.showMessage(success);
        await this.loadDetail();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    statusLabel(status) {
      return { pending: 'Chờ thanh toán', paid: 'Đã thanh toán', overdue: 'Quá hạn', cancelled: 'Đã hủy' }[status] || status;
    },
    reminderLabel(type) {
      return {
        due_soon_7_days: 'Nhắc trước hạn 7 ngày',
        due_today: 'Nhắc đúng ngày đến hạn',
        overdue_3_days: 'Cảnh báo quá hạn 3 ngày',
      }[type] || type;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    percent(value) {
      return `${Number(value || 0).toLocaleString('vi-VN')}%`;
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    showMessage(message, type = 'success') {
      this.toast = message;
      this.toastType = type;
      setTimeout(() => { this.toast = ''; }, 3500);
    },
  },
};
</script>

<style scoped>
.detail-page { display: flex; flex-direction: column; gap: 16px; }
.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
.panel-head, .actions, .modal-head, .icon-text { display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; }
h2, h3, p { margin: 0; }
.grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.metric span, .info-grid span { display: block; color: #64748b; font-size: 12px; }
.metric strong { display: block; font-size: 22px; }
.info-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.info-grid div, .receipt { background: #f8fafc; border-radius: 8px; padding: 12px; }
.status-dot {
  display: inline-grid;
  width: 14px;
  height: 14px;
  border-radius: 999px;
  background: #f59e0b;
  box-shadow: 0 0 0 3px #fef3c7;
}
.status-dot.paid { background: #10b981; box-shadow: 0 0 0 3px #d1fae5; }
.status-dot.overdue { background: #ef4444; box-shadow: 0 0 0 3px #fee2e2; }
.status-dot.cancelled { background: #94a3b8; box-shadow: 0 0 0 3px #e2e8f0; }
.email-chip { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 900; }
.email-chip { background: #e0f2fe; color: #075985; }
.btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.btn.danger { background: #dc2626; color: #fff; }
.btn:disabled { opacity: .45; cursor: not-allowed; }
.icon-text { align-items: center; justify-content: center; }
.link-btn { border: 0; background: transparent; color: #047857; font-weight: 900; cursor: pointer; width: fit-content; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 11px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
.empty { text-align: center; color: #64748b; }
.compact { padding: 24px; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; }
.toast.success { background: #ecfdf5; color: #047857; }
.toast.error { background: #fef2f2; color: #991b1b; }
.modal-backdrop { position: fixed; inset: 0; z-index: 900; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.55); }
.modal { width: min(560px, calc(100vw - 32px)); background: #fff; border-radius: 8px; padding: 18px; }
.icon-close {
  display: inline-grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border: 1px solid #dbe3ea;
  border-radius: 8px;
  background: #f8fafc;
  color: #334155;
  cursor: pointer;
}
@media (max-width: 900px) {
  .grid, .info-grid { grid-template-columns: 1fr 1fr; }
  .ledger-info-bar, .panel-head { flex-direction: column; align-items: flex-start; }
}
</style>
