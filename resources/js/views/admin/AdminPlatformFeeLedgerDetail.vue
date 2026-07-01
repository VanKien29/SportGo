<template>
  <section class="detail-page">
    <button class="link-btn" type="button" @click="$router.push({ name: 'admin-platform-fee-ledgers' })">Quay láº¡i danh sÃ¡ch</button>

    <div v-if="!ledger" class="panel empty">KhÃ´ng tÃ¬m tháº¥y ká»³ phÃ­.</div>
    <template v-else>
      <!-- Ledger Info Bar -->
      <div class="ledger-info-bar" style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
        <h2 style="margin: 0;">{{ ledger.code }}</h2>
        <span style="color: #64748b; font-size: 14px; font-weight: 600;">{{ ledger.venue?.name }} - {{ ledger.owner?.full_name }}</span>
        <span class="status-dot" :class="ledger.status" :title="statusLabel(ledger.status)" :aria-label="statusLabel(ledger.status)"></span>
      </div>

      <section class="grid">
        <div class="panel metric"><span>Sá»‘ tiá»n pháº£i Ä‘Ã³ng</span><strong>{{ money(ledger.amount_due) }}</strong></div>
        <div class="panel metric"><span>ÄÃ£ Ä‘Ã³ng</span><strong>{{ money(ledger.amount_paid) }}</strong></div>
        <div class="panel metric"><span>CÃ²n thiáº¿u</span><strong>{{ money(ledger.remaining_amount) }}</strong></div>
        <div class="panel metric"><span>Háº¡n thanh toÃ¡n</span><strong>{{ date(ledger.due_date) }}</strong></div>
      </section>

      <section class="panel">
        <h3>Snapshot ká»³ phÃ­</h3>
        <div class="info-grid">
          <div><span>Cá»¥m sÃ¢n</span><strong>{{ ledger.venue?.name }}</strong></div>
          <div><span>Chá»§ sÃ¢n</span><strong>{{ ledger.owner?.full_name }}</strong></div>
          <div><span>Sá»‘ sÃ¢n snapshot</span><strong>{{ ledger.court_count }}</strong></div>
          <div><span>Báº­c phÃ­ snapshot</span><strong>{{ ledger.tier_name }}</strong></div>
          <div><span>Ká»³ Ä‘Ã³ng</span><strong>{{ ledger.period_months }} thÃ¡ng</strong></div>
          <div><span>Thá»i gian ká»³ phÃ­</span><strong>{{ date(ledger.period_start) }} - {{ date(ledger.period_end) }}</strong></div>
          <div><span>Tráº¡ng thÃ¡i hiá»‡u lá»±c</span><strong :class="ledger.period_warning_level">{{ periodStatusLabel(ledger) }}</strong></div>
          <div><span>Khoáº£ng sÃ¢n snapshot</span><strong>{{ tierRangeLabel(ledger) }}</strong></div>
          <div><span>GiÃ¡/sÃ¢n/thÃ¡ng snapshot</span><strong>{{ money(ledger.price_per_court_month) }}</strong></div>
          <div><span>Giáº£m giÃ¡ snapshot</span><strong>{{ percent(ledger.discount_percent) }}</strong></div>
          <div><span>Thá»i Ä‘iá»ƒm chá»‘t giÃ¡</span><strong>{{ dateTime(ledger.pricing_snapshotted_at) }}</strong></div>
          <div><span>Tá»•ng trÆ°á»›c giáº£m</span><strong>{{ money(ledger.base_amount) }}</strong></div>
          <div><span>Sá»‘ tiá»n giáº£m</span><strong>{{ money(ledger.discount_amount) }}</strong></div>
          <div><span>NgÃ y thanh toÃ¡n</span><strong>{{ ledger.paid_at ? date(ledger.paid_at) : '-' }}</strong></div>
          <div><span>LÃ½ do há»§y</span><strong>{{ ledger.cancelled_reason || '-' }}</strong></div>
        </div>
      </section>

      <section class="panel">
        <h3>HÃ nh Ä‘á»™ng</h3>
        <div class="actions">
          <button class="btn primary icon-text" type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="payFull">
            <AppIcon name="creditCard" size="18" />
            <span>XÃ¡c nháº­n thanh toÃ¡n Ä‘á»§</span>
          </button>
          <button class="btn secondary icon-text" type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="markOverdueNow">
            <AppIcon name="clock" size="18" />
            <span>ÄÃ¡nh dáº¥u quÃ¡ háº¡n</span>
          </button>
          <button class="btn danger icon-text" type="button" :disabled="ledger.status !== 'overdue'" @click="lockVenue">
            <AppIcon name="lock" size="18" />
            <span>KhÃ³a cá»¥m sÃ¢n</span>
          </button>
          <button class="btn secondary icon-text" type="button" :disabled="ledger.status !== 'paid'" @click="unlockVenue">
            <AppIcon name="unlock" size="18" />
            <span>Má»Ÿ khÃ³a sau thanh toÃ¡n</span>
          </button>
          <button class="btn danger icon-text" type="button" :disabled="!canCancelLedger" @click="cancelCurrentLedger">
            <AppIcon name="trash" size="18" />
            <span>Há»§y ká»³ phÃ­</span>
          </button>
        </div>
      </section>

      <section class="panel">
        <h3>Phiáº¿u thu</h3>
        <div v-if="ledger.receipt" class="receipt">
          <strong>{{ ledger.receipt.code }}</strong>
          <span>{{ money(ledger.receipt.amount) }} - {{ date(ledger.receipt.issued_at) }}</span>
          <p>{{ ledger.receipt.content }}</p>
        </div>
        <div v-else class="empty compact">ChÆ°a cÃ³ phiáº¿u thu.</div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>Lá»‹ch sá»­ email nháº¯c thanh toÃ¡n</h3>
          <button class="btn secondary icon-text" type="button" @click="sendCurrentReminder">
            <AppIcon name="bell" size="18" />
            <span>Gá»­i nháº¯c phÃ­ theo ngÃ y hiá»‡n táº¡i</span>
          </button>
        </div>
        <div v-if="emailLogs.length === 0" class="empty compact">ChÆ°a cÃ³ email nháº¯c phÃ­ nÃ o Ä‘Æ°á»£c gá»­i.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Loáº¡i email</th>
              <th>Email nháº­n</th>
              <th>TiÃªu Ä‘á»</th>
              <th>Tráº¡ng thÃ¡i</th>
              <th>Thá»i gian gá»­i</th>
              <th>LÃ½ do lá»—i</th>
              <th>Ná»™i dung</th>
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
          <h3>Ná»™i dung email</h3>
          <button class="icon-close" type="button" title="ÄÃ³ng" aria-label="ÄÃ³ng" @click="selectedEmail = null">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <p>{{ selectedEmail.content }}</p>
      </div>
    </div>

    <div v-if="cancelDialogOpen" class="modal-backdrop" @click.self="closeCancelDialog">
      <form class="modal cancel-modal" @submit.prevent="submitCancellation">
        <header class="modal-head">
          <h3>XÃ¡c nháº­n há»§y ká»³ phÃ­</h3>
          <button class="icon-close" type="button" title="ÄÃ³ng" aria-label="ÄÃ³ng" @click="closeCancelDialog">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <p class="cancel-warning">Chá»‰ ká»³ chÆ°a ghi nháº­n tiá»n má»›i Ä‘Æ°á»£c há»§y. Dá»¯ liá»‡u ká»³ Ä‘Ã£ thanh toÃ¡n sáº½ khÃ´ng bá»‹ thay Ä‘á»•i.</p>
        <label>
          LÃ½ do há»§y *
          <textarea v-model.trim="cancelReason" rows="4" maxlength="500"></textarea>
        </label>
        <footer class="cancel-actions">
          <button class="btn secondary" type="button" @click="closeCancelDialog">Quay láº¡i</button>
          <button class="btn danger icon-text" type="submit" :disabled="!cancelReason">
            <AppIcon name="trash" size="18" />
            <span>XÃ¡c nháº­n há»§y</span>
          </button>
        </footer>
      </form>
    </div>

    <div v-if="actionDialog" class="modal-backdrop" @click.self="closeActionDialog">
      <form class="modal cancel-modal" @submit.prevent="submitActionDialog">
        <header class="modal-head">
          <h3>{{ actionDialog.title }}</h3>
          <button class="icon-close" type="button" title="Ã„ÂÃƒÂ³ng" aria-label="Ã„ÂÃƒÂ³ng" @click="closeActionDialog">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <label>
          LÃ½ do *
          <textarea v-model.trim="actionDialog.reason" rows="4" maxlength="500"></textarea>
        </label>
        <footer class="cancel-actions">
          <button class="btn secondary" type="button" @click="closeActionDialog">Quay lÃ¡ÂºÂ¡i</button>
          <button class="btn danger icon-text" type="submit" :disabled="!actionDialog.reason">
            <AppIcon :name="actionDialog.icon" size="18" />
            <span>{{ actionDialog.confirmLabel }}</span>
          </button>
        </footer>
      </form>
    </div>

    <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>
  </section>
</template>

<script>
import {
  cancelLedger,
  confirmLedgerPayment,
  getLedgerById,
  lockVenueForOverdueLedger,
  markLedgerOverdue,
  unlockVenueAfterPayment,
} from '../../services/platformFeeLedger.service.js';
import {
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
      cancelDialogOpen: false,
      cancelReason: '',
      actionDialog: null,
      toast: '',
      toastType: 'success',
    };
  },
  mounted() {
    this.loadDetail();
  },
  computed: {
    canCancelLedger() {
      return this.ledger && ['pending', 'overdue'].includes(this.ledger.status) && Number(this.ledger.amount_paid || 0) <= 0;
    },
  },
  methods: {
    async loadDetail() {
      this.ledger = await getLedgerById(this.$route.params.id);
      this.emailLogs = this.ledger?.email_logs || [];
    },
    async payFull() {
      await this.run(() => confirmLedgerPayment(this.ledger.id, { amount: this.ledger.remaining_amount }), 'ÄÃ£ xÃ¡c nháº­n thanh toÃ¡n Ä‘á»§.');
    },
    async markOverdueNow() {
      this.actionDialog = {
        type: 'overdue',
        title: 'Đánh dấu quá hạn',
        reason: 'Quá hạn thanh toán',
        confirmLabel: 'Đánh dấu quá hạn',
        icon: 'clock',
      };
    },
    async lockVenue() {
      this.actionDialog = {
        type: 'lock',
        title: 'Khóa cụm sân',
        reason: 'Quá hạn phí duy trì hệ thống',
        confirmLabel: 'Khóa cụm sân',
        icon: 'lock',
      };
    },
    closeActionDialog() {
      this.actionDialog = null;
    },
    async submitActionDialog() {
      if (!this.actionDialog?.reason) return;
      const dialog = this.actionDialog;
      this.closeActionDialog();
      if (dialog.type === 'overdue') {
        await this.run(() => markLedgerOverdue(this.ledger.id, dialog.reason), 'Đã đánh dấu quá hạn.');
      } else if (dialog.type === 'lock') {
        await this.run(() => lockVenueForOverdueLedger(this.ledger.id, dialog.reason), 'Đã khóa cụm sân.');
      }
    },    async unlockVenue() {
      await this.run(() => unlockVenueAfterPayment(this.ledger.id), 'ÄÃ£ má»Ÿ khÃ³a cá»¥m sÃ¢n.');
    },
    async cancelCurrentLedger() {
      if (!this.canCancelLedger) return;
      this.cancelReason = 'Admin há»§y ká»³ phÃ­ chÆ°a xá»­ lÃ½';
      this.cancelDialogOpen = true;
    },
    closeCancelDialog() {
      this.cancelDialogOpen = false;
      this.cancelReason = '';
    },
    async submitCancellation() {
      if (!this.canCancelLedger || !this.cancelReason) return;
      const reason = this.cancelReason;
      this.closeCancelDialog();
      await this.run(() => cancelLedger(this.ledger.id, reason), 'ÄÃ£ há»§y ká»³ phÃ­.');
    },
    async sendCurrentReminder() {
      const type = getReminderTypeForDate(this.ledger) || 'manual';
      await this.run(
        () => sendPlatformFeeReminderEmail(this.ledger, type, { force: true }),
        'ÄÃ£ gá»­i email nháº¯c phÃ­.',
      );
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
      return { pending: 'Chá» thanh toÃ¡n', paid: 'ÄÃ£ thanh toÃ¡n', overdue: 'QuÃ¡ háº¡n', cancelled: 'ÄÃ£ há»§y' }[status] || status;
    },
    reminderLabel(type) {
      return {
        due_soon_7_days: 'Nháº¯c trÆ°á»›c háº¡n 7 ngÃ y',
        due_today: 'Nháº¯c Ä‘Ãºng ngÃ y Ä‘áº¿n háº¡n',
        overdue_3_days: 'Cáº£nh bÃ¡o quÃ¡ háº¡n 3 ngÃ y',
        manual: 'Nháº¯c thá»§ cÃ´ng',
      }[type] || type;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    percent(value) {
      return `${Number(value || 0).toLocaleString('vi-VN')}%`;
    },
    periodRemainingLabel(ledger) {
      if (ledger.period_state === 'upcoming') return 'ChÆ°a báº¯t Ä‘áº§u';
      if (ledger.period_state === 'expired') return 'ÄÃ£ háº¿t háº¡n ' + Math.abs(ledger.period_days_remaining || 0) + ' ngÃ y';
      if (ledger.period_days_remaining === 0) return 'Háº¿t háº¡n hÃ´m nay';
      if (ledger.period_days_remaining !== null && ledger.period_days_remaining !== undefined) return 'CÃ²n ' + ledger.period_days_remaining + ' ngÃ y';
      return 'ChÆ°a cáº­p nháº­t';
    },
    periodStatusLabel(ledger) {
      const state = {
        active: 'Äang hiá»‡u lá»±c',
        upcoming: 'Sáº¯p Ã¡p dá»¥ng',
        expired: 'ÄÃ£ háº¿t háº¡n',
        unknown: 'ChÆ°a rÃµ thá»i gian',
      }[ledger.period_state] || '';
      return state ? state + ' Â· ' + this.periodRemainingLabel(ledger) : this.periodRemainingLabel(ledger);
    },
    tierRangeLabel(ledger) {
      const min = ledger.tier_min_courts_snapshot;
      const max = ledger.tier_max_courts_snapshot;
      if (min === null || min === undefined) return 'ChÆ°a lÆ°u';
      return max === null || max === undefined ? `Tá»« ${min} sÃ¢n` : `${min} - ${max} sÃ¢n`;
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
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
.expiring_soon { color: #92400e; }
.overdue { color: #b91c1c; }
.modal-backdrop { position: fixed; inset: 0; z-index: 900; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.55); }
.modal { width: min(560px, calc(100vw - 32px)); background: #fff; border-radius: 8px; padding: 18px; }
.cancel-modal { display: grid; gap: 16px; }
.cancel-modal label { display: grid; gap: 7px; color: #334155; font-weight: 800; }
.cancel-modal textarea { width: 100%; resize: vertical; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font: inherit; }
.cancel-warning { padding: 12px; border-radius: 8px; background: #fff7ed; color: #9a3412; line-height: 1.5; }
.cancel-actions { display: flex; justify-content: flex-end; gap: 10px; }
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
