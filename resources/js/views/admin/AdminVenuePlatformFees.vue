<template>
  <section class="venue-fees">
    <button class="link-btn" type="button" @click="$router.push({ name: 'admin-venue-clusters' })">Quay láº¡i cá»¥m sÃ¢n</button>

    <div v-if="!venue" class="panel empty">KhÃ´ng tÃ¬m tháº¥y cá»¥m sÃ¢n.</div>
    <template v-else>
      <div class="venue-info-bar">
        <h2>{{ venue.name }}</h2>
        <span class="status-dot" :class="venue.status" :title="venue.status === 'locked' ? 'ÄÃ£ khÃ³a' : 'Hoáº¡t Ä‘á»™ng'" :aria-label="venue.status === 'locked' ? 'ÄÃ£ khÃ³a' : 'Hoáº¡t Ä‘á»™ng'"></span>
      </div>

      <div v-if="snapshotChanged" class="notice">
        Sá»‘ sÃ¢n cá»§a cá»¥m Ä‘Ã£ thay Ä‘á»•i. CÃ¡c ká»³ phÃ­ Ä‘Ã£ táº¡o giá»¯ nguyÃªn snapshot cÅ©. Ká»³ phÃ­ tiáº¿p theo sáº½ dÃ¹ng sá»‘ sÃ¢n má»›i.
      </div>

      <section class="panel">
        <h3>Xem trÆ°á»›c phÃ­ theo ká»³</h3>
        <div class="preview-grid">
          <div v-for="month in periods" :key="month" class="preview-card">
            <span>{{ month }} thÃ¡ng</span>
            <strong>{{ previewFor(month).error || money(previewFor(month).fee.amount_due) }}</strong>
            <small>{{ previewFor(month).tier?.name || '' }}</small>
            <button class="btn primary icon-text" type="button" :disabled="Boolean(previewFor(month).error)" @click="createFor(month)">
              <AppIcon name="plus" size="18" />
              <span>Táº¡o ká»³ phÃ­</span>
            </button>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>Ledger cá»§a cá»¥m sÃ¢n</h3>
          <div class="actions">
            <button class="btn danger icon-text" type="button" :disabled="!canLock" @click="lockVenue">
              <AppIcon name="lock" size="18" />
              <span>KhÃ³a cá»¥m</span>
            </button>
            <button class="btn secondary icon-text" type="button" :disabled="!canUnlock" @click="unlockVenue">
              <AppIcon name="unlock" size="18" />
              <span>Má»Ÿ khÃ³a</span>
            </button>
          </div>
        </div>
        <div v-if="ledgers.length === 0" class="empty compact">ChÆ°a cÃ³ ká»³ phÃ­. HÃ£y táº¡o ká»³ phÃ­ má»›i.</div>
        <table v-else>
          <thead>
            <tr>
              <th>MÃ£</th>
              <th>Ká»³</th>
              <th>Sá»‘ sÃ¢n snapshot</th>
              <th>Báº­c phÃ­</th>
              <th>Háº¡n thanh toÃ¡n</th>
              <th>CÃ²n thiáº¿u</th>
              <th>Tráº¡ng thÃ¡i</th>
              <th>Email gáº§n nháº¥t</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ledger in ledgers" :key="ledger.id">
              <td><router-link :to="{ name: 'admin-platform-fee-ledger-detail', params: { id: ledger.id } }">{{ ledger.code }}</router-link></td>
              <td>{{ date(ledger.period_start) }} - {{ date(ledger.period_end) }}</td>
              <td>{{ ledger.court_count }}</td>
              <td>{{ ledger.tier_name }}</td>
              <td>{{ date(ledger.due_date) }}</td>
              <td>{{ money(ledger.remaining_amount) }}</td>
              <td><span class="status-dot" :class="ledger.status" :title="statusLabel(ledger.status)" :aria-label="statusLabel(ledger.status)"></span></td>
              <td>{{ latestEmail(ledger) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
    <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>
  </section>
</template>

<script>
import {
  calculateLedgerPreview,
  createLedger,
  getLedgersByVenue,
  lockVenueForOverdueLedger,
  unlockVenueAfterPayment,
} from '../../services/platformFeeLedger.service.js';
import { adminVenueClusterService } from '../../services/adminVenueClusterService.js';
import AppIcon from '../../components/AppIcon.vue';

export default {
  name: 'AdminVenuePlatformFees',
  components: { AppIcon },
  data() {
    return {
      venue: null,
      ledgers: [],
      previews: {},
      periods: [1, 3, 6, 9, 12],
      toast: '',
      toastType: 'success',
    };
  },
  computed: {
    snapshotChanged() {
      const latest = this.ledgers[0];
      return latest && latest.court_count !== this.venue.court_count;
    },
    canLock() {
      return this.ledgers.some((ledger) => ledger.status === 'overdue') && this.venue.status !== 'locked';
    },
    canUnlock() {
      return this.venue.status === 'locked' && this.ledgers.some((ledger) => ledger.status === 'paid');
    },
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      const [venueResponse, ledgers] = await Promise.all([
        adminVenueClusterService.show(this.$route.params.id),
        getLedgersByVenue(this.$route.params.id),
      ]);
      this.venue = venueResponse.data?.cluster || venueResponse.data || venueResponse;
      this.ledgers = ledgers;
      await this.loadPreviews();
    },
    async loadPreviews() {
      const entries = await Promise.all(
        this.periods.map(async (month) => {
          try {
            const preview = await calculateLedgerPreview({
              venue_cluster_id: this.$route.params.id,
              period_months: month,
              period_start: new Date().toISOString().slice(0, 10),
            });
            return [month, preview];
          } catch (error) {
            return [month, { isValid: false, error: error.message }];
          }
        }),
      );
      this.previews = Object.fromEntries(entries);
    },
    previewFor(month) {
      return this.previews[month] || {
        isValid: false,
        error: 'Äang táº£i...',
        fee: { amount_due: 0 },
      };
    },
    async createFor(month) {
      try {
        await createLedger({
          venue_cluster_id: this.$route.params.id,
          period_months: month,
          period_start: new Date().toISOString().slice(0, 10),
        });
        this.showMessage('ÄÃ£ táº¡o ká»³ phÃ­ má»›i.');
        await this.loadData();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async lockVenue() {
      const ledger = this.ledgers.find((item) => item.status === 'overdue');
      const reason = 'Quá hạn phí duy trì hệ thống';
      if (!ledger) return;
      await this.run(() => lockVenueForOverdueLedger(ledger.id, reason), 'Đã khóa cụm sân.');
    },    async unlockVenue() {
      const ledger = this.ledgers.find((item) => item.status === 'paid');
      if (!ledger) return;
      await this.run(() => unlockVenueAfterPayment(ledger.id), 'ÄÃ£ má»Ÿ khÃ³a cá»¥m sÃ¢n.');
    },
    async run(action, message) {
      try {
        await action();
        this.showMessage(message);
        await this.loadData();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    latestEmail(ledger) {
      const log = ledger.email_logs?.[0];
      return log ? `${this.reminderLabel(log.type)} - ${this.emailStatusLabel(log.status)}` : 'ChÆ°a gá»­i';
    },
    statusLabel(status) {
      return { pending: 'Chá» thanh toÃ¡n', paid: 'ÄÃ£ thanh toÃ¡n', overdue: 'QuÃ¡ háº¡n', cancelled: 'ÄÃ£ há»§y', active: 'Hoáº¡t Ä‘á»™ng', locked: 'ÄÃ£ khÃ³a' }[status] || status;
    },
    reminderLabel(type) {
      return {
        due_soon_7_days: 'Nháº¯c trÆ°á»›c háº¡n 7 ngÃ y',
        due_today: 'Nháº¯c Ä‘Ãºng háº¡n',
        overdue_3_days: 'Cáº£nh bÃ¡o quÃ¡ háº¡n 3 ngÃ y',
      }[type] || type;
    },
    emailStatusLabel(status) {
      return { sent: 'Ä‘Ã£ gá»­i', failed: 'lá»—i', queued: 'Ä‘ang chá»' }[status] || status;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
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
.venue-fees { display: flex; flex-direction: column; gap: 16px; }
.panel, .preview-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
.venue-info-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.panel-head, .actions, .icon-text { display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, h3, p { margin: 0; }
.notice { padding: 12px 14px; border-radius: 8px; background: #fef3c7; color: #92400e; font-weight: 800; }
.preview-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.preview-card span, .preview-card small { display: block; color: #64748b; }
.preview-card strong { display: block; margin: 6px 0 12px; }
.btn { border: 0; border-radius: 8px; padding: 9px 12px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.btn.danger { background: #dc2626; color: #fff; }
.btn:disabled { opacity: .45; cursor: not-allowed; }
.icon-text { align-items: center; justify-content: center; }
.status-dot {
  display: inline-grid;
  width: 14px;
  height: 14px;
  border-radius: 999px;
  background: #f59e0b;
  box-shadow: 0 0 0 3px #fef3c7;
}
.status-dot.active,
.status-dot.paid {
  background: #10b981;
  box-shadow: 0 0 0 3px #d1fae5;
}
.status-dot.locked,
.status-dot.overdue {
  background: #ef4444;
  box-shadow: 0 0 0 3px #fee2e2;
}
.status-dot.cancelled {
  background: #94a3b8;
  box-shadow: 0 0 0 3px #e2e8f0;
}
table { width: 100%; border-collapse: collapse; }
th, td { padding: 11px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
.link-btn { border: 0; background: transparent; color: #047857; font-weight: 900; cursor: pointer; width: fit-content; }
.empty { text-align: center; color: #64748b; }
.compact { padding: 24px; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; }
.toast.success { background: #ecfdf5; color: #047857; }
.toast.error { background: #fef2f2; color: #991b1b; }
@media (max-width: 1000px) { .preview-grid { grid-template-columns: 1fr 1fr; } }
</style>
