<template>
  <div class="wallet-simple">
    <div v-if="error" class="error-msg">{{ error }}</div>
    <div v-if="successMsg" class="success-msg">{{ successMsg }}</div>

    <!-- Ví của tôi (Wallet Balance Details) -->
    <div class="section-box">
      <h3 class="section-title">VÍ CỦA TÔI</h3>
      <div class="wallet-simple-grid">
        <div class="wallet-item">
          <span class="label">Số dư khả dụng:</span>
          <span class="value bold">{{ loadingWallet ? '...' : formatCurrency(wallet.available_balance) }}</span>
        </div>
        <div class="wallet-item">
          <span class="label">Chờ rút tiền:</span>
          <span class="value">{{ loadingWallet ? '...' : formatCurrency(wallet.pending_withdrawal_balance) }}</span>
        </div>
        <div class="wallet-item">
          <span class="label">Tổng thu nhập:</span>
          <span class="value">{{ loadingWallet ? '...' : formatCurrency(wallet.total_earned) }}</span>
        </div>
      </div>
    </div>

    <!-- Gửi yêu cầu rút tiền (Withdrawal Request Form) -->
    <div class="section-box">
      <h3 class="section-title">YÊU CẦU RÚT TIỀN</h3>
      <form @submit.prevent="handleWithdraw">
        <div class="form-group-simple">
          <label class="form-label">Tài khoản ngân hàng nhận tiền:</label>
          <select v-model="form.owner_bank_account_id" class="form-control-simple" required>
            <option value="" disabled>-- Chọn tài khoản ngân hàng --</option>
            <option v-for="bank in bankAccounts" :key="bank.id" :value="bank.id">
              {{ bank.bank_name }} - {{ bank.account_number }} ({{ bank.account_holder_name }})
            </option>
          </select>
          <p v-if="bankAccounts.length === 0" class="help-text">
            Chưa có tài khoản ngân hàng hoạt động. Vui lòng liên hệ Admin để cấu hình tài khoản nhận tiền.
          </p>
        </div>

        <div class="form-group-simple">
          <label class="form-label">Số tiền cần rút (VND):</label>
          <input
            v-model.number="form.amount"
            type="number"
            class="form-control-simple"
            placeholder="Ví dụ: 100000"
            min="50000"
            required
          />
          <span class="help-text">Số tiền rút tối thiểu là 50,000 VND.</span>
        </div>

        <div class="form-group-simple">
          <label class="form-label">Ghi chú (nếu có):</label>
          <textarea
            v-model="form.owner_note"
            class="form-control-simple"
            rows="3"
            placeholder="Nhập ghi chú cho quản trị viên..."
          ></textarea>
        </div>

        <button
          type="submit"
          class="btn-simple"
          :disabled="submitting || bankAccounts.length === 0"
        >
          {{ submitting ? 'Đang gửi...' : 'Gửi yêu cầu rút tiền' }}
        </button>
      </form>
    </div>

    <!-- Lịch sử rút tiền (Withdrawal History) -->
    <div class="section-box">
      <h3 class="section-title">LỊCH SỬ RÚT TIỀN</h3>
      <div v-if="loadingHistory && history.data.length === 0" class="loading-text">Đang tải lịch sử...</div>
      <div v-else-if="history.data.length === 0" class="empty-text">Chưa có giao dịch rút tiền nào được tạo.</div>
      <div v-else>
        <div class="table-responsive">
          <table class="simple-table">
            <thead>
              <tr>
                <th align="left">Mã yêu cầu</th>
                <th align="left">Tài khoản nhận</th>
                <th align="right">Số tiền</th>
                <th align="center">Trạng thái</th>
                <th align="left">Ngày yêu cầu</th>
                <th align="left">Ghi chú / Lý do</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in history.data" :key="item.id">
                <td><strong>{{ item.request_code }}</strong></td>
                <td>
                  <span v-if="item.bank_account">
                    {{ item.bank_account.bank_name }} - {{ item.bank_account.account_number }}
                  </span>
                  <span v-else>-</span>
                </td>
                <td align="right">{{ formatCurrency(item.amount) }}</td>
                <td align="center">
                  <span class="status-badge" :class="item.status">
                    {{ getStatusText(item.status) }}
                  </span>
                </td>
                <td>{{ formatDate(item.requested_at) }}</td>
                <td>
                  <span class="note-text">{{ item.status_reason || item.owner_note || '-' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Phân trang đơn giản (Simple Pagination) -->
        <div v-if="history.last_page > 1" class="pagination-simple">
          <button
            class="btn-page"
            :disabled="history.current_page === 1"
            @click="loadHistory(history.current_page - 1)"
          >
            Trước
          </button>
          <span class="page-info">Trang {{ history.current_page }} / {{ history.last_page }}</span>
          <button
            class="btn-page"
            :disabled="history.current_page === history.last_page"
            @click="loadHistory(history.current_page + 1)"
          >
            Sau
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { api } from '../../services/api.js';

export default {
  name: 'OwnerWallet',
  data() {
    return {
      wallet: {
        available_balance: 0,
        pending_withdrawal_balance: 0,
        total_earned: 0,
      },
      bankAccounts: [],
      history: {
        data: [],
        current_page: 1,
        last_page: 1,
      },
      form: {
        owner_bank_account_id: '',
        amount: null,
        owner_note: '',
      },
      loadingWallet: true,
      loadingHistory: true,
      submitting: false,
      error: null,
      successMsg: null,
    };
  },
  async mounted() {
    await this.loadWalletData();
    await this.loadHistory();
  },
  methods: {
    async loadWalletData() {
      this.loadingWallet = true;
      this.error = null;
      try {
        const res = await api('/api/owner/wallet');
        this.wallet = res.wallet || { available_balance: 0, pending_withdrawal_balance: 0, total_earned: 0 };
        this.bankAccounts = res.bank_accounts || [];
        
        // Auto select default bank account if available
        const defaultBank = this.bankAccounts.find(b => b.is_default) || this.bankAccounts[0];
        if (defaultBank) {
          this.form.owner_bank_account_id = defaultBank.id;
        }
      } catch (err) {
        this.error = err.message || 'Không thể tải thông tin ví.';
      } finally {
        this.loadingWallet = false;
      }
    },
    async loadHistory(page = 1) {
      this.loadingHistory = true;
      try {
        const res = await api(`/api/owner/wallet/withdrawals?page=${page}`);
        this.history = res;
      } catch (err) {
        console.error('Không thể tải lịch sử rút tiền:', err.message);
      } finally {
        this.loadingHistory = false;
      }
    },
    async handleWithdraw() {
      this.error = null;
      this.successMsg = null;
      this.submitting = true;

      try {
        await api('/api/owner/wallet/withdraw', {
          method: 'POST',
          body: JSON.stringify(this.form),
        });

        this.successMsg = 'Yêu cầu rút tiền của bạn đã được gửi thành công. Vui lòng chờ admin duyệt.';
        this.form.amount = null;
        this.form.owner_note = '';
        
        // Reload data
        await this.loadWalletData();
        await this.loadHistory(1);
      } catch (err) {
        this.error = err.message || 'Gửi yêu cầu rút tiền thất bại.';
      } finally {
        this.submitting = false;
      }
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
    },
    formatDate(dateStr) {
      if (!dateStr) return '-';
      const date = new Date(dateStr);
      return date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    },
    getStatusText(status) {
      const map = {
        pending: 'Chờ duyệt',
        reviewing: 'Đang xem xét',
        approved: 'Đã duyệt (Đang chuyển)',
        completed: 'Hoàn tất',
        rejected: 'Bị từ chối',
        cancelled: 'Đã hủy',
      };
      return map[status] || status;
    },
  },
};
</script>

<style scoped>
.wallet-simple {
  max-width: 1000px;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
  color: #333333;
}

.error-msg {
  padding: 10px 14px;
  background-color: #fee2e2;
  border: 1px solid #fca5a5;
  color: #b91c1c;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 700;
}

.success-msg {
  padding: 10px 14px;
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #15803d;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 700;
}

.section-box {
  border: 1px solid #dddddd;
  padding: 20px;
  margin-bottom: 20px;
  background-color: #ffffff;
  max-width: 100%;
  box-sizing: border-box;
}

.section-title {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-top: 0;
  margin-bottom: 15px;
  border-bottom: 1px solid #dddddd;
  padding-bottom: 8px;
  color: #000000;
}

.wallet-simple-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

@media (max-width: 600px) {
  .wallet-simple-grid {
    grid-template-columns: 1fr;
  }
}

.wallet-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.label {
  font-size: 12px;
  color: #666666;
}

.value {
  font-size: 16px;
}

.bold {
  font-weight: 700;
  color: #000000;
}

.form-group-simple {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.form-label {
  font-size: 13px;
  font-weight: 700;
  color: #333333;
}

.form-control-simple {
  width: 100%;
  max-width: 100%;
  padding: 10px 12px;
  border: 1px solid #cccccc;
  border-radius: 4px;
  font-size: 14px;
  outline: none;
  font-family: inherit;
  box-sizing: border-box;
}

.form-control-simple:focus {
  border-color: #000000;
}

.help-text {
  font-size: 11px;
  color: #666666;
  margin: 2px 0 0 0;
}

.btn-simple {
  padding: 10px 20px;
  background-color: #000000;
  color: #ffffff;
  border: 1px solid #000000;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 700;
}

.btn-simple:disabled {
  background-color: #cccccc;
  border-color: #cccccc;
  cursor: not-allowed;
}

.simple-table {
  width: 100% !important;
  border-collapse: collapse;
  font-size: 14px;
  min-width: 750px !important;
}

.simple-table th, .simple-table td {
  padding: 10px;
  border-bottom: 1px solid #eeeeee;
}

.simple-table th {
  font-weight: 700;
  color: #666666;
  border-bottom: 2px solid #dddddd;
}

.loading-text, .empty-text {
  padding: 20px;
  text-align: center;
  color: #666666;
  font-size: 14px;
}

.status-badge {
  display: inline-block;
  padding: 4px 8px;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid #cccccc;
  border-radius: 2px;
  background-color: #fcfcfc;
}

.status-badge.pending, .status-badge.reviewing {
  background-color: #fef3c7;
  border-color: #f59e0b;
  color: #d97706;
}

.status-badge.approved {
  background-color: #eff6ff;
  border-color: #3b82f6;
  color: #2563eb;
}

.status-badge.completed {
  background-color: #f0fdf4;
  border-color: #22c55e;
  color: #16a34a;
}

.status-badge.rejected, .status-badge.cancelled {
  background-color: #fef2f2;
  border-color: #ef4444;
  color: #dc2626;
}

.note-text {
  font-size: 12px;
  color: #666666;
  word-break: break-word;
  white-space: normal;
}

.table-responsive {
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  border: 1px solid #eeeeee;
  margin-bottom: 15px;
}

.pagination-simple {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
}

.btn-page {
  padding: 5px 12px;
  background-color: #ffffff;
  border: 1px solid #cccccc;
  cursor: pointer;
  font-size: 12px;
}

.btn-page:disabled {
  color: #cccccc;
  cursor: not-allowed;
}

.page-info {
  font-size: 13px;
  color: #333333;
}
</style>
