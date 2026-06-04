<template>
  <section class="pf-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Phi duy tri nen tang</p>
        <h2>Cau hinh bac phi nen tang</h2>
        <p>Quan ly bac phi dua tren so luong san con va chu ky dong phi.</p>
      </div>
      <div class="head-actions">
        <button class="btn secondary" type="button" @click="checkCoverage">Kiem tra khoang bac phi</button>
        <button class="btn primary" type="button" @click="openCreate">Them bac phi</button>
      </div>
    </header>

    <div class="notice-card">
      Phi nen tang tinh theo so luong san con va chu ky dong phi, khong tinh theo phan tram doanh thu booking.
    </div>

    <div class="info-grid">
      <article v-for="item in businessNotes" :key="item" class="info-card">{{ item }}</article>
    </div>

    <div v-if="toast" class="toast" :class="toastType">{{ toast }}</div>

    <section class="panel filter-panel">
      <input v-model.trim="keyword" placeholder="Tim theo ten bac phi" />
      <select v-model="statusFilter">
        <option value="">Tat ca trang thai</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button class="btn secondary" type="button" @click="resetStore">Reset mock store</button>
    </section>

    <section class="panel">
      <div class="panel-title">
        <strong>Danh sach bac phi</strong>
        <span>{{ filteredTiers.length }} bac phi</span>
      </div>
      <div v-if="filteredTiers.length === 0" class="empty">Chua co bac phi. Hay tao bac phi dau tien.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Ten bac</th>
              <th>Khoang so san</th>
              <th>Gia / san / thang</th>
              <th>1 thang</th>
              <th>3 thang</th>
              <th>6 thang</th>
              <th>9 thang</th>
              <th>12 thang</th>
              <th>Trang thai</th>
              <th>Ledger dung</th>
              <th>Cap nhat</th>
              <th>Hanh dong</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tier in filteredTiers" :key="tier.id">
              <td>
                <strong>{{ tier.name }}</strong>
                <small>{{ tier.note || 'Khong co ghi chu' }}</small>
              </td>
              <td>{{ rangeLabel(tier) }}</td>
              <td>{{ money(tier.price_per_court_month) }}</td>
              <td>{{ percent(tier.discount_1_month) }}</td>
              <td>{{ percent(tier.discount_3_months) }}</td>
              <td>{{ percent(tier.discount_6_months) }}</td>
              <td>{{ percent(tier.discount_9_months) }}</td>
              <td>{{ percent(tier.discount_12_months) }}</td>
              <td><span class="badge" :class="tier.is_active ? 'success' : 'neutral'">{{ tier.is_active ? 'Active' : 'Inactive' }}</span></td>
              <td>{{ usageCount(tier.id) }}</td>
              <td>{{ date(tier.updated_at) }}</td>
              <td>
                <div class="actions">
                  <button type="button" @click="viewTier(tier)">Xem</button>
                  <button type="button" @click="openEdit(tier)">Sua</button>
                  <button type="button" @click="toggleTier(tier)">{{ tier.is_active ? 'Tat' : 'Bat' }}</button>
                  <button type="button" @click="cloneTier(tier)">Nhan ban</button>
                  <button type="button" class="danger" @click="removeTier(tier)">Ngung dung</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel preview-panel">
      <div class="panel-title">
        <strong>Xem truoc tinh phi</strong>
        <span>Su dung dung ham calculatePlatformFee()</span>
      </div>
      <div class="preview-form">
        <label>
          Chon cum san
          <select v-model="preview.venue_cluster_id" @change="syncPreviewCourtCount">
            <option value="">Nhap so san gia lap</option>
            <option v-for="venue in venues" :key="venue.id" :value="venue.id">
              {{ venue.name }} - {{ venue.court_count }} san
            </option>
          </select>
        </label>
        <label>
          So san gia lap
          <input v-model.number="preview.court_count" type="number" min="1" />
        </label>
        <label>
          Ky dong
          <select v-model.number="preview.period_months">
            <option v-for="month in periods" :key="month" :value="month">{{ month }} thang</option>
          </select>
        </label>
        <button class="btn primary" type="button" @click="runPreview">Tinh thu</button>
      </div>

      <div v-if="previewError" class="alert error">{{ previewError }}</div>
      <div v-if="previewResult" class="preview-result">
        <div><span>So san</span><strong>{{ previewResult.court_count }}</strong></div>
        <div><span>Bac phi</span><strong>{{ previewResult.tier.name }}</strong></div>
        <div><span>Gia/san/thang</span><strong>{{ money(previewResult.tier.price_per_court_month) }}</strong></div>
        <div><span>So thang</span><strong>{{ previewResult.period_months }}</strong></div>
        <div><span>Giam gia</span><strong>{{ percent(previewResult.discount_percent) }}</strong></div>
        <div><span>Tong truoc giam</span><strong>{{ money(previewResult.base_amount) }}</strong></div>
        <div><span>So tien giam</span><strong>{{ money(previewResult.discount_amount) }}</strong></div>
        <div><span>Tong phai dong</span><strong>{{ money(previewResult.amount_due) }}</strong></div>
      </div>
      <div v-for="warning in previewWarnings" :key="warning" class="alert warning">{{ warning }}</div>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="saveTier">
        <header class="modal-head">
          <h3>{{ editingId ? 'Sua bac phi nen tang' : 'Them bac phi nen tang' }}</h3>
          <button type="button" @click="closeModal">Dong</button>
        </header>

        <div class="form-grid">
          <label>
            Ten bac phi *
            <input v-model.trim="form.name" />
            <small v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</small>
          </label>
          <label>
            Gia / san / thang *
            <input v-model.number="form.price_per_court_month" type="number" min="1" />
            <small v-if="fieldError('price_per_court_month')" class="field-error">{{ fieldError('price_per_court_month') }}</small>
          </label>
          <label>
            So san toi thieu *
            <input v-model.number="form.min_courts" type="number" min="1" step="1" />
            <small v-if="fieldError('min_courts')" class="field-error">{{ fieldError('min_courts') }}</small>
          </label>
          <label>
            So san toi da
            <input v-model="form.max_courts" type="number" min="1" step="1" placeholder="Bo trong = khong gioi han" />
            <small v-if="fieldError('max_courts')" class="field-error">{{ fieldError('max_courts') }}</small>
          </label>
          <label v-for="field in discountFields" :key="field.key">
            {{ field.label }}
            <input v-model.number="form[field.key]" type="number" min="0" max="100" step="0.01" />
            <small v-if="fieldError(field.key)" class="field-error">{{ fieldError(field.key) }}</small>
          </label>
          <label class="check-row">
            <input v-model="form.is_active" type="checkbox" />
            <span>Dang active</span>
          </label>
          <label class="full">
            Ghi chu noi bo
            <textarea v-model.trim="form.note" rows="3"></textarea>
          </label>
        </div>

        <div v-if="formErrors._coverage" class="alert error">
          <div v-for="message in formErrors._coverage" :key="message">{{ message }}</div>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeModal">Huy</button>
          <button class="btn primary" type="submit">Luu bac phi</button>
        </footer>
      </form>
    </div>

    <div v-if="viewingTier" class="modal-backdrop" @click.self="viewingTier = null">
      <div class="modal detail-modal">
        <header class="modal-head">
          <h3>Chi tiet bac phi</h3>
          <button type="button" @click="viewingTier = null">Dong</button>
        </header>
        <div class="detail-grid">
          <div><span>Ten bac</span><strong>{{ viewingTier.name }}</strong></div>
          <div><span>Khoang san</span><strong>{{ rangeLabel(viewingTier) }}</strong></div>
          <div><span>Gia</span><strong>{{ money(viewingTier.price_per_court_month) }}</strong></div>
          <div><span>Ledger dang dung</span><strong>{{ usageCount(viewingTier.id) }}</strong></div>
          <div class="full"><span>Ghi chu</span><strong>{{ viewingTier.note || '-' }}</strong></div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { platformFeeStore } from '../../stores/platformFee.store.js';
import {
  calculatePlatformFee,
  createTier,
  deactivateTier,
  deleteTier,
  findTierForCourtCount,
  getTierUsageCount,
  getTiers,
  reactivateTier,
  updateTier,
  validateTierCoverage,
} from '../../services/platformFeeTier.service.js';

const defaultForm = () => ({
  name: '',
  min_courts: 1,
  max_courts: '',
  price_per_court_month: 50000,
  discount_1_month: 0,
  discount_3_months: 0,
  discount_6_months: 0,
  discount_9_months: 0,
  discount_12_months: 0,
  is_active: true,
  note: '',
});

export default {
  name: 'AdminPlatformFeeTiers',
  data() {
    return {
      tiers: [],
      venues: platformFeeStore.state.venues,
      keyword: '',
      statusFilter: '',
      showModal: false,
      editingId: null,
      viewingTier: null,
      form: defaultForm(),
      formErrors: {},
      preview: { venue_cluster_id: '', court_count: 3, period_months: 3 },
      previewResult: null,
      previewError: '',
      previewWarnings: [],
      toast: '',
      toastType: 'success',
      periods: [1, 3, 6, 9, 12],
      businessNotes: [
        'Phi tinh theo so san con',
        'Khong tinh theo doanh thu booking',
        'Ledger da tao giu nguyen snapshot',
        'Qua han co the khoa cum san',
        'Email nhac phi gui theo ngay den han',
      ],
      discountFields: [
        { key: 'discount_1_month', label: 'Giam ky 1 thang (%)' },
        { key: 'discount_3_months', label: 'Giam ky 3 thang (%)' },
        { key: 'discount_6_months', label: 'Giam ky 6 thang (%)' },
        { key: 'discount_9_months', label: 'Giam ky 9 thang (%)' },
        { key: 'discount_12_months', label: 'Giam ky 12 thang (%)' },
      ],
    };
  },
  computed: {
    filteredTiers() {
      return this.tiers.filter((tier) => {
        const matchKeyword = !this.keyword || tier.name.toLowerCase().includes(this.keyword.toLowerCase());
        const matchStatus = !this.statusFilter || (this.statusFilter === 'active' ? tier.is_active : !tier.is_active);
        return matchKeyword && matchStatus;
      });
    },
  },
  mounted() {
    this.loadTiers();
    this.runPreview();
  },
  methods: {
    loadTiers() {
      this.tiers = getTiers();
    },
    openCreate() {
      this.editingId = null;
      this.form = defaultForm();
      this.formErrors = {};
      this.showModal = true;
    },
    openEdit(tier) {
      this.editingId = tier.id;
      this.form = { ...tier, max_courts: tier.max_courts ?? '' };
      this.formErrors = {};
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.formErrors = {};
    },
    async saveTier() {
      try {
        if (this.editingId) await updateTier(this.editingId, this.form);
        else await createTier(this.form);
        this.showMessage('Da luu bac phi.');
        this.closeModal();
        this.loadTiers();
        this.runPreview();
      } catch (error) {
        this.formErrors = error.validation?.errors || { _coverage: [error.message] };
        this.showMessage(error.message, 'error');
      }
    },
    async toggleTier(tier) {
      try {
        if (tier.is_active) await deactivateTier(tier.id, 'Admin tat trang thai');
        else await reactivateTier(tier.id);
        this.showMessage('Da cap nhat trang thai bac phi.');
        this.loadTiers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async cloneTier(tier) {
      try {
        await createTier({
          ...tier,
          name: `${tier.name} copy`,
          is_active: false,
          max_courts: tier.max_courts ?? '',
        });
        this.showMessage('Da nhan ban bac phi o trang thai inactive.');
        this.loadTiers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    async removeTier(tier) {
      const reason = prompt('Nhap ly do ngung dung bac phi:');
      if (!reason) return;
      try {
        await deleteTier(tier.id);
        this.showMessage('Da ngung dung bac phi.');
        this.loadTiers();
      } catch (error) {
        this.showMessage(error.message, 'error');
      }
    },
    viewTier(tier) {
      this.viewingTier = tier;
    },
    checkCoverage() {
      const result = validateTierCoverage(this.tiers);
      this.showMessage(result.isValid ? 'Coverage bac phi hop le.' : result.errors.join(' '), result.isValid ? 'success' : 'error');
    },
    syncPreviewCourtCount() {
      const venue = this.venues.find((item) => item.id === this.preview.venue_cluster_id);
      if (venue) this.preview.court_count = venue.court_count;
      this.runPreview();
    },
    runPreview() {
      this.previewError = '';
      this.previewResult = null;
      this.previewWarnings = [];
      const coverage = validateTierCoverage(this.tiers);
      if (!coverage.isValid) {
        this.previewError = 'Cau hinh bac phi hien chua hop le, vui long sua truoc khi tao ky phi.';
        return;
      }
      const found = findTierForCourtCount(this.preview.court_count);
      if (!found.tier) {
        this.previewError = 'Chua co bac phi phu hop cho cum san nay.';
        return;
      }
      this.previewResult = calculatePlatformFee({
        court_count: this.preview.court_count,
        period_months: this.preview.period_months,
        tier: found.tier,
      });
      this.previewWarnings = this.previewResult.warnings;
    },
    resetStore() {
      if (!confirm('Reset du lieu mock phi nen tang?')) return;
      platformFeeStore.reset();
      this.venues = platformFeeStore.state.venues;
      this.loadTiers();
      this.runPreview();
      this.showMessage('Da reset du lieu mock.');
    },
    fieldError(field) {
      return this.formErrors[field]?.[0] || '';
    },
    usageCount(id) {
      return getTierUsageCount(id);
    },
    rangeLabel(tier) {
      return tier.max_courts === null || tier.max_courts === ''
        ? `Tu ${tier.min_courts} san tro len`
        : `${tier.min_courts} - ${tier.max_courts} san`;
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
.pf-page { display: flex; flex-direction: column; gap: 18px; }
.page-head, .head-actions, .panel-title, .filter-panel, .actions, .preview-form, .modal-head, .modal-actions { display: flex; gap: 12px; }
.page-head { justify-content: space-between; align-items: flex-start; }
.head-actions, .modal-actions { align-items: center; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; font-weight: 900; text-transform: uppercase; }
h2, h3, p { margin: 0; }
.panel, .notice-card, .info-card, .modal { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.panel { padding: 16px; }
.notice-card { padding: 14px 16px; background: #fff7ed; color: #9a3412; font-weight: 800; }
.info-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.info-card { padding: 12px; color: #334155; font-weight: 800; }
.filter-panel { align-items: center; }
input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font: inherit; }
.filter-panel input { max-width: 360px; }
.filter-panel select { max-width: 220px; }
.panel-title { justify-content: space-between; align-items: center; margin-bottom: 12px; }
.panel-title span, small { color: #64748b; }
.table-wrap { overflow-x: auto; }
table { width: 100%; min-width: 1180px; border-collapse: collapse; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
td strong, td small { display: block; }
.badge { display: inline-flex; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 900; }
.success { background: #dcfce7; color: #166534; }
.neutral { background: #f1f5f9; color: #475569; }
.actions { flex-wrap: wrap; }
.actions button { border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; padding: 6px 9px; font-weight: 800; cursor: pointer; }
.actions .danger { color: #b91c1c; }
.btn { border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 900; cursor: pointer; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #e2e8f0; color: #334155; }
.preview-form { display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; align-items: end; }
label { display: flex; flex-direction: column; gap: 6px; font-weight: 800; color: #334155; }
.preview-result, .detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
.preview-result div, .detail-grid div { background: #f8fafc; border-radius: 8px; padding: 12px; }
.preview-result span, .detail-grid span { display: block; color: #64748b; font-size: 12px; }
.alert { border-radius: 8px; padding: 10px 12px; margin-top: 10px; font-weight: 800; }
.alert.error, .toast.error { background: #fef2f2; color: #991b1b; }
.alert.warning { background: #fef3c7; color: #92400e; }
.toast.success { background: #ecfdf5; color: #047857; }
.toast { border-radius: 8px; padding: 11px 13px; font-weight: 800; }
.empty { padding: 36px; text-align: center; color: #64748b; }
.modal-backdrop { position: fixed; inset: 0; z-index: 900; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.55); }
.modal { width: min(840px, calc(100vw - 32px)); max-height: calc(100vh - 40px); overflow: auto; }
.modal-head { justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; }
.modal-head button { border: 0; background: transparent; font-weight: 900; cursor: pointer; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 18px 22px; }
.full { grid-column: 1 / -1; }
.check-row { flex-direction: row; align-items: center; }
.check-row input { width: auto; }
.field-error { color: #b91c1c; font-weight: 800; }
.modal-actions { justify-content: flex-end; padding: 16px 22px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
@media (max-width: 900px) {
  .page-head { flex-direction: column; }
  .info-grid, .preview-result, .detail-grid { grid-template-columns: 1fr 1fr; }
  .preview-form, .form-grid { grid-template-columns: 1fr; }
}
</style>
