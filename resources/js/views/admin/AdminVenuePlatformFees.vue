<template>
  <section class="venue-fees">
    <button class="link-btn" type="button" @click="$router.push({ name: 'admin-venue-clusters' })">Quay lai cum san</button>

    <div v-if="!venue" class="panel empty">Khong tim thay cum san.</div>
    <template v-else>
      <header class="panel page-head">
        <div>
          <p class="eyebrow">Phi duy tri theo cum san</p>
          <h2>{{ venue.name }}</h2>
          <p>{{ venue.owner.full_name }} - {{ venue.court_count }} san hien tai</p>
        </div>
        <span class="badge" :class="venue.status">{{ venue.status === 'locked' ? 'Da khoa' : 'Hoat dong' }}</span>
      </header>

      <div v-if="snapshotChanged" class="notice">
        So san cua cum da thay doi. Cac ky phi da tao giu nguyen snapshot cu. Ky phi tiep theo se dung so san moi.
      </div>

      <section class="panel">
        <h3>Preview phi theo ky</h3>
        <div class="preview-grid">
          <div v-for="month in periods" :key="month" class="preview-card">
            <span>{{ month }} thang</span>
            <strong>{{ previewFor(month).error || money(previewFor(month).fee.amount_due) }}</strong>
            <small>{{ previewFor(month).tier?.name || '' }}</small>
            <button class="btn primary" type="button" :disabled="Boolean(previewFor(month).error)" @click="createFor(month)">Tao ky phi</button>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>Ledger cua cum san</h3>
          <div class="actions">
            <button class="btn danger" type="button" :disabled="!canLock" @click="lockVenue">Khoa cum</button>
            <button class="btn secondary" type="button" :disabled="!canUnlock" @click="unlockVenue">Mo khoa</button>
          </div>
        </div>
        <div v-if="ledgers.length === 0" class="empty compact">Chua co ky phi. Hay tao ky phi moi.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Ma</th>
              <th>Ky</th>
              <th>So san snapshot</th>
              <th>Bac phi</th>
              <th>Han thanh toan</th>
              <th>Con thieu</th>
              <th>Status</th>
              <th>Email gan nhat</th>
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
              <td><span class="badge" :class="ledger.status">{{ ledger.status }}</span></td>
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
import { platformFeeStore } from '../../stores/platformFee.store.js';
import {
  calculateLedgerPreview,
  createLedger,
  getLedgersByVenue,
  lockVenueForOverdueLedger,
  unlockVenueAfterPayment,
} from '../../services/platformFeeLedger.service.js';

export default {
  name: 'AdminVenuePlatformFees',
  data() {
    return {
      venue: null,
      ledgers: [],
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
      this.venue = platformFeeStore.state.venues.find((venue) => venue.id === this.$route.params.id);
      this.ledgers = await getLedgersByVenue(this.$route.params.id);
    },
    previewFor(month) {
      return calculateLedgerPreview({
        venue_cluster_id: this.$route.params.id,
        period_months: month,
        period_start: new Date().toISOString().slice(0, 10),
      });
    },
    async createFor(month) {
      try {
        await createLedger({
          venue_cluster_id: this.$route.params.id,
          period_months: month,
          period_start: new Date().toISOString().slice(0, 10),
        });
        this.showMessage('Da tao ky phi moi.');
        await this.loadData();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async lockVenue() {
      const ledger = this.ledgers.find((item) => item.status === 'overdue');
      const reason = prompt('Nhap ly do khoa:', 'Qua han phi duy tri he thong');
      if (!ledger || !reason) return;
      await this.run(() => lockVenueForOverdueLedger(ledger.id, reason), 'Da khoa cum san.');
    },
    async unlockVenue() {
      const ledger = this.ledgers.find((item) => item.status === 'paid');
      if (!ledger) return;
      await this.run(() => unlockVenueAfterPayment(ledger.id), 'Da mo khoa cum san.');
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
      return log ? `${log.type} - ${log.status}` : 'Chua gui';
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
.page-head, .panel-head, .actions { display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; }
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
.badge { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 900; }
.badge.active, .badge.paid { background: #dcfce7; color: #166534; }
.badge.locked, .badge.overdue { background: #fee2e2; color: #991b1b; }
.badge.pending { background: #fef3c7; color: #92400e; }
.badge.cancelled { background: #f1f5f9; color: #475569; }
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
