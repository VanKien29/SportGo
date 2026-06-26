<template>
  <section class="vip-admin-page">
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div v-if="loading" class="state">Đang tải gói VIP...</div>
    <div v-else class="package-grid">
      <form v-for="pkg in packages" :key="pkg.id" class="package-card" @submit.prevent="save(pkg)">
        <header>
          <div>
            <span>{{ pkg.type === 'free' ? 'Mặc định' : 'Trả phí' }}</span>
            <h3>{{ pkg.label || pkg.name }}</h3>
          </div>
          <label class="toggle">
            <input v-model="pkg.is_active" type="checkbox" :disabled="pkg.type === 'free'" />
            Hoạt động
          </label>
        </header>

        <div class="grid">
          <label>Tên gói<input v-model.trim="pkg.name" required /></label>
          <label>Giá 1 tháng<input v-model.number="pkg.monthly_price" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>Giá 1 quý<input v-model.number="pkg.quarterly_price" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>Giá 1 năm<input v-model.number="pkg.yearly_price" type="number" min="0" step="1000" :disabled="pkg.type === 'free'" /></label>
          <label>Danh hiệu<input v-model.trim="pkg.badge_name" :disabled="pkg.type === 'free'" /></label>
          <label>% Hoàn tiền<input v-model.number="pkg.cashback_percent" type="number" min="0" max="100" step="0.01" :disabled="pkg.type === 'free'" required /></label>
          <label>Bài giao lưu/tháng<input v-model.number="pkg.match_post_limit_per_month" type="number" min="-1" required /></label>
        </div>

        <label class="check">
          <input v-model="pkg.priority_complaint" type="checkbox" :disabled="pkg.type === 'free'" />
          Ưu tiên khiếu nại
        </label>

        <button class="btn primary" type="submit" :disabled="savingId === pkg.id">
          {{ savingId === pkg.id ? 'Đang lưu...' : 'Lưu gói' }}
        </button>
      </form>
    </div>

    <section class="voucher-section">
      <div class="section-head">
        <div>
          <span>Voucher VIP</span>
          <h3>Voucher áp dụng theo gói</h3>
        </div>
      </div>

      <form class="voucher-form" @submit.prevent="saveVipVoucher">
        <div class="voucher-grid">
          <label>Mã voucher<input v-model.trim="vipVoucherForm.code" required /></label>
          <label>Tên voucher<input v-model.trim="vipVoucherForm.name" required /></label>
          <label>Gói áp dụng
            <select v-model="vipVoucherForm.package_type" required>
              <option v-for="pkg in availableVipPackages" :key="pkg.type" :value="pkg.type">{{ pkg.label }}</option>
            </select>
          </label>
          <label>Loại giảm
            <select v-model="vipVoucherForm.discount_type">
              <option value="percent">Phần trăm</option>
              <option value="fixed">Số tiền</option>
            </select>
          </label>
          <label>{{ voucherDiscountValueLabel }}
            <input
              v-model.number="vipVoucherForm.discount_value"
              type="number"
              min="0.01"
              :max="vipVoucherForm.discount_type === 'percent' ? 100 : null"
              :step="vipVoucherForm.discount_type === 'percent' ? 0.01 : 1000"
              required
            />
          </label>
          <label v-if="vipVoucherForm.discount_type === 'percent'">Tiền giảm tối đa<input v-model.number="vipVoucherForm.max_discount_amount" type="number" min="0" step="1000" /></label>
          <label>Đơn tối thiểu<input v-model.number="vipVoucherForm.min_order_amount" type="number" min="0" step="1000" /></label>
          <label>Giới hạn mỗi khách<input v-model.number="vipVoucherForm.per_user_limit" type="number" min="1" required /></label>
          <label>Bắt đầu<input v-model="vipVoucherForm.valid_from" type="datetime-local" required /></label>
          <label>Kết thúc<input v-model="vipVoucherForm.valid_to" type="datetime-local" required /></label>
          <label>Trạng thái
            <select v-model="vipVoucherForm.status">
              <option value="draft">Bản nháp</option>
              <option value="active">Đang áp dụng</option>
              <option value="inactive">Đã tắt</option>
            </select>
          </label>
        </div>

        <label>Mô tả<textarea v-model.trim="vipVoucherForm.description" rows="3"></textarea></label>

        <div class="voucher-actions">
          <button class="btn secondary" type="button" @click="resetVipVoucherForm">Làm mới</button>
          <button class="btn primary" type="submit" :disabled="voucherSaving || availableVipPackages.length === 0">
            {{ voucherSaving ? 'Đang tạo...' : 'Tạo voucher' }}
          </button>
        </div>
      </form>

      <div class="voucher-table">
        <div v-if="voucherLoading" class="state">Đang tải voucher VIP...</div>
        <div v-else-if="vipPackageVouchers.length === 0" class="state">Chưa có voucher áp dụng theo gói VIP.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Mã</th>
              <th>Tên</th>
              <th>Gói áp dụng</th>
              <th>Giảm</th>
              <th>Đơn tối thiểu</th>
              <th>Mỗi khách</th>
              <th>Đã dùng</th>
              <th>Hiệu lực</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="voucher in vipPackageVouchers" :key="voucher.id">
              <td><strong>{{ voucher.code }}</strong></td>
              <td>{{ voucher.name }}</td>
              <td>{{ vipVoucherPackageLabel(voucher) }}</td>
              <td>{{ discountText(voucher) }}</td>
              <td>{{ money(voucher.min_order_amount) }}</td>
              <td>{{ voucher.per_user_limit || 'Không giới hạn' }}</td>
              <td>{{ voucher.used_quantity }}</td>
              <td>{{ date(voucher.valid_from) }} - {{ date(voucher.valid_to) }}</td>
              <td><span class="badge" :class="voucher.status">{{ voucher.status_label }}</span></td>
              <td class="actions-col">
                <button
                  class="mini-btn danger"
                  type="button"
                  :disabled="voucher.status === 'inactive'"
                  @click="deactivateVipVoucher(voucher)"
                >
                  Tắt
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>

<script>
import { adminVoucherService } from '../../services/adminVoucherService.js';
import { vipMembershipService } from '../../services/vipMembershipService.js';

export default {
  name: 'AdminMembershipPackages',
  data() {
    return {
      packages: [],
      vouchers: [],
      loading: false,
      voucherLoading: false,
      voucherSaving: false,
      savingId: '',
      error: '',
      success: '',
      vipVoucherForm: this.emptyVipVoucherForm(),
    };
  },
  mounted() {
    this.load();
    this.loadVipVouchers();
  },
  computed: {
    availableVipPackages() {
      const packages = this.packages
        .filter((pkg) => pkg.type !== 'free')
        .map((pkg) => ({
          type: pkg.type,
          label: pkg.label || pkg.name,
        }));

      return packages.length
        ? packages
        : [
            { type: 'saving', label: 'Tiết kiệm' },
            { type: 'pro', label: 'Pro' },
          ];
    },
    vipPackageVouchers() {
      return this.vouchers.filter((voucher) => (voucher.scopes || [])
        .some((scope) => scope.scope_type === 'vip_package'));
    },
    voucherDiscountValueLabel() {
      return this.vipVoucherForm.discount_type === 'percent'
        ? 'Phần trăm giảm (%)'
        : 'Số tiền giảm (VND)';
    },
  },
  methods: {
    emptyVipVoucherForm() {
      const validFrom = new Date();
      const validTo = new Date();
      validTo.setMonth(validTo.getMonth() + 1);

      return {
        code: '',
        name: '',
        description: '',
        package_type: 'saving',
        discount_type: 'percent',
        discount_value: 10,
        max_discount_amount: null,
        min_order_amount: 0,
        per_user_limit: 1,
        valid_from: this.toDatetimeLocal(validFrom),
        valid_to: this.toDatetimeLocal(validTo),
        status: 'active',
      };
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await vipMembershipService.adminPackages();
        this.packages = (response.data || [])
          .map((pkg) => ({ ...pkg }))
          .sort((a, b) => this.packageSortOrder(a) - this.packageSortOrder(b));
        if (!this.availableVipPackages.some((pkg) => pkg.type === this.vipVoucherForm.package_type)) {
          this.vipVoucherForm.package_type = this.availableVipPackages[0]?.type || 'saving';
        }
      } catch (error) {
        this.error = error.message || 'Không thể tải gói VIP.';
      } finally {
        this.loading = false;
      }
    },
    async loadVipVouchers() {
      this.voucherLoading = true;
      try {
        const response = await adminVoucherService.list({ per_page: 50 });
        this.vouchers = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải voucher VIP.';
      } finally {
        this.voucherLoading = false;
      }
    },
    payload(pkg) {
      return {
        name: pkg.name,
        monthly_price: pkg.monthly_price,
        quarterly_price: pkg.quarterly_price || null,
        yearly_price: pkg.yearly_price || null,
        voucher_count_per_month: 0,
        voucher_discount_percent: 0,
        voucher_min_order_amount: 0,
        voucher_max_discount_amount: null,
        cashback_percent: Number(pkg.cashback_percent || 0),
        match_post_limit_per_month: Number(pkg.match_post_limit_per_month || 0),
        priority_complaint: Boolean(pkg.priority_complaint),
        badge_name: pkg.badge_name || null,
        is_active: Boolean(pkg.is_active),
        sort_order: this.packageSortOrder(pkg),
      };
    },
    packageSortOrder(pkg) {
      return {
        free: 1,
        saving: 2,
        pro: 3,
      }[pkg.type] || Number(pkg.sort_order || 99);
    },
    validatePackage(pkg) {
      const postLimit = Number(pkg.match_post_limit_per_month);
      const cashback = Number(pkg.cashback_percent);

      if (!Number.isFinite(postLimit) || postLimit < -1) {
        this.error = 'Bài giao lưu/tháng chỉ được nhập -1 hoặc số từ 0 trở lên. -1 nghĩa là không giới hạn.';
        return false;
      }

      if (!Number.isFinite(cashback) || cashback < 0 || cashback > 100) {
        this.error = '% Hoàn tiền phải nằm trong khoảng 0 đến 100.';
        return false;
      }

      return true;
    },
    async save(pkg) {
      if (!this.validatePackage(pkg)) return;

      this.savingId = pkg.id;
      this.error = '';
      try {
        const response = await vipMembershipService.updateAdminPackage(pkg.id, this.payload(pkg));
        this.success = response.message || 'Đã lưu gói VIP.';
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu gói VIP.';
      } finally {
        this.savingId = '';
      }
    },
    vipVoucherPayload() {
      return {
        code: this.vipVoucherForm.code,
        name: this.vipVoucherForm.name,
        description: this.vipVoucherForm.description || null,
        discount_type: this.vipVoucherForm.discount_type,
        discount_value: Number(this.vipVoucherForm.discount_value || 0),
        max_discount_amount: this.vipVoucherForm.discount_type === 'percent'
          ? this.vipVoucherForm.max_discount_amount || null
          : null,
        min_order_amount: Number(this.vipVoucherForm.min_order_amount || 0),
        total_quantity: null,
        per_user_limit: Number(this.vipVoucherForm.per_user_limit || 1),
        valid_from: this.vipVoucherForm.valid_from,
        valid_to: this.vipVoucherForm.valid_to,
        status: this.vipVoucherForm.status,
        scopes: [{
          scope_type: 'vip_package',
          scope_id: this.vipVoucherForm.package_type,
        }],
      };
    },
    validateVipVoucher() {
      const discountValue = Number(this.vipVoucherForm.discount_value);

      if (!Number.isFinite(discountValue) || discountValue <= 0) {
        this.error = this.vipVoucherForm.discount_type === 'percent'
          ? 'Phần trăm giảm phải lớn hơn 0.'
          : 'Số tiền giảm phải lớn hơn 0.';
        return false;
      }

      if (this.vipVoucherForm.discount_type === 'percent' && discountValue > 100) {
        this.error = 'Phần trăm giảm không được lớn hơn 100%.';
        return false;
      }

      return true;
    },
    async saveVipVoucher() {
      if (!this.validateVipVoucher()) return;

      this.voucherSaving = true;
      this.error = '';
      try {
        const response = await adminVoucherService.create(this.vipVoucherPayload());
        this.success = response.message || 'Đã tạo voucher VIP.';
        this.resetVipVoucherForm();
        await this.loadVipVouchers();
      } catch (error) {
        this.error = error.message || 'Không thể tạo voucher VIP.';
      } finally {
        this.voucherSaving = false;
      }
    },
    resetVipVoucherForm() {
      this.vipVoucherForm = this.emptyVipVoucherForm();
      this.vipVoucherForm.package_type = this.availableVipPackages[0]?.type || 'saving';
    },
    async deactivateVipVoucher(voucher) {
      if (!confirm(`Tắt voucher ${voucher.code}?`)) return;
      try {
        const response = await adminVoucherService.deactivate(voucher.id, 'Admin tắt voucher áp dụng theo gói VIP.');
        this.success = response.message || 'Đã tắt voucher VIP.';
        await this.loadVipVouchers();
      } catch (error) {
        this.error = error.message || 'Không thể tắt voucher VIP.';
      }
    },
    vipVoucherPackageLabel(voucher) {
      const scope = (voucher.scopes || []).find((item) => item.scope_type === 'vip_package');
      const matched = this.availableVipPackages.find((pkg) => pkg.type === scope?.scope_id);
      return matched?.label || scope?.scope_id || '-';
    },
    discountText(voucher) {
      return voucher.discount_type === 'percent'
        ? `${Number(voucher.discount_value)}%`
        : this.money(voucher.discount_value);
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    toDatetimeLocal(value) {
      const date = value instanceof Date ? value : new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
  },
};
</script>

<style scoped>
.vip-admin-page{display:grid;gap:16px}.alert,.state{padding:12px 14px;border-radius:10px;font-weight:750}.alert.error{background:#fee2e2;color:#b91c1c}.alert.success{background:#dcfce7;color:#166534}.state{background:#fff;border:1px solid #e2e8f0;color:#64748b}.package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.package-card,.voucher-section{display:grid;gap:14px;padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.package-card header,.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.package-card h3,.section-head h3{margin:3px 0 0;color:#0f172a}.package-card header span,.section-head span{color:#059669;font-size:11px;font-weight:900;text-transform:uppercase}.grid,.voucher-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}label{display:grid;gap:5px;color:#475569;font-size:12px;font-weight:800}input,select,textarea{border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font:inherit}input,select{height:38px}textarea{padding-top:10px;resize:vertical}.toggle,.check{display:flex;align-items:center;gap:8px}.toggle input,.check input{width:16px;height:16px}.btn,.mini-btn{border:0;border-radius:8px;font-weight:850;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:7px 10px}.primary{background:#16a34a;color:#fff}.secondary{background:#f1f5f9;color:#0f172a}.danger{background:#fee2e2;color:#b91c1c}.primary:disabled,.mini-btn:disabled{opacity:.55;cursor:not-allowed}.voucher-form{display:grid;gap:12px}.voucher-actions{display:flex;justify-content:flex-end;gap:10px}.voucher-table{overflow:auto;border:1px solid #e2e8f0;border-radius:10px}table{width:100%;min-width:1040px;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:middle}tbody tr:last-child td{border-bottom:0}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e2e8f0}.badge.active{background:#dcfce7;color:#166534}.badge.inactive,.badge.expired{background:#fee2e2;color:#b91c1c}.badge.draft{background:#f1f5f9;color:#475569}.actions-col{text-align:right}@media(max-width:1100px){.package-grid{grid-template-columns:1fr}.grid,.voucher-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.grid,.voucher-grid{grid-template-columns:1fr}.voucher-actions{justify-content:stretch}.voucher-actions .btn{flex:1}}
</style>
