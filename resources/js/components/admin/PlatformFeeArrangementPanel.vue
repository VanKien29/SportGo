<template>
  <section class="arrangement-panel">
    <div class="panel-head">
      <div>
        <p class="eyebrow">Ngoại lệ có kiểm soát</p>
        <h2>Thỏa thuận trả chậm 1–3 tháng</h2>
        <p>Chủ sân phải xác nhận và đủ số dư bảo đảm. Đây không phải trả trước nên không giảm theo kỳ hạn.</p>
      </div>
      <button class="btn primary" type="button" :disabled="busy" @click="openForm = !openForm">
        {{ openForm ? 'Đóng biểu mẫu' : 'Tạo thỏa thuận' }}
      </button>
    </div>

    <form v-if="openForm" class="arrangement-form" @submit.prevent="submit">
      <label class="wide">Cụm sân
        <select v-model="form.venue_cluster_id" required>
          <option value="" disabled>Chọn cụm sân</option>
          <option v-for="venue in venues" :key="venue.id" :value="venue.id">
            {{ venue.name }}{{ venue.owner?.full_name ? ` · ${venue.owner.full_name}` : '' }}
          </option>
        </select>
      </label>
      <label>Số tháng dịch vụ
        <select v-model.number="form.service_months" required>
          <option :value="1">1 tháng</option>
          <option :value="2">2 tháng</option>
          <option :value="3">3 tháng</option>
        </select>
      </label>
      <label>Tháng bắt đầu<input v-model="form.service_start" type="date" required /></label>
      <label>Hạn thanh toán<input v-model="form.payment_due_date" type="date" :min="minimumDueDate" required /></label>
      <label class="wide">Lý do thỏa thuận<textarea v-model.trim="form.reason" minlength="10" maxlength="1000" rows="3" required></textarea></label>
      <label class="wide">Ghi chú nội bộ<textarea v-model.trim="form.admin_note" maxlength="1000" rows="2"></textarea></label>
      <div class="warning wide">
        Hệ thống chỉ giữ chỗ các kỳ. Sau khi chủ sân xác nhận, toàn bộ số tiền bảo đảm mới bị tạm giữ và không thể rút.
      </div>
      <div class="form-actions wide"><button class="btn primary" type="submit" :disabled="busy">Gửi chủ sân xác nhận</button></div>
    </form>

    <div v-if="loading" class="state">Đang tải thỏa thuận...</div>
    <div v-else-if="arrangements.length" class="arrangement-list">
      <article v-for="item in arrangements" :key="item.id" class="arrangement-row">
        <div>
          <div class="title-line"><strong>{{ item.code }}</strong><span class="badge" :class="item.status">{{ statusLabel(item.status) }}</span></div>
          <p>{{ item.venue_cluster?.name || 'Cụm sân' }} · {{ item.owner?.full_name || 'Chủ sân' }}</p>
          <small>{{ item.service_months }} tháng, {{ date(item.service_start) }} - {{ date(item.service_end) }} · hạn {{ date(item.payment_due_date) }}</small>
        </div>
        <div class="amount"><span>Tổng nghĩa vụ</span><strong>{{ money(item.total_amount) }}</strong><small>Tạm giữ {{ money(item.secured_amount) }}</small></div>
        <button
          v-if="['pending_owner_acceptance', 'active'].includes(item.status)"
          class="btn danger"
          type="button"
          :disabled="busy"
          @click="cancel(item)"
        >Hủy thỏa thuận</button>
      </article>
    </div>

    <p v-if="message" class="message" :class="messageType">{{ message }}</p>
  </section>
</template>

<script>
import { platformFeeArrangementService } from '../../services/platformFeeArrangement.service.js';

function dateOnly(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function nextMonthStart() {
  const date = new Date();
  return dateOnly(new Date(date.getFullYear(), date.getMonth() + 1, 1));
}

export default {
  name: 'PlatformFeeArrangementPanel',
  props: { venues: { type: Array, default: () => [] } },
  emits: ['changed'],
  data() {
    return {
      arrangements: [], loading: true, busy: false, openForm: false,
      message: '', messageType: 'success',
      form: { venue_cluster_id: '', service_months: 3, service_start: nextMonthStart(), payment_due_date: '', reason: '', admin_note: '' },
    };
  },
  computed: {
    minimumDueDate() {
      if (!this.form.service_start) return '';
      const start = new Date(`${this.form.service_start}T00:00:00`);
      return dateOnly(new Date(start.getFullYear(), start.getMonth() + Number(this.form.service_months), 1));
    },
  },
  watch: {
    'form.service_start': 'syncDueDate',
    'form.service_months': 'syncDueDate',
  },
  mounted() { this.syncDueDate(); this.load(); },
  methods: {
    async load() {
      this.loading = true;
      try { this.arrangements = await platformFeeArrangementService.list(); }
      catch (error) { this.show(error.message || 'Không tải được thỏa thuận trả chậm.', 'error'); }
      finally { this.loading = false; }
    },
    syncDueDate() {
      if (!this.form.payment_due_date || this.form.payment_due_date < this.minimumDueDate) this.form.payment_due_date = this.minimumDueDate;
    },
    async submit() {
      this.busy = true;
      try {
        const response = await platformFeeArrangementService.create(this.form);
        this.show(response.message);
        this.openForm = false;
        this.form = { venue_cluster_id: '', service_months: 3, service_start: nextMonthStart(), payment_due_date: '', reason: '', admin_note: '' };
        this.syncDueDate();
        await this.load();
        this.$emit('changed');
      } catch (error) { this.show(error.message || 'Không tạo được thỏa thuận.', 'error'); }
      finally { this.busy = false; }
    },
    async cancel(item) {
      if (!window.confirm(`Hủy thỏa thuận ${item.code}? Các khoản giữ chỗ/tạm giữ sẽ được giải phóng.`)) return;
      this.busy = true;
      try {
        const response = await platformFeeArrangementService.cancel(item.id);
        this.show(response.message);
        await this.load();
        this.$emit('changed');
      } catch (error) { this.show(error.message || 'Không hủy được thỏa thuận.', 'error'); }
      finally { this.busy = false; }
    },
    date(value) { return value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('vi-VN') : '-'; },
    money(value) { return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0); },
    statusLabel(status) { return ({ pending_owner_acceptance: 'Chờ chủ sân', active: 'Đang áp dụng', overdue: 'Quá hạn', fulfilled: 'Hoàn tất', cancelled: 'Đã hủy' })[status] || status; },
    show(message, type = 'success') { this.message = message; this.messageType = type; window.setTimeout(() => { this.message = ''; }, 5000); },
  },
};
</script>

<style scoped>
.arrangement-panel { display: grid; gap: 14px; padding: 18px; border: 1px solid #dbe5df; border-radius: 10px; background: #fff; }
.panel-head, .title-line, .arrangement-row, .form-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.eyebrow { margin: 0 0 4px; color: #15803d; font-size: 12px; text-transform: uppercase; } h2, p { margin: 0; } .panel-head p { margin-top: 4px; color: #64748b; font-size: 13px; }
.arrangement-form { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 14px; border-radius: 9px; background: #f8fafc; }
.arrangement-form label { display: grid; gap: 6px; color: #334155; font-size: 13px; } .wide { grid-column: 1 / -1; }
select, input, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 11px; background: #fff; font: inherit; box-sizing: border-box; }
.warning { padding: 10px 12px; border-left: 3px solid #d97706; background: #fffbeb; color: #92400e; font-size: 12px; }
.form-actions { justify-content: flex-end; }.arrangement-list { display: grid; gap: 8px; }
.arrangement-row { padding: 12px 14px; border-radius: 9px; background: #f8fafc; }.arrangement-row > div:first-child { display: grid; gap: 4px; }.arrangement-row p, .arrangement-row small { color: #64748b; font-size: 12px; }
.title-line { justify-content: flex-start; }.amount { display: grid; gap: 3px; margin-left: auto; text-align: right; }.amount span, .amount small { color: #64748b; font-size: 11px; }
.badge { border-radius: 999px; padding: 3px 8px; background: #e2e8f0; color: #475569; font-size: 11px; }.badge.active, .badge.fulfilled { background: #dcfce7; color: #166534; }.badge.pending_owner_acceptance { background: #fef3c7; color: #92400e; }.badge.overdue { background: #fee2e2; color: #b91c1c; }
.btn { min-height: 36px; border: 1px solid transparent; border-radius: 8px; padding: 8px 12px; font-weight: 600; cursor: pointer; }.btn.primary { background: #169447; color: #fff; }.btn.danger { border-color: #fecaca; background: #fff; color: #b91c1c; }.btn:disabled { opacity: .55; cursor: not-allowed; }
.message { color: #166534; }.message.error { color: #b91c1c; }.state { color: #64748b; text-align: center; }
@media (max-width: 800px) { .panel-head, .arrangement-row { align-items: stretch; flex-direction: column; }.arrangement-form { grid-template-columns: 1fr; }.amount { margin-left: 0; text-align: left; }.arrangement-row .btn { width: 100%; } }
</style>
