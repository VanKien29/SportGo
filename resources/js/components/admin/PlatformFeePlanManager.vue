<template>
  <section class="plan-card">
    <div class="plan-head">
      <div>
        <p class="eyebrow">Phiên bản bảng giá</p>
        <h2>Ngày áp dụng và quy tắc kỳ phí</h2>
        <p class="muted">Bảng giá đã công bố chỉ để xem. Mọi thay đổi phải bắt đầu từ một bản nháp mới.</p>
      </div>
      <div class="plan-actions">
        <select v-model="selectedId" aria-label="Chọn phiên bản bảng giá" @change="selectCurrent">
          <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">
            {{ plan.code }} · {{ statusLabel(plan.status) }}
          </option>
        </select>
        <button class="btn secondary" type="button" :disabled="busy || !current" @click="openClone">
          Tạo bản nháp mới
        </button>
      </div>
    </div>

    <div v-if="loading" class="plan-state">Đang tải phiên bản bảng giá...</div>
    <template v-else-if="current">
      <div class="plan-summary">
        <div>
          <span class="label">Trạng thái</span>
          <strong class="status" :class="current.status">{{ statusLabel(current.status) }}</strong>
        </div>
        <div><span class="label">Áp dụng</span><strong>{{ dateRange(current) }}</strong></div>
        <div><span class="label">Dùng thử</span><strong>{{ current.trial_days }} ngày</strong></div>
        <div><span class="label">Ngày đóng kỳ</span><strong>Ngày {{ current.due_day }} hằng tháng</strong></div>
        <div><span class="label">Thông báo trước</span><strong>{{ current.notice_days }} ngày</strong></div>
      </div>

      <div v-if="current.status !== 'draft'" class="readonly-note">
        Phiên bản này đã được công bố nên không thể sửa trực tiếp. Các kỳ đã tạo tiếp tục giữ nguyên snapshot.
      </div>

      <form v-if="current.status === 'draft'" class="plan-form" @submit.prevent="saveDraft">
        <label>Tên phiên bản<input v-model.trim="form.name" required maxlength="150" /></label>
        <label>Số ngày dùng thử<input v-model.number="form.trial_days" type="number" min="0" max="365" required /></label>
        <label>Tạo kỳ trước<input v-model.number="form.invoice_lead_days" type="number" min="0" max="28" required /></label>
        <label>Ngày đóng kỳ<input v-model.number="form.due_day" type="number" min="1" max="28" required /></label>
        <label>Thông báo trước<input v-model.number="form.notice_days" type="number" min="1" max="180" required /></label>
        <label class="wide">Ghi chú<textarea v-model.trim="form.notes" rows="2" maxlength="2000"></textarea></label>
        <div class="discounts wide">
          <label v-for="rule in form.prepay_discounts" :key="rule.months">
            Trả trước {{ rule.months }} tháng (%)
            <input v-model.number="rule.discount_percent" type="number" min="0" max="100" step="0.01" />
          </label>
        </div>
        <div class="draft-actions wide">
          <button class="btn secondary" type="submit" :disabled="busy">Lưu bản nháp</button>
          <label class="effective-date">Ngày bắt đầu<input v-model="effectiveFrom" type="date" :min="minimumEffectiveDate" required /></label>
          <button class="btn primary" type="button" :disabled="busy || !effectiveFrom" @click="scheduleDraft">Lên lịch áp dụng</button>
        </div>
      </form>

      <div v-if="current.status === 'scheduled'" class="scheduled-actions">
        <span>Hệ thống sẽ tự kích hoạt vào {{ formatDate(current.effective_from) }}.</span>
        <button class="btn danger" type="button" :disabled="busy" @click="cancelSchedule">Hủy lịch áp dụng</button>
      </div>
    </template>

    <div v-if="cloneOpen" class="dialog-backdrop" @click.self="cloneOpen = false">
      <form class="dialog" @submit.prevent="createClone">
        <h3>Tạo phiên bản nháp</h3>
        <p class="muted">Hệ thống sao chép toàn bộ bậc phí và mức giảm từ phiên bản đang chọn.</p>
        <label>Mã phiên bản<input v-model.trim="cloneForm.code" required maxlength="50" placeholder="SPORTGO-2026-02" /></label>
        <label>Tên phiên bản<input v-model.trim="cloneForm.name" required maxlength="150" /></label>
        <div class="dialog-actions">
          <button class="btn secondary" type="button" @click="cloneOpen = false">Đóng</button>
          <button class="btn primary" type="submit" :disabled="busy">Tạo bản nháp</button>
        </div>
      </form>
    </div>

    <p v-if="message" class="message" :class="messageType">{{ message }}</p>
  </section>
</template>

<script>
import { platformFeePlanService } from '../../services/platformFeePlan.service.js';

const defaultRules = () => [1, 3, 6, 9, 12].map((months) => ({ months, discount_percent: 0, is_active: true }));
const localDate = (value) => {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

export default {
  name: 'PlatformFeePlanManager',
  emits: ['selected', 'changed'],
  data() {
    return {
      plans: [], selectedId: '', current: null, loading: true, busy: false,
      cloneOpen: false, cloneForm: { code: '', name: '' }, effectiveFrom: '',
      form: {}, message: '', messageType: 'success',
    };
  },
  computed: {
    minimumEffectiveDate() {
      const date = new Date();
      date.setDate(date.getDate() + Number(this.current?.notice_days || 1));
      return localDate(date);
    },
  },
  mounted() { this.load(); },
  methods: {
    async load(preferredId = null) {
      this.loading = true;
      try {
        this.plans = await platformFeePlanService.list();
        const preferred = this.plans.find((item) => String(item.id) === String(preferredId));
        this.current = preferred || this.plans.find((item) => item.status === 'active') || this.plans[0] || null;
        this.selectedId = this.current ? String(this.current.id) : '';
        this.syncForm();
        this.emitSelection();
      } catch (error) { this.show(error.message, 'error'); }
      finally { this.loading = false; }
    },
    selectCurrent() {
      this.current = this.plans.find((item) => String(item.id) === this.selectedId) || null;
      this.syncForm();
      this.emitSelection();
    },
    syncForm() {
      if (!this.current) return;
      const rules = defaultRules().map((fallback) => this.current.prepay_discounts?.find((item) => item.months === fallback.months) || fallback);
      this.form = {
        name: this.current.name, trial_days: this.current.trial_days,
        invoice_lead_days: this.current.invoice_lead_days, due_day: this.current.due_day,
        notice_days: this.current.notice_days, notes: this.current.notes || '',
        prepay_discounts: rules.map((item) => ({ ...item })),
      };
      this.effectiveFrom = this.minimumEffectiveDate;
    },
    emitSelection() { this.$emit('selected', this.current); },
    openClone() {
      this.cloneForm = {
        code: `SPORTGO-${new Date().toISOString().slice(0, 7).replace('-', '')}-`,
        name: `${this.current.name} - bản mới`,
      };
      this.cloneOpen = true;
    },
    async createClone() {
      this.busy = true;
      try {
        const response = await platformFeePlanService.createDraft({
          source_plan_version_id: this.current.id, ...this.cloneForm,
          trial_days: this.current.trial_days, invoice_lead_days: this.current.invoice_lead_days,
          due_day: this.current.due_day, notice_days: this.current.notice_days, notes: this.current.notes,
        });
        this.cloneOpen = false;
        await this.load(response.data.id);
        this.show(response.message);
        this.$emit('changed');
      } catch (error) { this.show(error.message, 'error'); }
      finally { this.busy = false; }
    },
    async saveDraft() {
      this.busy = true;
      try {
        const response = await platformFeePlanService.update(this.current.id, this.form);
        await this.load(response.data.id);
        this.show(response.message);
        this.$emit('changed');
      } catch (error) { this.show(error.message, 'error'); }
      finally { this.busy = false; }
    },
    async scheduleDraft() {
      if (!window.confirm(`Áp dụng bảng giá từ ${this.formatDate(this.effectiveFrom)}? Các kỳ đã chốt sẽ không đổi.`)) return;
      this.busy = true;
      try {
        const response = await platformFeePlanService.schedule(this.current.id, this.effectiveFrom);
        await this.load(response.data.id);
        this.show(response.message);
        this.$emit('changed');
      } catch (error) { this.show(error.message, 'error'); }
      finally { this.busy = false; }
    },
    async cancelSchedule() {
      if (!window.confirm('Hủy lịch áp dụng và đưa phiên bản về nháp?')) return;
      this.busy = true;
      try {
        const response = await platformFeePlanService.cancelSchedule(this.current.id);
        await this.load(response.data.id);
        this.show(response.message);
        this.$emit('changed');
      } catch (error) { this.show(error.message, 'error'); }
      finally { this.busy = false; }
    },
    statusLabel(status) { return ({ draft: 'Nháp', scheduled: 'Chờ áp dụng', active: 'Đang áp dụng', retired: 'Ngừng áp dụng' })[status] || status; },
    formatDate(value) { return value ? new Date(`${value}T00:00:00`).toLocaleDateString('vi-VN') : 'chưa đặt'; },
    dateRange(plan) { return plan.effective_from ? `${this.formatDate(plan.effective_from)} - ${plan.effective_to ? this.formatDate(plan.effective_to) : 'không thời hạn'}` : 'Chưa lên lịch'; },
    show(message, type = 'success') { this.message = message; this.messageType = type; window.setTimeout(() => { this.message = ''; }, 4500); },
  },
};
</script>

<style scoped>
.plan-card { position: relative; padding: 18px; border: 1px solid #dbe5df; border-radius: 12px; background: #fff; box-shadow: 0 8px 24px rgba(15, 54, 31, .05); }
.plan-head, .plan-actions, .draft-actions, .scheduled-actions, .dialog-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.eyebrow { margin: 0 0 4px; color: #15803d; font-size: 12px; text-transform: uppercase; }
h2, h3, p { margin: 0; } .muted { margin-top: 5px; color: #64748b; font-size: 13px; }
select, input, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 11px; font: inherit; background: #fff; }
.plan-actions select { min-width: 250px; }
.plan-summary { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-top: 16px; }
.plan-summary > div { padding: 11px 12px; border-radius: 9px; background: #f8fafc; }
.label { display: block; margin-bottom: 5px; color: #64748b; font-size: 12px; }
.status { display: inline-flex; padding: 3px 8px; border-radius: 999px; font-size: 12px; }
.status.active { color: #166534; background: #dcfce7; } .status.draft { color: #92400e; background: #fef3c7; }
.status.scheduled { color: #1d4ed8; background: #dbeafe; } .status.retired { color: #475569; background: #e2e8f0; }
.readonly-note { margin-top: 14px; padding: 11px 13px; border-left: 3px solid #16a34a; background: #f0fdf4; color: #166534; font-size: 13px; }
.plan-form { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-top: 16px; }
.plan-form label, .dialog label { display: grid; gap: 6px; color: #334155; font-size: 13px; }
.wide { grid-column: 1 / -1; } .discounts { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
.effective-date { display: flex !important; align-items: center; grid-template-columns: auto 155px; white-space: nowrap; }
.scheduled-actions { margin-top: 14px; padding-top: 14px; border-top: 1px solid #e2e8f0; }
.btn { min-height: 38px; border: 1px solid transparent; border-radius: 8px; padding: 8px 13px; font-weight: 600; cursor: pointer; }
.btn.primary { background: #169447; color: #fff; } .btn.secondary { border-color: #cbd5e1; background: #fff; color: #334155; }
.btn.danger { border-color: #fecaca; background: #fff; color: #b91c1c; } .btn:disabled { opacity: .55; cursor: not-allowed; }
.dialog-backdrop { position: fixed; inset: 0; z-index: 1100; display: grid; place-items: center; padding: 18px; background: rgba(15, 23, 42, .45); }
.dialog { width: min(480px, 100%); display: grid; gap: 14px; padding: 20px; border-radius: 12px; background: #fff; }
.dialog-actions { justify-content: flex-end; } .message { margin-top: 12px; color: #166534; } .message.error { color: #b91c1c; }
.plan-state { padding: 24px; text-align: center; color: #64748b; }
@media (max-width: 900px) { .plan-head { align-items: stretch; flex-direction: column; } .plan-summary, .plan-form { grid-template-columns: repeat(2, 1fr); } .discounts { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .plan-actions, .draft-actions, .scheduled-actions { align-items: stretch; flex-direction: column; } .plan-actions select { min-width: 0; } .plan-summary, .plan-form, .discounts { grid-template-columns: 1fr; } }
</style>
