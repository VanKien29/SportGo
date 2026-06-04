<template>
  <section class="detail-page">
    <button class="link-btn" type="button" @click="$router.push({ name: 'admin-platform-fee-ledgers' })">Quay lai danh sach</button>

    <div v-if="!ledger" class="panel empty">Khong tim thay ky phi.</div>
    <template v-else>
      <header class="panel page-head">
        <div>
          <p class="eyebrow">Chi tiet ky phi</p>
          <h2>{{ ledger.code }}</h2>
          <p>{{ ledger.venue?.name }} - {{ ledger.owner?.full_name }}</p>
        </div>
        <span class="badge" :class="ledger.status">{{ statusLabel(ledger.status) }}</span>
      </header>

      <section class="grid">
        <div class="panel metric"><span>So tien phai dong</span><strong>{{ money(ledger.amount_due) }}</strong></div>
        <div class="panel metric"><span>Da dong</span><strong>{{ money(ledger.amount_paid) }}</strong></div>
        <div class="panel metric"><span>Con thieu</span><strong>{{ money(ledger.remaining_amount) }}</strong></div>
        <div class="panel metric"><span>Han thanh toan</span><strong>{{ date(ledger.due_date) }}</strong></div>
      </section>

      <section class="panel">
        <h3>Snapshot ky phi</h3>
        <div class="info-grid">
          <div><span>Cum san</span><strong>{{ ledger.venue?.name }}</strong></div>
          <div><span>Chu san</span><strong>{{ ledger.owner?.full_name }}</strong></div>
          <div><span>So san snapshot</span><strong>{{ ledger.court_count }}</strong></div>
          <div><span>Bac phi snapshot</span><strong>{{ ledger.tier_name }}</strong></div>
          <div><span>Ky dong</span><strong>{{ ledger.period_months }} thang</strong></div>
          <div><span>Thoi gian ky phi</span><strong>{{ date(ledger.period_start) }} - {{ date(ledger.period_end) }}</strong></div>
          <div><span>Gia/san/thang snapshot</span><strong>{{ money(ledger.price_per_court_month) }}</strong></div>
          <div><span>Giam gia snapshot</span><strong>{{ percent(ledger.discount_percent) }}</strong></div>
          <div><span>Tong truoc giam</span><strong>{{ money(ledger.base_amount) }}</strong></div>
          <div><span>So tien giam</span><strong>{{ money(ledger.discount_amount) }}</strong></div>
          <div><span>Ngay thanh toan</span><strong>{{ ledger.paid_at ? date(ledger.paid_at) : '-' }}</strong></div>
          <div><span>Ly do huy</span><strong>{{ ledger.cancelled_reason || '-' }}</strong></div>
        </div>
      </section>

      <section class="panel">
        <h3>Hanh dong</h3>
        <div class="actions">
          <button class="btn primary" type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="payFull">Xac nhan thanh toan du</button>
          <button class="btn secondary" type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="markOverdueNow">Danh dau qua han</button>
          <button class="btn danger" type="button" :disabled="ledger.status !== 'overdue'" @click="lockVenue">Khoa cum san</button>
          <button class="btn secondary" type="button" :disabled="ledger.status !== 'paid'" @click="unlockVenue">Mo khoa sau thanh toan</button>
        </div>
      </section>

      <section class="panel">
        <h3>Phieu thu</h3>
        <div v-if="ledger.receipt" class="receipt">
          <strong>{{ ledger.receipt.code }}</strong>
          <span>{{ money(ledger.receipt.amount) }} - {{ date(ledger.receipt.issued_at) }}</span>
          <p>{{ ledger.receipt.content }}</p>
        </div>
        <div v-else class="empty compact">Chua co phieu thu.</div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>Lich su email nhac thanh toan</h3>
          <button class="btn secondary" type="button" @click="sendCurrentReminder">Gui nhac phi theo ngay hien tai</button>
        </div>
        <div v-if="emailLogs.length === 0" class="empty compact">Chua co email nhac phi nao duoc gui.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Loai email</th>
              <th>Email nhan</th>
              <th>Tieu de</th>
              <th>Trang thai</th>
              <th>Thoi gian gui</th>
              <th>Ly do loi</th>
              <th>Noi dung</th>
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
          <h3>Noi dung email</h3>
          <button type="button" @click="selectedEmail = null">Dong</button>
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

export default {
  name: 'AdminPlatformFeeLedgerDetail',
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
      await this.run(() => confirmLedgerPayment(this.ledger.id, { amount: this.ledger.remaining_amount }), 'Da xac nhan thanh toan du.');
    },
    async markOverdueNow() {
      const reason = prompt('Nhap ly do qua han:', 'Qua han thanh toan');
      if (!reason) return;
      await this.run(() => markLedgerOverdue(this.ledger.id, reason), 'Da danh dau qua han.');
    },
    async lockVenue() {
      const reason = prompt('Nhap ly do khoa cum san:', 'Qua han phi duy tri he thong');
      if (!reason) return;
      await this.run(() => lockVenueForOverdueLedger(this.ledger.id, reason), 'Da khoa cum san.');
    },
    async unlockVenue() {
      await this.run(() => unlockVenueAfterPayment(this.ledger.id), 'Da mo khoa cum san.');
    },
    async sendCurrentReminder() {
      const type = getReminderTypeForDate(this.ledger, new Date());
      if (!type) {
        this.showMessage('Hom nay khong dung moc gui email cho ky phi nay.', 'error');
        return;
      }
      await this.run(() => sendPlatformFeeReminderEmail(this.ledger, type), 'Da xu ly email nhac phi.');
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
      return { pending: 'Cho thanh toan', paid: 'Da thanh toan', overdue: 'Qua han', cancelled: 'Da huy' }[status] || status;
    },
    reminderLabel(type) {
      return {
        due_soon_7_days: 'Nhac truoc han 7 ngay',
        due_today: 'Nhac dung ngay den han',
        overdue_3_days: 'Canh bao qua han 3 ngay',
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
.page-head, .panel-head, .actions, .modal-head { display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, h3, p { margin: 0; }
.grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.metric span, .info-grid span { display: block; color: #64748b; font-size: 12px; }
.metric strong { display: block; font-size: 22px; }
.info-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.info-grid div, .receipt { background: #f8fafc; border-radius: 8px; padding: 12px; }
.badge, .email-chip { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 900; }
.badge.pending { background: #fef3c7; color: #92400e; }
.badge.paid { background: #dcfce7; color: #166534; }
.badge.overdue { background: #fee2e2; color: #991b1b; }
.badge.cancelled { background: #f1f5f9; color: #475569; }
.email-chip { background: #e0f2fe; color: #075985; }
.btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.btn.danger { background: #dc2626; color: #fff; }
.btn:disabled { opacity: .45; cursor: not-allowed; }
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
.modal-head button { border: 0; background: transparent; font-weight: 900; cursor: pointer; }
@media (max-width: 900px) {
  .grid, .info-grid { grid-template-columns: 1fr 1fr; }
  .page-head, .panel-head { flex-direction: column; }
}
</style>
