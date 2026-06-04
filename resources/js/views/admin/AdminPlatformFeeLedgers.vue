<template>
  <section class="ledger-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">So phi duy tri</p>
        <h2>Quan ly phi duy tri he thong</h2>
        <p>Xem, tao, theo doi, xac nhan thanh toan, danh dau qua han va khoa/mo khoa cum san.</p>
      </div>
      <div class="head-actions">
        <button class="btn secondary" type="button" @click="runReminderCheck">Chay kiem tra nhac phi</button>
        <button class="btn primary" type="button" @click="openCreate">Tao ky phi</button>
      </div>
    </header>

    <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>

    <section class="panel filter-grid">
      <select v-model="filters.venue_cluster_id" @change="loadLedgers">
        <option value="">Tat ca cum san</option>
        <option v-for="venue in venues" :key="venue.id" :value="venue.id">{{ venue.name }}</option>
      </select>
      <select v-model="filters.owner_id" @change="loadLedgers">
        <option value="">Tat ca owner</option>
        <option v-for="owner in owners" :key="owner.id" :value="owner.id">{{ owner.full_name }}</option>
      </select>
      <select v-model="filters.status" @change="loadLedgers">
        <option value="">Tat ca status</option>
        <option value="pending">Cho thanh toan</option>
        <option value="paid">Da thanh toan</option>
        <option value="overdue">Qua han</option>
        <option value="cancelled">Da huy</option>
      </select>
      <select v-model="filters.period_months" @change="loadLedgers">
        <option value="">Tat ca ky dong</option>
        <option v-for="month in periods" :key="month" :value="month">{{ month }} thang</option>
      </select>
      <input v-model="filters.period_start" type="date" @change="loadLedgers" />
      <input v-model="filters.period_end" type="date" @change="loadLedgers" />
      <input v-model="filters.due_date" type="date" @change="loadLedgers" />
      <select v-model="filters.email_status" @change="loadLedgers">
        <option value="">Tat ca email</option>
        <option value="due_soon_7_days">Da gui nhac truoc han</option>
        <option value="due_today">Da gui nhac dung han</option>
        <option value="overdue_3_days">Da gui canh bao qua han 3 ngay</option>
        <option value="not_sent">Chua gui nhac phi</option>
        <option value="failed">Gui email loi</option>
      </select>
      <label class="check-row">
        <input v-model="filters.overdue_only" type="checkbox" @change="loadLedgers" />
        <span>Chi xem qua han</span>
      </label>
      <input v-model.trim="filters.keyword" placeholder="Tim ma ky phi, cum san, owner" @input="loadLedgers" />
    </section>

    <section class="kpi-grid">
      <router-link class="kpi-card" to="/admin/platform-fee-ledgers?status=pending">
        <strong>{{ metrics.pending }}</strong><span>Pending</span>
      </router-link>
      <router-link class="kpi-card danger" to="/admin/platform-fee-ledgers?status=overdue">
        <strong>{{ metrics.overdue }}</strong><span>Overdue</span>
      </router-link>
      <article class="kpi-card"><strong>{{ money(metrics.pending_amount) }}</strong><span>Cho thanh toan</span></article>
      <article class="kpi-card danger"><strong>{{ money(metrics.overdue_amount) }}</strong><span>Qua han</span></article>
    </section>

    <section class="panel">
      <div v-if="loading" class="empty">Dang tai danh sach ky phi...</div>
      <div v-else-if="ledgers.length === 0" class="empty">Chua co ky phi. Hay tao ky phi moi.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Ma ky phi</th>
              <th>Cum san</th>
              <th>Chu san</th>
              <th>So san</th>
              <th>Bac phi snapshot</th>
              <th>Ky dong</th>
              <th>Thoi gian ky phi</th>
              <th>Han thanh toan</th>
              <th>Gia snapshot</th>
              <th>Giam</th>
              <th>Phai dong</th>
              <th>Da dong</th>
              <th>Con thieu</th>
              <th>Trang thai</th>
              <th>Ngay thanh toan</th>
              <th>Email</th>
              <th>Hanh dong</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ledger in ledgers" :key="ledger.id">
              <td class="mono">{{ ledger.code }}</td>
              <td>{{ ledger.venue?.name || '-' }}</td>
              <td>{{ ledger.owner?.full_name || '-' }}</td>
              <td>{{ ledger.court_count }}</td>
              <td>{{ ledger.tier_name }}</td>
              <td>{{ ledger.period_months }} thang</td>
              <td>{{ date(ledger.period_start) }} - {{ date(ledger.period_end) }}</td>
              <td :class="{ overdue: ledger.status === 'overdue' }">{{ date(ledger.due_date) }}</td>
              <td>{{ money(ledger.price_per_court_month) }}</td>
              <td>{{ percent(ledger.discount_percent) }}</td>
              <td>{{ money(ledger.amount_due) }}</td>
              <td>{{ money(ledger.amount_paid) }}</td>
              <td>{{ money(ledger.remaining_amount) }}</td>
              <td><span class="badge" :class="ledger.status">{{ statusLabel(ledger.status) }}</span></td>
              <td>{{ ledger.paid_at ? date(ledger.paid_at) : '-' }}</td>
              <td>{{ emailSummary(ledger) }}</td>
              <td>
                <div class="actions">
                  <button type="button" @click="$router.push({ name: 'admin-platform-fee-ledger-detail', params: { id: ledger.id } })">Xem</button>
                  <button type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="openPay(ledger)">Thanh toan</button>
                  <button type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="markOverdue(ledger)">Qua han</button>
                  <button type="button" :disabled="ledger.status === 'paid' || ledger.status === 'cancelled'" @click="openCancel(ledger)">Huy</button>
                  <button type="button" :disabled="ledger.status !== 'overdue'" @click="openLock(ledger)">Khoa cum</button>
                  <button type="button" :disabled="ledger.status !== 'paid'" @click="unlockVenue(ledger)">Mo khoa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="showCreate" class="modal-backdrop" @click.self="closeCreate">
      <form class="modal" @submit.prevent="createNewLedger">
        <header class="modal-head">
          <h3>Tao ky phi duy tri</h3>
          <button type="button" @click="closeCreate">Dong</button>
        </header>
        <div class="form-grid">
          <label>
            Cum san *
            <select v-model="form.venue_cluster_id" required @change="refreshPreview">
              <option value="">Chon cum san</option>
              <option v-for="venue in venues" :key="venue.id" :value="venue.id">{{ venue.name }} - {{ venue.court_count }} san</option>
            </select>
          </label>
          <label>
            Ky dong *
            <select v-model.number="form.period_months" @change="refreshPreview">
              <option v-for="month in periods" :key="month" :value="month">{{ month }} thang</option>
            </select>
          </label>
          <label>
            Ngay bat dau *
            <input v-model="form.period_start" type="date" required @change="refreshPreview" />
          </label>
          <label>
            Han thanh toan
            <input v-model="form.due_date" type="date" @change="refreshPreview" />
          </label>
        </div>
        <div v-if="previewError" class="alert error">{{ previewError }}</div>
        <div v-if="previewResult" class="preview-grid">
          <div><span>So san snapshot</span><strong>{{ previewResult.court_count }}</strong></div>
          <div><span>Bac phi</span><strong>{{ previewResult.tier.name }}</strong></div>
          <div><span>Ky phi</span><strong>{{ date(previewResult.period_start) }} - {{ date(previewResult.period_end) }}</strong></div>
          <div><span>Tong phai dong</span><strong>{{ money(previewResult.fee.amount_due) }}</strong></div>
        </div>
        <div v-for="warning in previewWarnings" :key="warning" class="alert warning">{{ warning }}</div>
        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeCreate">Huy</button>
          <button class="btn primary" type="submit" :disabled="!previewResult || Boolean(previewError)">Tao ky phi</button>
        </footer>
      </form>
    </div>

    <div v-if="dialog.type" class="modal-backdrop" @click.self="closeDialog">
      <form class="modal small" @submit.prevent="submitDialog">
        <header class="modal-head">
          <h3>{{ dialogTitle }}</h3>
          <button type="button" @click="closeDialog">Dong</button>
        </header>
        <div class="form-grid one">
          <label v-if="dialog.type === 'pay'">
            So tien thanh toan *
            <input v-model.number="dialog.amount" type="number" min="1" required />
          </label>
          <label v-if="dialog.type !== 'pay'">
            Ly do *
            <textarea v-model.trim="dialog.reason" rows="4" required></textarea>
          </label>
        </div>
        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeDialog">Huy</button>
          <button class="btn primary" type="submit">Xac nhan</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import { platformFeeStore } from '../../stores/platformFee.store.js';
import {
  calculateLedgerPreview,
  cancelLedger,
  confirmLedgerPayment,
  createLedger,
  getLedgers,
  getPlatformFeeDashboardMetrics,
  lockVenueForOverdueLedger,
  markLedgerOverdue,
  unlockVenueAfterPayment,
} from '../../services/platformFeeLedger.service.js';
import { processPlatformFeeReminders } from '../../services/platformFeeReminder.service.js';

function initialFilters(routeQuery = {}) {
  return {
    venue_cluster_id: '',
    owner_id: '',
    status: routeQuery.status || '',
    period_months: '',
    period_start: '',
    period_end: '',
    due_date: '',
    overdue_only: false,
    email_status: routeQuery.email_status || '',
    range: routeQuery.range || '',
    keyword: '',
  };
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

export default {
  name: 'AdminPlatformFeeLedgers',
  data() {
    return {
      ledgers: [],
      venues: platformFeeStore.state.venues,
      filters: initialFilters(this.$route.query),
      metrics: getPlatformFeeDashboardMetrics(),
      periods: [1, 3, 6, 9, 12],
      loading: false,
      showCreate: false,
      form: { venue_cluster_id: '', period_months: 1, period_start: today(), due_date: '' },
      previewResult: null,
      previewError: '',
      previewWarnings: [],
      dialog: { type: '', ledger: null, amount: 0, reason: '' },
      toast: '',
      toastType: 'success',
    };
  },
  computed: {
    owners() {
      const map = new Map();
      this.venues.forEach((venue) => {
        if (venue.owner?.id) map.set(venue.owner.id, venue.owner);
      });
      return Array.from(map.values());
    },
    dialogTitle() {
      return {
        pay: 'Xac nhan thanh toan',
        cancel: 'Huy ky phi',
        lock: 'Khoa cum san vi qua han',
      }[this.dialog.type] || 'Xac nhan';
    },
  },
  watch: {
    '$route.query': {
      handler(query) {
        this.filters = initialFilters(query);
        this.loadLedgers();
      },
    },
  },
  mounted() {
    this.loadLedgers();
  },
  methods: {
    async loadLedgers() {
      this.loading = true;
      this.ledgers = await getLedgers(this.filters);
      this.metrics = getPlatformFeeDashboardMetrics();
      this.loading = false;
    },
    openCreate() {
      this.form = { venue_cluster_id: '', period_months: 1, period_start: today(), due_date: '' };
      this.previewResult = null;
      this.previewError = '';
      this.previewWarnings = [];
      this.showCreate = true;
    },
    closeCreate() {
      this.showCreate = false;
    },
    refreshPreview() {
      if (!this.form.venue_cluster_id) return;
      const result = calculateLedgerPreview(this.form);
      this.previewResult = result.isValid ? result : null;
      this.previewError = result.isValid ? '' : result.error;
      this.previewWarnings = result.warnings || [];
    },
    async createNewLedger() {
      try {
        await createLedger(this.form);
        this.showMessage('Da tao ky phi pending.');
        this.closeCreate();
        await this.loadLedgers();
      } catch (error) {
        this.previewError = error.message;
        this.showMessage(error.message, 'error');
      }
    },
    openPay(ledger) {
      this.dialog = { type: 'pay', ledger, amount: ledger.remaining_amount, reason: '' };
    },
    openCancel(ledger) {
      this.dialog = { type: 'cancel', ledger, amount: 0, reason: '' };
    },
    openLock(ledger) {
      this.dialog = { type: 'lock', ledger, amount: 0, reason: 'Qua han phi duy tri he thong' };
    },
    closeDialog() {
      this.dialog = { type: '', ledger: null, amount: 0, reason: '' };
    },
    async submitDialog() {
      try {
        if (this.dialog.type === 'pay') await confirmLedgerPayment(this.dialog.ledger.id, { amount: this.dialog.amount });
        if (this.dialog.type === 'cancel') await cancelLedger(this.dialog.ledger.id, this.dialog.reason);
        if (this.dialog.type === 'lock') await lockVenueForOverdueLedger(this.dialog.ledger.id, this.dialog.reason);
        this.showMessage('Thao tac thanh cong.');
        this.closeDialog();
        await this.loadLedgers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async markOverdue(ledger) {
      const reason = prompt('Nhap ly do danh dau qua han:', 'Qua han thanh toan');
      if (!reason) return;
      try {
        await markLedgerOverdue(ledger.id, reason);
        this.showMessage('Da danh dau qua han.');
        await this.loadLedgers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async unlockVenue(ledger) {
      try {
        await unlockVenueAfterPayment(ledger.id);
        this.showMessage('Da mo khoa cum san.');
        await this.loadLedgers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async runReminderCheck() {
      const logs = await processPlatformFeeReminders(new Date());
      this.showMessage(logs.length ? `Da xu ly ${logs.length} email nhac phi.` : 'Khong co email nhac phi can gui hom nay.');
      await this.loadLedgers();
    },
    emailSummary(ledger) {
      const logs = ledger.email_logs || [];
      if (!logs.length) return 'Chua gui';
      if (logs.some((log) => log.status === 'failed')) return 'Co loi';
      return `${logs.filter((log) => log.status === 'sent').length} da gui`;
    },
    statusLabel(status) {
      return { pending: 'Cho thanh toan', paid: 'Da thanh toan', overdue: 'Qua han', cancelled: 'Da huy' }[status] || status;
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
.ledger-page { display: flex; flex-direction: column; gap: 18px; }
.page-head, .head-actions, .actions, .modal-head, .modal-actions { display: flex; gap: 12px; }
.page-head { justify-content: space-between; align-items: flex-start; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, h3, p { margin: 0; }
.panel, .kpi-card, .modal { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.panel { padding: 16px; }
.filter-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; align-items: center; }
input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font: inherit; }
.check-row { flex-direction: row; align-items: center; font-weight: 800; color: #334155; }
.check-row input { width: auto; }
.kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.kpi-card { padding: 16px; text-decoration: none; color: #0f172a; }
.kpi-card strong { display: block; font-size: 24px; }
.kpi-card span { color: #64748b; }
.kpi-card.danger strong { color: #b91c1c; }
.table-wrap { overflow-x: auto; }
table { width: 100%; min-width: 1680px; border-collapse: collapse; }
th, td { padding: 11px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
.mono { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
.overdue { color: #b91c1c; font-weight: 900; }
.badge { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 900; }
.badge.pending { background: #fef3c7; color: #92400e; }
.badge.paid { background: #dcfce7; color: #166534; }
.badge.overdue { background: #fee2e2; color: #991b1b; }
.badge.cancelled { background: #f1f5f9; color: #475569; }
.actions { flex-wrap: wrap; min-width: 260px; }
.actions button { border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; padding: 6px 8px; font-weight: 800; cursor: pointer; }
.actions button:disabled { opacity: .45; cursor: not-allowed; }
.btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.empty { padding: 36px; text-align: center; color: #64748b; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; }
.toast.success { background: #ecfdf5; color: #047857; }
.toast.error, .alert.error { background: #fef2f2; color: #991b1b; }
.alert { border-radius: 8px; padding: 10px 12px; margin: 10px 18px 0; font-weight: 800; }
.alert.warning { background: #fef3c7; color: #92400e; }
.modal-backdrop { position: fixed; inset: 0; z-index: 900; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.55); }
.modal { width: min(820px, calc(100vw - 32px)); max-height: calc(100vh - 40px); overflow: auto; }
.modal.small { width: min(520px, calc(100vw - 32px)); }
.modal-head { justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; }
.modal-head button { border: 0; background: transparent; font-weight: 900; cursor: pointer; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 18px 22px; }
.form-grid.one { grid-template-columns: 1fr; }
label { display: flex; flex-direction: column; gap: 6px; font-weight: 800; color: #334155; }
.preview-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; padding: 0 18px 10px; }
.preview-grid div { background: #f8fafc; border-radius: 8px; padding: 12px; }
.preview-grid span { display: block; color: #64748b; font-size: 12px; }
.modal-actions { justify-content: flex-end; padding: 16px 22px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
@media (max-width: 1000px) {
  .page-head { flex-direction: column; }
  .filter-grid, .kpi-grid, .preview-grid, .form-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 640px) {
  .filter-grid, .kpi-grid, .preview-grid, .form-grid { grid-template-columns: 1fr; }
}
</style>
