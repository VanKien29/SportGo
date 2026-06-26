<template>
  <section class="page">
    <div class="back-action-bar">
      <button class="back-link" type="button" @click="$router.push({ name: 'admin-vouchers' })">
        <AppIcon name="arrow-left" size="16" />
        <span>Danh sách voucher</span>
      </button>
      <span v-if="voucher" class="badge" :class="summary.status_tone">{{ summary.status_label }}</span>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="loading" class="state">Đang tải chi tiết voucher...</div>

    <template v-else-if="voucher">
      <section class="summary-grid">
        <article class="metric">
          <span>Mã voucher</span>
          <strong>{{ voucher.code }}</strong>
        </article>
        <article class="metric">
          <span>Giá trị giảm</span>
          <strong>{{ voucher.discount_label }}</strong>
        </article>
        <article class="metric">
          <span>Đã dùng</span>
          <strong>{{ usageSummary.used_quantity || 0 }}</strong>
        </article>
        <article class="metric">
          <span>Còn lại</span>
          <strong>{{ usageSummary.remaining_quantity ?? 'Không giới hạn' }}</strong>
        </article>
      </section>

      <nav class="tabs">
        <button v-for="tab in tabs" :key="tab.key" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="panel-grid">
        <article class="panel">
          <h3>Thông tin voucher</h3>
          <dl>
            <div><dt>Mã voucher</dt><dd>{{ voucher.code }}</dd></div>
            <div><dt>Tên voucher</dt><dd>{{ voucher.name }}</dd></div>
            <div><dt>Mô tả</dt><dd>{{ voucher.description || '(Chưa có mô tả)' }}</dd></div>
            <div><dt>Loại giảm</dt><dd>{{ voucher.discount_type_label }}</dd></div>
            <div><dt>Trạng thái</dt><dd>{{ voucher.status_label }}</dd></div>
          </dl>
        </article>

        <article class="panel">
          <h3>Điều kiện áp dụng</h3>
          <dl>
            <div><dt>Đơn tối thiểu</dt><dd>{{ money(conditions.min_order_amount) }}</dd></div>
            <div><dt>Giảm tối đa</dt><dd>{{ conditions.max_discount_amount ? money(conditions.max_discount_amount) : 'Không giới hạn' }}</dd></div>
            <div><dt>Giới hạn mỗi khách</dt><dd>{{ conditions.per_user_limit || 'Không giới hạn' }}</dd></div>
            <div><dt>Quy tắc dùng chung</dt><dd>{{ conditions.stacking_rule_label }}</dd></div>
            <div><dt>Hiệu lực</dt><dd>{{ dateTime(conditions.valid_from) }} - {{ dateTime(conditions.valid_to) }}</dd></div>
          </dl>
        </article>
      </section>

      <section v-else-if="activeTab === 'scopes'" class="panel">
        <h3>Phạm vi áp dụng</h3>
        <div v-if="scopes.length === 0" class="state small">Voucher đang áp dụng toàn hệ thống.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Loại phạm vi</th>
              <th>Giá trị</th>
              <th>Mã kỹ thuật</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="scope in scopes" :key="scope.id">
              <td>{{ scope.scope_type_label }}</td>
              <td>{{ scope.display_label }}</td>
              <td><code>{{ scope.scope_key }}</code></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-else-if="activeTab === 'usages'" class="panel">
        <h3>Lịch sử sử dụng voucher</h3>
        <div v-if="usages.length === 0" class="state small">Chưa có lượt sử dụng voucher.</div>
        <table v-else>
          <thead>
            <tr>
              <th>Người dùng</th>
              <th>Booking</th>
              <th>Số tiền giảm</th>
              <th>Trạng thái</th>
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="usage in usages" :key="usage.id">
              <td>
                <strong>{{ usage.user_name || 'Không rõ' }}</strong>
                <span>{{ usage.user_email }}</span>
              </td>
              <td>
                <strong>{{ usage.booking_code || '-' }}</strong>
                <span>{{ usage.booking_status_label }}</span>
              </td>
              <td>{{ money(usage.discount_amount) }}</td>
              <td>{{ usage.status_label }}</td>
              <td>{{ dateTime(usage.used_at || usage.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-else class="panel">
        <h3>Lịch sử thay đổi</h3>
        <div v-if="auditLogs.length === 0" class="state small">Chưa có audit log cho voucher này.</div>
        <div v-else class="timeline">
          <article v-for="log in auditLogs" :key="log.id" class="timeline-item">
            <strong>{{ log.summary }}</strong>
            <span>{{ log.actor_name || 'Hệ thống' }} - {{ dateTime(log.created_at) }}</span>
            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ json({ old_values: log.technical_old_values, new_values: log.technical_new_values }) }}</pre>
            </details>
          </article>
        </div>
      </section>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminVoucherService } from '../../services/adminVoucherService.js';

export default {
  name: 'AdminVoucherDetail',
  components: { AppIcon },
  data() {
    return {
      loading: false,
      error: '',
      activeTab: 'overview',
      detail: null,
      tabs: [
        { key: 'overview', label: 'Tổng quan' },
        { key: 'scopes', label: 'Phạm vi áp dụng' },
        { key: 'usages', label: 'Lịch sử sử dụng' },
        { key: 'audit', label: 'Lịch sử thay đổi' },
      ],
    };
  },
  computed: {
    summary() { return this.detail?.summary || {}; },
    voucher() { return this.detail?.voucher || null; },
    conditions() { return this.detail?.conditions || {}; },
    scopes() { return this.detail?.scopes || []; },
    usages() { return this.detail?.usages || []; },
    usageSummary() { return this.detail?.usage_summary || {}; },
    auditLogs() { return this.detail?.audit_logs || []; },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminVoucherService.get(this.$route.params.id);
        this.detail = response.data;
      } catch (error) {
        this.error = error.message || 'Không thể tải chi tiết voucher.';
      } finally {
        this.loading = false;
      }
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    json(value) {
      return JSON.stringify(value, null, 2);
    },
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.back-action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.back-link { border: 0; background: transparent; color: #2563eb; font-weight: 800; display: inline-flex; gap: 6px; align-items: center; cursor: pointer; }
.eyebrow { margin: 0 0 4px; color: #16a34a; font-size: 12px; text-transform: uppercase; font-weight: 800; }
.alert.error { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 10px; font-weight: 700; }
.state { padding: 24px; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
.state.small { padding: 16px; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.metric, .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
.metric span { color: #64748b; display: block; margin-bottom: 8px; }
.metric strong { font-size: 22px; }
.badge { border-radius: 999px; padding: 7px 11px; font-size: 12px; font-weight: 800; background: #f1f5f9; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.danger { background: #fee2e2; color: #b91c1c; }
.badge.warning { background: #fef3c7; color: #92400e; }
.tabs { display: flex; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid #e2e8f0; }
.tabs button { border: 0; background: transparent; padding: 11px 12px; font-weight: 800; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; }
.tabs button.active { color: #16a34a; border-color: #16a34a; }
.panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.panel h3 { margin: 0 0 12px; }
dl { display: grid; gap: 10px; margin: 0; }
dl div { display: grid; grid-template-columns: 160px 1fr; gap: 12px; }
dt { color: #64748b; }
dd { margin: 0; font-weight: 700; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
td span { display: block; color: #64748b; font-size: 12px; margin-top: 3px; }
code { background: #f8fafc; padding: 3px 6px; border-radius: 6px; color: #475569; }
.timeline { display: grid; gap: 10px; }
.timeline-item { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; display: grid; gap: 6px; }
.timeline-item span { color: #64748b; font-size: 13px; }
details { color: #475569; }
summary { cursor: pointer; font-weight: 800; }
pre { white-space: pre-wrap; word-break: break-word; background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 8px; }
@media (max-width: 900px) {
  .summary-grid, .panel-grid { grid-template-columns: 1fr; }
  dl div { grid-template-columns: 1fr; }
}
</style>
