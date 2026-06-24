<template>
  <section class="venue-fees">
    <button class="link-btn" type="button" @click="$router.push({ name: 'admin-venue-clusters' })">Quay lại cụm sân</button>

    <div v-if="!venue" class="panel empty">Không tìm thấy cụm sân.</div>
    <template v-else>
      <div class="venue-info-bar">
        <h2>{{ venue.name }}</h2>
        <span class="status-dot" :class="venue.status" :title="venue.status === 'locked' ? 'Đã khóa' : 'Hoạt động'" :aria-label="venue.status === 'locked' ? 'Đã khóa' : 'Hoạt động'"></span>
      </div>

      <div v-if="snapshotChanged" class="notice">
        Số sân của cụm đã thay đổi. Các kỳ phí đã tạo giữ nguyên snapshot cũ. Kỳ phí tiếp theo sẽ dùng số sân mới.
      </div>

      <section class="panel">
        <h3>Xem trước phí theo kỳ</h3>
        <div class="preview-grid">
          <div v-for="month in periods" :key="month" class="preview-card">
            <span>{{ month }} tháng</span>
            <strong>{{ previewFor(month).error || money(previewFor(month).fee.amount_due) }}</strong>
            <small>{{ previewFor(month).tier?.name || '' }}</small>
            <button class="btn primary icon-text" type="button" :disabled="Boolean(previewFor(month).error)" @click="createFor(month)">
              <AppIcon name="plus" size="18" />
              <span>Tạo kỳ phí</span>
            </button>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h3>Ledger của cụm sân</h3>
          <div class="actions">
            <button class="btn danger icon-text" type="button" :disabled="!canLock" @click="lockVenue">
              <AppIcon name="lock" size="18" />
              <span>Khóa cụm</span>
            </button>
            <button class="btn secondary icon-text" type="button" :disabled="!canUnlock" @click="unlockVenue">
              <AppIcon name="unlock" size="18" />
              <span>Mở khóa</span>
            </button>
          </div>
        </div>
        <div v-if="ledgers.length === 0" class="empty compact">Chưa có kỳ phí. Hãy tạo kỳ phí mới.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Mã</th>
              <th>Kỳ</th>
              <th>Số sân snapshot</th>
              <th>Bậc phí</th>
              <th>Hạn thanh toán</th>
              <th>Còn thiếu</th>
              <th>Trạng thái</th>
              <th>Email gần nhất</th>
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
import { platformFeeStore } from '../../stores/platformFee.store.js';
import {
  calculateLedgerPreview,
  createLedger,
  getLedgersByVenue,
  lockVenueForOverdueLedger,
  unlockVenueAfterPayment,
} from '../../services/platformFeeLedger.service.js';
import AppIcon from '../../components/AppIcon.vue';

export default {
  name: 'AdminVenuePlatformFees',
  components: { AppIcon },
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
        this.showMessage('Đã tạo kỳ phí mới.');
        await this.loadData();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async lockVenue() {
      const ledger = this.ledgers.find((item) => item.status === 'overdue');
      const reason = prompt('Nhập lý do khóa:', 'Quá hạn phí duy trì hệ thống');
      if (!ledger || !reason) return;
      await this.run(() => lockVenueForOverdueLedger(ledger.id, reason), 'Đã khóa cụm sân.');
    },
    async unlockVenue() {
      const ledger = this.ledgers.find((item) => item.status === 'paid');
      if (!ledger) return;
      await this.run(() => unlockVenueAfterPayment(ledger.id), 'Đã mở khóa cụm sân.');
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
      return log ? `${this.reminderLabel(log.type)} - ${this.emailStatusLabel(log.status)}` : 'Chưa gửi';
    },
    statusLabel(status) {
      return { pending: 'Chờ thanh toán', paid: 'Đã thanh toán', overdue: 'Quá hạn', cancelled: 'Đã hủy', active: 'Hoạt động', locked: 'Đã khóa' }[status] || status;
    },
    reminderLabel(type) {
      return {
        due_soon_7_days: 'Nhắc trước hạn 7 ngày',
        due_today: 'Nhắc đúng hạn',
        overdue_3_days: 'Cảnh báo quá hạn 3 ngày',
      }[type] || type;
    },
    emailStatusLabel(status) {
      return { sent: 'đã gửi', failed: 'lỗi', queued: 'đang chờ' }[status] || status;
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
.panel, .preview-card { background: var(--admin-surface, #fff); border: 1px solid var(--admin-border); border-radius: 8px; padding: 16px; }
.venue-info-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.panel-head, .actions, .icon-text { display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, h3, p { margin: 0; }
.notice { padding: 12px 14px; border-radius: 8px; background: #fef3c7; color: #92400e; font-weight: 800; }
.preview-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.preview-card span, .preview-card small { display: block; color: var(--admin-muted); }
.preview-card strong { display: block; margin: 6px 0 12px; }
.btn { border: 0; border-radius: 8px; padding: 9px 12px; font-weight: 900; cursor: pointer; }
.btn.primary { background: var(--admin-primary); color: var(--admin-bg); }
.btn.secondary { background: var(--admin-border); color: var(--admin-text); }
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
  background: var(--admin-primary); box-shadow: 0 0 0 3px var(--admin-primary-ring);
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
th, td { padding: 11px 12px; border-bottom: 1px solid var(--admin-border); text-align: left; }
th { background: var(--admin-surface-muted); color: var(--admin-faint); font-size: 12px; text-transform: uppercase; }
.link-btn { border: 0; background: transparent; color: var(--admin-primary); font-weight: 900; cursor: pointer; width: fit-content; }
.empty { text-align: center; color: var(--admin-muted); }
.compact { padding: 24px; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; }
.toast.success { background: #ecfdf5; color: var(--admin-primary); }
.toast.error { background: #fef2f2; color: #991b1b; }
@media (max-width: 1000px) { .preview-grid { grid-template-columns: 1fr 1fr; } }
</style>
