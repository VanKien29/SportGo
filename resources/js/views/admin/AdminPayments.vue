<template>
  <section class="admin-payments">
    <header class="page-header">
      <div>
        <h2>Theo dõi thanh toán booking</h2>
        <p>Đối soát payment attempt, gateway logs và tiền hệ thống thu hộ chủ sân.</p>
      </div>
      <button class="icon-command" type="button" :disabled="loading" title="Tải lại" @click="loadPayments">
        <AppIcon name="refresh" size="18" />
        <span>Tải lại</span>
      </button>
    </header>

    <div class="summary-grid">
      <div class="summary-item">
        <span>Tổng giao dịch</span>
        <strong>{{ summary.total }}</strong>
      </div>
      <div class="summary-item">
        <span>Chờ xử lý</span>
        <strong>{{ summary.pending }}</strong>
      </div>
      <div class="summary-item">
        <span>Thành công</span>
        <strong>{{ summary.paid }}</strong>
      </div>
      <div class="summary-item">
        <span>Tiền đã thu hộ</span>
        <strong>{{ formatCurrency(summary.collected_amount) }}</strong>
      </div>
    </div>

    <form class="filters" @submit.prevent="applyFilters">
      <label class="search-field">
        <AppIcon name="search" size="17" />
        <input v-model.trim="filters.keyword" type="search" placeholder="Mã payment, booking, khách, cụm sân..." />
      </label>
      <select v-model="filters.status">
        <option value="">Tất cả trạng thái</option>
        <option value="pending">Chờ thanh toán</option>
        <option value="paid">Đã thanh toán</option>
        <option value="failed">Thất bại</option>
        <option value="refunded">Đã hoàn tiền</option>
      </select>
      <select v-model="filters.payment_kind">
        <option value="">Tất cả loại</option>
        <option value="full">Thanh toán toàn bộ</option>
        <option value="deposit">Đặt cọc</option>
        <option value="partial">Thanh toán một phần</option>
      </select>
      <select v-model="filters.method">
        <option value="">Tất cả phương thức</option>
        <option value="sepay">SePay</option>
        <option value="bank_transfer">Chuyển khoản</option>
        <option value="wallet">Ví</option>
        <option value="mixed">Kết hợp</option>
        <option value="cash">Tiền mặt</option>
      </select>
      <select v-model="filters.paid_range">
        <option value="">Ngày thanh toán</option>
        <option value="today">Hôm nay</option>
        <option value="yesterday">Hôm qua</option>
        <option value="last_3_days">3 ngày gần đây</option>
        <option value="last_7_days">7 ngày gần đây</option>
        <option value="last_30_days">30 ngày gần đây</option>
        <option value="this_month">Tháng này</option>
        <option value="last_month">Tháng trước</option>
        <option value="custom">Tùy chỉnh</option>
      </select>
      <div v-if="filters.paid_range === 'custom'" class="date-range-fields" aria-label="Khoảng ngày thanh toán tùy chỉnh">
        <input v-model="filters.paid_from" type="date" title="Thanh toán từ ngày" />
        <span>đến</span>
        <input v-model="filters.paid_to" type="date" title="Thanh toán đến ngày" />
      </div>
      <button class="primary-btn" type="submit">
        <AppIcon name="filter" size="16" />
        Lọc
      </button>
      <button class="secondary-btn" type="button" @click="resetFilters">Xóa lọc</button>
    </form>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Payment / Booking</th>
            <th>Khách hàng</th>
            <th>Cụm sân</th>
            <th>Số tiền</th>
            <th>Loại / Phương thức</th>
            <th>Trạng thái</th>
            <th>Paid at</th>
            <th>Logs</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td class="empty" colspan="9">Đang tải giao dịch...</td>
          </tr>
          <tr v-else-if="payments.length === 0">
            <td class="empty" colspan="9">Không có giao dịch phù hợp.</td>
          </tr>
          <tr v-for="payment in payments" :key="payment.id">
            <td>
              <button class="code-link" type="button" @click="openDetail(payment.id)">{{ payment.payment_code }}</button>
              <span class="sub-line">{{ payment.booking?.booking_code || '-' }}</span>
            </td>
            <td>
              <strong>{{ payment.customer?.full_name || payment.customer?.username || '-' }}</strong>
              <span class="sub-line">{{ payment.customer?.email || payment.customer?.phone || '-' }}</span>
            </td>
            <td>{{ payment.venue_cluster?.name || '-' }}</td>
            <td>
              <strong>{{ formatCurrency(payment.amount) }}</strong>
              <span class="sub-line">Gateway: {{ formatCurrency(payment.gateway_amount) }}</span>
            </td>
            <td>
              <span>{{ kindLabel(payment.payment_kind) }}</span>
              <span class="sub-line">{{ methodLabel(payment.method) }}</span>
            </td>
            <td><span class="status-pill" :class="payment.status">{{ statusLabel(payment.status) }}</span></td>
            <td>{{ formatDate(payment.paid_at) }}</td>
            <td>{{ payment.logs_count }}</td>
            <td>
              <button class="icon-only" type="button" title="Xem chi tiết" @click="openDetail(payment.id)">
                <AppIcon name="eye" size="17" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <button class="secondary-btn" type="button" :disabled="meta.current_page <= 1 || loading" @click="changePage(meta.current_page - 1)">Trước</button>
      <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="secondary-btn" type="button" :disabled="meta.current_page >= meta.last_page || loading" @click="changePage(meta.current_page + 1)">Sau</button>
    </div>

    <div v-if="detailOpen" class="drawer-backdrop" @click.self="closeDetail">
      <aside class="detail-drawer">
        <header class="drawer-header">
          <div>
            <span class="eyebrow">Payment detail</span>
            <h3>{{ detail?.payment?.payment_code || 'Đang tải...' }}</h3>
          </div>
          <button class="icon-only" type="button" title="Đóng" @click="closeDetail">
            <AppIcon name="x" size="19" />
          </button>
        </header>

        <div v-if="detailLoading" class="drawer-loading">Đang tải chi tiết...</div>
        <template v-else-if="detail?.payment">
          <div class="detail-facts">
            <div><span>Booking</span><strong>{{ detail.payment.booking?.booking_code }}</strong></div>
            <div><span>Khách</span><strong>{{ detail.payment.customer?.full_name || detail.payment.customer?.username }}</strong></div>
            <div><span>Cụm sân</span><strong>{{ detail.payment.venue_cluster?.name }}</strong></div>
            <div><span>Số tiền</span><strong>{{ formatCurrency(detail.payment.amount) }}</strong></div>
            <div><span>Loại</span><strong>{{ kindLabel(detail.payment.payment_kind) }}</strong></div>
            <div><span>Phương thức</span><strong>{{ methodLabel(detail.payment.method) }}</strong></div>
            <div><span>Trạng thái</span><strong>{{ statusLabel(detail.payment.status) }}</strong></div>
            <div><span>Gateway txn</span><strong>{{ detail.payment.gateway_txn_id || '-' }}</strong></div>
          </div>

          <div class="actions-bar">
            <button v-if="detail.payment.can_retry" class="secondary-btn" type="button" @click="openAction('retry')">
              <AppIcon name="refresh" size="16" /> Retry attempt
            </button>
            <button
              v-for="status in detail.payment.allowed_statuses"
              :key="status"
              class="primary-btn"
              :class="{ danger: status === 'failed' }"
              type="button"
              @click="openAction(status)"
            >
              {{ actionLabel(status) }}
            </button>
          </div>

          <section class="detail-section">
            <h4>Payment logs</h4>
            <div v-if="detail.logs.length === 0" class="empty-block">Chưa có log.</div>
            <article v-for="log in detail.logs" :key="log.id" class="log-row">
              <div class="log-head">
                <strong>{{ log.event_type }}</strong>
                <time>{{ formatDate(log.created_at) }}</time>
              </div>
              <div class="log-meta">
                <span>{{ log.status_before || '-' }} → {{ log.status_after || '-' }}</span>
                <span v-if="log.gateway_txn_id">Txn: {{ log.gateway_txn_id }}</span>
                <span v-if="log.error_code" class="error-text">{{ log.error_code }}</span>
              </div>
              <details v-if="log.request_payload || log.response_payload">
                <summary>Payload</summary>
                <pre>{{ prettyJson({ request: log.request_payload, response: log.response_payload }) }}</pre>
              </details>
            </article>
          </section>

          <section class="detail-section">
            <h4>Ledger ví chủ sân</h4>
            <div v-if="detail.owner_wallet_ledgers.length === 0" class="empty-block">Payment chưa phát sinh ledger ví chủ sân.</div>
            <article v-for="ledger in detail.owner_wallet_ledgers" :key="ledger.id" class="ledger-row">
              <span class="status-pill" :class="ledger.direction">{{ ledger.direction }}</span>
              <strong>{{ formatCurrency(ledger.amount) }}</strong>
              <span>{{ formatCurrency(ledger.balance_before) }} → {{ formatCurrency(ledger.balance_after) }}</span>
              <small>{{ ledger.transaction_code || ledger.reference_code }}</small>
            </article>
          </section>
        </template>
      </aside>
    </div>

    <div v-if="actionType" class="modal-backdrop" @click.self="closeAction">
      <form class="action-modal" @submit.prevent="submitAction">
        <header>
          <h3>{{ actionTitle }}</h3>
          <button class="icon-only" type="button" title="Đóng" @click="closeAction"><AppIcon name="x" size="18" /></button>
        </header>
        <label>
          Nguồn xử lý
          <select v-model="actionForm.source" required>
            <option value="mock">Mock</option>
            <option value="gateway">Gateway</option>
            <option value="admin">Admin đối soát</option>
          </select>
        </label>
        <label v-if="actionType !== 'retry'">
          Gateway transaction ID
          <input v-model.trim="actionForm.gateway_txn_id" type="text" placeholder="Không bắt buộc" />
        </label>
        <label>
          Lý do
          <textarea v-model.trim="actionForm.reason" rows="3" required placeholder="Nhập lý do xử lý"></textarea>
        </label>
        <label v-if="actionType !== 'retry'">
          Gateway / mock response JSON
          <textarea v-model="actionForm.gateway_response_json" rows="6" placeholder='{"message":"mock success"}'></textarea>
        </label>
        <div v-if="actionError" class="alert error">{{ actionError }}</div>
        <footer>
          <button class="secondary-btn" type="button" @click="closeAction">Hủy</button>
          <button class="primary-btn" :class="{ danger: actionType === 'failed' }" type="submit" :disabled="saving">
            {{ saving ? 'Đang xử lý...' : 'Xác nhận' }}
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminPaymentService } from '../../services/adminPayments.js';

export default {
  name: 'AdminPayments',
  components: { AppIcon },
  data() {
    return {
      payments: [],
      summary: { total: 0, pending: 0, paid: 0, failed: 0, refunded: 0, collected_amount: 0 },
      meta: { current_page: 1, last_page: 1, total: 0 },
      filters: {
        keyword: '',
        status: '',
        payment_kind: '',
        method: '',
        paid_range: '',
        paid_from: '',
        paid_to: '',
      },
      loading: false,
      error: '',
      success: '',
      detailOpen: false,
      detailLoading: false,
      detail: null,
      actionType: '',
      actionForm: { source: 'mock', reason: '', gateway_txn_id: '', gateway_response_json: '{}' },
      actionError: '',
      saving: false,
    };
  },
  computed: {
    actionTitle() {
      if (this.actionType === 'retry') return 'Tạo payment attempt mới';
      return `Cập nhật trạng thái: ${this.statusLabel(this.actionType)}`;
    },
  },
  mounted() {
    this.loadPayments();
  },
  methods: {
    async loadPayments(page = this.meta.current_page || 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPaymentService.list(this.paymentFilterParams(page));
        this.payments = response.data || [];
        this.summary = response.summary || this.summary;
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách thanh toán.';
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.loadPayments(1);
    },
    resetFilters() {
      this.filters = {
        keyword: '',
        status: '',
        payment_kind: '',
        method: '',
        paid_range: '',
        paid_from: '',
        paid_to: '',
      };
      this.loadPayments(1);
    },
    changePage(page) {
      this.loadPayments(page);
    },
    paymentFilterParams(page) {
      const params = { ...this.filters, page };
      delete params.paid_range;

      if (this.filters.paid_range === 'custom') {
        if (!params.paid_from) delete params.paid_from;
        if (!params.paid_to) delete params.paid_to;
        return params;
      }

      delete params.paid_from;
      delete params.paid_to;
      const range = this.resolveDateRange(this.filters.paid_range);

      if (range) {
        params.paid_from = range.from;
        params.paid_to = range.to;
      }

      return params;
    },
    resolveDateRange(value) {
      const today = new Date();
      const from = new Date(today);
      const to = new Date(today);

      if (value === 'today') {
        return { from: this.dateInputValue(from), to: this.dateInputValue(to) };
      }

      if (value === 'yesterday') {
        from.setDate(from.getDate() - 1);
        to.setDate(to.getDate() - 1);
        return { from: this.dateInputValue(from), to: this.dateInputValue(to) };
      }

      if (value === 'last_3_days') {
        from.setDate(from.getDate() - 3);
        return { from: this.dateInputValue(from), to: this.dateInputValue(to) };
      }

      if (value === 'last_7_days') {
        from.setDate(from.getDate() - 7);
        return { from: this.dateInputValue(from), to: this.dateInputValue(to) };
      }

      if (value === 'last_30_days') {
        from.setDate(from.getDate() - 30);
        return { from: this.dateInputValue(from), to: this.dateInputValue(to) };
      }

      if (value === 'this_month') {
        from.setDate(1);
        return { from: this.dateInputValue(from), to: this.dateInputValue(to) };
      }

      if (value === 'last_month') {
        const firstDayThisMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDayLastMonth = new Date(firstDayThisMonth);
        lastDayLastMonth.setDate(0);
        const firstDayLastMonth = new Date(lastDayLastMonth.getFullYear(), lastDayLastMonth.getMonth(), 1);
        return { from: this.dateInputValue(firstDayLastMonth), to: this.dateInputValue(lastDayLastMonth) };
      }

      return null;
    },
    dateInputValue(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
    async openDetail(id) {
      this.detailOpen = true;
      this.detailLoading = true;
      this.detail = null;
      try {
        const response = await adminPaymentService.show(id);
        this.detail = response.data;
      } catch (error) {
        this.error = error.message || 'Không tải được chi tiết payment.';
        this.detailOpen = false;
      } finally {
        this.detailLoading = false;
      }
    },
    closeDetail() {
      this.detailOpen = false;
      this.detail = null;
    },
    openAction(type) {
      this.actionType = type;
      this.actionError = '';
      this.actionForm = { source: 'mock', reason: '', gateway_txn_id: '', gateway_response_json: '{}' };
    },
    closeAction() {
      this.actionType = '';
      this.actionError = '';
    },
    async submitAction() {
      if (!this.detail?.payment) return;
      this.saving = true;
      this.actionError = '';
      try {
        let response;
        if (this.actionType === 'retry') {
          response = await adminPaymentService.retry(this.detail.payment.id, {
            source: this.actionForm.source,
            reason: this.actionForm.reason,
          });
        } else {
          let gatewayResponse = {};
          try {
            gatewayResponse = this.actionForm.gateway_response_json ? JSON.parse(this.actionForm.gateway_response_json) : {};
          } catch {
            throw new Error('Gateway response phải là JSON hợp lệ.');
          }
          response = await adminPaymentService.updateStatus(this.detail.payment.id, {
            status: this.actionType,
            source: this.actionForm.source,
            reason: this.actionForm.reason,
            gateway_txn_id: this.actionForm.gateway_txn_id || null,
            gateway_response: gatewayResponse,
          });
        }
        this.success = response.message;
        this.closeAction();
        await this.loadPayments(this.meta.current_page);
        await this.openDetail(response.data.id);
      } catch (error) {
        this.actionError = error.message || 'Không thể xử lý payment.';
      } finally {
        this.saving = false;
      }
    },
    statusLabel(value) {
      return { pending: 'Chờ thanh toán', paid: 'Đã thanh toán', failed: 'Thất bại', refunded: 'Đã hoàn tiền' }[value] || value;
    },
    kindLabel(value) {
      return { full: 'Toàn bộ', deposit: 'Đặt cọc', partial: 'Một phần' }[value] || value;
    },
    methodLabel(value) {
      return { sepay: 'SePay', bank_transfer: 'Chuyển khoản', cash: 'Tiền mặt', wallet: 'Ví', mixed: 'Kết hợp' }[value] || value;
    },
    actionLabel(value) {
      return { paid: 'Xác nhận paid', failed: 'Đánh dấu failed' }[value] || value;
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    prettyJson(value) {
      return JSON.stringify(value, null, 2);
    },
  },
};
</script>

<style scoped>
.admin-payments { display: flex; flex-direction: column; gap: 18px; }
.page-header, .filters, .actions-bar, .drawer-header, .log-head, .log-meta, .pagination, .action-modal header, .action-modal footer { display: flex; align-items: center; }
.page-header { justify-content: space-between; gap: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; color: #0f172a; }
.page-header p { margin: 0; color: #64748b; font-size: 13px; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
.summary-item { padding: 16px; border-right: 1px solid #e2e8f0; }
.summary-item:last-child { border-right: 0; }
.summary-item span, .sub-line, .detail-facts span, .ledger-row small { display: block; color: #64748b; font-size: 12px; }
.summary-item strong { display: block; margin-top: 7px; font-size: 20px; color: #0f172a; }
.filters { gap: 8px; flex-wrap: wrap; align-items: stretch; }
.filters select, .filters input, .action-modal select, .action-modal input, .action-modal textarea { border: 1px solid #dbe2ea; border-radius: 7px; background: #fff; color: #0f172a; padding: 9px 10px; font: inherit; }
.search-field { display: flex; align-items: center; gap: 8px; min-width: 290px; border: 1px solid #dbe2ea; border-radius: 7px; padding: 0 10px; background: #fff; }
.search-field input { flex: 1; border: 0; padding-inline: 0; outline: 0; }
.date-range-fields { display: inline-flex; align-items: center; gap: 8px; padding: 0 8px; border: 1px solid #dbe2ea; border-radius: 7px; background: #f8fafc; color: #64748b; }
.date-range-fields input { width: 142px; border-color: transparent; background: #fff; }
.primary-btn, .secondary-btn, .icon-command, .icon-only { display: inline-flex; align-items: center; justify-content: center; gap: 7px; border-radius: 7px; font-weight: 700; cursor: pointer; }
.primary-btn { border: 1px solid #16a34a; background: #16a34a; color: #fff; padding: 9px 12px; }
.primary-btn.danger { border-color: #dc2626; background: #dc2626; }
.secondary-btn, .icon-command { border: 1px solid #dbe2ea; background: #f8fafc; color: #334155; padding: 9px 12px; }
.icon-only { width: 34px; height: 34px; border: 1px solid #dbe2ea; background: #fff; color: #475569; }
button:disabled { opacity: .55; cursor: not-allowed; }
.alert { padding: 11px 13px; border-radius: 7px; font-size: 13px; }
.alert.error { background: #fef2f2; color: #b91c1c; }
.alert.success { background: #ecfdf5; color: #047857; }
.table-wrap { overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
table { width: 100%; min-width: 1220px; border-collapse: collapse; }
th, td { padding: 12px 13px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; font-size: 13px; }
th { background: #f8fafc; color: #334155; font-weight: 800; }
.empty { padding: 28px; text-align: center; color: #64748b; }
.code-link { padding: 0; background: transparent; color: #15803d; font-weight: 800; text-decoration: underline; }
.status-pill { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.status-pill.pending { background: #fef3c7; color: #92400e; }
.status-pill.paid, .status-pill.credit { background: #dcfce7; color: #166534; }
.status-pill.failed, .status-pill.refunded, .status-pill.debit { background: #fee2e2; color: #991b1b; }
.pagination { justify-content: flex-end; gap: 12px; color: #64748b; font-size: 13px; }
.drawer-backdrop, .modal-backdrop { position: fixed; inset: 0; z-index: 500; background: rgba(15, 23, 42, .48); }
.detail-drawer { position: absolute; top: 0; right: 0; width: min(720px, 100vw); height: 100%; overflow: auto; background: #f8fafc; box-shadow: -20px 0 50px rgba(15, 23, 42, .18); padding: 22px; }
.drawer-header { justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.drawer-header h3 { margin: 3px 0 0; font-size: 22px; }
.eyebrow { color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.drawer-loading, .empty-block { padding: 24px; text-align: center; color: #64748b; }
.detail-facts { display: grid; grid-template-columns: repeat(2, 1fr); background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
.detail-facts div { padding: 12px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
.detail-facts div:nth-child(2n) { border-right: 0; }
.detail-facts strong { display: block; margin-top: 4px; color: #0f172a; word-break: break-word; }
.actions-bar { gap: 8px; flex-wrap: wrap; margin: 16px 0; }
.detail-section { margin-top: 18px; }
.detail-section h4 { margin: 0 0 9px; font-size: 15px; }
.log-row, .ledger-row { border: 1px solid #e2e8f0; background: #fff; padding: 12px; margin-bottom: 8px; border-radius: 7px; }
.log-head { justify-content: space-between; gap: 12px; }
.log-head time, .log-meta { color: #64748b; font-size: 11px; }
.log-meta { gap: 12px; margin-top: 5px; }
.error-text { color: #b91c1c; }
details { margin-top: 8px; }
summary { cursor: pointer; color: #475569; font-size: 12px; font-weight: 700; }
pre { max-height: 250px; overflow: auto; padding: 10px; background: #0f172a; color: #d1fae5; border-radius: 6px; font-size: 11px; white-space: pre-wrap; }
.ledger-row { display: grid; grid-template-columns: auto 1fr 1.5fr; align-items: center; gap: 8px 12px; }
.ledger-row small { grid-column: 2 / -1; }
.modal-backdrop { display: grid; place-items: center; padding: 20px; }
.action-modal { width: min(520px, calc(100vw - 32px)); padding: 22px; border-radius: 10px; background: #fff; display: flex; flex-direction: column; gap: 14px; }
.action-modal header { justify-content: space-between; }
.action-modal h3 { margin: 0; font-size: 20px; }
.action-modal label { display: flex; flex-direction: column; gap: 6px; color: #334155; font-size: 13px; font-weight: 700; }
.action-modal footer { justify-content: flex-end; gap: 8px; }
@media (max-width: 900px) {
  .summary-grid { grid-template-columns: repeat(2, 1fr); }
  .summary-item:nth-child(2) { border-right: 0; }
}
@media (max-width: 600px) {
  .page-header { align-items: flex-start; flex-direction: column; }
  .summary-grid, .detail-facts { grid-template-columns: 1fr; }
  .summary-item, .detail-facts div { border-right: 0; }
  .search-field { min-width: 100%; }
}
</style>
