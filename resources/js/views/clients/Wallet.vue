<!-- SportGo Client Wallet View - Pure White Page Layout -->
<template>
  <div class="sg-client-page wallet-white-page">
    <PublicNavbar />

    <main class="wallet-white-main">
      <div class="wallet-layout-grid">
        <!-- LEFT SIDEBAR NAVIGATION -->
        <ClientAccountNav />

        <!-- SKELETON LOADING STATE -->
        <div v-if="loading" class="w2-skeleton-wrapper">
          <div class="w2-sk-hero">
            <div class="w2-sk-line w2-sk-title"></div>
            <div class="w2-sk-line w2-sk-amount"></div>
            <div class="w2-sk-line w2-sk-sub"></div>
          </div>
          <div class="w2-sk-ledger">
            <div v-for="n in 3" :key="n" class="w2-sk-row">
              <div class="w2-sk-circle"></div>
              <div class="w2-sk-col">
                <div class="w2-sk-line w2-sk-text1"></div>
                <div class="w2-sk-line w2-sk-text2"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- ERROR STATE -->
        <div v-else-if="error" class="w2-state-card w2-error">
          <div>
            <span>Không thể kết nối đến Ví SportGo</span>
            <p>{{ error }}</p>
            <button class="w2-btn w2-btn--primary" type="button" @click="loadWallet">Thử lại</button>
          </div>
        </div>

        <!-- MAIN CONTENT: PURE WHITE PAGE LAYOUT LIKE PROFILE PAGE -->
        <div v-else class="w2-white-content">
        <!-- TOP BALANCE BANNER ON PURE WHITE -->
        <section class="w2-white-hero">
          <div class="w2-hero-top">
            <div class="w2-hero-main-group">
              <div class="w2-balance-caption">
                <span class="w2-caption-text">SỐ DƯ KHẢ DỤNG</span>
                <button
                  type="button"
                  class="w2-icon-btn"
                  :title="showBalance ? 'Ẩn số dư' : 'Hiện số dư'"
                  @click="showBalance = !showBalance"
                >
                  <svg v-if="showBalance" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>

              <div class="w2-balance-val">
                <template v-if="showBalance">
                  {{ money(wallet.balance) }}
                </template>
                <template v-else>
                  •••••••• VNĐ
                </template>
              </div>
            </div>

            <div class="w2-hero-actions">
              <button
                type="button"
                class="w2-btn w2-btn--primary"
                @click="openDepositModal"
              >
                Nạp tiền VietQR
              </button>

              <button
                type="button"
                class="w2-btn w2-btn--outline"
                :disabled="!wallet || wallet.balance <= 0"
                @click="openWithdrawModal"
              >
                Rút tiền ngân hàng
              </button>

              <router-link to="/refunds" class="w2-btn w2-btn--outline">
                Lịch sử hoàn tiền
              </router-link>
            </div>
          </div>

          <!-- METRICS INFO INLINE ROW -->
          <div class="w2-metrics-inline-row">
            <div class="w2-meta-item">
              <span class="w2-meta-label">Tạm khóa:</span>
              <span class="w2-meta-val">{{ money(wallet.locked_balance) }}</span>
            </div>

            <span class="w2-meta-divider">/</span>

            <div class="w2-meta-item">
              <span class="w2-meta-label">Tổng giao dịch:</span>
              <span class="w2-meta-val">{{ ledgers.length }}</span>
            </div>

            <span class="w2-meta-divider">/</span>

            <div class="w2-meta-item">
              <span class="w2-meta-label">Mã ví:</span>
              <span class="w2-meta-val font-mono">#SPG-WLT-{{ wallet.id || 'CLIENT' }}</span>
            </div>
          </div>
        </section>

        <!-- TRANSACTIONS LEDGER ON PURE WHITE -->
        <section class="w2-white-ledger">
          <!-- TOOLBAR & FILTERS -->
          <div class="w2-toolbar">
            <div class="w2-tabs">
              <button
                v-for="tab in filterTabs"
                :key="tab.value"
                type="button"
                class="w2-tab"
                :class="{ 'is-active': activeTab === tab.value }"
                @click="activeTab = tab.value"
              >
                {{ tab.label }}
                <span v-if="tab.count !== undefined" class="w2-tab-count">({{ tab.count }})</span>
              </button>
            </div>

            <div class="w2-search">
              <input
                v-model.trim="searchQuery"
                type="text"
                class="w2-search-input"
                placeholder="Tìm giao dịch..."
              />
              <button
                v-if="searchQuery"
                type="button"
                class="w2-search-clear"
                @click="searchQuery = ''"
              >
                Xóa
              </button>
            </div>
          </div>

          <!-- SUMMARY BAR FOR FILTERED LEDGERS -->
          <div v-if="filteredLedgers.length > 0" class="w2-summary-bar">
            <div class="w2-sum-col">
              <span>Tổng phát sinh tăng:</span>
              <span class="is-credit">{{ money(totalCredit) }}</span>
            </div>
            <div class="w2-sum-divider">|</div>
            <div class="w2-sum-col">
              <span>Tổng phát sinh giảm:</span>
              <span class="is-debit">{{ money(totalDebit) }}</span>
            </div>
          </div>

          <!-- EMPTY STATE WITH SVG ILLUSTRATION -->
          <div v-if="filteredLedgers.length === 0" class="w2-empty-ledger">
            <div class="w2-empty-illustration">
              <svg width="80" height="60" viewBox="0 0 80 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="10" y="8" width="60" height="44" rx="5" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.5" />
                <line x1="20" y1="18" x2="60" y2="18" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" />
                <line x1="20" y1="28" x2="45" y2="28" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="40" cy="42" r="10" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.5" />
                <circle cx="40" cy="42" r="4" fill="#94a3b8" />
              </svg>
            </div>
            <span class="w2-empty-title">Chưa phát sinh giao dịch nào</span>
            <p>Các khoản nạp tiền, thanh toán booking hoặc hoàn tiền sẽ hiển thị tại đây.</p>
          </div>

          <!-- LEDGER ITEMS LIST -->
          <div v-else class="w2-ledger-list">
            <article
              v-for="item in filteredLedgers"
              :key="item.id"
              class="w2-ledger-row"
            >
              <div class="w2-tx-info">
                <div class="w2-tx-head">
                  <span class="w2-tx-title">{{ item.note || typeLabel(item.type) }}</span>
                  <span class="w2-tx-type-tag">{{ typeLabel(item.type) }}</span>
                </div>

                <div class="w2-tx-meta">
                  <span class="w2-tx-code-pill">
                    Mã GD: {{ item.transaction_code }}
                    <button
                      type="button"
                      class="w2-icon-btn"
                      title="Sao chép mã giao dịch"
                      @click="copyText(item.transaction_code, 'Mã giao dịch')"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                      </svg>
                    </button>
                  </span>
                  <span class="w2-tx-date">{{ formatDate(item.created_at) }}</span>
                </div>
              </div>

              <div class="w2-tx-amount-block" :class="item.direction">
                <span class="w2-tx-amount">
                  {{ money(item.amount) }}
                </span>
                <span class="w2-tx-balance-after">
                  Số dư sau GD: {{ money(item.balance_after) }}
                </span>
              </div>
            </article>
          </div>
        </section>
      </div>
      </div>

      <!-- TOAST NOTIFICATION -->
      <Transition name="w2-toast">
        <div v-if="toastMessage" class="w2-toast">
          <span>{{ toastMessage }}</span>
        </div>
      </Transition>
    </main>

    <!-- TOP-UP / DEPOSIT VIETQR MODAL -->
    <Teleport to="body">
      <div v-if="showDepositModal" class="w2-backdrop" @click.self="closeDepositModal">
        <div class="w2-modal w2-modal--deposit">
          <div class="w2-modal-head">
            <div>
              <span class="sg3-kicker">Nạp tiền vào tài khoản</span>
              <h3>Hướng dẫn Nạp Ví SportGo (VietQR)</h3>
            </div>
            <button type="button" class="w2-modal-close" @click="closeDepositModal">Đóng</button>
          </div>

          <div class="w2-modal-body">
            <div class="w2-alert-banner">
              <div>
                <span>Chuyển khoản tự động 24/7</span>
                <p>Tiền sẽ tự động cộng vào Ví SportGo ngay khi bạn chuyển khoản đúng cú pháp bên dưới.</p>
              </div>
            </div>

            <div class="w2-vietqr-box">
              <div class="w2-vietqr-head">
                <span>NGÂN HÀNG THỤ HƯỞNG</span>
                <span>MB BANK (NGÂN HÀNG QUÂN ĐỘI)</span>
              </div>

              <div class="w2-vietqr-row">
                <span class="w2-v-label">Số tài khoản:</span>
                <div class="w2-v-val">
                  <span>0988888888</span>
                  <button
                    type="button"
                    class="w2-v-copy-btn"
                    @click="copyText('0988888888', 'Số tài khoản')"
                  >
                    Sao chép STK
                  </button>
                </div>
              </div>

              <div class="w2-vietqr-row">
                <span class="w2-v-label">Chủ tài khoản:</span>
                <div class="w2-v-val">
                  <span>CÔNG TY TNHH SPORTGO VIỆT NAM</span>
                </div>
              </div>

              <div class="w2-vietqr-row is-memo">
                <span class="w2-v-label">Nội dung chuyển khoản (bắt buộc):</span>
                <div class="w2-v-val">
                  <span class="w2-memo-text">NAP VISO {{ wallet.id || 'CLIENT' }}</span>
                  <button
                    type="button"
                    class="w2-v-copy-btn is-primary"
                    @click="copyText(`NAP VISO ${wallet.id || 'CLIENT'}`, 'Nội dung chuyển khoản')"
                  >
                    Sao chép Nội dung
                  </button>
                </div>
              </div>
            </div>

            <div class="w2-steps-box">
              <span>Hướng dẫn nạp nhanh:</span>
              <ol>
                <li>Mở ứng dụng Ngân hàng (Vietcombank, MB Bank, Techcombank...).</li>
                <li>Chọn Chuyển tiền đến STK 0988888888 tại MB Bank.</li>
                <li>Nhập số tiền cần nạp (Tối thiểu 10.000 VNĐ).</li>
                <li>Dán đúng nội dung: NAP VISO {{ wallet.id || 'CLIENT' }}.</li>
                <li>Hoàn tất chuyển khoản. Ví sẽ được cộng tiền tự động sau 1 - 3 phút.</li>
              </ol>
            </div>
          </div>

          <div class="w2-modal-foot">
            <button type="button" class="w2-btn w2-btn--primary" @click="closeDepositModal">
              Đã hiểu &amp; Đóng
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- WITHDRAWAL MODAL -->
    <Teleport to="body">
      <div v-if="showWithdrawModal" class="w2-backdrop" @click.self="closeWithdrawModal">
        <div class="w2-modal">
          <div class="w2-modal-head">
            <div>
              <span class="sg3-kicker">Tài chính cá nhân</span>
              <h3>Rút tiền về tài khoản ngân hàng</h3>
            </div>
            <button type="button" class="w2-modal-close" @click="closeWithdrawModal">Đóng</button>
          </div>

          <form @submit.prevent="submitWithdrawal">
            <div class="w2-modal-body">
              <div class="w2-avail-preview">
                <span>Số dư khả dụng hiện tại:</span>
                <span>{{ money(wallet.balance) }}</span>
              </div>

              <div class="w2-form-group">
                <label for="w2BankName">Ngân hàng nhận tiền</label>
                <select id="w2BankName" v-model="wdrData.bank_name" class="w2-input" required>
                  <option value="" disabled>-- Chọn ngân hàng --</option>
                  <option value="Vietcombank">Vietcombank (VCB)</option>
                  <option value="Techcombank">Techcombank (TCB)</option>
                  <option value="MB Bank">MB Bank (MB)</option>
                  <option value="ACB">ACB</option>
                  <option value="VPBank">VPBank</option>
                  <option value="VietinBank">VietinBank</option>
                  <option value="BIDV">BIDV</option>
                  <option value="Agribank">Agribank</option>
                  <option value="TPBank">TPBank</option>
                </select>
              </div>

              <div class="w2-form-row">
                <div class="w2-form-group">
                  <label for="w2AccNo">Số tài khoản</label>
                  <input
                    id="w2AccNo"
                    v-model.trim="wdrData.bank_account_number"
                    type="text"
                    class="w2-input"
                    placeholder="Ví dụ: 0988888888"
                    required
                  />
                </div>

                <div class="w2-form-group">
                  <label for="w2AccName">Tên chủ tài khoản</label>
                  <input
                    id="w2AccName"
                    v-model="wdrData.bank_account_name"
                    type="text"
                    class="w2-input"
                    placeholder="NGUYEN VAN A"
                    required
                    @input="wdrData.bank_account_name = $event.target.value.toUpperCase()"
                  />
                </div>
              </div>

              <div class="w2-form-group">
                <label for="w2Amount">Số tiền muốn rút (VNĐ)</label>
                <input
                  id="w2Amount"
                  v-model.number="wdrData.amount"
                  type="number"
                  class="w2-input"
                  min="10000"
                  :max="wallet.balance"
                  placeholder="Ví dụ: 100000"
                  required
                />

                <!-- PRESET AMOUNT CHIPS -->
                <div class="w2-chips-row">
                  <button
                    v-for="preset in amountPresets"
                    :key="preset.value"
                    type="button"
                    class="w2-chip"
                    :class="{ 'is-active': wdrData.amount === preset.value }"
                    :disabled="preset.value > wallet.balance"
                    @click="wdrData.amount = preset.value"
                  >
                    {{ preset.label }}
                  </button>
                </div>
              </div>

              <div class="w2-otp-card">
                <div class="w2-form-group">
                  <label for="w2Otp">Mã OTP xác nhận giao dịch</label>
                  <div class="w2-otp-input-group">
                    <input
                      id="w2Otp"
                      v-model.trim="wdrData.otp"
                      type="text"
                      maxlength="6"
                      placeholder="123456"
                      required
                      class="w2-input w2-otp-input"
                    />
                    <button
                      type="button"
                      class="w2-fill-otp-btn"
                      @click="wdrData.otp = '123456'"
                    >
                      Điền OTP test (123456)
                    </button>
                  </div>
                </div>
              </div>

              <div v-if="wdrError" class="w2-alert is-error">
                <span>{{ wdrError }}</span>
              </div>

              <div v-if="wdrSuccess" class="w2-alert is-success">
                <span>{{ wdrSuccess }}</span>
              </div>
            </div>

            <div class="w2-modal-foot">
              <button type="button" class="w2-btn w2-btn--outline" @click="closeWithdrawModal">Quay lại</button>
              <button
                type="submit"
                class="w2-btn w2-btn--primary"
                :disabled="wdrSubmitting || !wdrData.amount || wdrData.amount > wallet.balance || wdrData.amount < 10000"
              >
                <span>{{ wdrSubmitting ? "Đang xử lý rút tiền..." : "Xác nhận Rút tiền" }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import ClientAccountNav from "../../components/ClientAccountNav.vue";
import { bookingService } from "../../services/bookingService.js";

export default {
  name: "ClientWallet",
  components: { PublicNavbar, ClientAccountNav },
  data() {
    return {
      wallet: { id: null, balance: 0, locked_balance: 0, status: "active" },
      ledgers: [],
      loading: true,
      error: "",
      showBalance: true,
      searchQuery: "",
      activeTab: "all",
      toastMessage: "",
      toastTimer: null,

      // MODALS STATE
      showDepositModal: false,
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
  computed: {
    filterTabs() {
      const counts = {
        all: this.ledgers.length,
        deposit: this.ledgers.filter((l) => l.type === "deposit").length,
        payment: this.ledgers.filter((l) => l.type === "payment").length,
        refund: this.ledgers.filter((l) => l.type === "refund").length,
        withdrawal: this.ledgers.filter((l) => l.type === "withdrawal").length,
      };

      return [
        { value: "all", label: "Tất cả", count: counts.all },
        { value: "deposit", label: "Nạp tiền", count: counts.deposit },
        { value: "payment", label: "Thanh toán", count: counts.payment },
        { value: "refund", label: "Hoàn tiền", count: counts.refund },
        { value: "withdrawal", label: "Rút tiền", count: counts.withdrawal },
      ];
    },

    filteredLedgers() {
      let list = this.ledgers;

      if (this.activeTab !== "all") {
        list = list.filter((l) => l.type === this.activeTab);
      }

      if (this.searchQuery) {
        const q = this.searchQuery.toLowerCase();
        list = list.filter(
          (l) =>
            (l.note && l.note.toLowerCase().includes(q)) ||
            (l.transaction_code && l.transaction_code.toLowerCase().includes(q)) ||
            (l.type && l.type.toLowerCase().includes(q))
        );
      }

      return list;
    },

    totalCredit() {
      return this.filteredLedgers
        .filter((l) => l.direction === "credit")
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);
    },

    totalDebit() {
      return this.filteredLedgers
        .filter((l) => l.direction === "debit")
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);
    },

    amountPresets() {
      const bal = Number(this.wallet.balance || 0);
      const presets = [
        { label: "50.000 đ", value: 50000 },
        { label: "100.000 đ", value: 100000 },
        { label: "200.000 đ", value: 200000 },
        { label: "500.000 đ", value: 500000 },
      ];
      if (bal > 0) {
        presets.push({ label: "Tất cả số dư", value: bal });
      }
      return presets;
    },
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

    openDepositModal() {
      this.showDepositModal = true;
    },

    closeDepositModal() {
      this.showDepositModal = false;
    },

    openWithdrawModal() {
      this.showWithdrawModal = true;
      this.wdrError = "";
      this.wdrSuccess = "";
      this.wdrData.amount = Math.min(50000, Number(this.wallet.balance || 0));
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
      if (this.wdrData.amount < 10000) {
        this.wdrError = "Số tiền rút tối thiểu là 10.000 đ.";
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

    copyText(text, label = "Nội dung") {
      if (!text) return;
      navigator.clipboard.writeText(text).then(() => {
        this.showToast(`Đã sao chép ${label} vào bộ nhớ tạm!`);
      }).catch(() => {
        this.showToast(`Không thể sao chép tự động.`);
      });
    },

    showToast(msg) {
      this.toastMessage = msg;
      if (this.toastTimer) clearTimeout(this.toastTimer);
      this.toastTimer = setTimeout(() => {
        this.toastMessage = "";
      }, 2500);
    },

    money(value) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },

    formatDate(value) {
      return value ? new Date(value).toLocaleString("vi-VN") : "-";
    },

    statusLabel(status) {
      return (
        { active: "Đang hoạt động", locked: "Đang khóa", suspended: "Tạm ngưng" }[status] ||
        status ||
        "Đang hoạt động"
      );
    },

    typeLabel(type) {
      return (
        {
          deposit: "Nạp tiền",
          payment: "Thanh toán",
          refund: "Hoàn tiền",
          withdrawal: "Rút tiền",
          adjustment: "Điều chỉnh",
        }[type] || type
      );
    },
  },
};
</script>

<style scoped>
/* NO BOLD FONTS & PURE WHITE PAGE LAYOUT MATCHING PROFILE PAGE */
* {
  font-weight: 400 !important;
}

.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
  color: #0f172a;
}

.wallet-layout-grid {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  width: 100%;
}

/* PURE WHITE CONTENT WRAPPER */
.w2-white-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* TOP HERO BANNER ON PURE WHITE */
.w2-white-hero {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 16px 0 16px;
}

.w2-hero-top {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}

.w2-hero-main-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.w2-balance-caption {
  display: flex;
  align-items: center;
  gap: 8px;
}

.w2-caption-text {
  font-size: 12px;
  color: #475569;
  letter-spacing: 0.05em;
}

.w2-balance-val {
  font-size: 36px;
  color: #0f172a;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.w2-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 3px 5px;
  border-radius: 4px;
  transition: all 0.15s ease;
}

.w2-icon-btn:hover {
  color: #0f172a;
  background: #f1f5f9;
}

.w2-metrics-inline-row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.w2-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  color: #334155;
  background: transparent;
  border: none;
}

.w2-meta-label {
  color: #64748b;
}

.w2-meta-val {
  color: #0f172a;
}

.w2-meta-divider {
  color: #cbd5e1;
  font-size: 13px;
}

.font-mono {
  font-family: monospace;
}

.w2-hero-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.w2-link-item {
  font-size: 13.5px;
  color: #0f172a;
  text-decoration: underline;
  padding: 8px 12px;
}

/* BUTTON SYSTEM */
.w2-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 18px;
  font-size: 13.5px;
  border-radius: 4px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
}

.w2-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.w2-btn--primary:hover:not(:disabled) {
  background: #166534;
  border-color: #166534;
}

.w2-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.w2-btn--outline:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #0f172a;
}

.w2-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* SKELETON LOADING STATE */
.w2-skeleton-wrapper {
  flex: 1;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding-top: 8px;
}

.w2-sk-hero {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-bottom: 28px;
  border-bottom: 1px solid #f1f5f9;
}

.w2-sk-line {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: w2SkShimmer 1.5s infinite;
  border-radius: 4px;
}

.w2-sk-title { width: 140px; height: 14px; }
.w2-sk-amount { width: 220px; height: 36px; }
.w2-sk-sub { width: 320px; height: 14px; }

.w2-sk-ledger {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.w2-sk-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid #f1f5f9;
}

.w2-sk-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: w2SkShimmer 1.5s infinite;
}

.w2-sk-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.w2-sk-text1 { width: 45%; height: 16px; }
.w2-sk-text2 { width: 28%; height: 12px; }

@keyframes w2SkShimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* STATE CARDS */
.w2-state-card {
  flex: 1;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  background: #ffffff;
  border: 1px solid #f1f5f9;
  border-radius: 6px;
  gap: 16px;
  color: #0f172a;
}

.w2-loading span {
  font-size: 16px;
  color: #0f172a;
}

.w2-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #cbd5e1;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: w2Spin 0.7s linear infinite;
}

@keyframes w2Spin {
  to {
    transform: rotate(360deg);
  }
}

/* WHITE LEDGER SECTION */
.w2-white-ledger {
  display: flex;
  flex-direction: column;
  background: #ffffff;
}

.w2-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0 12px;
  flex-wrap: wrap;
}

.w2-tabs {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
}

.w2-tab {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 7px 14px;
  font-size: 13.5px;
  color: #334155;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 4px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.w2-tab:hover {
  color: #0f172a;
  background: #f8fafc;
}

.w2-tab.is-active {
  color: #15803d;
  background: #ffffff;
  border-color: #15803d;
}

.w2-tab-count {
  font-size: 12px;
  color: #64748b;
}

.w2-search {
  position: relative;
  display: flex;
  align-items: center;
}

.w2-search-input {
  padding: 8px 32px 8px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  width: 240px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

.w2-search-input:focus {
  border-color: #15803d;
}

.w2-search-clear {
  position: absolute;
  right: 10px;
  background: transparent;
  border: none;
  color: #334155;
  cursor: pointer;
  font-size: 12px;
}

/* SUMMARY BAR */
.w2-summary-bar {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 8px 0;
  font-size: 13.5px;
  color: #0f172a;
}

.w2-sum-divider { color: #cbd5e1; }

/* EMPTY LEDGERS */
.w2-empty-ledger {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  color: #334155;
  gap: 10px;
}

.w2-empty-illustration {
  margin-bottom: 4px;
}

.w2-empty-title {
  font-size: 16px;
  color: #0f172a;
}

.w2-empty-ledger p {
  font-size: 13.5px;
  color: #334155;
  max-width: 420px;
  margin: 0;
  line-height: 1.5;
}

/* LEDGER ITEMS LIST */
.w2-ledger-list {
  display: flex;
  flex-direction: column;
}

.w2-ledger-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0;
  gap: 16px;
  transition: background 0.15s ease;
}

.w2-ledger-row:last-child {
  border-bottom: none;
}

.w2-tx-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.w2-tx-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.w2-tx-title {
  font-size: 14.5px;
  color: #0f172a;
}

.w2-tx-type-tag {
  font-size: 11.5px;
  padding: 2px 7px;
  border-radius: 4px;
  background: #f8fafc;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}

.w2-tx-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 12.5px;
  color: #334155;
}

.w2-tx-code-pill {
  font-family: monospace;
  font-size: 12px;
  background: #f8fafc;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
  color: #0f172a;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.w2-tx-copy-btn {
  background: transparent;
  border: none;
  color: #0f172a;
  cursor: pointer;
  padding: 0;
  font-size: 11.5px;
  text-decoration: underline;
}

.w2-tx-amount-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  text-align: right;
}

.w2-tx-amount {
  font-size: 16px;
  color: #0f172a;
}

.w2-tx-balance-after {
  font-size: 12px;
  color: #475569;
}

/* TOAST NOTIFICATION */
.w2-toast {
  position: fixed;
  bottom: 28px;
  right: 28px;
  background: #0f172a;
  color: #ffffff;
  padding: 12px 20px;
  border-radius: 6px;
  font-size: 13.5px;
  z-index: 9999;
}

/* MODALS OVERLAYS */
.w2-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  padding: 16px;
}

.w2-modal {
  background: #ffffff;
  border-radius: 8px;
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  color: #0f172a;
}

.w2-modal--deposit {
  max-width: 520px;
}

.w2-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 18px 24px 14px;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
}

.w2-modal-head h3 {
  margin: 4px 0 0;
  font-size: 18px;
  color: #0f172a;
}

.w2-modal-close {
  background: transparent;
  border: none;
  font-size: 13.5px;
  color: #475569;
  cursor: pointer;
  padding: 4px;
}

.w2-modal-close:hover {
  color: #0f172a;
}

.w2-modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 75vh;
  overflow-y: auto;
}

.w2-alert-banner {
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  color: #0f172a;
}

.w2-alert-banner span {
  display: block;
  font-size: 13.5px;
  color: #0f172a;
}

.w2-alert-banner p {
  font-size: 12.5px;
  color: #475569;
  margin: 2px 0 0;
  line-height: 1.45;
}

.w2-vietqr-box {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.w2-vietqr-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f1f5f9;
}

.w2-vietqr-head span {
  font-size: 12.5px;
  color: #0f172a;
}

.w2-vietqr-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.w2-vietqr-row.is-memo {
  background: #f8fafc;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.w2-v-label {
  font-size: 12px;
  color: #475569;
}

.w2-v-val {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 13.5px;
  color: #0f172a;
}

.w2-memo-text {
  font-family: monospace;
  font-size: 14.5px;
  color: #15803d;
  background: #ffffff;
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
}

.w2-v-copy-btn {
  padding: 5px 10px;
  font-size: 12px;
  border-radius: 4px;
  border: 1px solid #15803d;
  background: #15803d;
  color: #ffffff;
  cursor: pointer;
}

.w2-v-copy-btn:hover {
  background: #166534;
}

.w2-steps-box {
  font-size: 13px;
  color: #334155;
}

.w2-steps-box span {
  font-size: 13.5px;
  color: #0f172a;
  margin-bottom: 6px;
  display: block;
}

.w2-steps-box ol {
  margin: 0;
  padding-left: 18px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  line-height: 1.5;
  color: #334155;
}

/* WITHDRAWAL FORM CONTROLS */
.w2-avail-preview {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  color: #0f172a;
}

.w2-form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.w2-form-group label {
  font-size: 13px;
  color: #0f172a;
}

.w2-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.w2-input {
  padding: 9px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
}

.w2-input:focus {
  border-color: #15803d;
}

.w2-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}

.w2-chip {
  padding: 5px 10px;
  font-size: 12px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  cursor: pointer;
  transition: all 0.15s ease;
}

.w2-chip:hover:not(:disabled) {
  border-color: #15803d;
}

.w2-chip.is-active {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.w2-chip:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.w2-otp-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 12px;
}

.w2-otp-input-group {
  display: flex;
  gap: 8px;
}

.w2-otp-input {
  letter-spacing: 4px;
  font-size: 16px !important;
  text-align: center;
  font-family: monospace;
  flex: 1;
}

.w2-fill-otp-btn {
  padding: 7px 12px;
  font-size: 12px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #15803d;
  cursor: pointer;
  white-space: nowrap;
}

.w2-fill-otp-btn:hover {
  background: #f0fdf4;
}

.w2-alert {
  display: flex;
  align-items: flex-start;
  padding: 10px 12px;
  font-size: 13px;
  border-radius: 4px;
}

.w2-alert.is-success {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.w2-alert.is-error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.w2-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 24px 18px;
  border-top: 1px solid #f1f5f9;
  background: #ffffff;
}

/* RESPONSIVE MEDIA BREAKPOINTS */
@media (max-width: 768px) {
  .w2-white-hero {
    flex-direction: column;
    align-items: flex-start;
  }

  .w2-hero-actions {
    width: 100%;
  }

  .w2-toolbar, .w2-summary-bar, .w2-ledger-row {
    padding-left: 0;
    padding-right: 0;
  }

  .w2-search-input {
    width: 100%;
  }

  .w2-form-row {
    grid-template-columns: 1fr;
  }
}
</style>