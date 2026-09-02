<template>
  <div class="cluster-profile-surface standalone">
    <!-- Toast / Alerts -->
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <!-- Top Status Tabs Row (Header Hero outside main content card) -->
    <div class="refund-header-hero">
      <div class="hero-integrated-tabs">
        <AppTabs
          :tabs="statusTabsForAppTabs"
          :model-value="filters.status"
          @update:model-value="setStatus"
        />
      </div>
    </div>

    <!-- Main Content Surface -->
    <div class="profile-section-card refund-main-content">
      <!-- Quick Attention Banner -->
      <div v-if="summaryStats.pending > 0" class="attention-banner">
        <span class="attention-icon">!</span>
        <div class="attention-text">
          <strong>{{ summaryStats.pending }} yêu cầu đang chờ bạn xác nhận</strong>
          <small>Xác nhận hoặc từ chối để hệ thống xử lý hoàn tiền cho khách hàng.</small>
        </div>
        <button class="attention-btn" type="button" @click="setStatus('pending_owner_confirmation')">
          Xem danh sách chờ
        </button>
      </div>

      <!-- Summary Statistics Grid -->
      <div class="summary-grid">
        <article class="summary-item" :class="{ highlight: summaryStats.pending > 0 }">
          <span class="summary-label">Chờ xác nhận</span>
          <strong class="summary-value">{{ summaryStats.pending }}</strong>
          <small class="summary-sub">Cần chủ sân xử lý</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Đã hoàn ví</span>
          <strong class="summary-value">{{ summaryStats.completedWalletCount }}</strong>
          <small class="summary-sub">{{ formatCurrency(summaryStats.completedWalletAmount) }}</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Hoàn tiền mặt</span>
          <strong class="summary-value">{{ summaryStats.completedCashCount }}</strong>
          <small class="summary-sub">{{ formatCurrency(summaryStats.completedCashAmount) }}</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Đã từ chối / Hủy</span>
          <strong class="summary-value danger-text">{{ summaryStats.rejected }}</strong>
          <small class="summary-sub">Không hoàn tiền</small>
        </article>
        <article class="summary-item">
          <span class="summary-label">Tổng số yêu cầu</span>
          <strong class="summary-value">{{ summaryStats.total }}</strong>
          <small class="summary-sub">Toàn bộ lịch sử</small>
        </article>
      </div>

      <!-- Table Section (styled exactly like ServicesTable.vue) -->
      <div class="services-table-section">
        <div v-if="loading" class="table-state-card">
          <div class="spinner-sm"></div>
          <span>Đang tải danh sách yêu cầu hoàn/hủy...</span>
        </div>

        <div v-else-if="refunds.length === 0" class="table-state-card">
          <span>Không có yêu cầu hoàn/hủy nào.</span>
        </div>

        <div v-else class="services-table-wrapper">
          <table class="services-data-table">
            <thead>
              <tr>
                <th>Booking / Khách</th>
                <th>Thời gian chơi</th>
                <th>Thanh toán</th>
                <th>Lý do hủy</th>
                <th>Mức hoàn</th>
                <th>Trạng thái</th>
                <th class="action-col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="refund in refunds" :key="refund.id">
                <td class="cell-name">
                  <button class="code-link" type="button" @click="openDetail(refund)">
                    <strong>{{ refund.booking?.booking_code || refund.payment?.payment_code || statusLabel(refund.status) }}</strong>
                  </button>
                  <small class="customer-sub">{{ customerName(refund) }} · {{ refund.customer?.phone || refund.customer?.email || '-' }}</small>
                </td>
                <td>
                  <strong>{{ formatDate(refund.booking?.booking_date) }}</strong>
                  <small class="cell-sub">{{ formatTime(refund.booking?.start_time) }} - {{ formatTime(refund.booking?.end_time) }}</small>
                </td>
                <td>
                  <strong>{{ formatCurrency(refund.payment?.amount) }}</strong>
                  <small class="cell-sub">{{ paymentMethod(refund.payment?.method) }} · {{ refund.payment?.payment_code || '-' }}</small>
                </td>
                <td class="cell-desc">
                  <span class="desc-text" :title="refund.reason || refund.booking?.status_reason">
                    {{ refund.reason || refund.booking?.status_reason || '-' }}
                  </span>
                </td>
                <td>
                  <strong>{{ formatCurrency(refundAmount(refund)) }}</strong>
                  <small class="cell-sub">{{ policyText(refund) }}</small>
                </td>
                <td class="cell-status">
                  <span class="status-pill" :class="refund.status">{{ statusLabel(refund.status) }}</span>
                </td>
                <td class="action-col">
                  <div class="table-actions">
                    <button
                      type="button"
                      class="action-btn view-btn"
                      title="Xem chi tiết"
                      @click="openDetail(refund)"
                    >
                      Chi tiết
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav v-if="meta.last_page > 1" class="pagination" aria-label="Phân trang">
          <ActionIconButton
            icon="chevronLeft"
            label="Trang trước"
            :disabled="meta.current_page <= 1"
            @click="loadRefunds(meta.current_page - 1)"
          />
          <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
          <ActionIconButton
            icon="chevronRight"
            label="Trang sau"
            :disabled="meta.current_page >= meta.last_page"
            @click="loadRefunds(meta.current_page + 1)"
          />
        </nav>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="detailRefund" class="modal-backdrop" @click.self="detailRefund = null">
      <article class="detail-modal">
        <header class="modal-head">
          <div>
            <p class="eyebrow">CHI TIẾT YÊU CẦU HOÀN/HỦY</p>
            <h3>{{ detailRefund.booking?.booking_code || 'Mã yêu cầu' }}</h3>
            <small class="modal-sub">{{ detailRefund.venue_cluster?.name || 'Cụm sân' }}</small>
          </div>
          <button type="button" class="close-btn" @click="detailRefund = null">×</button>
        </header>

        <div class="amount-box">
          <span>Số tiền hoàn dự kiến cho khách</span>
          <strong>{{ formatCurrency(refundAmount(detailRefund)) }}</strong>
        </div>

        <dl class="detail-grid">
          <div><dt>Khách hàng</dt><dd>{{ customerName(detailRefund) }}</dd></div>
          <div><dt>Liên hệ</dt><dd>{{ detailRefund.customer?.phone || detailRefund.customer?.email || '-' }}</dd></div>
          <div><dt>Thời gian chơi</dt><dd>{{ formatDate(detailRefund.booking?.booking_date) }}, {{ formatTime(detailRefund.booking?.start_time) }} - {{ formatTime(detailRefund.booking?.end_time) }}</dd></div>
          <div><dt>Đã thanh toán</dt><dd>{{ formatCurrency(detailRefund.payment?.amount) }}</dd></div>
          <div><dt>Hình thức hoàn</dt><dd>{{ refundDestinationLabel(detailRefund) }}</dd></div>
          <div><dt>Trạng thái</dt><dd><span class="status-pill" :class="detailRefund.status">{{ statusLabel(detailRefund.status) }}</span></dd></div>
          <div v-if="detailRefund.receipt">
            <dt>Hóa đơn</dt>
            <dd>
              <button class="receipt-link inline" type="button" @click="openReceipt(detailRefund.receipt)">
                {{ detailRefund.receipt.receipt_code }}
              </button>
            </dd>
          </div>
        </dl>

        <section class="policy-band">
          <strong>{{ detailRefund.policy_evaluation?.is_owner_fault_refund ? 'Hoàn do lỗi phía sân' : 'Đối chiếu chính sách' }}</strong>
          <p>{{ detailRefund.policy_evaluation?.summary || 'Chưa đủ dữ liệu để xác định quy tắc tự động.' }}</p>
          <small v-if="detailRefund.policy_evaluation?.warning" class="danger-text">{{ detailRefund.policy_evaluation.warning }}</small>
        </section>

        <section class="reason-block">
          <strong>{{ detailRefund.policy_evaluation?.is_owner_fault_refund ? 'Lý do hoàn tiền' : 'Lý do khách hủy' }}</strong>
          <p>{{ detailRefund.reason || '-' }}</p>
        </section>

        <section v-if="detailRefund.owner_confirm_note" class="reason-block">
          <strong>{{ detailRefund.status === 'owner_rejected' ? 'Lý do từ chối' : 'Phản hồi của chủ sân' }}</strong>
          <p>{{ detailRefund.owner_confirm_note }}</p>
        </section>

        <section v-else-if="detailRefund.status_reason" class="reason-block warning-block">
          <strong>{{ detailRefund.status === 'owner_rejected' ? 'Lý do từ chối' : 'Ghi chú xử lý' }}</strong>
          <p>{{ detailRefund.status_reason }}</p>
        </section>

        <footer v-if="detailRefund.can_decide" class="modal-footer">
          <button class="danger-btn" type="button" @click="openDecision(detailRefund, 'reject')">Từ chối</button>
          <button v-if="detailRefund.can_refund_wallet" class="submit-btn" type="button" @click="openDecision(detailRefund, 'approve')">Hoàn ví</button>
          <button v-if="detailRefund.can_refund_cash" class="cash-btn" type="button" @click="openDecision(detailRefund, 'approve_cash')">Hoàn tiền mặt</button>
        </footer>
      </article>
    </div>

    <!-- Decision Modal -->
    <div v-if="decisionRefund" class="modal-backdrop" @click.self="closeDecision">
      <form class="decision-modal" @submit.prevent="submitDecision">
        <header class="modal-head">
          <div>
            <p class="eyebrow">XỬ LÝ YÊU CẦU</p>
            <h3>{{ decisionTitle }}</h3>
            <small class="modal-sub">{{ decisionRefund.booking?.booking_code }} · {{ customerName(decisionRefund) }}</small>
          </div>
          <button type="button" class="close-btn" @click="closeDecision">×</button>
        </header>

        <div v-if="['approve', 'approve_cash'].includes(decision)" class="amount-box">
          <div>
            <span>{{ decision === 'approve_cash' ? 'Số tiền đã hoàn tiền mặt' : (decisionRefund.policy_evaluation?.is_owner_fault_refund ? 'Số tiền hoàn 100% do lỗi phía sân' : 'Số tiền hoàn theo chính sách') }}</span>
            <small>{{ decisionRefund.policy_evaluation?.summary || 'SportGo sẽ dùng số tiền đã được hệ thống tính cho yêu cầu này.' }}</small>
          </div>
          <strong>{{ formatCurrency(refundAmount(decisionRefund)) }}</strong>
        </div>

        <label class="field">
          <span>{{ decisionNoteLabel }}</span>
          <textarea v-model.trim="decisionForm.note" rows="4" maxlength="2000" :required="decision === 'reject'" placeholder="Nhập ghi chú phản hồi..." />
        </label>

        <footer class="modal-footer">
          <button class="cancel-btn" type="button" :disabled="submitting" @click="closeDecision">Đóng</button>
          <button :class="decision === 'reject' ? 'danger-btn' : decision === 'approve_cash' ? 'cash-btn' : 'submit-btn'" type="submit" :disabled="submitting">
            {{ submitting ? 'Đang xử lý...' : decisionSubmitLabel }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerRefundRequests',
  components: { ActionIconButton, AppTabs },
  data() {
    return {
      refunds: [],
      filters: { status: '' },
      statusTabs: [
        { value: '', label: 'Tất cả' },
        { value: 'pending_owner_confirmation', label: 'Chờ xác nhận' },
        { value: 'owner_rejected', label: 'Đã từ chối' },
        { value: 'completed', label: 'Hoàn ví' },
        { value: 'completed_cash', label: 'Hoàn tiền mặt' },
      ],
      meta: { current_page: 1, last_page: 1, total: 0 },
      loading: false,
      submitting: false,
      error: '',
      notice: '',
      detailRefund: null,
      decisionRefund: null,
      decision: 'approve',
      decisionForm: { note: '' },
    };
  },
  computed: {
    statusTabsForAppTabs() {
      return this.statusTabs.map((tab) => ({
        key: tab.value,
        value: tab.value,
        label: tab.label,
      }));
    },
    summaryStats() {
      const stats = {
        pending: 0,
        completedWalletCount: 0,
        completedWalletAmount: 0,
        completedCashCount: 0,
        completedCashAmount: 0,
        rejected: 0,
        total: this.meta.total || this.refunds.length,
      };

      this.refunds.forEach((item) => {
        const amt = this.refundAmount(item);
        if (item.status === 'pending_owner_confirmation') {
          stats.pending += 1;
        } else if (item.status === 'completed') {
          stats.completedWalletCount += 1;
          stats.completedWalletAmount += amt;
        } else if (item.status === 'completed_cash') {
          stats.completedCashCount += 1;
          stats.completedCashAmount += amt;
        } else if (item.status === 'owner_rejected') {
          stats.rejected += 1;
        }
      });

      return stats;
    },
    decisionTitle() {
      return {
        approve: 'Xác nhận hoàn vào ví',
        approve_cash: 'Xác nhận hoàn tiền mặt',
        reject: 'Từ chối yêu cầu',
      }[this.decision] || 'Xử lý yêu cầu';
    },
    decisionNoteLabel() {
      return {
        approve: 'Ghi chú xác nhận',
        approve_cash: 'Ghi chú hoàn tiền mặt',
        reject: 'Lý do từ chối',
      }[this.decision] || 'Ghi chú';
    },
    decisionSubmitLabel() {
      return {
        approve: 'Xác nhận hoàn ví',
        approve_cash: 'Xác nhận tiền mặt',
        reject: 'Từ chối',
      }[this.decision] || 'Xác nhận';
    },
  },
  async mounted() {
    await this.loadRefunds();
  },
  methods: {
    async loadRefunds(page = 1) {
      this.loading = true;
      this.error = '';
      const params = new URLSearchParams({ page: String(page) });
      if (this.filters.status) {
        params.set('status', this.filters.status);
      }

      try {
        const response = await api(`/api/owner/refunds?${params.toString()}`);
        this.refunds = response.data || [];
        this.meta = response.meta || this.meta;
        const focusId = String(this.$route.query.focus || '');
        if (focusId) {
          const focused = this.refunds.find((refund) => String(refund.id) === focusId);
          if (focused) this.openDetail(focused);
        }
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách yêu cầu.';
      } finally {
        this.loading = false;
      }
    },
    setStatus(status) {
      this.filters.status = String(status || '');
      this.loadRefunds(1);
    },
    openDetail(refund) {
      this.detailRefund = refund;
    },
    openDecision(refund, decision) {
      this.detailRefund = null;
      this.decisionRefund = refund;
      this.decision = decision;
      this.decisionForm = {
        note: '',
      };
    },
    closeDecision() {
      if (this.submitting) return;
      this.decisionRefund = null;
    },
    async submitDecision() {
      this.submitting = true;
      this.error = '';
      try {
        const payload = {
          decision: this.decision,
          note: this.decisionForm.note,
        };
        const response = await api(`/api/owner/refunds/${this.decisionRefund.id}/decision`, {
          method: 'PATCH',
          body: JSON.stringify(payload),
        });
        this.notice = response.message;
        this.closeDecision();
        await this.loadRefunds(this.meta.current_page);
      } catch (error) {
        this.error = error.message || 'Không thể xử lý yêu cầu.';
      } finally {
        this.submitting = false;
        if (this.decisionRefund && this.notice) this.decisionRefund = null;
      }
    },
    openReceipt(receipt) {
      if (!receipt?.view_url) return;
      window.open(receipt.view_url, '_blank', 'noopener,noreferrer');
    },
    refundAmount(refund) {
      return Number(refund?.policy_evaluation?.suggested_amount ?? refund?.payment?.amount ?? refund?.amount ?? 0);
    },
    policyText(refund) {
      if (refund.policy_evaluation?.is_owner_fault_refund) {
        return 'Hoàn 100% do lỗi phía sân';
      }
      const percent = refund.policy_evaluation?.refund_percent;
      return percent === undefined ? 'Chờ đối chiếu' : `Chính sách ${Number(percent)}%`;
    },
    customerName(refund) {
      return refund.customer?.full_name || refund.customer?.username || 'Khách hàng';
    },
    statusLabel(status) {
      return {
        pending_owner_confirmation: 'Chờ chủ sân',
        owner_rejected: 'Chủ sân từ chối',
        completed: 'Đã hoàn ví',
        completed_cash: 'Đã hoàn tiền mặt',
      }[status] || status;
    },
    paymentMethod(method) {
      return { sepay: 'Chuyển khoản QR', wallet: 'Ví SportGo', cash: 'Tiền mặt', bank_transfer: 'Chuyển khoản' }[method] || 'Không xác định';
    },
    refundDestinationLabel(refund) {
      return {
        user_wallet: 'Ví SportGo của khách',
        cash: 'Tiền mặt tại sân',
        original_payment: 'Theo phương thức thanh toán gốc',
        bank_account: 'Tài khoản ngân hàng',
      }[refund?.refund_destination] || refund?.refund_destination || '-';
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(value || 0);
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('vi-VN');
    },
    formatTime(value) {
      return value ? String(value).slice(0, 5) : '--:--';
    },
  },
};
</script>

<style scoped>
.cluster-profile-surface.standalone {
  width: 100%;
  min-width: 0;
  background: transparent;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Header Hero Tabs Row matching ServicesHeaderHero */
.refund-header-hero {
  background: var(--admin-surface, #ffffff);
  padding: 10px 10px 0 10px;
  display: flex;
  align-items: center;
}

.hero-integrated-tabs {
  flex: 1;
}

/* Single unified main surface */
.profile-section-card.refund-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 10px;
  background: var(--admin-surface, #ffffff);
  border: none;
  border-radius: 0;
  box-shadow: none;
  margin-top: 0 !important;
}

.alert {
  border-radius: 8px;
  padding: 14px 16px;
  font-weight: 500;
  font-size: 13px;
}

.alert.error {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--admin-danger, #ef4444);
}

.alert.success {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #16a34a);
}

/* Attention Banner */
.attention-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  color: var(--admin-danger, #ef4444);
}

.attention-text {
  display: grid;
  gap: 2px;
  flex: 1;
}

.attention-text strong {
  font-size: 13px;
}

.attention-text small {
  color: var(--admin-muted, #64748b);
  font-size: 12px;
}

.attention-icon {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  flex: 0 0 26px;
  border: 2px solid currentColor;
  border-radius: 50%;
  font-weight: 600;
  font-size: 13px;
}

.attention-btn {
  height: 34px;
  border: 0;
  border-radius: 6px;
  padding: 0 14px;
  background: var(--admin-danger, #ef4444);
  color: #ffffff;
  font: inherit;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

/* Summary Grid */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}

.summary-item {
  display: grid;
  gap: 4px;
  padding: 14px 16px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 8px;
}

.summary-item.highlight {
  border-color: var(--admin-primary-ring, rgba(34, 166, 83, 0.25));
  background: var(--admin-primary-soft, #f0fdf4);
}

.summary-label,
.summary-sub {
  color: var(--admin-muted, #64748b);
  font-size: 12px;
}

.summary-value {
  font-size: 20px;
  font-weight: 600;
  color: var(--admin-text, #101c15);
}

.summary-item.highlight .summary-value {
  color: var(--admin-primary, #22a653);
}

.danger-text {
  color: var(--admin-danger, #ef4444) !important;
}

/* Services Table Section (matching ServicesTable.vue) */
.services-table-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* States */
.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  padding: 36px 20px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
  text-align: center;
}

.spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid var(--admin-border, #cfded1);
  border-top-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Services Table Wrapper & Data Table matching ServicesTable.vue */
.services-table-wrapper {
  overflow-x: auto;
  border: none;
  border-radius: 10px;
}

.services-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.services-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.services-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: middle;
}

.services-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.services-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.cell-name strong {
  font-weight: 400;
}

.customer-sub,
.cell-sub {
  display: block;
  margin-top: 3px;
  color: var(--admin-muted, #64748b);
  font-size: 12px;
}

.code-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--admin-primary, #22a653);
  font: inherit;
  cursor: pointer;
  text-align: left;
}

.code-link strong {
  font-weight: 400;
}

.code-link:hover strong {
  text-decoration: underline;
}

.cell-desc {
  max-width: 240px;
}

.desc-text {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.45;
  color: var(--admin-muted, #2f3d34);
}

/* Status Pills */
.status-pill {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 400;
  white-space: nowrap;
}

.status-pill.pending_owner_confirmation {
  background: var(--admin-warning-soft, rgba(245, 158, 11, 0.1));
  color: var(--admin-warning, #d97706);
}

.status-pill.owner_rejected {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.1));
  color: var(--admin-danger, #ef4444);
}

.status-pill.completed,
.status-pill.completed_cash {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.1));
  color: var(--admin-primary, #22a653);
}

/* Actions Column & Action Buttons matching ServicesTable.vue */
.action-col {
  width: 1%;
  min-width: 90px;
  text-align: right;
}

.table-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.action-btn {
  height: 30px;
  padding: 0 10px;
  border-radius: 6px;
  border: none;
  font-size: 12px;
  font-weight: 400;
  cursor: pointer;
  transition: all 0.12s ease;
}

.view-btn {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  border: 1px solid var(--admin-border, #cfded1);
}

.view-btn:hover {
  background: var(--admin-hover, #edf7ed);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding-top: 12px;
}

/* Modals System */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 600;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, .58);
}

.detail-modal,
.decision-modal {
  display: grid;
  gap: 16px;
  width: min(600px, calc(100vw - 32px));
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  padding: 20px;
  border-radius: 12px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border, #e2e8f0);
  box-shadow: 0 20px 50px rgba(15, 23, 42, .22);
}

.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.eyebrow {
  margin: 0 0 4px;
  color: var(--admin-primary, #22a653);
  font-size: 11px;
  font-weight: 400;
  letter-spacing: .11em;
  text-transform: uppercase;
}

.modal-head h3 {
  margin: 0;
  color: var(--admin-text, #101c15);
  font-size: 17px;
  font-weight: 400;
}

.modal-sub {
  display: block;
  margin-top: 4px;
  color: var(--admin-muted, #64748b);
  font-size: 13px;
}

.close-btn {
  border: 0;
  padding: 2px 8px;
  background: transparent;
  color: var(--admin-muted, #64748b);
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  border-radius: 6px;
}

.close-btn:hover {
  background: var(--admin-hover, #edf7ed);
  color: var(--admin-text, #101c15);
}

.amount-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 12px 14px;
  border-radius: 8px;
  background: var(--admin-primary-soft, #f0fdf4);
  border: 1px solid var(--admin-primary-ring, rgba(34, 166, 83, 0.25));
  color: var(--admin-primary, #22a653);
}

.amount-box span {
  font-size: 13px;
  font-weight: 400;
}

.amount-box small {
  display: block;
  margin-top: 3px;
  color: var(--admin-muted, #64748b);
}

.amount-box strong {
  font-size: 18px;
  color: var(--admin-primary, #22a653);
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  margin: 0;
  padding: 4px 0;
}

.detail-grid div {
  padding: 10px 0;
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
}

.detail-grid dt {
  margin-bottom: 3px;
  font-size: 11px;
  color: var(--admin-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: .03em;
}

.detail-grid dd {
  margin: 0;
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.policy-band,
.reason-block {
  padding: 12px 14px;
  border-radius: 6px;
  border-left: 3px solid var(--admin-primary, #22a653);
  background: var(--admin-primary-soft, #f0fdf4);
}

.warning-block {
  border-left-color: var(--admin-danger, #ef4444);
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
}

.policy-band strong,
.reason-block strong {
  font-size: 13px;
  color: var(--admin-text, #101c15);
}

.policy-band p,
.reason-block p {
  margin: 5px 0 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--admin-text, #101c15);
}

.policy-band small {
  display: block;
  margin-top: 6px;
}

.field {
  display: grid;
  gap: 6px;
}

.field span {
  font-size: 13px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
}

.field textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 6px;
  background: var(--admin-surface, #ffffff);
  font: inherit;
  font-size: 13px;
  color: var(--admin-text, #101c15);
  resize: vertical;
}

.field textarea:focus {
  outline: 0;
  border-color: var(--admin-primary, #22a653);
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--admin-border-soft, #e2e8f0);
}

.submit-btn,
.cancel-btn,
.danger-btn,
.cash-btn {
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 6px;
  font: inherit;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  white-space: nowrap;
}

.submit-btn {
  background: var(--admin-primary, #22a653);
  color: #ffffff;
}

.cancel-btn {
  background: var(--admin-hover, #f8fafc);
  border: 1px solid var(--admin-border, #e2e8f0);
  color: var(--admin-text, #101c15);
}

.danger-btn {
  background: var(--admin-danger, #ef4444);
  color: #ffffff;
}

.cash-btn {
  background: #0f766e;
  color: #ffffff;
}

.submit-btn:disabled,
.cancel-btn:disabled,
.danger-btn:disabled,
.cash-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.receipt-link {
  display: inline-flex;
  align-items: center;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--admin-primary, #22a653);
  font: inherit;
  font-size: 13px;
  text-decoration: underline;
  cursor: pointer;
}

@media (max-width: 720px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

.profile-section-card.refund-main-content {
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.services-table-wrapper,
.services-table-section {
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
}

.summary-grid,
.attention-banner {
  border-radius: 0 !important;
}
</style>
