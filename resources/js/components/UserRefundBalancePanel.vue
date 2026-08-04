<template>
  <section class="refund-balance-panel" aria-labelledby="refund-balance-title">
    <header class="refund-balance-heading">
      <div>
        <p class="sg-client-eyebrow">Tài chính cá nhân</p>
        <h1 id="refund-balance-title">Số dư hoàn tiền</h1>
        <p>Theo dõi khoản SportGo đã hoàn và mọi biến động số dư của bạn.</p>
      </div>
      <button type="button" class="sg-client-button" :disabled="loading" @click="loadFinance">
        <AppIcon name="refresh" :size="17" />
        Làm mới
      </button>
    </header>

    <div v-if="loading" class="sg-client-state" aria-live="polite">
      <span class="refund-spinner" aria-hidden="true"></span>
      <span>Đang tải số dư hoàn tiền...</span>
    </div>

    <div v-else-if="error" class="sg-client-state refund-error" role="alert">
      <AppIcon name="alert" :size="28" />
      <strong>Không thể tải số dư</strong>
      <span>{{ error }}</span>
      <button type="button" class="sg-client-button" @click="loadFinance">Thử lại</button>
    </div>

    <template v-else>
      <section class="refund-summary-grid" aria-label="Tổng quan số dư">
        <article class="refund-summary-card refund-summary-card--primary sg-client-card">
          <span class="summary-icon"><AppIcon name="finance" :size="21" /></span>
          <div>
            <span>Có thể sử dụng</span>
            <strong>{{ formatCurrency(finance.available_balance) }}</strong>
            <small>Dùng cho các nghiệp vụ được SportGo hỗ trợ</small>
          </div>
        </article>
        <article class="refund-summary-card sg-client-card">
          <span class="summary-icon"><AppIcon name="history" :size="21" /></span>
          <div>
            <span>Đang xử lý</span>
            <strong>{{ formatCurrency(finance.locked_balance) }}</strong>
            <small>Khoản đang được đối soát hoặc chờ chi trả</small>
          </div>
        </article>
        <article class="refund-summary-card sg-client-card">
          <span class="summary-icon"><AppIcon name="creditCard" :size="21" /></span>
          <div>
            <span>Tổng số dư</span>
            <strong>{{ formatCurrency(finance.total_balance) }}</strong>
            <small>{{ walletStatusLabel(finance.status) }}</small>
          </div>
        </article>
      </section>

      <aside class="refund-explainer sg-client-card">
        <span aria-hidden="true"><AppIcon name="fileText" :size="20" /></span>
        <div>
          <strong>Đây là khoản hoàn tiền người dùng, không phải ví điện tử.</strong>
          <p>Mỗi khoản hoàn hoặc chi trả đều có mã giao dịch và trạng thái để bạn đối chiếu.</p>
        </div>
        <router-link class="sg-client-button" to="/chat">Cần hỗ trợ</router-link>
      </aside>

      <section class="refund-history sg-client-card" aria-labelledby="refund-history-title">
        <header class="refund-section-heading">
          <div>
            <h2 id="refund-history-title">Lịch sử biến động</h2>
            <p>{{ finance.transactions.length }} giao dịch gần nhất</p>
          </div>
          <div class="refund-filters" aria-label="Lọc giao dịch">
            <button
              v-for="filter in filters"
              :key="filter.value"
              type="button"
              :class="{ active: activeFilter === filter.value }"
              :aria-pressed="activeFilter === filter.value"
              @click="activeFilter = filter.value"
            >
              {{ filter.label }}
            </button>
          </div>
        </header>

        <div v-if="filteredTransactions.length" class="refund-transaction-list">
          <button
            v-for="transaction in filteredTransactions"
            :key="transaction.id"
            type="button"
            class="refund-transaction"
            @click="selectedTransaction = transaction"
          >
            <span class="transaction-icon" :class="transaction.direction">
              <AppIcon :name="transactionIcon(transaction.type)" :size="18" />
            </span>
            <span class="transaction-copy">
              <strong>{{ transaction.type_label }}</strong>
              <small>{{ transaction.transaction_code || 'Giao dịch SportGo' }} · {{ formatDateTime(transaction.created_at) }}</small>
            </span>
            <span class="transaction-amount" :class="transaction.direction">
              <strong>{{ signedAmount(transaction) }}</strong>
              <small>{{ transaction.status_label }}</small>
            </span>
            <AppIcon class="transaction-chevron" name="chevronRight" :size="17" />
          </button>
        </div>
        <div v-else class="refund-empty">
          <AppIcon name="history" :size="28" />
          <strong>Chưa có giao dịch phù hợp</strong>
          <p v-if="finance.transactions.length">Hãy chọn bộ lọc khác để xem các biến động đã có.</p>
          <p v-else>Khi một khoản hoàn tiền được ghi nhận, lịch sử sẽ xuất hiện tại đây.</p>
        </div>
      </section>

      <section v-if="finance.withdrawals.length" class="refund-withdrawals sg-client-card" aria-labelledby="withdrawal-history-title">
        <header class="refund-section-heading">
          <div>
            <h2 id="withdrawal-history-title">Yêu cầu chi trả</h2>
            <p>Theo dõi các khoản đã gửi về tài khoản ngân hàng.</p>
          </div>
        </header>
        <div class="withdrawal-list">
          <article v-for="withdrawal in finance.withdrawals" :key="withdrawal.id">
            <div>
              <strong>{{ formatCurrency(withdrawal.amount) }}</strong>
              <span>{{ withdrawal.bank_name || 'Tài khoản nhận tiền' }} {{ withdrawal.bank_account_masked || '' }}</span>
            </div>
            <div>
              <span class="withdrawal-status" :class="withdrawal.status">{{ withdrawal.status_label }}</span>
              <small>{{ formatDateTime(withdrawal.requested_at) }}</small>
            </div>
            <p v-if="withdrawal.rejected_reason">Lý do: {{ withdrawal.rejected_reason }}</p>
          </article>
        </div>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="selectedTransaction" class="refund-modal-backdrop" role="presentation" @click.self="selectedTransaction = null">
        <section ref="transactionModal" class="refund-transaction-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-detail-title">
          <header>
            <div>
              <p class="sg-client-eyebrow">Chi tiết giao dịch</p>
              <h2 id="transaction-detail-title">{{ selectedTransaction.type_label }}</h2>
            </div>
            <button ref="transactionCloseButton" type="button" class="sg-client-icon-button" aria-label="Đóng chi tiết giao dịch" @click="selectedTransaction = null">
              <AppIcon name="close" :size="20" />
            </button>
          </header>
          <div class="transaction-detail-amount" :class="selectedTransaction.direction">
            {{ signedAmount(selectedTransaction) }}
          </div>
          <dl>
            <div><dt>Mã giao dịch</dt><dd>{{ selectedTransaction.transaction_code || 'Giao dịch SportGo' }}</dd></div>
            <div><dt>Trạng thái</dt><dd>{{ selectedTransaction.status_label }}</dd></div>
            <div><dt>Thời gian</dt><dd>{{ formatDateTime(selectedTransaction.created_at) }}</dd></div>
            <div><dt>Số dư sau giao dịch</dt><dd>{{ formatCurrency(selectedTransaction.balance_after) }}</dd></div>
          </dl>
          <button type="button" class="sg-client-button sg-client-button--primary" @click="selectedTransaction = null">Đã hiểu</button>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<script>
import AppIcon from './AppIcon.vue';
import { authService } from '../services/authService.js';

const emptyFinance = () => ({
  available_balance: 0,
  locked_balance: 0,
  total_balance: 0,
  status: 'none',
  transactions: [],
  withdrawals: [],
});

export default {
  name: 'UserRefundBalancePanel',
  components: { AppIcon },
  data() {
    return {
      finance: emptyFinance(),
      loading: true,
      error: '',
      activeFilter: 'all',
      selectedTransaction: null,
      bodyOverflowBeforeModal: '',
      modalTrigger: null,
      filters: [
        { value: 'all', label: 'Tất cả' },
        { value: 'refund', label: 'Hoàn tiền' },
        { value: 'payment', label: 'Thanh toán' },
        { value: 'withdrawal', label: 'Chi trả' },
      ],
    };
  },
  computed: {
    filteredTransactions() {
      if (this.activeFilter === 'all') return this.finance.transactions;
      return this.finance.transactions.filter((transaction) => transaction.type === this.activeFilter);
    },
  },
  mounted() {
    this.loadFinance();
    document.addEventListener('keydown', this.handleKeydown);
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this.handleKeydown);
    this.restoreBodyScroll();
  },
  watch: {
    selectedTransaction(transaction) {
      if (transaction) {
        this.modalTrigger = document.activeElement;
        this.bodyOverflowBeforeModal = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.transactionCloseButton?.focus());
      } else {
        this.restoreBodyScroll();
        const trigger = this.modalTrigger;
        this.modalTrigger = null;
        this.$nextTick(() => trigger?.focus?.());
      }
    },
  },
  methods: {
    handleKeydown(event) {
      if (!this.selectedTransaction) return;
      if (event.key === 'Escape') {
        this.selectedTransaction = null;
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = [...(this.$refs.transactionModal?.querySelectorAll(
        'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ) || [])];
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },
    restoreBodyScroll() {
      document.body.style.overflow = this.bodyOverflowBeforeModal;
      this.bodyOverflowBeforeModal = '';
    },
    async loadFinance() {
      this.loading = true;
      this.error = '';
      try {
        const response = await authService.me('refund_finance');
        this.finance = {
          ...emptyFinance(),
          ...(response.refund_finance || {}),
          transactions: Array.isArray(response.refund_finance?.transactions) ? response.refund_finance.transactions : [],
          withdrawals: Array.isArray(response.refund_finance?.withdrawals) ? response.refund_finance.withdrawals : [],
        };
      } catch (requestError) {
        this.finance = emptyFinance();
        this.error = requestError.message || 'Không thể tải thông tin hoàn tiền.';
      } finally {
        this.loading = false;
      }
    },
    transactionIcon(type) {
      return {
        refund: 'rotateCcw',
        payment: 'creditCard',
        withdrawal: 'banknote',
        deposit: 'plus',
        adjustment: 'history',
      }[type] || 'finance';
    },
    signedAmount(transaction) {
      const prefix = transaction.direction === 'credit' ? '+' : transaction.direction === 'debit' ? '-' : '';
      return `${prefix}${this.formatCurrency(transaction.amount)}`;
    },
    walletStatusLabel(status) {
      return {
        active: 'Số dư đang hoạt động',
        locked: 'Số dư đang bị khóa',
        suspended: 'Số dư đang tạm ngưng',
        none: 'Chưa phát sinh số dư',
      }[status] || 'Trạng thái đang được cập nhật';
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
    formatDateTime(value) {
      if (!value) return 'Chưa cập nhật';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return 'Chưa cập nhật';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      }).format(date);
    },
  },
};
</script>

