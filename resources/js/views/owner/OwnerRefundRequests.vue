<template>
  <section class="refund-page">
    <header class="page-head">
      <div>
        <h1>Yêu cầu hoàn/hủy</h1>
        <p>Xác nhận yêu cầu hoàn tiền; SportGo sẽ cộng tiền hoàn vào ví của khách sau khi xử lý.</p>
      </div>
      <ActionIconButton icon="refresh" label="Tải lại" :disabled="loading" @click="loadRefunds" />
    </header>

    <div class="status-tabs" role="tablist" aria-label="Lọc trạng thái hoàn tiền">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        type="button"
        :class="{ active: filters.status === tab.value }"
        @click="setStatus(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <form class="filters" @submit.prevent="loadRefunds(1)">
      <label class="search-field">
        <AppIcon name="search" size="17" />
        <input v-model.trim="filters.keyword" type="search" placeholder="Mã booking, payment, tên hoặc số điện thoại" />
      </label>
      <input v-model="filters.date_from" type="date" aria-label="Từ ngày" />
      <input v-model="filters.date_to" type="date" aria-label="Đến ngày" :min="filters.date_from" />
      <ActionIconButton icon="filter" label="Lọc" variant="primary" type="submit" />
      <ActionIconButton icon="x" label="Xóa lọc" @click="clearFilters" />
    </form>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <div class="table-card">
      <div v-if="loading" class="state-card">Đang tải yêu cầu...</div>
      <div v-else-if="refunds.length === 0" class="state-card">Không có yêu cầu phù hợp.</div>
      <div v-else class="table-scroll">
        <table class="responsive-table refund-table">
          <thead>
            <tr>
              <th>Booking / Khách</th>
              <th>Thời gian chơi</th>
              <th>Thanh toán</th>
              <th>Lý do hủy</th>
              <th>Mức hoàn</th>
              <th>Trạng thái</th>
              <th class="actions-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="refund in refunds" :key="refund.id">
              <td data-label="Booking / Khách">
                <button class="code-link" type="button" @click="openDetail(refund)">
                  {{ refund.booking?.booking_code || shortId(refund.id) }}
                </button>
                <small>{{ customerName(refund) }} · {{ refund.customer?.phone || refund.customer?.email || '-' }}</small>
              </td>
              <td data-label="Thời gian chơi">
                <strong>{{ formatDate(refund.booking?.booking_date) }}</strong>
                <small>{{ formatTime(refund.booking?.start_time) }} - {{ formatTime(refund.booking?.end_time) }}</small>
              </td>
              <td data-label="Thanh toán">
                <strong>{{ formatCurrency(refund.payment?.amount) }}</strong>
                <small>{{ paymentMethod(refund.payment?.method) }} · {{ refund.payment?.payment_code || '-' }}</small>
              </td>
              <td class="reason-cell" data-label="Lý do hủy">{{ refund.reason || refund.booking?.status_reason || '-' }}</td>
              <td data-label="Mức hoàn">
                <strong>{{ formatCurrency(refundAmount(refund)) }}</strong>
                <small>{{ policyText(refund) }}</small>
              </td>
              <td data-label="Trạng thái">
                <span class="status-pill" :class="refund.status">{{ statusLabel(refund.status) }}</span>
              </td>
              <td class="actions-col" data-label="Thao tác">
                <TableActionGroup>
                  <ActionIconButton icon="eye" label="Xem chi tiết" @click="openDetail(refund)" />
                </TableActionGroup>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
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

    <div v-if="detailRefund" class="modal-backdrop" @click.self="detailRefund = null">
      <article class="detail-modal">
        <header class="modal-header">
          <div>
            <h2>{{ detailRefund.booking?.booking_code }}</h2>
            <p>{{ detailRefund.venue_cluster?.name || 'Cụm sân' }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" @click="detailRefund = null" />
        </header>

        <dl class="detail-grid">
          <div><dt>Khách hàng</dt><dd>{{ customerName(detailRefund) }}</dd></div>
          <div><dt>Liên hệ</dt><dd>{{ detailRefund.customer?.phone || detailRefund.customer?.email || '-' }}</dd></div>
          <div><dt>Thời gian chơi</dt><dd>{{ formatDate(detailRefund.booking?.booking_date) }}, {{ formatTime(detailRefund.booking?.start_time) }} - {{ formatTime(detailRefund.booking?.end_time) }}</dd></div>
          <div><dt>Đã thanh toán</dt><dd>{{ formatCurrency(detailRefund.payment?.amount) }}</dd></div>
          <div><dt>Số tiền sẽ hoàn</dt><dd>{{ formatCurrency(refundAmount(detailRefund)) }}</dd></div>
          <div><dt>Trạng thái</dt><dd>{{ statusLabel(detailRefund.status) }}</dd></div>
        </dl>

        <section class="policy-band">
          <strong>{{ detailRefund.policy_evaluation?.is_owner_fault_refund ? 'Hoàn do lỗi phía sân' : 'Đối chiếu chính sách' }}</strong>
          <p>{{ detailRefund.policy_evaluation?.summary || 'Chưa đủ dữ liệu để xác định quy tắc tự động.' }}</p>
          <small v-if="detailRefund.policy_evaluation?.warning">{{ detailRefund.policy_evaluation.warning }}</small>
        </section>

        <section class="reason-block">
          <strong>{{ detailRefund.policy_evaluation?.is_owner_fault_refund ? 'Lý do hoàn tiền' : 'Lý do khách hủy' }}</strong>
          <p>{{ detailRefund.reason || '-' }}</p>
        </section>

        <section v-if="detailRefund.owner_confirm_note" class="reason-block">
          <strong>Phản hồi của chủ sân</strong>
          <p>{{ detailRefund.owner_confirm_note }}</p>
        </section>

        <footer v-if="detailRefund.can_decide" class="modal-actions">
          <button class="secondary-btn danger-text" type="button" @click="openDecision(detailRefund, 'reject')">Từ chối</button>
          <button class="primary-btn" type="button" @click="openDecision(detailRefund, 'approve')">Đồng ý hoàn</button>
        </footer>
      </article>
    </div>

    <div v-if="decisionRefund" class="modal-backdrop" @click.self="closeDecision">
      <form class="decision-modal" @submit.prevent="submitDecision">
        <header class="modal-header">
          <div>
            <h2>{{ decision === 'approve' ? 'Xác nhận hoàn tiền' : 'Từ chối yêu cầu' }}</h2>
            <p>{{ decisionRefund.booking?.booking_code }} · {{ customerName(decisionRefund) }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" @click="closeDecision" />
        </header>

        <div v-if="decision === 'approve'" class="amount-summary fixed-amount">
          <div>
            <span>{{ decisionRefund.policy_evaluation?.is_owner_fault_refund ? 'Số tiền hoàn 100% do lỗi phía sân' : 'Số tiền hoàn theo chính sách' }}</span>
            <small>{{ decisionRefund.policy_evaluation?.summary || 'SportGo sẽ dùng số tiền đã được hệ thống tính cho yêu cầu này.' }}</small>
          </div>
          <strong>{{ formatCurrency(refundAmount(decisionRefund)) }}</strong>
        </div>

        <label class="field">
          <span>{{ decision === 'approve' ? 'Ghi chú xác nhận' : 'Lý do từ chối' }}</span>
          <textarea v-model.trim="decisionForm.note" rows="4" maxlength="2000" :required="decision === 'reject'" />
        </label>

        <footer class="modal-actions">
          <button class="secondary-btn" type="button" :disabled="submitting" @click="closeDecision">Đóng</button>
          <button :class="decision === 'approve' ? 'primary-btn' : 'danger-btn'" type="submit" :disabled="submitting">
            {{ submitting ? 'Đang xử lý...' : (decision === 'approve' ? 'Xác nhận' : 'Từ chối') }}
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerRefundRequests',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      refunds: [],
      filters: { keyword: '', status: '', date_from: '', date_to: '' },
      statusTabs: [
        { value: '', label: 'Tất cả' },
        { value: 'pending_owner_confirmation', label: 'Chờ xác nhận' },
        { value: 'owner_confirmed', label: 'Chờ hoàn ví' },
        { value: 'owner_rejected', label: 'Đã từ chối' },
        { value: 'completed', label: 'Hoàn tất' },
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
  mounted() {
    this.loadRefunds();
  },
  methods: {
    async loadRefunds(page = 1) {
      this.loading = true;
      this.error = '';
      const params = new URLSearchParams({ page: String(page) });
      Object.entries(this.filters).forEach(([key, value]) => {
        if (value) params.set(key, value);
      });

      try {
        const response = await api(`/api/owner/refunds?${params.toString()}`);
        this.refunds = response.data || [];
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách yêu cầu.';
      } finally {
        this.loading = false;
      }
    },
    setStatus(status) {
      this.filters.status = status;
      this.loadRefunds(1);
    },
    clearFilters() {
      this.filters = { keyword: '', status: '', date_from: '', date_to: '' };
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
        owner_confirmed: 'Chờ SportGo hoàn ví',
        owner_rejected: 'Chủ sân từ chối',
        admin_processing: 'Chờ SportGo hoàn ví',
        processing: 'Chờ SportGo hoàn ví',
        completed: 'Đã hoàn tiền',
        failed: 'Xử lý thất bại',
        rejected: 'Đã từ chối',
        cancelled: 'Đã hủy',
      }[status] || status;
    },
    paymentMethod(method) {
      return { sepay: 'Chuyển khoản', wallet: 'Ví SportGo', cash: 'Tiền mặt' }[method] || method || '-';
    },
    formatCurrency(value) {
      return `${Number(value || 0).toLocaleString('vi-VN')} đ`;
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('vi-VN');
    },
    formatTime(value) {
      return value ? String(value).slice(0, 5) : '--:--';
    },
    shortId(value) {
      return value ? String(value).slice(0, 8).toUpperCase() : '-';
    },
  },
};
</script>

<style scoped>
.refund-page {
  display: grid;
  gap: 16px;
  min-width: 0;
}

.page-head h1,
.page-head p {
  margin: 0;
}

.status-tabs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding-bottom: 2px;
}

.status-tabs button {
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid #d5e3d6;
  border-radius: 7px;
  background: #fff;
  color: #344238;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
}

.status-tabs button.active {
  border-color: #2f9e44;
  background: #2f9e44;
  color: #fff;
}

.filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.search-field {
  flex: 1 1 320px;
  max-width: 520px;
}

.reason-cell {
  max-width: 250px;
  white-space: normal;
  line-height: 1.45;
}

td small {
  display: block;
  margin-top: 4px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}

.pending_owner_confirmation {
  background: #fff4d6;
  color: #8a4b08;
}

.owner_confirmed,
.processing,
.admin_processing {
  background: #e8f7ec;
  color: #216b34;
}

.completed {
  background: #dcfce7;
  color: #166534;
}

.owner_rejected,
.rejected,
.failed,
.cancelled {
  background: #fef2f2;
  color: #991b1b;
}

.actions-col {
  width: 1%;
  min-width: 150px;
  text-align: right;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.detail-modal,
.decision-modal {
  width: min(680px, calc(100vw - 32px));
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  padding: 0;
  border: 1px solid #d7e4d7;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 22px 60px rgba(24, 42, 29, .18);
}

.modal-backdrop {
  position: fixed !important;
  inset: 0 !important;
  z-index: 520 !important;
  display: grid !important;
  place-items: center !important;
  width: 100vw !important;
  height: 100vh !important;
  padding: 20px !important;
  overflow-y: auto !important;
}

.modal-header,
.modal-actions {
  padding: 16px 18px;
}

.modal-header {
  border-bottom: 1px solid #e1eae1;
}

.modal-header h2,
.modal-header p {
  margin: 0;
}

.modal-header p {
  margin-top: 4px;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  margin: 0;
  padding: 8px 18px;
}

.detail-grid div {
  padding: 12px 0;
  border-bottom: 1px solid #edf2ed;
}

.detail-grid dt {
  margin-bottom: 4px;
  font-size: 12px;
  font-weight: 700;
}

.detail-grid dd {
  margin: 0;
  font-weight: 750;
}

.policy-band,
.reason-block {
  margin: 12px 18px;
  padding: 14px;
  border-left: 3px solid #2f9e44;
  background: #f3faf4;
}

.policy-band p,
.reason-block p {
  margin: 6px 0 0;
  line-height: 1.5;
}

.policy-band small {
  display: block;
  margin-top: 6px;
  color: #9a3412;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  border-top: 1px solid #e1eae1;
}

.decision-modal .field {
  display: grid;
  gap: 7px;
  margin: 16px 18px;
}

.amount-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 16px 18px 0;
  padding: 12px 14px;
  border: 1px solid #cfe3d1;
  background: #f3faf4;
}

.amount-summary strong {
  color: #216b34;
  font-size: 18px;
}

.amount-summary.fixed-amount {
  align-items: flex-start;
  gap: 18px;
}

.amount-summary.fixed-amount div {
  display: grid;
  gap: 4px;
}

.amount-summary.fixed-amount small {
  color: #536257;
  line-height: 1.45;
}

.primary-btn,
.secondary-btn,
.danger-btn {
  min-height: 38px;
  padding: 0 15px;
  border-radius: 7px;
  font-weight: 750;
  cursor: pointer;
}

.primary-btn {
  border: 1px solid #2f9e44;
  background: #2f9e44;
  color: #fff;
}

.secondary-btn {
  border: 1px solid #d5e3d6;
  background: #fff;
  color: #344238;
}

.danger-btn {
  border: 1px solid #dc2626;
  background: #dc2626;
  color: #fff;
}

.danger-text {
  color: #991b1b;
}

@media (max-width: 720px) {
  .sg-shell-admin .content-area .refund-page form.filters {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
    align-items: center !important;
  }

  .sg-shell-admin .content-area .refund-page form.filters > label.search-field {
    grid-column: 1 / -1;
    flex: none !important;
    width: 100% !important;
    max-width: none !important;
    height: 42px !important;
    min-height: 42px !important;
    max-height: 42px !important;
  }

  .sg-shell-admin .content-area .refund-page form.filters > input {
    flex: none !important;
    width: 100% !important;
    height: 42px !important;
    min-height: 42px !important;
    max-height: 42px !important;
  }

  .sg-shell-admin .content-area .refund-page form.filters > .action-icon-button {
    justify-self: start;
  }

  .refund-table,
  .refund-table tbody,
  .refund-table tr,
  .refund-table td {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
  }

  .refund-table {
    min-width: 0 !important;
  }

  .refund-table thead {
    display: none;
  }

  .refund-table tr {
    padding: 12px 14px;
    border-bottom: 1px solid #dce8dc;
  }

  .refund-table td {
    display: grid !important;
    grid-template-columns: 112px minmax(0, 1fr);
    gap: 10px;
    padding: 7px 0 !important;
    border: 0 !important;
    white-space: normal !important;
    text-align: left !important;
  }

  .refund-table td::before {
    content: attr(data-label);
    color: #536257;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
  }

  .refund-table .actions-col {
    min-width: 0;
  }

  .refund-table .table-action-group {
    justify-content: flex-start;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .detail-modal,
  .decision-modal {
    width: 100%;
    max-height: calc(100vh - 24px);
  }

  .modal-backdrop {
    padding: 12px !important;
  }
}
</style>
