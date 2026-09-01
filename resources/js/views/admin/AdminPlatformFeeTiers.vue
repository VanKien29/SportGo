<template>
  <main class="fee-page">
    <PlatformFeeSubnav />

    <header class="version-page-head">
      <div>
        <p class="eyebrow">Bảng giá phí nền tảng</p>
        <h1>Danh sách phiên bản</h1>
        <p>Mỗi lần thay đổi được tạo thành một phiên bản. Kỳ đã phát hành luôn giữ nguyên mức phí đã chốt.</p>
      </div>
      <button class="btn primary" type="button" @click="openCreate">
        <AppIcon name="plus" size="18" />
        Tạo phiên bản nháp
      </button>
    </header>

    <section class="summary-grid" aria-label="Tổng quan phiên bản">
      <article><span>Tổng phiên bản</span><strong>{{ summary.total }}</strong></article>
      <article><span>Đang áp dụng</span><strong class="green">{{ summary.active }}</strong></article>
      <article><span>Chờ áp dụng</span><strong class="blue">{{ summary.scheduled }}</strong></article>
      <article><span>Bản nháp</span><strong class="amber">{{ summary.draft }}</strong></article>
    </section>

    <section class="list-card">
      <div class="filters">
        <label class="search-field">
          <AppIcon name="search" size="17" />
          <input v-model.trim="filters.q" placeholder="Tìm theo mã hoặc tên phiên bản" @keyup.enter="load(1)" />
        </label>
        <select v-model="filters.status" aria-label="Lọc trạng thái" @change="load(1)">
          <option value="">Tất cả trạng thái</option>
          <option value="active">Đang áp dụng</option>
          <option value="scheduled">Chờ áp dụng</option>
          <option value="draft">Nháp</option>
          <option value="retired">Ngừng áp dụng</option>
        </select>
        <button class="btn secondary" type="button" @click="load(1)">Tìm kiếm</button>
        <button v-if="filters.q || filters.status" class="btn link" type="button" @click="resetFilters">Xóa lọc</button>
      </div>

      <div v-if="loading" class="state-box">Đang tải danh sách phiên bản...</div>
      <div v-else-if="plans.length === 0" class="state-box empty">
        <AppIcon name="layers3" size="30" />
        <strong>Không có phiên bản phù hợp</strong>
        <span>Thử thay đổi bộ lọc hoặc tạo phiên bản nháp mới.</span>
      </div>
      <div v-else class="table-wrap">
        <table>
          <thead><tr><th class="number">STT</th><th>Phiên bản</th><th>Ngày áp dụng</th><th>Ngày kết thúc</th><th>Ngày tạo</th><th>Trạng thái</th><th class="actions-head">Thao tác</th></tr></thead>
          <tbody>
            <tr v-for="(plan, index) in plans" :key="plan.id">
              <td class="number" data-label="STT">{{ rowNumber(index) }}</td>
              <td class="identity-cell" data-label="Phiên bản"><strong class="plan-name">{{ plan.name }}</strong><span class="plan-code">{{ plan.code }}</span></td>
              <td data-label="Ngày áp dụng">{{ date(plan.effective_from) }}</td>
              <td data-label="Ngày kết thúc">{{ plan.effective_to ? date(plan.effective_to) : plan.effective_from ? 'Không thời hạn' : '-' }}</td>
              <td data-label="Ngày tạo">{{ date(plan.created_at) }}</td>
              <td data-label="Trạng thái"><span class="status" :class="plan.status">{{ statusLabel(plan.status) }}</span></td>
              <td class="actions-cell" data-label="Thao tác">
                <div class="row-actions">
                  <router-link class="icon-btn" :to="detailRoute(plan)" :title="plan.status === 'draft' ? 'Mở cấu hình' : 'Xem chi tiết'">
                    <AppIcon :name="plan.status === 'draft' ? 'pencil' : 'eye'" size="17" />
                  </router-link>
                  <button class="icon-btn" type="button" title="Nhân bản phiên bản" @click="openClone(plan)"><AppIcon name="copy" size="17" /></button>
                  <button v-if="plan.status === 'draft'" class="icon-btn danger" type="button" title="Xóa bản nháp" @click="requestDelete(plan)"><AppIcon name="trash" size="17" /></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="meta.last_page > 1" class="pagination">
        <span>Trang {{ meta.current_page }}/{{ meta.last_page }} · {{ meta.total }} phiên bản</span>
        <div><button class="btn secondary" type="button" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Trước</button><button class="btn secondary" type="button" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Sau</button></div>
      </footer>
    </section>

    <Teleport to="body">
      <div v-if="createOpen" class="modal-backdrop" @click.self="closeCreate">
        <form class="modal" @submit.prevent="createDraft">
          <header>
            <div><h2>{{ cloneSource ? 'Nhân bản phiên bản' : 'Tạo phiên bản nháp' }}</h2><p>Cấu hình chỉ có hiệu lực sau khi được kiểm tra và lên lịch.</p></div>
            <button class="close-btn" type="button" aria-label="Đóng" @click="closeCreate"><AppIcon name="x" size="18" /></button>
          </header>
          <div class="modal-body">
            <div v-if="cloneSource" class="clone-note">Sao chép từ <strong>{{ cloneSource.code }} · {{ cloneSource.name }}</strong></div>
            <label>Mã phiên bản <span>*</span>
              <input v-model.trim="createForm.code" minlength="3" maxlength="50" pattern="[A-Z0-9]+(?:-[A-Z0-9]+)*" title="Chỉ dùng chữ in hoa, số và dấu gạch ngang ở giữa các nhóm." placeholder="Ví dụ: SPORTGO-2026-02" required @input="createForm.code = createForm.code.toUpperCase()" />
              <small>Chỉ dùng chữ in hoa, số và dấu gạch ngang; không đổi sau khi tạo.</small>
              <em v-if="fieldError('code')">{{ fieldError('code') }}</em>
            </label>
            <label>Tên phiên bản <span>*</span>
              <input v-model.trim="createForm.name" minlength="3" maxlength="150" required />
              <em v-if="fieldError('name')">{{ fieldError('name') }}</em>
            </label>
          </div>
          <footer><button class="btn secondary" type="button" :disabled="busy" @click="closeCreate">Hủy</button><button class="btn primary" type="submit" :disabled="busy">{{ busy ? 'Đang tạo...' : 'Tạo và mở cấu hình' }}</button></footer>
        </form>
      </div>
    </Teleport>

    <ConfirmModal v-model="confirm.open" title="Xóa phiên bản nháp?" :message="confirm.plan ? `Bạn sắp xóa ${confirm.plan.code} · ${confirm.plan.name}.` : ''" consequence="Bậc phí và cấu hình trả trước trong bản nháp cũng bị xóa. Thao tác không thể hoàn tác." confirm-text="Xóa bản nháp" type="danger" @confirm="deleteDraft" />
    <p v-if="toast" class="toast" :class="toastType" role="status">{{ toast }}</p>
  </main>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import PlatformFeeSubnav from '../../components/PlatformFeeSubnav.vue';
import { platformFeePlanService } from '../../services/platformFeePlan.service.js';

const blankMeta = () => ({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const codeTimestamp = () => {
  const now = new Date();
  const date = [now.getFullYear(), now.getMonth() + 1, now.getDate()].map((value, index) => String(value).padStart(index === 0 ? 4 : 2, '0')).join('');
  const time = [now.getHours(), now.getMinutes(), now.getSeconds()].map((value) => String(value).padStart(2, '0')).join('');
  return `${date}-${time}`;
};

export default {
  name: 'AdminPlatformFeeTiers', components: { AppIcon, ConfirmModal, PlatformFeeSubnav },
  data() { return { plans: [], meta: blankMeta(), loading: true, busy: false, filters: { q: '', status: '' }, createOpen: false, cloneSource: null, createForm: { code: '', name: '' }, errors: {}, confirm: { open: false, plan: null }, toast: '', toastType: 'success' }; },
  computed: {
    summary() { return this.meta.status_summary || { total: this.meta.total, active: this.plans.filter((item) => item.status === 'active').length, scheduled: this.plans.filter((item) => item.status === 'scheduled').length, draft: this.plans.filter((item) => item.status === 'draft').length }; },
  },
  mounted() { this.load(); },
  methods: {
    async load(page = 1) { this.loading = true; try { const result = await platformFeePlanService.list({ ...this.filters, page }); this.plans = result.items; this.meta = result.meta; } catch (error) { this.show(error.message || 'Không tải được danh sách phiên bản.', 'error'); } finally { this.loading = false; } },
    resetFilters() { this.filters = { q: '', status: '' }; this.load(1); },
    detailRoute(plan) { return { name: 'admin-platform-fee-plan-detail', params: { id: plan.id } }; },
    rowNumber(index) { return (this.meta.current_page - 1) * this.meta.per_page + index + 1; },
    openCreate() { this.openClone(null); },
    openClone(plan) { this.cloneSource = plan; this.createForm = { code: `SPORTGO-${codeTimestamp()}`, name: plan ? `${plan.name} - bản mới` : 'Bảng giá phí nền tảng' }; this.errors = {}; this.createOpen = true; },
    closeCreate() { if (!this.busy) this.createOpen = false; },
    async createDraft() {
      this.busy = true; this.errors = {}; const source = this.cloneSource;
      try {
        const response = await platformFeePlanService.createDraft({ ...(source ? { source_plan_version_id: source.id } : {}), code: this.createForm.code, name: this.createForm.name, trial_days: source?.trial_days ?? 30, billing_anchor_day: source?.billing_anchor_day ?? 1, invoice_lead_days: source?.invoice_lead_days ?? 7, due_day: source?.due_day ?? 5, notice_days: source?.notice_days ?? 30, notes: source?.notes || null });
        this.createOpen = false; this.$router.push({ name: 'admin-platform-fee-plan-detail', params: { id: response.data.id } });
      } catch (error) { this.errors = error.validation?.errors || error.data?.errors || {}; this.show(error.message || 'Không tạo được phiên bản nháp.', 'error'); } finally { this.busy = false; }
    },
    requestDelete(plan) { this.confirm = { open: true, plan }; },
    async deleteDraft() { const plan = this.confirm.plan; this.confirm = { open: false, plan: null }; if (!plan) return; try { const response = await platformFeePlanService.remove(plan.id); this.show(response.message); await this.load(this.meta.current_page); } catch (error) { this.show(error.message || 'Không xóa được bản nháp.', 'error'); } },
    fieldError(field) { return this.errors[field]?.[0] || ''; },
    statusLabel(status) { return ({ draft: 'Nháp', scheduled: 'Chờ áp dụng', active: 'Đang áp dụng', retired: 'Ngừng áp dụng' })[status] || status; },
    date(value) { return value ? new Date(value).toLocaleDateString('vi-VN') : '-'; },
    show(message, type = 'success') { this.toast = message; this.toastType = type; window.setTimeout(() => { this.toast = ''; }, 4500); },
  },
};
</script>

<style scoped>
.fee-page { display: grid; gap: 18px; padding: 10px; color: var(--admin-text, #17251d); }.version-page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }.version-page-head h1, .version-page-head p, .eyebrow { margin: 0; }.version-page-head h1 { margin-top: 3px; font-size: 24px; }.version-page-head p:last-child { margin-top: 7px; color: var(--admin-muted, #64748b); font-size: 13px; }.eyebrow { color: var(--admin-primary, #15803d); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }.summary-grid article { display: grid; gap: 7px; min-height: 82px; padding: 15px 17px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }.summary-grid span { color: #64748b; font-size: 12px; }.summary-grid strong { font-size: 24px; }.green { color: #15803d; }.blue { color: #2563eb; }.amber { color: #b45309; }
.list-card { container-type: inline-size; border: 1px solid var(--admin-border, #e2e8f0); border-radius: 10px; background: var(--admin-surface, #fff); overflow: hidden; }.filters { display: flex; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--admin-border, #e2e8f0); }.filters select { min-width: 190px; }.search-field { position: relative; flex: 1; }.search-field svg { position: absolute; top: 11px; left: 12px; color: var(--admin-muted, #64748b); }.search-field input { padding-left: 38px; } input, select { width: 100%; min-height: 40px; box-sizing: border-box; border: 1px solid var(--admin-border, #cbd5e1); border-radius: 8px; padding: 9px 11px; background: var(--admin-surface, #fff); color: var(--admin-text, #17251d); font: inherit; }
.table-wrap { overflow-x: auto; } table { width: 100%; min-width: 900px; border-collapse: collapse; } th, td { padding: 13px 15px; border-bottom: 1px solid #eef2f7; text-align: left; vertical-align: middle; } th { background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }.number { width: 54px; text-align: center; }.actions-head { text-align: center; }.plan-name, .plan-code { display: block; }.plan-name { font-size: 14px; }.plan-code { margin-top: 4px; color: #64748b; font-size: 11px; }
.status { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 11px; font-weight: 600; white-space: nowrap; }.status.active { color: #166534; background: #dcfce7; }.status.scheduled { color: #1d4ed8; background: #dbeafe; }.status.draft { color: #92400e; background: #fef3c7; }.status.retired { color: #475569; background: #e2e8f0; }.row-actions { display: flex; justify-content: center; gap: 7px; }.icon-btn { display: inline-grid; width: 34px; height: 34px; place-items: center; border: 1px solid #dbe3ea; border-radius: 8px; background: #fff; color: #334155; cursor: pointer; text-decoration: none; }.icon-btn.danger { border-color: #fecaca; color: #b91c1c; background: #fff7f7; }
.btn { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; gap: 7px; border: 1px solid transparent; border-radius: 8px; padding: 9px 13px; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }.btn.primary { background: #16834b; color: #fff; }.btn.secondary { border-color: #cbd5e1; background: #fff; color: #334155; }.btn.link { background: transparent; color: #166534; }.btn:disabled { opacity: .5; cursor: not-allowed; }.state-box { display: grid; min-height: 210px; place-items: center; align-content: center; gap: 7px; color: #64748b; }.state-box.empty strong { color: #334155; }.state-box.empty span { font-size: 13px; }.pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; color: #64748b; font-size: 12px; }.pagination div { display: flex; gap: 8px; }
.modal-backdrop { position: fixed; inset: 0; z-index: 9000; display: grid; place-items: center; padding: 18px; background: rgba(15, 23, 42, .55); }.modal { width: min(560px, calc(100vw - 28px)); border-radius: 12px; background: #fff; box-shadow: 0 20px 60px rgba(15, 23, 42, .2); overflow: hidden; }.modal header, .modal footer { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 17px 20px; }.modal header { border-bottom: 1px solid #e2e8f0; }.modal header h2, .modal header p { margin: 0; }.modal header h2 { font-size: 18px; }.modal header p { margin-top: 5px; color: #64748b; font-size: 12px; }.modal footer { justify-content: flex-end; border-top: 1px solid #e2e8f0; background: #f8fafc; }.close-btn { display: grid; width: 34px; height: 34px; place-items: center; border: 0; border-radius: 8px; background: #f1f5f9; cursor: pointer; }.modal-body { display: grid; gap: 15px; padding: 20px; }.modal-body label { display: grid; gap: 6px; color: #334155; font-size: 13px; font-weight: 600; }.modal-body label > span { color: #dc2626; }.modal-body small { color: #64748b; font-weight: 400; }.modal-body em { color: #dc2626; font-size: 12px; font-style: normal; font-weight: 400; }.clone-note { padding: 10px 12px; border-radius: 8px; background: #f0fdf4; color: #166534; font-size: 13px; }.toast { position: fixed; right: 22px; bottom: 22px; z-index: 9500; max-width: 430px; margin: 0; border-radius: 9px; padding: 12px 15px; box-shadow: 0 8px 24px rgba(15, 23, 42, .15); background: #ecfdf5; color: #166534; }.toast.error { background: #fef2f2; color: #991b1b; }
@container (max-width: 760px) {
  .table-wrap { padding: 12px; background: var(--admin-surface-muted, #f8fafc); }
  .table-wrap table, .table-wrap tbody { display: grid; width: 100% !important; min-width: 0 !important; gap: 12px; }
  thead { display: none; }
  .table-wrap tr { display: grid; width: 100%; min-width: 0; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px 18px; box-sizing: border-box; padding: 15px; border: 1px solid var(--admin-border, #e2e8f0); border-radius: 10px; background: var(--admin-surface, #fff); }
  .table-wrap td { display: grid; min-width: 0; grid-template-columns: 112px minmax(0, 1fr); align-items: center; gap: 8px; padding: 0; border: 0; overflow-wrap: anywhere; }
  td::before { content: attr(data-label); color: var(--admin-muted, #64748b); font-size: 11px; font-weight: 600; text-transform: uppercase; }
  td.number { width: auto; text-align: left; }
  td.identity-cell, td.actions-cell { grid-column: 1 / -1; }
  td.identity-cell { display: block; padding-bottom: 10px; border-bottom: 1px solid var(--admin-border, #eef2f7); }
  td.identity-cell::before { display: none; }
  td.actions-cell { grid-template-columns: 112px 1fr; padding-top: 10px; border-top: 1px solid var(--admin-border, #eef2f7); }
  .row-actions { justify-content: flex-start; }
}
@media (max-width: 820px) { .version-page-head { align-items: stretch; flex-direction: column; }.version-page-head .btn { width: 100%; }.summary-grid { grid-template-columns: 1fr 1fr; }.filters { display: grid; grid-template-columns: 1fr 1fr; }.search-field { grid-column: 1 / -1; } }
@media (max-width: 520px) { .fee-page { padding: 4px; }.summary-grid, .filters { grid-template-columns: 1fr; }.search-field { grid-column: auto; }.pagination { align-items: stretch; flex-direction: column; gap: 10px; }.pagination div .btn { flex: 1; } }
@container (max-width: 480px) { tr { grid-template-columns: 1fr; } td, td.actions-cell { grid-column: 1; grid-template-columns: 104px minmax(0, 1fr); } td.identity-cell { grid-column: 1; } }

/* Keep filter controls away from the card edge and reserve a real icon gutter. */
.list-card > .filters { box-sizing: border-box; gap: 12px; padding: 16px; }
.filters > * { min-width: 0; }
.search-field { position: relative; min-width: 0; }
.search-field > .app-icon { position: absolute; inset-inline-start: 13px; top: 50%; z-index: 1; color: #64748b; pointer-events: none; transform: translateY(-50%); }
.search-field > input { min-width: 0; padding: 9px 12px 9px 44px; }
.filters > .btn, .filters > select { min-height: 40px; }
.search-field { display: block !important; padding: 0 !important; }
.search-field > .app-icon { position: absolute !important; inset-inline-start: 13px !important; top: 50% !important; transform: translateY(-50%) !important; }
.search-field > input { box-sizing: border-box; min-height: 40px !important; padding: 9px 12px 9px 44px !important; }
</style>
