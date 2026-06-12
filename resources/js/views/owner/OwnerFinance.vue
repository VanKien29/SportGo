<template>
  <div class="owner-finance-page">
    <div class="page-header">
      <h2>Ví tài chính & Doanh thu</h2>
      <p class="muted">Quản lý số dư, lịch sử dòng tiền và thực hiện rút tiền về tài khoản ngân hàng.</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs">
      <button :class="{ active: activeTab === 'wallets' }" @click="activeTab = 'wallets'">
        Tổng quan Ví
      </button>
      <button :class="{ active: activeTab === 'ledgers' }" @click="activeTab = 'ledgers'">
        Lịch sử Sổ cái (Giao dịch)
      </button>
      <button :class="{ active: activeTab === 'withdrawals' }" @click="activeTab = 'withdrawals'">
        Lịch sử Rút tiền
      </button>
    </div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải dữ liệu...</p>
    </div>

    <div v-else-if="error" class="notice error">
      {{ error }}
    </div>

    <!-- TAB: Tổng quan Ví -->
    <div v-else-if="activeTab === 'wallets'" class="tab-content">
      <div v-if="wallets.length === 0" class="state-box card">
        <p>Bạn chưa có Ví nào. Ví sẽ tự động được tạo khi có dòng tiền phát sinh từ Cụm sân.</p>
      </div>
      
      <div v-else class="wallets-grid">
        <div v-for="wallet in wallets" :key="wallet.id" class="card wallet-card">
          <div class="wallet-header">
            <h3>{{ wallet.venue_cluster?.name || 'Cụm sân' }}</h3>
            <span class="badge">Ví ID: {{ wallet.id.split('-')[0] }}...</span>
          </div>
          
          <div class="balances">
            <div class="balance-item highlight">
              <span class="label">Số dư khả dụng</span>
              <span class="amount text-success">{{ formatCurrency(wallet.available_balance) }}</span>
            </div>
            <div class="balance-item">
              <span class="label">Đang chờ rút</span>
              <span class="amount text-warning">{{ formatCurrency(wallet.pending_withdrawal_balance) }}</span>
            </div>
          </div>
          
          <div class="wallet-stats">
            <div class="stat-item">
              <span class="label">Tổng thu</span>
              <span class="value">{{ formatCurrency(wallet.total_earned) }}</span>
            </div>
            <div class="stat-item">
              <span class="label">Tổng đã rút</span>
              <span class="value">{{ formatCurrency(wallet.total_withdrawn) }}</span>
            </div>
          </div>
          
          <div class="wallet-actions">
            <button class="btn primary full-width" @click="openWithdrawalModal(wallet)" :disabled="wallet.available_balance <= 0">
              <AppIcon name="dollarSign" size="16" /> Rút tiền
            </button>
            <button class="btn ghost full-width" @click="viewLedgers(wallet)">
              <AppIcon name="list" size="16" /> Xem lịch sử
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Sổ cái Giao dịch -->
    <div v-else-if="activeTab === 'ledgers'" class="tab-content card">
      <div class="toolbar">
        <div class="filters">
          <label class="field compact">
            <span>Ví cụm sân</span>
            <select v-model="ledgerFilters.wallet_id" @change="loadLedgers(1)">
              <option value="">Tất cả các ví</option>
              <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.venue_cluster?.name || 'Ví' }}</option>
            </select>
          </label>
        </div>
      </div>

      <div class="table-scroll" v-if="ledgers.length > 0">
        <table>
          <thead>
            <tr>
              <th>Mã GD</th>
              <th>Loại</th>
              <th>Thời gian</th>
              <th class="right">Số tiền</th>
              <th class="right">Số dư sau GD</th>
              <th>Mô tả</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in ledgers" :key="l.id">
              <td>{{ l.transaction_code }}</td>
              <td>
                <span class="badge" :class="'type-' + l.type">{{ formatLedgerType(l.type) }}</span>
              </td>
              <td>{{ formatDate(l.created_at) }}</td>
              <td class="right" :class="l.direction === 'credit' ? 'text-success' : 'text-danger'">
                {{ l.direction === 'credit' ? '+' : '-' }}{{ formatCurrency(l.amount) }}
              </td>
              <td class="right fw-bold">{{ formatCurrency(l.balance_after) }}</td>
              <td>
                <div class="text-sm">{{ l.description }}</div>
                <div v-if="l.booking" class="muted text-xs">Booking: {{ l.booking.booking_code }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="state-box">Không có giao dịch nào.</div>
      
      <div class="pagination" v-if="ledgerPagination.last_page > 1">
        <button class="icon-btn" :disabled="ledgerPagination.current_page <= 1" @click="loadLedgers(ledgerPagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="16" />
        </button>
        <span>{{ ledgerPagination.current_page }} / {{ ledgerPagination.last_page }}</span>
        <button class="icon-btn" :disabled="ledgerPagination.current_page >= ledgerPagination.last_page" @click="loadLedgers(ledgerPagination.current_page + 1)">
          <AppIcon name="chevronRight" size="16" />
        </button>
      </div>
    </div>

    <!-- TAB: Lịch sử rút tiền -->
    <div v-else-if="activeTab === 'withdrawals'" class="tab-content card">
      <div class="table-scroll" v-if="withdrawals.length > 0">
        <table>
          <thead>
            <tr>
              <th>Mã Y/C</th>
              <th>Cụm sân</th>
              <th>Thời gian</th>
              <th class="right">Số tiền</th>
              <th class="center">Trạng thái</th>
              <th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in withdrawals" :key="w.id">
              <td>{{ w.request_code }}</td>
              <td>{{ w.owner_wallet?.venue_cluster?.name }}</td>
              <td>{{ formatDate(w.created_at) }}</td>
              <td class="right fw-bold">{{ formatCurrency(w.amount) }}</td>
              <td class="center">
                <span class="badge" :class="'status-' + w.status">{{ formatStatus(w.status) }}</span>
              </td>
              <td>
                <div class="text-sm" v-if="w.note"><strong>Lời nhắn:</strong> {{ w.note }}</div>
                <div class="text-sm muted" v-if="w.reject_reason"><strong>Từ chối:</strong> {{ w.reject_reason }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="state-box">Không có yêu cầu rút tiền nào.</div>

      <div class="pagination" v-if="withdrawalPagination.last_page > 1">
        <button class="icon-btn" :disabled="withdrawalPagination.current_page <= 1" @click="loadWithdrawals(withdrawalPagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="16" />
        </button>
        <span>{{ withdrawalPagination.current_page }} / {{ withdrawalPagination.last_page }}</span>
        <button class="icon-btn" :disabled="withdrawalPagination.current_page >= withdrawalPagination.last_page" @click="loadWithdrawals(withdrawalPagination.current_page + 1)">
          <AppIcon name="chevronRight" size="16" />
        </button>
      </div>
    </div>

    <!-- Modal Yêu cầu Rút tiền -->
    <div v-if="showWithdrawModal" class="modal-backdrop">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Tạo Yêu cầu Rút tiền</h3>
          <button class="close-btn" @click="showWithdrawModal = false">
            <AppIcon name="x" size="20" />
          </button>
        </div>
        <div class="modal-body">
          <div class="notice info mb-4">
            Bạn đang yêu cầu rút tiền từ Ví của cụm sân: <strong>{{ selectedWallet?.venue_cluster?.name }}</strong>. <br/>
            Số dư khả dụng tối đa có thể rút: <strong class="text-success">{{ formatCurrency(selectedWallet?.available_balance) }}</strong>
          </div>
          
          <form @submit.prevent="submitWithdrawal">
            <div class="form-group">
              <label>Số tiền cần rút (VNĐ) *</label>
              <input v-model.number="withdrawForm.amount" type="number" min="1000" :max="selectedWallet?.available_balance" required class="input" />
            </div>
            
            <div class="form-group">
              <label>Lời nhắn / Ghi chú cho Admin</label>
              <textarea v-model="withdrawForm.note" rows="3" class="input" placeholder="Thông tin ngân hàng nhận tiền hoặc lời nhắn..."></textarea>
            </div>
            
            <div class="modal-footer">
              <button type="button" class="btn ghost" @click="showWithdrawModal = false">Hủy</button>
              <button type="submit" class="btn primary" :disabled="submittingWithdrawal">
                {{ submittingWithdrawal ? 'Đang gửi...' : 'Xác nhận Rút tiền' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { api } from '../../services/api.js';

export default {
  name: 'OwnerFinance',
  components: { AppIcon },
  data() {
    return {
      activeTab: 'wallets',
      loading: true,
      error: '',
      
      wallets: [],
      
      ledgers: [],
      ledgerFilters: { wallet_id: '' },
      ledgerPagination: { current_page: 1, last_page: 1 },
      
      withdrawals: [],
      withdrawalPagination: { current_page: 1, last_page: 1 },
      
      showWithdrawModal: false,
      selectedWallet: null,
      withdrawForm: { amount: 0, note: '' },
      submittingWithdrawal: false,
    };
  },
  watch: {
    activeTab(newTab) {
      if (newTab === 'wallets' && this.wallets.length === 0) this.loadWallets();
      if (newTab === 'ledgers' && this.ledgers.length === 0) this.loadLedgers(1);
      if (newTab === 'withdrawals' && this.withdrawals.length === 0) this.loadWithdrawals(1);
    }
  },
  mounted() {
    this.loadWallets();
  },
  methods: {
    async loadWallets() {
      this.loading = true;
      try {
        const res = await api('/api/owner/finance/wallets');
        this.wallets = res.data || [];
      } catch (err) {
        this.error = err.message || 'Không tải được danh sách Ví.';
      } finally {
        this.loading = false;
      }
    },
    async loadLedgers(page = 1) {
      try {
        let url = `/api/owner/finance/ledgers?page=${page}`;
        if (this.ledgerFilters.wallet_id) url += `&wallet_id=${this.ledgerFilters.wallet_id}`;
        
        const res = await api(url);
        this.ledgers = res.data || [];
        this.ledgerPagination = {
          current_page: res.current_page || 1,
          last_page: res.last_page || 1
        };
      } catch (err) {
        console.error(err);
      }
    },
    async loadWithdrawals(page = 1) {
      try {
        const res = await api(`/api/owner/finance/withdrawals?page=${page}`);
        this.withdrawals = res.data || [];
        this.withdrawalPagination = {
          current_page: res.current_page || 1,
          last_page: res.last_page || 1
        };
      } catch (err) {
        console.error(err);
      }
    },
    viewLedgers(wallet) {
      this.ledgerFilters.wallet_id = wallet.id;
      this.activeTab = 'ledgers';
      this.loadLedgers(1);
    },
    openWithdrawalModal(wallet) {
      this.selectedWallet = wallet;
      this.withdrawForm = { amount: wallet.available_balance, note: '' };
      this.showWithdrawModal = true;
    },
    async submitWithdrawal() {
      this.submittingWithdrawal = true;
      try {
        await api('/api/owner/finance/withdrawals', {
          method: 'POST',
          body: JSON.stringify({
            owner_wallet_id: this.selectedWallet.id,
            amount: this.withdrawForm.amount,
            note: this.withdrawForm.note
          })
        });
        alert('Yêu cầu rút tiền đã được gửi thành công!');
        this.showWithdrawModal = false;
        this.loadWallets();
        if (this.activeTab === 'withdrawals') {
          this.loadWithdrawals(1);
        } else {
          this.activeTab = 'withdrawals';
        }
      } catch (err) {
        alert(err.message || 'Có lỗi xảy ra khi yêu cầu rút tiền.');
      } finally {
        this.submittingWithdrawal = false;
      }
    },
    formatCurrency(value) {
      return Number(value || 0).toLocaleString('vi-VN') + ' đ';
    },
    formatDate(value) {
      if (!value) return '';
      return new Date(value).toLocaleString('vi-VN');
    },
    formatStatus(status) {
      const map = {
        pending: 'Chờ xử lý',
        approved: 'Đã duyệt',
        completed: 'Hoàn tất',
        rejected: 'Từ chối'
      };
      return map[status] || status;
    },
    formatLedgerType(type) {
      const map = {
        payment: 'Thanh toán booking',
        refund: 'Hoàn tiền',
        hold: 'Giữ tiền Rút',
        release: 'Hoàn tiền Rút',
        debit: 'Thanh toán Rút'
      };
      return map[type] || type;
    }
  }
};
</script>

<style scoped>
.owner-finance-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header h2 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 4px;
}

.tabs {
  display: flex;
  gap: 10px;
  border-bottom: 1px solid var(--sg-border);
}

.tabs button {
  background: none;
  border: none;
  padding: 12px 20px;
  font-size: 15px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  border-bottom: 2px solid transparent;
}

.tabs button.active {
  color: #0f172a;
  border-bottom-color: #0f172a;
}

.card {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  padding: 24px;
}

.wallets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.wallet-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.wallet-header h3 {
  margin: 0;
  font-size: 18px;
}

.balances {
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #f8fafc;
  padding: 16px;
  border-radius: 8px;
}

.balance-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.balance-item.highlight {
  font-size: 18px;
  font-weight: 700;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 12px;
}

.wallet-stats {
  display: flex;
  justify-content: space-between;
  border-top: 1px solid var(--sg-border);
  padding-top: 16px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-item .label {
  font-size: 12px;
  color: #64748b;
}

.stat-item .value {
  font-weight: 600;
}

.wallet-actions {
  display: flex;
  gap: 12px;
  margin-top: auto;
}

.full-width {
  flex: 1;
  justify-content: center;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  background: #f1f5f9;
}

.text-success { color: #166534; }
.text-danger { color: #dc2626; }
.text-warning { color: #ca8a04; }
.fw-bold { font-weight: 700; }
.muted { color: #64748b; }
.text-sm { font-size: 13px; }
.text-xs { font-size: 11px; }

.status-pending { background: #fef08a; color: #854d0e; }
.status-approved { background: #dbeafe; color: #1e40af; }
.status-completed { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }

.type-payment { background: #dcfce7; color: #166534; }
.type-refund { background: #fee2e2; color: #991b1b; }
.type-hold { background: #fef08a; color: #854d0e; }
.type-debit { background: #e0e7ff; color: #3730a3; }

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
}

th.right, td.right {
  text-align: right;
}

th.center, td.center {
  text-align: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.ghost {
  background: #fff;
  border-color: var(--sg-border);
  color: #0f172a;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
}

.pagination {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 20px;
  justify-content: center;
}

.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #64748b;
  gap: 12px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid var(--sg-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 { margin: 0; font-size: 18px; }

.close-btn {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
}

.notice.info {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e3a8a;
  padding: 12px;
  border-radius: 8px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
}

.input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--sg-border);
  border-radius: 6px;
  font-family: inherit;
}

.modal-footer {
  margin-top: 24px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
