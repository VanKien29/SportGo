<template>
  <section class="plan-detail-shell">
    <div v-if="loading" class="state-box">Đang tải cấu hình phiên bản...</div>
    <template v-else-if="plan">
      <header class="detail-head">
        <div>
          <router-link class="back-link" :to="{ name: 'admin-platform-fee-tiers' }"><AppIcon name="arrowLeft" size="16" /> Danh sách phiên bản</router-link>
          <div class="title-line"><h1>{{ plan.name }}</h1><span class="status" :class="plan.status">{{ statusLabel(plan.status) }}</span></div>
          <p>{{ plan.code }} · {{ dateRange(plan) }}</p>
        </div>
        <div class="head-actions">
          <button v-if="plan.status === 'scheduled'" class="btn danger-outline" type="button" :disabled="busy" @click="openConfirm('cancel-schedule')">Hủy lịch áp dụng</button>
        </div>
      </header>

      <nav class="tabs" aria-label="Nội dung phiên bản">
        <button type="button" :class="{ active: tab === 'settings' }" @click="tab = 'settings'">Cấu hình chung</button>
        <button type="button" :class="{ active: tab === 'tiers' }" @click="tab = 'tiers'">Bậc phí <span>{{ tiers.length }}</span></button>
      </nav>

      <form v-if="tab === 'settings' && isDraft" class="settings-card" @submit.prevent="saveDraft">
        <section class="form-section">
          <div class="section-copy"><h2>Nhận diện phiên bản</h2><p>Giúp Admin phân biệt mục đích của lần thay đổi. Mã phiên bản không thể sửa.</p></div>
          <div class="field-grid">
            <label>Mã phiên bản<input :value="plan.code" disabled /></label>
            <label>Tên phiên bản <span>*</span><input v-model.trim="form.name" minlength="3" maxlength="150" required /><em v-if="errorFor('name')">{{ errorFor('name') }}</em></label>
            <label class="full">Ghi chú nội bộ<textarea v-model.trim="form.notes" rows="3" maxlength="1000"></textarea><em v-if="errorFor('notes')">{{ errorFor('notes') }}</em></label>
          </div>
        </section>

        <section class="form-section">
          <div class="section-copy"><h2>Chu kỳ dịch vụ</h2><p>Thay ngày bắt đầu kỳ sẽ tạo một kỳ lẻ cầu nối và tính theo số ngày thực tế, không hở và không trùng dịch vụ.</p></div>
          <div class="field-grid">
            <label>Ngày bắt đầu kỳ hằng tháng <span>*</span><input v-model.number="form.billing_anchor_day" type="number" min="1" max="28" required /><small>Từ ngày 1 đến 28. Ví dụ ngày 5: kỳ từ ngày 5 đến ngày 4 tháng sau.</small><em v-if="errorFor('billing_anchor_day')">{{ errorFor('billing_anchor_day') }}</em></label>
            <label>Ngày đến hạn thanh toán <span>*</span><input v-model.number="form.due_day" type="number" min="1" max="28" required /><small>Chỉ là hạn trả tiền, không đổi khoảng dịch vụ.</small><em v-if="errorFor('due_day')">{{ errorFor('due_day') }}</em></label>
            <label>Phát hành trước <span>*</span><input v-model.number="form.invoice_lead_days" type="number" min="0" max="28" required /><small>Số ngày tạo kỳ trước ngày bắt đầu dịch vụ.</small><em v-if="errorFor('invoice_lead_days')">{{ errorFor('invoice_lead_days') }}</em></label>
            <label>Báo trước thay đổi <span>*</span><input v-model.number="form.notice_days" type="number" min="1" max="180" required /><small>Ngày áp dụng không được sớm hơn khoảng báo trước này.</small><em v-if="errorFor('notice_days')">{{ errorFor('notice_days') }}</em></label>
          </div>
        </section>

        <section class="form-section">
          <div class="section-copy"><h2>Dùng thử & trả trước</h2><p>Dùng thử áp dụng cho cụm sân mới. Các mức giảm chỉ áp dụng khi Chủ sân trả trước, không áp dụng cho thỏa thuận trả chậm.</p></div>
          <div class="field-grid">
            <label>Số ngày dùng thử <span>*</span><input v-model.number="form.trial_days" type="number" min="0" max="365" required /><small>0 là không dùng thử; kỳ miễn phí được ghi nhận 0 đồng.</small><em v-if="errorFor('trial_days')">{{ errorFor('trial_days') }}</em></label>
            <div class="discount-box full">
              <label v-for="rule in form.prepay_discounts" :key="rule.months">Trả trước {{ rule.months }} tháng
                <span class="percent-field"><input v-model.number="rule.discount_percent" type="number" min="0" max="100" step="0.01" required /><b>%</b></span>
              </label>
              <em v-if="errorFor('prepay_discounts')">{{ errorFor('prepay_discounts') }}</em>
            </div>
          </div>
        </section>

        <footer class="sticky-actions">
          <button class="btn secondary" type="submit" :disabled="busy">{{ busy ? 'Đang lưu...' : 'Lưu bản nháp' }}</button>
          <div class="schedule-group"><label>Ngày bắt đầu áp dụng<input v-model="effectiveFrom" type="date" :min="minimumEffectiveDate" required /></label><button class="btn primary" type="button" :disabled="busy || !effectiveFrom" @click="openConfirm('schedule')">Kiểm tra & lên lịch</button></div>
        </footer>
      </form>

      <section v-else-if="tab === 'settings'" class="readonly-card">
        <div class="immutable-note"><AppIcon name="lock" size="17" /><div><strong>Phiên bản đã công bố chỉ được xem</strong><span>Muốn thay đổi, hãy nhân bản thành bản nháp mới. Kỳ đã phát hành không bị tính lại.</span></div></div>
        <dl class="facts">
          <div><dt>Ngày bắt đầu kỳ</dt><dd>Ngày {{ plan.billing_anchor_day }} hằng tháng</dd></div>
          <div><dt>Ngày đến hạn</dt><dd>Ngày {{ plan.due_day }} hằng tháng</dd></div>
          <div><dt>Phát hành trước</dt><dd>{{ plan.invoice_lead_days }} ngày</dd></div>
          <div><dt>Báo trước thay đổi</dt><dd>{{ plan.notice_days }} ngày</dd></div>
          <div><dt>Dùng thử</dt><dd>{{ plan.trial_days }} ngày</dd></div>
          <div><dt>Thông báo</dt><dd>Thông báo tới Chủ sân</dd></div>
        </dl>
        <div class="readonly-section"><h2>Giảm khi trả trước</h2><div class="rule-pills"><span v-for="rule in normalizedRules" :key="rule.months"><b>{{ rule.months }} tháng</b>{{ number(rule.discount_percent) }}%</span></div></div>
        <div v-if="plan.notes" class="readonly-section"><h2>Ghi chú nội bộ</h2><p>{{ plan.notes }}</p></div>
      </section>

      <section v-else class="tiers-card">
        <header><div><h2>Bậc phí theo số sân con</h2><p>Các bậc đang dùng phải bắt đầu từ 1 sân, liên tục và giá mỗi sân giảm dần khi quy mô tăng.</p></div><button v-if="isDraft" class="btn primary" type="button" @click="openTier()"><AppIcon name="plus" size="17" /> Thêm bậc phí</button></header>
        <div v-if="tiersLoading" class="state-box small">Đang tải bậc phí...</div>
        <div v-else-if="tiers.length === 0" class="state-box small"><strong>Chưa có bậc phí</strong><span>Phiên bản chưa thể lên lịch cho đến khi có bậc bắt đầu từ 1 sân.</span></div>
        <div v-else class="table-wrap"><table><thead><tr><th>STT</th><th>Tên bậc</th><th>Khoảng số sân</th><th>Giá / sân / tháng</th><th>Trạng thái</th><th>Kỳ đã dùng</th><th v-if="isDraft">Thao tác</th></tr></thead><tbody><tr v-for="(tier, index) in tiers" :key="tier.id"><td>{{ index + 1 }}</td><td><strong>{{ tier.name }}</strong></td><td>{{ rangeLabel(tier) }}</td><td>{{ money(tier.price_per_court_month) }}</td><td><span class="status" :class="tier.is_active ? 'active' : 'retired'">{{ tier.is_active ? 'Đang dùng' : 'Ngừng dùng' }}</span></td><td>{{ tier.usage_count || 0 }}</td><td v-if="isDraft"><div class="row-actions"><button class="icon-btn" type="button" title="Sửa bậc phí" @click="openTier(tier)"><AppIcon name="pencil" size="17" /></button><button class="icon-btn danger" type="button" title="Xóa bậc phí" @click="requestRemoveTier(tier)"><AppIcon name="trash" size="17" /></button></div></td></tr></tbody></table></div>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="tierModal" class="modal-backdrop" @click.self="closeTier">
        <form class="modal" @submit.prevent="saveTier">
          <header><div><h2>{{ tierForm.id ? 'Sửa bậc phí' : 'Thêm bậc phí' }}</h2><p>Khoảng tối đa được tự động cân theo bậc kế tiếp.</p></div><button class="close-btn" type="button" @click="closeTier"><AppIcon name="x" size="18" /></button></header>
          <div class="modal-body two-columns">
            <label class="full">Tên bậc phí <span>*</span><input v-model.trim="tierForm.name" maxlength="50" required /><em v-if="tierError('name')">{{ tierError('name') }}</em></label>
            <label>Số sân tối thiểu <span>*</span><input v-model.number="tierForm.min_courts" type="number" min="1" required /><em v-if="tierError('min_courts')">{{ tierError('min_courts') }}</em></label>
            <label>Giá / sân / tháng <span>*</span><input v-model.number="tierForm.price_per_court_month" type="number" min="1" step="1" required /><em v-if="tierError('price_per_court_month')">{{ tierError('price_per_court_month') }}</em></label>
            <label class="check full"><input v-model="tierForm.is_active" type="checkbox" /><span>Dùng bậc này khi phiên bản có hiệu lực</span></label>
          </div>
          <footer><button class="btn secondary" type="button" @click="closeTier">Hủy</button><button class="btn primary" type="submit" :disabled="busy">Lưu bậc phí</button></footer>
        </form>
      </div>
    </Teleport>

    <ConfirmModal v-model="confirm.open" :title="confirm.title" :message="confirm.message" :consequence="confirm.consequence" :confirm-text="confirm.confirmText" :type="confirm.type" @confirm="runConfirmed" />
    <p v-if="toast" class="toast" :class="toastType" role="status">{{ toast }}</p>
  </section>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import ConfirmModal from '../ConfirmModal.vue';
import { platformFeePlanService } from '../../services/platformFeePlan.service.js';
import { createTier, deleteTier, getTiers, updateTier } from '../../services/platformFeeTier.service.js';
import { addCalendarDays, businessDateLabel, businessDateString } from '../../utils/businessTime.js';

const rules = () => [1, 3, 6, 9, 12].map((months) => ({ months, discount_percent: 0, is_active: true }));
const emptyConfirm = () => ({ open: false, action: '', target: null, title: '', message: '', consequence: '', confirmText: 'Xác nhận', type: 'warning' });
export default {
  name: 'PlatformFeePlanManager', components: { AppIcon, ConfirmModal }, props: { planId: { type: [Number, String], required: true } },
  data() { return { plan: null, impact: null, form: {}, tiers: [], loading: true, tiersLoading: true, busy: false, tab: 'settings', effectiveFrom: '', errors: {}, tierErrors: {}, tierModal: false, tierForm: {}, confirm: emptyConfirm(), toast: '', toastType: 'success' }; },
  computed: {
    isDraft() { return this.plan?.status === 'draft'; },
    normalizedRules() { return rules().map((fallback) => this.plan?.prepay_discounts?.find((item) => Number(item.months) === fallback.months) || fallback); },
    minimumEffectiveDate() { return addCalendarDays(businessDateString(), Number(this.form.notice_days || this.plan?.notice_days || 1)); },
  },
  watch: { planId: 'load' }, mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; try { const response = await platformFeePlanService.detail(this.planId); this.plan = response.data; this.impact = response.impact; this.syncForm(); await this.loadTiers(); } catch (error) { this.show(error.message || 'Không tải được phiên bản.', 'error'); } finally { this.loading = false; } },
    syncForm() { if (!this.plan) return; this.form = { name: this.plan.name, trial_days: this.plan.trial_days, billing_anchor_day: this.plan.billing_anchor_day || 1, invoice_lead_days: this.plan.invoice_lead_days, due_day: this.plan.due_day, notice_days: this.plan.notice_days, notes: this.plan.notes || '', expected_revision: this.plan.revision, prepay_discounts: this.normalizedRules.map((item) => ({ ...item })) }; this.effectiveFrom = this.minimumEffectiveDate; this.errors = {}; },
    async loadTiers() { this.tiersLoading = true; try { this.tiers = await getTiers({ plan_version_id: this.planId }); } catch (error) { this.show(error.message || 'Không tải được bậc phí.', 'error'); } finally { this.tiersLoading = false; } },
    async saveDraft() { this.busy = true; this.errors = {}; try { const response = await platformFeePlanService.update(this.plan.id, this.form); this.plan = response.data; this.syncForm(); this.show(response.message); } catch (error) { this.errors = error.validation?.errors || error.data?.errors || {}; this.show(error.message || 'Không lưu được bản nháp.', 'error'); } finally { this.busy = false; } },
    openConfirm(action) {
      if (action === 'schedule') this.confirm = { open: true, action, target: null, title: 'Kiểm tra và lên lịch phiên bản?', message: `${this.plan.code} dự kiến áp dụng từ ${this.date(this.effectiveFrom)}.`, consequence: 'Cấu hình hiện tại được lưu trong cùng thao tác. Chủ sân sẽ nhận thông báo; các kỳ đã phát hành không thay đổi.', confirmText: 'Lưu và lên lịch', type: 'warning' };
      else this.confirm = { open: true, action, target: null, title: 'Hủy lịch áp dụng?', message: `${this.plan.code} sẽ trở về trạng thái nháp.`, consequence: 'Chủ sân đã nhận thông báo trước đó sẽ nhận thông báo hủy lịch.', confirmText: 'Hủy lịch', type: 'danger' };
    },
    requestRemoveTier(tier) { this.confirm = { open: true, action: 'remove-tier', target: tier, title: Number(tier.usage_count) > 0 ? 'Ngừng dùng bậc phí?' : 'Xóa bậc phí?', message: `${tier.name} · ${this.rangeLabel(tier)}.`, consequence: Number(tier.usage_count) > 0 ? 'Bậc đã được kỳ phí tham chiếu nên chỉ được ngừng dùng.' : 'Bậc chưa được sử dụng sẽ bị xóa khỏi bản nháp.', confirmText: Number(tier.usage_count) > 0 ? 'Ngừng dùng' : 'Xóa bậc', type: 'danger' }; },
    async runConfirmed() { const dialog = this.confirm; this.confirm = emptyConfirm(); this.busy = true; try { if (dialog.action === 'schedule') { const response = await platformFeePlanService.schedule(this.plan.id, this.effectiveFrom, this.form); this.plan = response.data; this.syncForm(); this.show(response.message); } else if (dialog.action === 'cancel-schedule') { const response = await platformFeePlanService.cancelSchedule(this.plan.id); this.plan = response.data; this.syncForm(); this.show(response.message); } else if (dialog.action === 'remove-tier') { const response = await deleteTier(dialog.target.id); this.show(response.message); await this.loadTiers(); } } catch (error) { this.errors = error.validation?.errors || error.data?.errors || {}; this.show(error.message || 'Không thực hiện được thao tác.', 'error'); } finally { this.busy = false; } },
    openTier(tier = null) { this.tierErrors = {}; this.tierForm = tier ? { ...tier } : { id: null, plan_version_id: Number(this.planId), name: '', min_courts: this.nextMinimum(), price_per_court_month: '', annual_discount_percent: 0, is_active: true }; this.tierModal = true; },
    closeTier() { if (!this.busy) this.tierModal = false; },
    async saveTier() { this.busy = true; this.tierErrors = {}; try { if (this.tierForm.id) await updateTier(this.tierForm.id, this.tierForm, this.tiers); else await createTier(this.tierForm, this.tiers); this.tierModal = false; await this.loadTiers(); this.show('Đã lưu bậc phí.'); } catch (error) { this.tierErrors = error.validation?.errors || error.data?.errors || {}; this.show(error.message || 'Không lưu được bậc phí.', 'error'); } finally { this.busy = false; } },
    nextMinimum() { const active = this.tiers.filter((item) => item.is_active); if (!active.length) return 1; return Math.max(...active.map((item) => Number(item.min_courts))) + 1; },
    errorFor(field) { return this.errors[field]?.[0] || ''; }, tierError(field) { return this.tierErrors[field]?.[0] || ''; },
    statusLabel(status) { return ({ draft: 'Nháp', scheduled: 'Chờ áp dụng', active: 'Đang áp dụng', retired: 'Ngừng áp dụng' })[status] || status; },
    date(value) { return businessDateLabel(value) || '-'; },
    dateRange(plan) { return plan.effective_from ? `${this.date(plan.effective_from)} - ${plan.effective_to ? this.date(plan.effective_to) : 'không thời hạn'}` : 'Chưa lên lịch'; },
    rangeLabel(tier) { return tier.max_courts == null ? `Từ ${tier.min_courts} sân trở lên` : `${tier.min_courts} - ${tier.max_courts} sân`; },
    money(value) { return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0); }, number(value) { return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 2 }); },
    show(message, type = 'success') { this.toast = message; this.toastType = type; window.setTimeout(() => { this.toast = ''; }, 4500); },
  },
};
</script>

<style scoped>
.plan-detail-shell { display: grid; gap: 0; border: 1px solid var(--admin-border, #e2e8f0); border-radius: 11px; background: var(--admin-surface, #fff); color: var(--admin-text, #17251d); overflow: hidden; }.detail-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; padding: 20px 22px; }.back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 11px; color: var(--admin-primary-dark, #166534); font-size: 12px; text-decoration: none; }.title-line { display: flex; align-items: center; gap: 10px; }.title-line h1, .detail-head p { margin: 0; }.title-line h1 { font-size: 23px; }.detail-head p { margin-top: 6px; color: var(--admin-muted, #64748b); font-size: 13px; }.head-actions { display: flex; gap: 8px; }
.tabs { display: flex; gap: 4px; padding: 0 22px; border-top: 1px solid #eef2f7; border-bottom: 1px solid #e2e8f0; background: #fafcfb; }.tabs button { display: inline-flex; align-items: center; gap: 7px; min-height: 46px; border: 0; border-bottom: 2px solid transparent; background: transparent; color: #64748b; font: inherit; font-size: 13px; cursor: pointer; }.tabs button.active { border-bottom-color: #16834b; color: #166534; font-weight: 600; }.tabs span { display: grid; min-width: 21px; height: 21px; place-items: center; border-radius: 999px; background: #e2e8f0; font-size: 11px; }
.form-section { display: grid; grid-template-columns: minmax(180px, 27%) minmax(0, 1fr); gap: 28px; padding: 23px 22px; border-bottom: 1px solid #eef2f7; }.section-copy h2, .section-copy p { margin: 0; }.section-copy h2, .tiers-card h2, .readonly-section h2 { font-size: 15px; }.section-copy p { margin-top: 7px; color: #64748b; font-size: 12px; line-height: 1.55; }.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }.field-grid label, .schedule-group label, .modal-body label { display: grid; gap: 6px; color: #334155; font-size: 12px; font-weight: 600; }.field-grid label > span, .modal-body label > span { color: #dc2626; }.field-grid small { color: #64748b; font-weight: 400; line-height: 1.4; }.field-grid em, .modal-body em { color: #dc2626; font-size: 11px; font-style: normal; font-weight: 400; }.full { grid-column: 1 / -1; } input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 11px; background: #fff; font: inherit; } input { min-height: 40px; } input:disabled { background: #f1f5f9; color: #64748b; }.discount-box { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; padding: 13px; border-radius: 9px; background: #f8fafc; }.percent-field { position: relative; display: block; }.percent-field input { padding-right: 30px; }.percent-field b { position: absolute; top: 11px; right: 11px; color: #64748b; }
.sticky-actions { position: sticky; bottom: 0; display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; padding: 14px 22px; border-top: 1px solid #dbe5df; background: rgba(255, 255, 255, .96); box-shadow: 0 -7px 18px rgba(15, 23, 42, .04); }.schedule-group { display: flex; align-items: flex-end; gap: 10px; }.schedule-group input { min-width: 165px; }
.readonly-card { padding: 22px; }.immutable-note { display: flex; gap: 11px; padding: 13px 15px; border: 1px solid #bae6c7; border-radius: 9px; background: #f0fdf4; color: #166534; }.immutable-note div { display: grid; gap: 3px; }.immutable-note span { font-size: 12px; }.facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 18px 0; }.facts div { padding: 14px; border: 1px solid #e2e8f0; border-radius: 9px; background: #fafcfb; }.facts dt { color: #64748b; font-size: 11px; }.facts dd { margin: 6px 0 0; font-size: 14px; font-weight: 600; }.readonly-section { padding: 18px 0; border-top: 1px solid #eef2f7; }.readonly-section h2, .readonly-section p { margin: 0; }.readonly-section p { margin-top: 8px; color: #475569; }.rule-pills { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 10px; }.rule-pills span { display: grid; gap: 3px; min-width: 90px; padding: 9px 11px; border-radius: 8px; background: #f1f5f9; color: #475569; font-size: 12px; }.rule-pills b { color: #17251d; }
.tiers-card > header { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; padding: 20px 22px; }.tiers-card header h2, .tiers-card header p { margin: 0; }.tiers-card header p { margin-top: 6px; color: #64748b; font-size: 12px; }.table-wrap { overflow-x: auto; } table { width: 100%; min-width: 760px; border-collapse: collapse; } th, td { padding: 13px 15px; border-top: 1px solid #eef2f7; text-align: left; } th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; }.row-actions { display: flex; gap: 7px; }.icon-btn { display: grid; width: 33px; height: 33px; place-items: center; border: 1px solid #dbe3ea; border-radius: 8px; background: #fff; color: #334155; cursor: pointer; }.icon-btn.danger { border-color: #fecaca; color: #b91c1c; }
.status { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 11px; font-weight: 600; }.status.active { color: #166534; background: #dcfce7; }.status.scheduled { color: #1d4ed8; background: #dbeafe; }.status.draft { color: #92400e; background: #fef3c7; }.status.retired { color: #475569; background: #e2e8f0; }.btn { display: inline-flex; min-height: 39px; align-items: center; justify-content: center; gap: 7px; border: 1px solid transparent; border-radius: 8px; padding: 8px 13px; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }.btn.primary { background: #16834b; color: #fff; }.btn.secondary { border-color: #cbd5e1; background: #fff; color: #334155; }.btn.danger-outline { border-color: #fecaca; background: #fff; color: #b91c1c; }.btn:disabled { opacity: .5; cursor: not-allowed; }.state-box { display: grid; min-height: 320px; place-items: center; align-content: center; gap: 7px; color: #64748b; }.state-box.small { min-height: 190px; border-top: 1px solid #eef2f7; }
.modal-backdrop { position: fixed; inset: 0; z-index: 9000; display: grid; place-items: center; padding: 18px; background: rgba(15, 23, 42, .55); }.modal { width: min(610px, calc(100vw - 28px)); border-radius: 11px; background: #fff; overflow: hidden; }.modal header, .modal footer { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 17px 20px; }.modal header { border-bottom: 1px solid #e2e8f0; }.modal header h2, .modal header p { margin: 0; }.modal header h2 { font-size: 18px; }.modal header p { margin-top: 5px; color: #64748b; font-size: 12px; }.modal footer { justify-content: flex-end; border-top: 1px solid #e2e8f0; background: #f8fafc; }.close-btn { display: grid; width: 34px; height: 34px; place-items: center; border: 0; border-radius: 8px; background: #f1f5f9; cursor: pointer; }.modal-body { display: grid; gap: 14px; padding: 20px; }.modal-body.two-columns { grid-template-columns: 1fr 1fr; }.check { display: flex !important; grid-column: 1 / -1; flex-direction: row; align-items: center; }.check input { width: auto; }.check span { color: #334155 !important; }
.toast { position: fixed; right: 22px; bottom: 22px; z-index: 9500; max-width: 430px; margin: 0; border-radius: 9px; padding: 12px 15px; box-shadow: 0 8px 24px rgba(15, 23, 42, .15); background: #ecfdf5; color: #166534; }.toast.error { background: #fef2f2; color: #991b1b; }
@media (max-width: 850px) { .detail-head, .tiers-card > header, .sticky-actions { align-items: stretch; flex-direction: column; }.form-section { grid-template-columns: 1fr; }.facts { grid-template-columns: 1fr 1fr; }.discount-box { grid-template-columns: 1fr 1fr; }.schedule-group { display: grid; grid-template-columns: 1fr auto; } } @media (max-width: 560px) { .field-grid, .facts, .modal-body.two-columns, .discount-box { grid-template-columns: 1fr; }.full, .check { grid-column: auto; }.schedule-group { grid-template-columns: 1fr; }.schedule-group .btn, .sticky-actions > .btn { width: 100%; } }

/* Give the detail tabs and forms breathing room inside the bordered shell. */
.tabs { gap: 8px; padding: 14px 22px; }
.tabs button { min-height: 40px; padding: 8px 14px; border: 1px solid #dbe3ea; border-radius: 8px; background: #fff; }
.tabs button.active { border-color: #9ac7aa; background: #eaf5ee; }
.field-grid, .schedule-group, .discount-box, .modal-body { min-width: 0; }
input, textarea { min-width: 0; }
.btn { min-height: 40px; }
</style>
