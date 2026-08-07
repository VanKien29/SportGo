<template>
  <div class="sg-client-page sg3-utility-page">
    <PublicNavbar />

    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head">
        <div>
          <div class="sg3-breadcrumbs">
            <router-link to="/profile">Tài khoản</router-link>
            <span>/</span>
            <strong>Ví SportGo</strong>
          </div>
          <p class="sg3-kicker">Tài chính cá nhân</p>
          <h1>Ví SportGo</h1>
          <p>Số dư dùng để thanh toán booking, nhận hoàn tiền và rút về tài khoản ngân hàng.</p>
        </div>

        <div class="sg3-head-actions">
          <button
            type="button"
            class="sg3-button sg3-button--primary"
            :disabled="!wallet || wallet.balance <= 0"
            @click="openWithdrawModal"
          >
            <AppIcon name="creditCard" :size="16" />
            Rút tiền về ngân hàng
          </button>
        </div>
      </div>

      <div v-if="loading" class="sg3-empty">
        <div>
          <strong>Đang tải thông tin ví</strong>
          <p>Đang đồng bộ số dư và lịch sử giao dịch.</p>
        </div>
      </div>

      <div v-else-if="error" class="sg3-error">
        <div>
          <strong>Không tải được ví</strong>
          <p>{{ error }}</p>
          <button class="sg3-button sg3-button--primary" type="button" @click="loadWallet">Thử lại</button>
        </div>
      </div>

      <template v-else>
        <section class="sg3-card sg3-wallet-balance">
          <div>
            <span>Số dư khả dụng</span>
            <strong>{{ money(wallet.balance) }}</strong>
          </div>
          <div>
            <span>Đang khóa</span>
            <strong>{{ money(wallet.locked_balance) }}</strong>
          </div>
          <span class="sg3-wallet-status">{{ statusLabel(wallet.status) }}</span>
        </section>

        <section class="sg3-card sg3-ledger-card">
          <header>
            <div>
              <p class="sg3-kicker">Biến động số dư</p>
              <h2>Lịch sử ví</h2>
            </div>
            <button class="sg3-button sg3-button--secondary" type="button" @click="loadWallet">
              <AppIcon name="refresh" :size="16" />Làm mới
            </button>
          </header>

          <div v-if="!ledgers.length" class="sg3-empty sg3-empty--inline">
            <div>
              <strong>Ví chưa có giao dịch</strong>
              <p>Các khoản thanh toán hoặc hoàn tiền sẽ xuất hiện tại đây.</p>
            </div>
          </div>

          <div v-else class="sg3-ledger-list">
            <article v-for="ledger in ledgers" :key="ledger.id">
              <div>
                <strong>{{ ledger.note || typeLabel(ledger.type) }}</strong>
                <small>{{ ledger.transaction_code }} · {{ formatDate(ledger.created_at) }}</small>
              </div>
              <div class="sg3-ledger-amount" :class="ledger.direction">
                <strong>{{ ledger.direction === 'credit' ? '+' : '-' }}{{ money(ledger.amount) }}</strong>
                <small>Số dư {{ money(ledger.balance_after) }}</small>
              </div>
            </article>
          </div>
        </section>
      </template>
    </main>

    <!-- WITHDRAWAL MODAL WITH OTP VERIFICATION -->
    <Teleport to="body">
      <div v-if="showWithdrawModal" class="wdr-modal-backdrop" @click.self="closeWithdrawModal">
        <div class="wdr-modal">
          <div class="wdr-modal-head">
            <h3>Rút tiền về ngân hàng</h3>
            <button type="button" class="wdr-modal-close" @click="closeWithdrawModal">✕</button>
          </div>

          <form @submit.prevent="submitWithdrawal">
            <div class="wdr-modal-body">
              <p class="wdr-modal-desc">
                Nhập thông tin tài khoản ngân hàng thụ hưởng và mã OTP 6 chữ số để xác thực giao dịch rút tiền.
              </p>

              <div class="wdr-form-group">
                <label for="bankName">Tên ngân hàng</label>
                <select id="bankName" v-model="wdrData.bank_name" required>
                  <option value="" disabled>-- Chọn ngân hàng --</option>
                  <option value="Vietcombank">Vietcombank (VCB)</option>
                  <option value="Techcombank">Techcombank (TCB)</option>
                  <option value="MB Bank">MB Bank (MB)</option>
                  <option value="ACB">ACB</option>
                  <option value="VPBank">VPBank</option>
                  <option value="VietinBank">VietinBank</option>
                  <option value="BIDV">BIDV</option>
                </select>
              </div>

              <div class="wdr-form-row">
                <div class="wdr-form-group">
                  <label for="bankAccNo">Số tài khoản</label>
                  <input
                    id="bankAccNo"
                    v-model.trim="wdrData.bank_account_number"
                    type="text"
                    placeholder="Ví dụ: 0988888888"
                    required
                  />
                </div>

                <div class="wdr-form-group">
                  <label for="bankAccName">Tên chủ tài khoản</label>
                  <input
                    id="bankAccName"
                    v-model.trim="wdrData.bank_account_name"
                    type="text"
                    placeholder="NGUYEN VAN A"
                    required
                  />
                </div>
              </div>

              <div class="wdr-form-group">
                <label for="wdrAmount">
                  Số tiền muốn rút (Tối đa: {{ money(wallet.balance) }})
                </label>
                <input
                  id="wdrAmount"
                  v-model.number="wdrData.amount"
                  type="number"
                  min="10000"
                  :max="wallet.balance"
                  placeholder="Ví dụ: 100000"
                  required
                />
              </div>

              <div class="wdr-otp-box">
                <div class="wdr-form-group">
                  <label for="wdrOtp">Mã OTP xác nhận giao dịch (6 chữ số)</label>
                  <input
                    id="wdrOtp"
                    v-model.trim="wdrData.otp"
                    type="text"
                    maxlength="6"
                    placeholder="Mã OTP test: 123456"
                    required
                    class="wdr-otp-input"
                  />
                </div>
                <small class="wdr-otp-help">Mã OTP dùng thử: <strong>123456</strong></small>
              </div>

              <div v-if="wdrError" class="wdr-alert wdr-alert--error">
                {{ wdrError }}
              </div>

              <div v-if="wdrSuccess" class="wdr-alert wdr-alert--success">
                {{ wdrSuccess }}
              </div>
            </div>

            <div class="wdr-modal-foot">
              <button type="button" class="wdr-btn wdr-btn--outline" @click="closeWithdrawModal">Quay lại</button>
              <button
                type="submit"
                class="wdr-btn wdr-btn--primary"
                :disabled="wdrSubmitting || !wdrData.amount || wdrData.amount > wallet.balance"
              >
                <span>{{ wdrSubmitting ? "Đang xử lý..." : "Xác thực & Rút tiền" }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { bookingService } from "../../services/bookingService.js";

export default {
  name: "ClientWallet",
  components: { AppIcon, PublicNavbar },
  data() {
    return {
      wallet: { balance: 0, locked_balance: 0, status: "active" },
      ledgers: [],
      loading: true,
      error: "",
      // WITHDRAWAL MODAL STATE
      showWithdrawModal: false,
      wdrSubmitting: false,
      wdrError: "",
      wdrSuccess: "",
      wdrData: {
        bank_name: "Vietcombank",
        bank_account_number: "",
        bank_account_name: "",
        amount: 50000,
        otp: "123456",
      },
    };
  },
  mounted() {
    this.loadWallet();
  },
  methods: {
    async loadWallet() {
      this.loading = true;
      this.error = "";
      try {
        const response = await bookingService.getWallet();
        this.wallet = response.wallet || this.wallet;
        this.ledgers = response.ledgers || [];
      } catch (error) {
        this.error = error.message || "Không thể tải thông tin ví.";
      } finally {
        this.loading = false;
      }
    },
    openWithdrawModal() {
      this.showWithdrawModal = true;
      this.wdrError = "";
      this.wdrSuccess = "";
      this.wdrData.amount = Math.min(50000, this.wallet.balance);
    },
    closeWithdrawModal() {
      this.showWithdrawModal = false;
      this.wdrError = "";
      this.wdrSuccess = "";
    },
    async submitWithdrawal() {
      if (this.wdrData.amount > this.wallet.balance) {
        this.wdrError = "Số tiền rút vượt quá số dư khả dụng.";
        return;
      }
      this.wdrSubmitting = true;
      this.wdrError = "";
      this.wdrSuccess = "";
      try {
        const res = await bookingService.requestWithdrawal(this.wdrData);
        this.wdrSuccess = res.message || "Yêu cầu rút tiền thành công!";
        if (res.new_balance !== undefined) {
          this.wallet.balance = res.new_balance;
        }
        setTimeout(() => {
          this.closeWithdrawModal();
          this.loadWallet();
        }, 1200);
      } catch (err) {
        this.wdrError = err.message || "Không thể xử lý yêu cầu rút tiền.";
      } finally {
        this.wdrSubmitting = false;
      }
    },
    money(value) {
      return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString("vi-VN") : "-";
    },
    statusLabel(status) {
      return { active: "Đang hoạt động", locked: "Đang khóa", suspended: "Tạm ngưng" }[status] || status;
    },
    typeLabel(type) {
      return { deposit: "Nạp tiền", payment: "Thanh toán booking", refund: "Hoàn tiền", withdrawal: "Rút tiền", adjustment: "Điều chỉnh" }[type] || type;
    },
  },
};
</script>

<style scoped>
.sg3-head-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* MODAL WITHDRAWAL STYLES */
.wdr-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.wdr-modal {
  background: #ffffff;
  border-radius: 6px;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.wdr-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 8px;
  border-bottom: none;
}

.wdr-modal-head h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.wdr-modal-close {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #64748b;
  cursor: pointer;
}

.wdr-modal-body {
  padding: 12px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.wdr-modal-desc {
  font-size: 13px;
  color: #1e293b;
  margin: 0;
  line-height: 1.5;
}

.wdr-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.wdr-form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
}

.wdr-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.wdr-form-group input,
.wdr-form-group select {
  padding: 8px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
  font-family: inherit;
}

.wdr-form-group input:focus,
.wdr-form-group select:focus {
  border-color: #15803d;
}

.wdr-otp-box {
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  padding: 12px;
}

.wdr-otp-input {
  letter-spacing: 4px;
  font-size: 18px !important;
  font-weight: 600;
  text-align: center;
}

.wdr-otp-help {
  font-size: 12px;
  color: #15803d;
  display: block;
  margin-top: 4px;
}

.wdr-alert {
  padding: 10px 14px;
  font-size: 13px;
  border-radius: 4px;
}

.wdr-alert--success {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.wdr-alert--error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.wdr-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 8px 20px 20px;
  border-top: none;
  background: #ffffff;
}

.wdr-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 4px;
  cursor: pointer;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
}

.wdr-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.wdr-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}
</style>
